<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Property;
use App\Models\User;
use App\Models\Vendor;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Twilio\Rest\Client as TwilioClient;
use TransferWise\Client as WiseClient;

class PaymentController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        
        $stripeSecret = config('services.stripe.secret_key');
        if ($stripeSecret) {
            Stripe::setApiKey($stripeSecret);
        }
    }

    /**
     * Create Stripe Connect account for property owner using OAuth flow
     */
    public function createConnectAccount(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Check if Connect client ID is configured
            $connectClientId = config('services.stripe.connect_client_id');
            
            if (!$connectClientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe Connect client ID is not configured. Please contact support.',
                ], 500);
            }

            // If user already has a Stripe account, check its status with Stripe API
            if ($user->stripe_account_id) {
                try {
                    $account = \Stripe\Account::retrieve($user->stripe_account_id);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Stripe account is already connected',
                        'account_id' => $user->stripe_account_id,
                        'onboarding_completed' => $account->details_submitted,
                        'charges_enabled' => $account->charges_enabled,
                        'payouts_enabled' => $account->payouts_enabled,
                    ]);
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    // Account doesn't exist or is invalid, clear it and create new
                    $this->clearInvalidStripeAccount($user, 'Invalid account ID: ' . $user->stripe_account_id);
                } catch (\Stripe\Exception\PermissionException $e) {
                    // API key doesn't have access to this account, clear it and create new
                    $this->clearInvalidStripeAccount($user, 'API key does not have access to account: ' . $user->stripe_account_id);
                } catch (\Exception $e) {
                    // Any other Stripe error, clear it and create new
                    $this->clearInvalidStripeAccount($user, 'Stripe API error: ' . $e->getMessage());
                }
            }

            // Create OAuth URL for new account connection
            $oauthUrl = $this->createOAuthUrl();

            return response()->json([
                'success' => true,
                'oauth_url' => $oauthUrl,
                'onboarding_completed' => false,
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe Connect OAuth creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Stripe Connect OAuth: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create OAuth URL for Stripe Connect
     */
    private function createOAuthUrl($accountId = null)
    {
        $clientId = config('services.stripe.connect_client_id');
        
        // Use ngrok URL for Stripe Connect redirect when running locally
        $baseUrl = config('app.url');
        if (str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1')) {
            // Try to get ngrok URL dynamically
            $ngrokUrl = $this->getNgrokUrl();
            if ($ngrokUrl) {
                $baseUrl = $ngrokUrl;
            } else {
                // Fallback to configured ngrok URL
                $baseUrl = config('app.ngrok_url', 'https://30c78227bf81.ngrok-free.app');
            }
        }
        
        $redirectUri = $baseUrl . '/api/stripe/connect/callback';
        $state = auth()->user()->id; // Use user ID as state for security
        
        // Determine if we're in test mode based on the secret key
        $isTestMode = str_starts_with(config('services.stripe.secret_key'), 'sk_test_');
        
        // Log the mode for debugging
        Log::info('Stripe Connect OAuth - Mode: ' . ($isTestMode ? 'TEST' : 'LIVE') . ', Redirect URI: ' . $redirectUri);
        
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'read_write',
            'state' => $state,
        ];
        
        // If we have an existing account, include it in the URL
        if ($accountId) {
            $params['stripe_user_id'] = $accountId;
        }
        
        $queryString = http_build_query($params);
        
        // Both test and live use the same Connect URL
        $baseConnectUrl = 'https://connect.stripe.com/oauth/authorize';
        
        return $baseConnectUrl . '?' . $queryString;
    }

    /**
     * Get ngrok URL dynamically
     */
    private function getNgrokUrl(): ?string
    {
        try {
            $response = \Illuminate\Support\Facades\Http::get('http://localhost:4040/api/tunnels');
            if ($response->successful()) {
                $data = $response->json();
                $tunnels = $data['tunnels'] ?? [];
                
                // Get the first HTTPS tunnel
                $httpsTunnel = collect($tunnels)->firstWhere('proto', 'https');
                if ($httpsTunnel) {
                    return $httpsTunnel['public_url'];
                }
            }
        } catch (\Exception $e) {
            // Ngrok not running or not accessible
            Log::debug('Could not get ngrok URL: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Handle Stripe Connect OAuth callback
     */
    public function handleOAuthCallback(Request $request)
    {
        try {
            $code = $request->get('code');
            $state = $request->get('state');
            $error = $request->get('error');
            
            if ($error) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe Connect authorization failed: ' . $error,
                ], 400);
            }

            if (!$code || !$state) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing authorization code or state',
                ], 400);
            }

            // Verify state matches user ID
            $user = User::find($state);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid state parameter',
                ], 400);
            }
            
            // Exchange authorization code for access token
            $tokenResult = $this->exchangeCodeForToken($code);
            
            if (!$tokenResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $tokenResult['message'],
                ], 400);
            }
            
            // Store the account information
            $user->update([
                'stripe_account_id' => $tokenResult['stripe_user_id'],
                'stripe_onboarding_completed' => false, // Will be updated via webhook
            ]);

            Log::info('Stripe Connect account created for user ' . $user->id . ': ' . $tokenResult['stripe_user_id']);

            // Redirect back to frontend with success message
            $frontendUrl = config('app.frontend_url') . '/admin/settings?stripe_success=true';
            return redirect($frontendUrl);

        } catch (\Exception $e) {
            Log::error('Stripe OAuth callback failed: ' . $e->getMessage());
            
            // Redirect back to frontend with error message
            $frontendUrl = config('app.frontend_url') . '/admin/settings?stripe_error=' . urlencode($e->getMessage());
            return redirect($frontendUrl);
        }
    }

    /**
     * Clear invalid Stripe account data for a user
     */
    private function clearInvalidStripeAccount($user, $reason = 'Unknown error')
    {
        Log::warning("Clearing invalid Stripe account for user {$user->id}: {$reason}");
        $user->update([
            'stripe_account_id' => null,
            'stripe_onboarding_completed' => false
        ]);
    }

    /**
     * Exchange authorization code for access token
     */
    private function exchangeCodeForToken($code)
    {
        try {
            $clientId = config('services.stripe.connect_client_id');
            $clientSecret = config('services.stripe.secret_key');
            
            $response = \Http::asForm()->post('https://connect.stripe.com/oauth/token', [
                'client_secret' => $clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'access_token' => $data['access_token'],
                    'stripe_user_id' => $data['stripe_user_id'],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to exchange code for token: ' . $response->body(),
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Token exchange failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check Stripe Connect account status
     */
    public function checkConnectStatus(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user->stripe_account_id) {
                return response()->json([
                    'success' => true,
                    'connected' => false,
                    'onboarding_completed' => false,
                    'message' => 'No Stripe account connected',
                ]);
            }

            // Retrieve account details from Stripe
            try {
                $account = \Stripe\Account::retrieve($user->stripe_account_id);
                
                return response()->json([
                    'success' => true,
                    'connected' => true,
                    'onboarding_completed' => $account->details_submitted,
                    'account_id' => $user->stripe_account_id,
                    'charges_enabled' => $account->charges_enabled,
                    'payouts_enabled' => $account->payouts_enabled,
                    'details_submitted' => $account->details_submitted,
                    'requirements' => $account->requirements ?? null,
                ]);
                
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Account doesn't exist or is invalid
                $this->clearInvalidStripeAccount($user, 'Invalid account ID in checkConnectStatus: ' . $user->stripe_account_id);
                
                return response()->json([
                    'success' => true,
                    'connected' => false,
                    'onboarding_completed' => false,
                    'message' => 'Stripe account is no longer valid',
                ]);
            } catch (\Stripe\Exception\PermissionException $e) {
                // API key doesn't have access to this account
                $this->clearInvalidStripeAccount($user, 'API key does not have access in checkConnectStatus: ' . $user->stripe_account_id);
                
                return response()->json([
                    'success' => true,
                    'connected' => false,
                    'onboarding_completed' => false,
                    'message' => 'Stripe account access has been revoked',
                ]);
            } catch (\Exception $e) {
                // Any other Stripe error
                $this->clearInvalidStripeAccount($user, 'Stripe API error in checkConnectStatus: ' . $e->getMessage());
                
                return response()->json([
                    'success' => true,
                    'connected' => false,
                    'onboarding_completed' => false,
                    'message' => 'Stripe account is no longer accessible',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to check Stripe Connect status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check Stripe Connect status',
            ], 500);
        }
    }

    /**
     * Create payment intent for checkout
     */
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'cart_items' => 'required|array',
            'cart_items.*.upsell_id' => 'required|integer|exists:upsells,id',
            'cart_items.*.guest_count' => 'required|integer|min:1|max:20',
            'cart_items.*.total_price' => 'required|numeric|min:0',
            'cart_items.*.selected_date' => 'nullable|date',
            'cart_items.*.menu_options' => 'nullable|string',
            'cart_items.*.special_notes' => 'nullable|string',
        ]);

        try {
            $user = auth()->user();
            $property = Property::where('user_id', $user->id)->first();

            if (!$property) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property not found',
                ], 404);
            }

            // Calculate total amount
            $totalAmount = collect($request->cart_items)->sum('total_price');
            $totalAmountCents = (int) ($totalAmount * 100); // Convert to cents

            // Create payment intent (simplified for test mode)
            $paymentIntent = PaymentIntent::create([
                'amount' => $totalAmountCents,
                'currency' => $property->currency ?? 'usd',
                'metadata' => [
                    'property_id' => $property->id,
                    'user_id' => $user->id,
                    'cart_items' => json_encode($request->cart_items),
                ],
                // Note: In test mode, we're not using Connect transfers
                // In production, you'd add:
                // 'application_fee_amount' => (int) ($totalAmountCents * 0.1),
                // 'transfer_data' => ['destination' => $user->stripe_account_id],
            ]);

            return response()->json([
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment intent creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment intent',
            ], 500);
        }
    }

    /**
     * Handle Stripe webhook events
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded($event->data->object);
                break;
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;
            case 'account.updated':
                $this->handleAccountUpdated($event->data->object);
                break;
            case 'account.application.deauthorized':
                $this->handleAccountDeauthorized($event->data->object);
                break;
            default:
                Log::info('Unhandled Stripe webhook event: ' . $event->type);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSucceeded($paymentIntent)
    {
        try {
            DB::beginTransaction();

            $metadata = $paymentIntent->metadata ?? (object)[];
            $cartItems = [];
            
            if (isset($metadata->cart_items) && $metadata->cart_items) {
                $cartItems = json_decode($metadata->cart_items, true) ?? [];
            }

            // Load property info for currency and relations
            $property = null;
            if (isset($metadata->property_id)) {
                $property = Property::find($metadata->property_id);
            }

            // Check if this is a guest payment
            $isGuestPayment = isset($metadata->is_guest_payment) && $metadata->is_guest_payment === 'true';

            // If no cart items, create a basic order for testing
            if (empty($cartItems)) {
                Log::info('No cart items found in payment intent metadata, creating test order');
                $cartItems = [[
                    'upsell_id' => 1,
                    'guest_count' => 1,
                    'total_price' => $paymentIntent->amount / 100, // Convert from cents
                    'selected_date' => now()->toISOString(),
                    'special_notes' => 'Test payment from Stripe CLI',
                    'menu_options' => null,
                ]];
            }

            // Create orders for each cart item
            foreach ($cartItems as $item) {
                $upsell = \App\Models\Upsell::find($item['upsell_id']);
                if (!$upsell) continue;

                $order = Order::create([
                    'property_id' => $metadata->property_id,
                    'upsell_id' => $item['upsell_id'],
                    'vendor_id' => $upsell->primary_vendor_id,
                    'guest_name' => $isGuestPayment ? 'Guest' : 'Guest', // Will be updated with actual guest info
                    'guest_email' => $isGuestPayment ? 'guest@example.com' : 'guest@example.com', // Will be updated with actual guest info
                    'guest_phone' => null,
                    'amount' => $item['total_price'],
                    'currency' => $property->currency ?? 'USD',
                    'status' => 'confirmed',
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'order_details' => [
                        'access_token' => $metadata->access_token ?? null,
                        'cart_items' => $cartItems,
                        'guest_count' => $item['guest_count'],
                        'unit_price' => $upsell->price,
                        'scheduled_date' => $item['selected_date'] ?? now()->toISOString(),
                        'special_requests' => $item['special_notes'] ?? null,
                        'menu_preferences' => $item['menu_options'] ?? null,
                        'payment_method' => 'stripe',
                    ],
                ]);

                // Send all notifications using the NotificationService
                $notificationResults = $this->notificationService->sendOrderNotifications($order);
                
                Log::info('Notifications sent for order ' . $order->id . ': ' . json_encode($notificationResults));
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($paymentIntent)
    {
        Log::info('Payment failed for intent: ' . $paymentIntent->id);
        // Could send notification to user about failed payment
    }

    /**
     * Handle Stripe Connect account updates
     */
    private function handleAccountUpdated($account)
    {
        try {
            $user = User::where('stripe_account_id', $account->id)->first();
            
            if (!$user) {
                Log::warning('No user found for Stripe account: ' . $account->id);
                return;
            }

            // Update onboarding status based on account details
            $onboardingCompleted = $account->details_submitted && 
                                 $account->charges_enabled && 
                                 $account->payouts_enabled;

            $user->update([
                'stripe_onboarding_completed' => $onboardingCompleted,
            ]);

            Log::info('Updated Stripe Connect status for user: ' . $user->id . 
                     ' (onboarding_completed: ' . ($onboardingCompleted ? 'true' : 'false') . ')');

        } catch (\Exception $e) {
            Log::error('Failed to handle account update: ' . $e->getMessage());
        }
    }

    /**
     * Handle Stripe Connect account deauthorization
     */
    private function handleAccountDeauthorized($account)
    {
        try {
            $user = User::where('stripe_account_id', $account->id)->first();
            
            if (!$user) {
                Log::warning('No user found for deauthorized Stripe account: ' . $account->id);
                return;
            }

            // Reset user's Stripe connection
            $user->update([
                'stripe_account_id' => null,
                'stripe_onboarding_completed' => false,
            ]);

            Log::info('Deauthorized Stripe Connect account for user: ' . $user->id);

        } catch (\Exception $e) {
            Log::error('Failed to handle account deauthorization: ' . $e->getMessage());
        }
    }




    /**
     * Create payment intent for guest checkout (no authentication required)
     */
    public function createGuestPaymentIntent(Request $request)
    {
        Log::info('createGuestPaymentIntent', $request->all());
        $request->validate([
            'access_token' => 'required|string',
            'cart_items' => 'required|array',
            'cart_items.*.upsell_id' => 'required|integer|exists:upsells,id',
            'cart_items.*.guest_count' => 'required|integer|min:1|max:20',
            'cart_items.*.total_price' => 'required|numeric|min:0',
            'cart_items.*.selected_date' => 'nullable|date',
            'cart_items.*.menu_options' => 'nullable|string',
            'cart_items.*.special_notes' => 'nullable|string',
        ]);

        try {
            // Find property by access token with owner relationship
            $property = Property::with('owner')->where('access_token', $request->access_token)->first();
            if (!$property) {
                Log::error('Property not found for access token: ' . $request->access_token);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid access token',
                ], 404);
            }
            
            Log::info('Property found: ' . $property->name . ' (ID: ' . $property->id . ')');
            Log::info('Property owner: ' . ($property->owner ? $property->owner->name : 'NO OWNER'));
            
            // Calculate total amount
            $totalAmount = collect($request->cart_items)->sum('total_price');
            $totalAmountCents = (int) ($totalAmount * 100); // Convert to cents

            // Check if property uses Stripe (default to stripe if not set)
            $paymentProcessor = $property->payment_processor ?? 'stripe';
            if ($paymentProcessor !== 'stripe') {
                return response()->json([
                    'success' => false,
                    'message' => 'This property does not accept Stripe payments. Please use the configured payment method.',
                ], 400);
            }

            // Check if Stripe is configured
            $stripeSecret = config('services.stripe.secret_key');
            if (!$stripeSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured. Please contact support.',
                ], 500);
            }

            // For test mode, we'll create a simple payment intent without Connect
            // In production, you'd check for Connect account and use transfers
            
            // Create payment intent (simplified for test mode)
            $paymentIntent = PaymentIntent::create([
                'amount' => $totalAmountCents,
                'currency' => $property->currency ?? 'usd',
                'metadata' => [
                    'property_id' => $property->id,
                    'user_id' => $property->user_id,
                    'access_token' => $request->access_token,
                    'cart_items' => json_encode($request->cart_items),
                    'is_guest_payment' => 'true',
                ],
                // Note: In test mode, we're not using Connect transfers
                // In production, you'd add:
                // 'application_fee_amount' => (int) ($totalAmountCents * 0.1),
                // 'transfer_data' => ['destination' => $property->owner->stripe_account_id],
            ]);

            return response()->json([
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Guest payment intent creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment intent',
            ], 500);
        }
    }

    /**
     * Alternative payment method using Wise API
     */
    public function createWisePayment(Request $request)
    {
        $request->validate([
            'cart_items' => 'required|array',
            'cart_items.*.upsell_id' => 'required|integer|exists:upsells,id',
            'cart_items.*.guest_count' => 'required|integer|min:1|max:20',
            'cart_items.*.total_price' => 'required|numeric|min:0',
        ]);

        try {
            $user = auth()->user();
            $property = Property::where('user_id', $user->id)->first();

            if (!$property) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property not found',
                ], 404);
            }

            // Calculate total amount
            $totalAmount = collect($request->cart_items)->sum('total_price');

            // Check if Wise is configured
            $wiseToken = config('services.wise.token');
            $wiseProfileId = config('services.wise.profile_id');
            
            if (!$wiseToken || !$wiseProfileId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wise is not configured. Please contact support.',
                ], 500);
            }

            // Initialize Wise client
            $wiseClient = new WiseClient([
                'token' => $wiseToken,
                'profile_id' => $wiseProfileId,
                'env' => config('services.wise.environment', 'sandbox'),
            ]);

            // Create pending order first
            $order = Order::create([
                'property_id' => $property->id,
                'upsell_id' => $request->cart_items[0]['upsell_id'],
                'vendor_id' => Upsell::find($request->cart_items[0]['upsell_id'])->primary_vendor_id,
                'guest_name' => 'Guest',
                'guest_email' => 'guest@example.com',
                'guest_phone' => null,
                'amount' => $totalAmount,
                'currency' => $property->currency ?? 'USD',
                'status' => 'pending',
                'order_details' => [
                    'guest_count' => $request->cart_items[0]['guest_count'],
                    'unit_price' => Upsell::find($request->cart_items[0]['upsell_id'])->price,
                    'scheduled_date' => now()->addDays(1)->toISOString(),
                    'payment_method' => 'wise',
                ],
            ]);

            return response()->json([
                'success' => true,
                'payment_url' => null, // No redirect URL for bank transfer
                'order_id' => $order->id,
                'wise_account_details' => $property->wise_account_details,
                'transfer_id' => 'ORDER_' . $order->id,
                'message' => 'Order created successfully. Please complete bank transfer.',
            ]);

        } catch (\Exception $e) {
            Log::error('Wise payment creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Wise payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process Wise payment (simulated)
     */
    private function processWisePayment($cartItems, $paymentData)
    {
        // Simulate payment processing
        // In real implementation, this would be called by Wise webhook
        foreach ($cartItems as $item) {
            $upsell = \App\Models\Upsell::find($item['upsell_id']);
            if (!$upsell) continue;

            $order = Order::create([
                'property_id' => auth()->user()->properties()->first()->id,
                'upsell_id' => $item['upsell_id'],
                'vendor_id' => $upsell->primary_vendor_id,
                'guest_name' => 'Guest',
                'guest_email' => 'guest@example.com',
                'guest_phone' => null,
                'amount' => $item['total_price'],
                'currency' => 'USD',
                'status' => 'confirmed',
                'order_details' => [
                    'guest_count' => $item['guest_count'],
                    'unit_price' => $upsell->price,
                    'scheduled_date' => now()->addDays(1)->toISOString(),
                    'payment_method' => 'wise',
                ],
            ]);

            // Send all notifications using the NotificationService
            $notificationResults = $this->notificationService->sendOrderNotifications($order);
            
            Log::info('Wise notifications sent for order ' . $order->id . ': ' . json_encode($notificationResults));
        }
    }

    /**
     * Alternative payment method using Wise API for guests
     */
    public function createGuestWisePayment(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
            'cart_items' => 'required|array',
            'cart_items.*.upsell_id' => 'required|integer|exists:upsells,id',
            'cart_items.*.guest_count' => 'required|integer|min:1|max:20',
            'cart_items.*.total_price' => 'required|numeric|min:0',
        ]);

        try {
            // Find property by access token
            $property = Property::where('access_token', $request->access_token)->first();
            if (!$property) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid access token',
                ], 404);
            }

            // Calculate total amount
            $totalAmount = collect($request->cart_items)->sum('total_price');

            // Check if property is configured for Wise payments
            if ($property->payment_processor !== 'wise') {
                return response()->json([
                    'success' => false,
                    'message' => 'This property does not accept Wise payments',
                ], 400);
            }

            // Check if Wise is configured
            $wiseToken = config('services.wise.token');
            $wiseProfileId = config('services.wise.profile_id');
            
            if (!$wiseToken || !$wiseProfileId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wise is not configured. Please contact support.',
                ], 500);
            }

            // Initialize Wise client
            $wiseClient = new WiseClient([
                'token' => $wiseToken,
                'profile_id' => $wiseProfileId,
                'env' => config('services.wise.environment', 'sandbox'),
            ]);

            // Create pending order first
            $order = Order::create([
                'property_id' => $property->id,
                'upsell_id' => $request->cart_items[0]['upsell_id'],
                'vendor_id' => Upsell::find($request->cart_items[0]['upsell_id'])->primary_vendor_id,
                'guest_name' => 'Guest', // Will be updated when guest provides info
                'guest_email' => 'guest@example.com', // Will be updated when guest provides info
                'guest_phone' => null,
                'amount' => $totalAmount,
                'currency' => $property->currency ?? 'USD',
                'status' => 'pending', // Pending until owner confirms payment
                'order_details' => [
                    'guest_count' => $request->cart_items[0]['guest_count'],
                    'unit_price' => Upsell::find($request->cart_items[0]['upsell_id'])->price,
                    'scheduled_date' => $request->cart_items[0]['selected_date'] ?? now()->toISOString(),
                    'special_requests' => $request->cart_items[0]['special_notes'] ?? null,
                    'menu_preferences' => $request->cart_items[0]['menu_options'] ?? null,
                    'payment_method' => 'wise',
                ],
            ]);

            // For now, return bank transfer details instead of creating actual Wise transfers
            // This is safer for testing and doesn't require complex Wise API setup
            return response()->json([
                'success' => true,
                'payment_url' => null, // No redirect URL for bank transfer
                'order_id' => $order->id,
                'wise_account_details' => $property->wise_account_details,
                'transfer_id' => 'ORDER_' . $order->id,
                'message' => 'Order created successfully. Please complete bank transfer.',
            ]);

        } catch (\Exception $e) {
            Log::error('Guest Wise payment creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Wise payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process Wise payment for guests (simulated)
     */
    private function processGuestWisePayment($cartItems, $paymentData, $property)
    {
        // Simulate payment processing
        // In real implementation, this would be called by Wise webhook
        foreach ($cartItems as $item) {
            $upsell = \App\Models\Upsell::find($item['upsell_id']);
            if (!$upsell) continue;

            $order = Order::create([
                'property_id' => $property->id,
                'upsell_id' => $item['upsell_id'],
                'vendor_id' => $upsell->primary_vendor_id,
                'guest_name' => 'Guest',
                'guest_email' => 'guest@example.com',
                'guest_phone' => null,
                'amount' => $item['total_price'],
                'currency' => $property->currency ?? 'USD',
                'status' => 'confirmed',
                'order_details' => [
                    'guest_count' => $item['guest_count'],
                    'unit_price' => $upsell->price,
                    'scheduled_date' => $item['selected_date'] ? (new \DateTime($item['selected_date']))->toISOString() : now()->addDays(1)->toISOString(),
                    'special_requests' => $item['special_notes'] ?? null,
                    'menu_preferences' => $item['menu_options'] ?? null,
                    'payment_method' => 'wise',
                ],
            ]);

            // Send all notifications using the NotificationService
            $notificationResults = $this->notificationService->sendOrderNotifications($order);
            
            Log::info('Guest Wise notifications sent for order ' . $order->id . ': ' . json_encode($notificationResults));
        }
    }
    public function handleWiseWebhook(Request $request)
    {
        // Handle Wise webhook events
        // This would be implemented based on Wise API documentation
        Log::info('Wise webhook received: ' . json_encode($request->all()));
        
        return response()->json(['status' => 'success']);
    }
}