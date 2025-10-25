<?php

namespace App\Services;

use App\Models\Order;
use App\Models\GuestCheckIn;
use App\Models\Property;
use App\Models\Vendor;
use App\Models\Upsell;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Twilio\Rest\Client as TwilioClient;
use SendGrid\Mail\Mail as SendGridMail;
use SendGrid\Mail\From;
use SendGrid\Mail\To;
use SendGrid\Mail\Subject;
use SendGrid\Mail\HtmlContent;
use SendGrid\Mail\PlainTextContent;
use SendGrid;

class NotificationService
{
    /**
     * Send email via SendGrid using cURL (with SSL bypass for local development)
     */
    private function sendEmailViaSendGrid(string $to, string $subject, string $htmlContent, string $fromEmail = null, string $fromName = null): bool
    {
        try {
            $sendGridApiKey = config('services.sendgrid.api_key');
            
            if (!$sendGridApiKey) {
                Log::warning('SendGrid API key not configured. Falling back to default mailer.');
                return false;
            }

            // Set from address
            $fromEmail = $fromEmail ?? config('mail.from.address');
            $fromName = $fromName ?? config('mail.from.name');

            // Create email data for SendGrid API
            $emailData = [
                'personalizations' => [
                    [
                        'to' => [
                            ['email' => $to]
                        ],
                        'subject' => $subject
                    ]
                ],
                'from' => [
                    'email' => $fromEmail,
                    'name' => $fromName
                ],
                'content' => [
                    [
                        'type' => 'text/html',
                        'value' => $htmlContent
                    ]
                ]
            ];

            // Use cURL with SSL bypass for local development
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $sendGridApiKey,
                'Content-Type: application/json',
            ]);
            
