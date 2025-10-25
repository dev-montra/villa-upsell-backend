# Stripe Connect Setup Guide

This document explains how to set up Stripe Connect for the Villa Upsell application.

## Overview

The application uses Stripe Connect to allow property owners to receive payments directly to their bank accounts. When a guest makes a payment for an upsell service, the platform takes a 10% fee and the remaining 90% goes directly to the property owner's Stripe account.

## Backend Configuration

### Environment Variables

Add these environment variables to your `.env` file:

```env
# Stripe Configuration
STRIPE_KEY=pk_live_your_publishable_key
STRIPE_SECRET=sk_live_your_secret_key
STRIPE_SECRET_KEY=sk_live_your_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret
STRIPE_CONNECT_CLIENT_ID=ca_your_connect_client_id

# Application URLs
APP_URL=https://your-backend-domain.com
FRONTEND_URL=https://your-frontend-domain.com
```

### Stripe Dashboard Setup

1. **Create a Stripe Connect Application**
   - Go to https://dashboard.stripe.com/connect/applications
   - Click "Create application"
   - Fill in your application details
   - Note down the `Client ID` (starts with `ca_`)

2. **Configure OAuth Settings**
   - In your Connect application settings, add the redirect URI:
     - `https://your-backend-domain.com/api/stripe/connect/callback`

3. **Set up Webhooks**
   - Go to https://dashboard.stripe.com/webhooks
   - Click "Add endpoint"
   - Set the endpoint URL to: `https://your-backend-domain.com/api/webhooks/stripe`
   - Select these events:
     - `account.updated`
     - `account.application.deauthorized`
     - `payment_intent.succeeded`
     - `payment_intent.payment_failed`

## How It Works

### 1. Property Owner Connects Stripe Account

When a property owner clicks "Connect Stripe Account" in the admin dashboard:

1. Frontend calls `POST /api/stripe/connect`
2. Backend creates OAuth URL with proper parameters
3. User is redirected to Stripe's hosted OAuth page
4. User authorizes the application
5. Stripe redirects back to `/api/stripe/connect/callback`
6. Backend exchanges authorization code for account ID
7. User is redirected back to frontend with success/error status

### 2. Payment Processing

When a guest makes a payment:

1. Frontend creates payment intent via `POST /api/payments/create-intent`
2. Backend creates Stripe PaymentIntent with Connect transfer
3. Guest completes payment on Stripe's hosted checkout
4. Stripe webhook notifies backend of successful payment
5. Backend creates order records and notifies vendors

### 3. Account Status Monitoring

The application continuously monitors Stripe Connect account status:

- Frontend polls `GET /api/stripe/connect/status` every 5 seconds
- Backend retrieves real-time account status from Stripe API
- Webhooks update account status when Stripe account changes

## Security Considerations

- **State Parameter**: Uses user ID as state parameter to prevent CSRF attacks
- **Webhook Verification**: All webhooks are verified using Stripe signatures
- **Token Exchange**: Authorization codes are exchanged server-side only
- **Account Validation**: Account status is verified with Stripe API before processing payments

## Testing

### Test Mode

For testing, use Stripe test keys and test Connect application:

```env
STRIPE_KEY=pk_test_your_test_publishable_key
STRIPE_SECRET=sk_test_your_test_secret_key
STRIPE_CONNECT_CLIENT_ID=ca_test_your_test_connect_client_id
```

### Test Flow

1. Create a test Stripe Connect account
2. Use test payment methods (4242 4242 4242 4242)
3. Verify webhook events in Stripe dashboard
4. Check account status updates in application

## Troubleshooting

### Common Issues

1. **"Stripe Connect client ID is not configured"**
   - Ensure `STRIPE_CONNECT_CLIENT_ID` is set in environment variables

2. **"Invalid redirect URI"**
   - Verify the redirect URI in Stripe Connect application settings matches your backend URL

3. **"Webhook signature verification failed"**
   - Ensure `STRIPE_WEBHOOK_SECRET` is correctly set
   - Verify webhook endpoint URL is accessible

4. **"API key does not have access to account"**
   - This error occurs when the user has an old/invalid Stripe account ID in the database
   - The application will automatically clear the invalid account and allow reconnection
   - To manually clear all invalid accounts, run: `php artisan stripe:clear-invalid-accounts`

5. **Account status not updating**
   - Check webhook endpoint is receiving events
   - Verify webhook secret is correct
   - Check application logs for webhook processing errors

### Clearing Invalid Stripe Accounts

If you encounter issues with old/invalid Stripe account IDs, you can clear them using the provided command:

```bash
# Dry run to see what would be cleared
php artisan stripe:clear-invalid-accounts --dry-run

# Actually clear the invalid accounts
php artisan stripe:clear-invalid-accounts
```

This command will:
- Find all users with Stripe account IDs
- Clear the `stripe_account_id` and `stripe_onboarding_completed` fields
- Allow users to reconnect their Stripe accounts

### Logs

Check these log entries for debugging:

- `Stripe Connect OAuth creation failed`
- `Stripe OAuth callback failed`
- `Failed to check Stripe Connect status`
- `Failed to handle account update`

## Production Checklist

- [ ] Use live Stripe keys (not test keys)
- [ ] Set up live webhook endpoint
- [ ] Configure proper redirect URIs
- [ ] Test complete payment flow
- [ ] Verify webhook events are received
- [ ] Monitor account status updates
- [ ] Set up error alerting for webhook failures