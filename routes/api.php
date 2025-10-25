<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\UpsellController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\TwilioWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint for Render monitoring
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'environment' => app()->environment(),
    ]);
});

// Public routes (no authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Guest access routes (no authentication required)
Route::get('/properties/access/{accessToken}', [PropertyController::class, 'access']);
Route::get('/properties/{property}/upsells', [PropertyController::class, 'upsells']);

// Guest check-in routes (no authentication required)
Route::post('/guest/check-in', [GuestController::class, 'checkIn']);
Route::get('/guest/check-in-status/{accessToken}', [GuestController::class, 'getCheckInStatus']);
Route::get('/guest/check-specific-status/{accessToken}', [GuestController::class, 'checkSpecificGuestStatus']);

// Public file upload route for guest check-ins only
Route::post('/guest/upload-image', [UploadController::class, 'uploadImage']);

// Guest payment routes (no authentication required)
Route::post('/guest/payments/create-intent', [PaymentController::class, 'createGuestPaymentIntent']);
Route::post('/guest/payments/wise', [PaymentController::class, 'createGuestWisePayment']);

// Guest routes (tokenized access)
Route::prefix('guest')->group(function () {
    // These will be implemented in Phase 3
    // Route::get('/validate/{token}', [GuestController::class, 'validateToken']);
    // Route::post('/check-in', [GuestController::class, 'checkIn']);
    // Route::get('/upsells/{token}', [GuestController::class, 'getUpsells']);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    // Dashboard routes
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/recent-orders', [DashboardController::class, 'recentOrders']);
    Route::get('/dashboard/revenue-analytics', [DashboardController::class, 'revenueAnalytics']);
    Route::get('/dashboard/upsell-analytics', [DashboardController::class, 'upsellAnalytics']);
    
    // Accounting export routes
    Route::get('/dashboard/export-accounting', [DashboardController::class, 'exportAccountingData']);
    Route::get('/dashboard/accounting-summary', [DashboardController::class, 'getAccountingSummary']);

    // Property management routes
    Route::apiResource('properties', PropertyController::class);
    
    // Vendor management routes
    Route::apiResource('vendors', VendorController::class);
    
    // Upsell management routes
    Route::apiResource('upsells', UpsellController::class);
    Route::put('/upsells/sort-order', [UpsellController::class, 'updateSortOrder']);
    
    // Order management routes
    Route::apiResource('orders', OrderController::class);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::get('/orders/recent', [OrderController::class, 'recent']);
    Route::get('/orders/stats', [OrderController::class, 'stats']);
    Route::get('/orders/export', [OrderController::class, 'export']);

    // File upload routes (protected - for admin use)
    Route::post('/upload-image', [UploadController::class, 'uploadImage']);
    Route::delete('/delete-image', [UploadController::class, 'deleteImage']);
    
    // Payment routes (protected)
    Route::post('/stripe/connect', [PaymentController::class, 'createConnectAccount']);
    Route::get('/stripe/connect/status', [PaymentController::class, 'checkConnectStatus']);
    Route::post('/payments/create-intent', [PaymentController::class, 'createPaymentIntent']);
    Route::post('/payments/wise', [PaymentController::class, 'createWisePayment']);
});

// Public routes for external callbacks
Route::post('/stripe/connect/callback', [PaymentController::class, 'handleOAuthCallback']);

// Webhook routes (no authentication, but with signature verification)
Route::prefix('webhooks')->group(function () {
    // Stripe webhook
    Route::post('/stripe', [PaymentController::class, 'handleWebhook']);
    Route::post('/wise', [PaymentController::class, 'handleWiseWebhook']);
    
    // Twilio webhooks
    Route::post('/twilio/message', [TwilioWebhookController::class, 'handleIncomingMessage']);
    Route::post('/twilio/status', [TwilioWebhookController::class, 'handleStatusCallback']);
});