            // Disable SSL verification for local development
            if (app()->environment('local')) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_CAINFO, '');
                curl_setopt($ch, CURLOPT_CAPATH, '');
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::error('SendGrid cURL error: ' . $error);
                return false;
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                Log::info('SendGrid email sent successfully to: ' . $to . ' (Status: ' . $httpCode . ')');
                return true;
            } else {
                Log::error('SendGrid email failed with status code: ' . $httpCode . ' - ' . $response);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('SendGrid email failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send vendor notification email after successful payment
     */
    public function sendVendorNotification(Order $order): bool
    {
        try {
            $vendor = $order->vendor;
            $upsell = $order->upsell;
            $property = $order->property;

            if (!$vendor || !$vendor->email) {
                Log::warning('Vendor email not available for order: ' . $order->id);
                return false;
            }

            // Get guest information from check-in data
            $guestInfo = $this->getGuestInfo($order);

            // Generate email content
            $emailContent = view('emails.vendor-notification', [
                'order' => $order,
                'vendor' => $vendor,
                'upsell' => $upsell,
                'property' => $property,
                'guestInfo' => $guestInfo,
                'orderDetails' => $order->order_details ?? [],
            ])->render();

            $subject = '🎉 New Service Booking - ' . $order->upsell->title;

            // Try SendGrid first, fallback to default mailer
            $sent = $this->sendEmailViaSendGrid($vendor->email, $subject, $emailContent);
            
            if (!$sent) {
                // Fallback to default mailer
                Mail::send('emails.vendor-notification', [
                    'order' => $order,
                    'vendor' => $vendor,
                    'upsell' => $upsell,
                    'property' => $property,
                    'guestInfo' => $guestInfo,
                    'orderDetails' => $order->order_details ?? [],
                ], function ($message) use ($vendor, $order) {
                    $message->to($vendor->email)
                        ->subject('🎉 New Service Booking - ' . $order->upsell->title);
                });
            }

            Log::info('Vendor notification sent successfully for order: ' . $order->id);
            return true;

        } catch (\Exception $e) {
            Log::error('Vendor notification email failed for order ' . $order->id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send guest confirmation email with invoice
     */
    public function sendGuestConfirmation(Order $order): bool
    {
        try {
            // Get guest email from order or check-in data
            $guestEmail = $order->guest_email;
            
            if (!$guestEmail) {
                $guestInfo = $this->getGuestInfo($order);
                $guestEmail = $guestInfo['email'] ?? null;
            }

            if (!$guestEmail) {
                Log::warning('Guest email not available for order: ' . $order->id);
                return false;
            }

            // Generate invoice data
            $invoiceData = $this->generateInvoiceData($order);

            // Generate email content
            $emailContent = view('emails.guest-confirmation', [
                'order' => $order,
                'upsell' => $order->upsell,
                'property' => $order->property,
                'vendor' => $order->vendor,
                'guestInfo' => $this->getGuestInfo($order),
                'invoiceData' => $invoiceData,
                'orderDetails' => $order->order_details ?? [],
            ])->render();

            $subject = '✅ Booking Confirmation & Invoice - ' . $order->upsell->title;

            // Try SendGrid first, fallback to default mailer
            $sent = $this->sendEmailViaSendGrid($guestEmail, $subject, $emailContent);
            
            if (!$sent) {
                // Fallback to default mailer
                Mail::send('emails.guest-confirmation', [
                    'order' => $order,
                    'upsell' => $order->upsell,
                    'property' => $order->property,
                    'vendor' => $order->vendor,
                    'guestInfo' => $this->getGuestInfo($order),
                    'invoiceData' => $invoiceData,
                    'orderDetails' => $order->order_details ?? [],
                ], function ($message) use ($guestEmail, $order) {
                    $message->to($guestEmail)
                        ->subject('✅ Booking Confirmation & Invoice - ' . $order->upsell->title);
                });
            }

            Log::info('Guest confirmation email sent successfully for order: ' . $order->id);
            return true;

        } catch (\Exception $e) {
            Log::error('Guest confirmation email failed for order ' . $order->id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp notification to vendor
     */
    public function sendVendorWhatsApp(Order $order): bool
    {
        try {
            $vendor = $order->vendor;
            
            if (!$vendor || !$vendor->whatsapp_number) {
                Log::warning('Vendor WhatsApp number not available for order: ' . $order->id);
                return false;
            }

            $message = $this->generateWhatsAppMessage($order);

            // Attempt Twilio send; fall back to logging
            $this->sendWhatsAppMessage($vendor->whatsapp_number, $message);

            return true;

        } catch (\Exception $e) {
            Log::error('WhatsApp notification failed for order ' . $order->id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp confirmation to guest
     */
    public function sendGuestWhatsApp(Order $order): bool
    {
        try {
            $guestInfo = $this->getGuestInfo($order);
            $guestPhone = $guestInfo['phone'] ?? null;

            if (!$guestPhone) {
                Log::warning('Guest phone number not available for order: ' . $order->id);
                return false;
            }

            $message = $this->generateGuestWhatsAppMessage($order);

            // Attempt Twilio send; fall back to logging
            $this->sendWhatsAppMessage($guestPhone, $message);

            return true;

        } catch (\Exception $e) {
            Log::error('Guest WhatsApp notification failed for order ' . $order->id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get guest information from check-in data
     */
    private function getGuestInfo(Order $order): array
    {
        try {
            // Try to find guest check-in data by access token
            $accessToken = $order->order_details['access_token'] ?? null;
            
            if ($accessToken) {
                $guestCheckIn = GuestCheckIn::where('access_token', $accessToken)->first();
                
                if ($guestCheckIn) {
                    return [
                        'name' => $guestCheckIn->full_name,
                        'email' => $guestCheckIn->email,
                        'phone' => $guestCheckIn->phone_number,
                        'passport_url' => $guestCheckIn->passport_url,
                    ];
                }
            }

            // Fallback to order data
            return [
                'name' => $order->guest_name,
                'email' => $order->guest_email,
                'phone' => $order->guest_phone,
                'passport_url' => null,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get guest info for order ' . $order->id . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate invoice data for the order
     */
    private function generateInvoiceData(Order $order): array
    {
        $orderDetails = $order->order_details ?? [];
        $cartItems = $orderDetails['cart_items'] ?? [];
        
        $invoiceItems = [];
        $subtotal = 0;

        foreach ($cartItems as $item) {
            $itemTotal = $item['total_price'] ?? 0;
            $subtotal += $itemTotal;
            
            $invoiceItems[] = [
                'title' => $item['upsell_title'] ?? 'Service',
                'quantity' => $item['guest_count'] ?? 1,
                'unit_price' => $item['unit_price'] ?? $itemTotal,
                'total' => $itemTotal,
                'date' => $item['selected_date'] ?? null,
                'notes' => $item['special_notes'] ?? '',
            ];
        }

        $taxRate = 0.21; // 21% VAT (adjust based on your location)
        $taxAmount = $subtotal * $taxRate;
        $total = $subtotal + $taxAmount;

        return [
            'invoice_number' => 'INV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
            'order_number' => 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
            'date' => $order->created_at->format('d/m/Y'),
            'due_date' => $order->created_at->addDays(30)->format('d/m/Y'),
            'items' => $invoiceItems,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate * 100,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'currency' => $order->currency ?? 'EUR',
        ];
    }

    /**
     * Generate WhatsApp message for vendor
     */
    private function generateWhatsAppMessage(Order $order): string
    {
        $guestInfo = $this->getGuestInfo($order);
        $guestName = $guestInfo['name'] ?? 'Guest';
        
        return "🎉 *New Booking Alert!*\n\n" .
               "Service: *{$order->upsell->title}*\n" .
               "Guest: *{$guestName}*\n" .
               "Amount: *{$order->currency} {$order->amount}*\n" .
               "Property: *{$order->property->name}*\n\n" .
               "Please check your email for full details.\n\n" .
               "Thank you! 🏖️";
    }

    /**
     * Generate WhatsApp message for guest
     */
    private function generateGuestWhatsAppMessage(Order $order): string
    {
        return "✅ *Booking Confirmed!*\n\n" .
               "Service: *{$order->upsell->title}*\n" .
               "Amount: *{$order->currency} {$order->amount}*\n" .
               "Property: *{$order->property->name}*\n\n" .
               "Your confirmation email with invoice has been sent.\n\n" .
               "Thank you for choosing us! 🏖️";
    }

    /**
     * Send a WhatsApp message via Twilio if configured; otherwise log.
     */
    private function sendWhatsAppMessage(string $toPhone, string $message): void
    {
        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $from = config('services.twilio.whatsapp_from');

            if (!$sid || !$token || !$from) {
                Log::warning('Twilio not configured. Skipping WhatsApp send.', [
                    'sid' => (bool) $sid,
                    'token' => (bool) $token,
                    'from' => (bool) $from,
                ]);
                Log::info('WhatsApp (mock) to ' . $toPhone . ': ' . $message);
                return;
            }

            // Normalize numbers to whatsapp:+<number>
            $fromAddress = str_starts_with($from, 'whatsapp:') ? $from : ('whatsapp:' . ltrim($from, '+'));
            $toAddress = str_starts_with($toPhone, 'whatsapp:') ? $toPhone : ('whatsapp:' . ltrim($toPhone, '+'));

            // Use direct cURL approach for WhatsApp messaging
            $this->sendWhatsAppDirect($sid, $token, $fromAddress, $toAddress, $message);

            // Logging is handled in sendWhatsAppDirect
        } catch (\Throwable $e) {
            Log::error('Twilio WhatsApp send failed: ' . $e->getMessage());
            // Always log the message so we can see what would have been sent
            Log::info('WhatsApp (fallback log) to ' . $toPhone . ': ' . $message);
        }
    }

    /**
     * Send WhatsApp message using direct cURL (bypasses SSL issues in local development)
     */
    private function sendWhatsAppDirect(string $sid, string $token, string $fromAddress, string $toAddress, string $message): void
    {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        
        $data = [
            'From' => $fromAddress,
            'To' => $toAddress,
            'Body' => $message,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        
        // Disable SSL verification for local development
        if (app()->environment('local')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CAINFO, '');
            curl_setopt($ch, CURLOPT_CAPATH, '');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('WhatsApp direct cURL error: ' . $error);
            Log::info('WhatsApp (fallback log) to ' . $toAddress . ': ' . $message);
            return;
        }

        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            Log::info('WhatsApp message sent via direct cURL', [
                'to' => $toAddress,
                'message_sid' => $result['sid'] ?? 'unknown',
                'status' => $result['status'] ?? 'unknown',
            ]);
        } else {
            Log::error('WhatsApp direct cURL HTTP error: ' . $httpCode . ' - ' . $response);
            Log::info('WhatsApp (fallback log) to ' . $toAddress . ': ' . $message);
        }
    }

    /**
     * Send all notifications for a completed order
     */
    public function sendOrderNotifications(Order $order): array
    {
        $results = [
            'vendor_email' => false,
            'guest_email' => false,
            'vendor_whatsapp' => false,
            'guest_whatsapp' => false,
        ];

        try {
            // Send vendor email notification
            $results['vendor_email'] = $this->sendVendorNotification($order);
            
            // Send guest confirmation email
            $results['guest_email'] = $this->sendGuestConfirmation($order);
            
            // Send WhatsApp notifications
            $results['vendor_whatsapp'] = $this->sendVendorWhatsApp($order);
            $results['guest_whatsapp'] = $this->sendGuestWhatsApp($order);
            
            Log::info('Order notifications sent for order ' . $order->id . ': ' . json_encode($results));
            
        } catch (\Exception $e) {
            Log::error('Failed to send order notifications for order ' . $order->id . ': ' . $e->getMessage());
        }

        return $results;
    }
}