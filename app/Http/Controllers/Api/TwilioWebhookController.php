<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\MessagingResponse;

class TwilioWebhookController extends Controller
{
    /**
     * Handle incoming WhatsApp messages from Twilio
     */
    public function handleIncomingMessage(Request $request)
    {
        try {
            // Log the incoming message for debugging
            Log::info('Twilio WhatsApp webhook received', [
                'from' => $request->input('From'),
                'to' => $request->input('To'),
                'body' => $request->input('Body'),
                'message_sid' => $request->input('MessageSid'),
                'all_params' => $request->all()
            ]);

            $from = $request->input('From'); // whatsapp:+1234567890
            $body = $request->input('Body', '');
            $messageSid = $request->input('MessageSid');

            // Create TwiML response
            $response = new MessagingResponse();

            // Handle different message types
            if (empty($body)) {
                $response->message('Hello! Please send a text message.');
            } else {
                // Process the message and send a response
                $replyMessage = $this->processIncomingMessage($from, $body);
                $response->message($replyMessage);
            }

            return response($response, 200)
                ->header('Content-Type', 'text/xml');

        } catch (\Exception $e) {
            Log::error('Twilio webhook error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty TwiML response to avoid Twilio retries
            $response = new MessagingResponse();
            return response($response, 200)
                ->header('Content-Type', 'text/xml');
        }
    }

    /**
     * Process incoming WhatsApp message and generate response
     */
    private function processIncomingMessage(string $from, string $body): string
    {
        $body = strtolower(trim($body));

        // Handle common commands
        switch ($body) {
            case 'hello':
            case 'hi':
            case 'hey':
                return "Hello! Welcome to Villa Upsell. How can I help you today? 🏖️";

            case 'help':
                return "Available commands:\n• hello - Greeting\n• help - Show this help\n• status - Check order status\n• support - Contact support";

            case 'status':
                return "To check your order status, please provide your order number or contact our support team.";

            case 'support':
                return "For support, please contact us at:\n📧 Email: support@villa-upsell.com\n📞 Phone: +1-800-VILLA-UP";

            default:
                // Default response for unrecognized messages
                return "Thank you for your message! For assistance, please contact our support team or visit our website. We're here to help! 😊";
        }
    }

    /**
     * Handle Twilio status callbacks
     */
    public function handleStatusCallback(Request $request)
    {
        try {
            Log::info('Twilio status callback received', [
                'message_sid' => $request->input('MessageSid'),
                'status' => $request->input('MessageStatus'),
                'error_code' => $request->input('ErrorCode'),
                'error_message' => $request->input('ErrorMessage'),
                'all_params' => $request->all()
            ]);

            $messageSid = $request->input('MessageSid');
            $status = $request->input('MessageStatus');
            $errorCode = $request->input('ErrorCode');
            $errorMessage = $request->input('ErrorMessage');

            // Log status updates for monitoring
            if ($status === 'failed' || $status === 'undelivered') {
                Log::warning('WhatsApp message delivery failed', [
                    'message_sid' => $messageSid,
                    'status' => $status,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage
                ]);
            }

            return response()->json(['status' => 'received']);

        } catch (\Exception $e) {
            Log::error('Twilio status callback error: ' . $e->getMessage());
            return response()->json(['error' => 'Callback processing failed'], 500);
        }
    }
}