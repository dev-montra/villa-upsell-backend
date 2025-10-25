# Stripe CLI Testing Guide for Villa Upsell

## 🔌 Stripe CLI Integration Testing

This guide will help you test Stripe payments with webhook forwarding using Stripe CLI, which is essential for proper webhook testing during development.

## 📋 Prerequisites

### 1. Install Stripe CLI
```bash
# Windows (using winget)
winget install stripe.stripe-cli

# Or download from GitHub
# https://github.com/stripe/stripe-cli/releases
```

### 2. Login to Stripe
```bash
stripe login
```

### 3. Set Up Environment Variables
Make sure your `.env` file has the correct Stripe test keys:

```env
# Stripe Test Keys
STRIPE_KEY=pk_test_your_stripe_publishable_key_here
STRIPE_SECRET=sk_test_your_stripe_secret_key_here
STRIPE_SECRET_KEY=sk_test_your_stripe_secret_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_from_cli
```

## 🚀 Testing Commands

### Quick Setup and Test
```bash
# Set up Stripe CLI integration
php artisan stripe:cli-test --setup

# Test payment with webhook forwarding
php artisan stripe:cli-test --test-payment

# Check Stripe CLI status
php artisan stripe:cli-test --status
```

### Manual Stripe CLI Commands

#### 1. Start Webhook Forwarding
```bash
# Forward webhooks to your local Laravel app
stripe listen --forward-to http://localhost:8000/api/webhooks/stripe
```

#### 2. Test Payment Events
```bash
# Trigger a test payment event
stripe trigger payment_intent.succeeded
```

#### 3. Listen to Specific Events
```bash
# Listen to specific webhook events
stripe listen --events payment_intent.succeeded,payment_intent.payment_failed
```

## 🧪 Available Test Commands

### 1. Stripe CLI Test Command
```bash
# Setup Stripe CLI
php artisan stripe:cli-test --setup

# Test payment with webhook
php artisan stripe:cli-test --test-payment

# Start webhook listener
php artisan stripe:cli-test --listen

# Check status
php artisan stripe:cli-test --status
```

### 2. Webhook Test Command
```bash
# Create test event
php artisan test:webhooks --create-test-event

# Simulate payment webhook
php artisan test:webhooks --simulate-payment

# Test webhook endpoint
php artisan test:webhooks --test-endpoint
```

### 3. Comprehensive Integration Test
```bash
# Test all integrations including Stripe CLI
php artisan test:integrations --all --email=your-email@example.com --whatsapp=+your-phone-number

# Test only Stripe CLI
php artisan test:integrations --stripe-cli

# Test webhook handling
php artisan test:integrations --webhook
```

## 🔄 Complete Testing Workflow

### Step 1: Start Laravel Development Server
```bash
php artisan serve
# Your app will be available at http://localhost:8000
```

### Step 2: Start Stripe CLI Webhook Forwarding
```bash
stripe listen --forward-to http://localhost:8000/api/webhooks/stripe
```

**Important:** Copy the webhook signing secret from the output and add it to your `.env` file:
```env
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here
```

### Step 3: Test Payment Processing
```bash
# Test payment creation and webhook handling
php artisan stripe:cli-test --test-payment
```

### Step 4: Verify Webhook Processing
Check your Laravel logs to see if the webhook was processed:
```bash
tail -f storage/logs/laravel.log
```

## 📊 Expected Test Results

### Successful Stripe CLI Test Output
```
🔌 Stripe CLI Integration Testing
==================================
🔧 Setting up Stripe CLI for webhook testing...
✅ Stripe CLI found: stripe version 1.x.x
✅ Stripe CLI is configured
🔗 Setting up webhook forwarding...
Webhook URL: http://localhost:8000/api/webhooks/stripe
✅ Stripe CLI setup complete!
Next steps:
1. Run: stripe listen --forward-to http://localhost:8000/api/webhooks/stripe
2. Copy the webhook signing secret from the output
3. Add it to your .env file as STRIPE_WEBHOOK_SECRET
4. Run: php artisan stripe:cli-test --test-payment
```

### Successful Payment Test Output
```
💳 Testing payment with webhook...
✅ Test customer created: cus_test_1234567890
✅ Test payment method created: pm_test_1234567890
✅ Payment intent created: pi_test_1234567890
   Status: succeeded
📋 Test Summary:
   Customer ID: cus_test_1234567890
   Payment Method ID: pm_test_1234567890
   Payment Intent ID: pi_test_1234567890
   Status: succeeded
🔍 Check your webhook logs to see if the payment event was received!
```

## 🔍 Troubleshooting

### Stripe CLI Not Found
```bash
# Check if Stripe CLI is installed
stripe --version

# If not installed, install it:
winget install stripe.stripe-cli
```

### Not Logged In
```bash
# Login to Stripe
stripe login
```

### Webhook Not Receiving Events
1. Check if webhook forwarding is running
2. Verify the webhook URL is correct
3. Check Laravel logs for errors
4. Ensure webhook secret is configured

### Payment Test Fails
1. Verify Stripe test keys are correct
2. Check if Stripe CLI is logged in
3. Ensure Laravel app is running
4. Check webhook forwarding is active

## 📱 Testing with Real Webhooks

### 1. Create Test Payment Intent
The test command creates a real payment intent with test data:
- Amount: $20.00 USD
- Test card: 4242424242424242
- Metadata includes property and order information

### 2. Webhook Events Triggered
When payment succeeds, these events are triggered:
- `payment_intent.succeeded`
- Order creation
- Email notifications
- WhatsApp notifications

### 3. Verify in Stripe Dashboard
- Check the payment intent in Stripe Dashboard
- Verify webhook events were sent
- Check delivery status

## 🔐 Security Notes

- Always use test keys for development
- Never commit webhook secrets to version control
- Use environment variables for all sensitive data
- Test webhook signature verification

## 📞 Support

If you encounter issues:
1. Check Stripe CLI status: `stripe config --list`
2. Verify webhook forwarding: `stripe listen --print-secret`
3. Check Laravel logs: `tail -f storage/logs/laravel.log`
4. Test webhook endpoint: `php artisan test:webhooks --test-endpoint`

Happy testing with Stripe CLI! 🎉