# Complete Stripe Testing Guide - Connect OAuth + Webhooks

## 🎯 Testing Both Stripe Connect OAuth and Webhooks

You're absolutely right! Stripe CLI is primarily for webhook testing, but for Stripe Connect OAuth flow, you need to test the actual OAuth redirect flow. This guide covers both scenarios.

## 🔧 Prerequisites

### 1. Install Required Tools
```bash
# Install ngrok for local development
# Download from: https://ngrok.com/download
# Or use package manager: winget install ngrok.ngrok

# Install Stripe CLI for webhook testing
winget install stripe.stripe-cli
```

### 2. Set Up Environment Variables
```env
# Stripe Test Keys
STRIPE_KEY=pk_test_your_stripe_publishable_key_here
STRIPE_SECRET=sk_test_your_stripe_secret_key_here
STRIPE_SECRET_KEY=sk_test_your_stripe_secret_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here
STRIPE_CONNECT_CLIENT_ID=ca_your_connect_client_id_here

# Optional: Set ngrok URL if you want to hardcode it
NGROK_URL=https://your-ngrok-url.ngrok-free.app
```

## 🚀 Testing Commands

### Stripe Connect OAuth Testing
```bash
# Set up Stripe Connect for testing
php artisan stripe:connect-test --setup

# Check ngrok status and get current URL
php artisan stripe:connect-test --check-ngrok

# Test OAuth flow (creates OAuth URL)
php artisan stripe:connect-test --test-oauth

# Create test Connect account
php artisan stripe:connect-test --create-test-account

# Get instructions for updating redirect URI
php artisan stripe:connect-test --update-redirect-uri
```

### Stripe CLI Webhook Testing
```bash
# Set up Stripe CLI for webhook testing
php artisan stripe:cli-test --setup

# Test payment with webhook forwarding
php artisan stripe:cli-test --test-payment

# Start webhook listener
php artisan stripe:cli-test --listen

# Check Stripe CLI status
php artisan stripe:cli-test --status
```

### Webhook Testing (Without CLI)
```bash
# Simulate payment webhook
php artisan test:webhooks --simulate-payment

# Create test event
php artisan test:webhooks --create-test-event

# Test webhook endpoint
php artisan test:webhooks --test-endpoint
```

## 🔄 Complete Testing Workflow

### Part 1: Test Stripe Connect OAuth Flow

#### Step 1: Start ngrok
```bash
# Start ngrok to expose your local server
ngrok http 8000
```

#### Step 2: Check ngrok URL
```bash
php artisan stripe:connect-test --check-ngrok
```

#### Step 3: Update Stripe Dashboard
```bash
# Get the redirect URI to add to Stripe Dashboard
php artisan stripe:connect-test --update-redirect-uri
```

Then:
1. Go to https://dashboard.stripe.com/connect/applications
2. Select your Connect app
3. Go to "OAuth settings"
4. Add the redirect URI shown in the command output
5. Save changes

#### Step 4: Test OAuth Flow
```bash
# Generate OAuth URL for testing
php artisan stripe:connect-test --test-oauth
```

Then:
1. Open the generated URL in your browser
2. Complete the Stripe Connect authorization
3. Verify you get redirected back successfully

### Part 2: Test Webhooks with Stripe CLI

#### Step 1: Start Laravel Server
```bash
php artisan serve
```

#### Step 2: Set Up Webhook Forwarding
```bash
# Set up Stripe CLI
php artisan stripe:cli-test --setup

# Start webhook forwarding
stripe listen --forward-to http://localhost:8000/api/webhooks/stripe
```

**Important:** Copy the webhook signing secret and add it to your `.env` file:
```env
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here
```

#### Step 3: Test Payment with Webhook
```bash
# Test payment creation and webhook handling
php artisan stripe:cli-test --test-payment
```

#### Step 4: Verify Webhook Processing
Check your Laravel logs to see if the webhook was processed:
```bash
tail -f storage/logs/laravel.log
```

## 🧪 Testing Scenarios

### Scenario 1: Admin Connects Stripe Account
```bash
# 1. Start ngrok
ngrok http 8000

# 2. Check ngrok URL
php artisan stripe:connect-test --check-ngrok

# 3. Update redirect URI in Stripe Dashboard
php artisan stripe:connect-test --update-redirect-uri

# 4. Test OAuth flow
php artisan stripe:connect-test --test-oauth

# 5. Complete OAuth in browser
# 6. Verify redirect back to admin dashboard
```

### Scenario 2: Guest Makes Payment (Webhook Testing)
```bash
# 1. Start Laravel server
php artisan serve

# 2. Start webhook forwarding
stripe listen --forward-to http://localhost:8000/api/webhooks/stripe

# 3. Test payment with webhook
php artisan stripe:cli-test --test-payment

# 4. Check webhook processing in logs
tail -f storage/logs/laravel.log
```

### Scenario 3: Complete Integration Test
```bash
# Test all integrations
php artisan test:integrations --all --email=your-email@example.com --whatsapp=+your-phone-number

# Test only Stripe Connect
php artisan test:integrations --stripe-cli

# Test webhook handling
php artisan test:integrations --webhook
```

## 🔍 Troubleshooting

### Stripe Connect OAuth Issues

#### "Invalid redirect URI" Error
1. Check ngrok URL: `php artisan stripe:connect-test --check-ngrok`
2. Update redirect URI in Stripe Dashboard
3. Make sure the URL matches exactly (including https://)

#### OAuth Flow Not Working
1. Verify Stripe Connect Client ID is correct
2. Check if you're using test keys for test mode
3. Ensure ngrok is running and accessible

### Webhook Issues

#### Webhooks Not Received
1. Check if webhook forwarding is running
2. Verify webhook secret is configured
3. Check Laravel logs for errors
4. Ensure webhook endpoint is accessible

#### Payment Test Fails
1. Verify Stripe test keys are correct
2. Check if Stripe CLI is logged in
3. Ensure Laravel app is running
4. Check webhook forwarding is active

## 📊 Expected Results

### Successful Stripe Connect Setup
```
🔧 Setting up Stripe Connect for testing...
✅ Stripe Connect configuration found
   Client ID: ca_TFpBtlt...
   Mode: TEST
✅ Ngrok detected: https://abc123.ngrok-free.app
   Redirect URI: https://abc123.ngrok-free.app/api/stripe/connect/callback
📋 Next steps:
1. Make sure your ngrok is running: ngrok http 8000
2. Update redirect URI in Stripe Dashboard:
   https://abc123.ngrok-free.app/api/stripe/connect/callback
3. Test OAuth flow: php artisan stripe:connect-test --test-oauth
```

### Successful OAuth Test
```
🔐 Testing Stripe Connect OAuth flow...
✅ OAuth URL created:
   https://connect.stripe.com/oauth/authorize?client_id=ca_TFpBtlt...&redirect_uri=https://abc123.ngrok-free.app/api/stripe/connect/callback&response_type=code&scope=read_write&state=test_user_123

📋 To test:
1. Open this URL in your browser
2. Complete the Stripe Connect authorization
3. Check if you get redirected back successfully

⚠️ Make sure this redirect URI is added to your Stripe Connect app:
   https://abc123.ngrok-free.app/api/stripe/connect/callback
```

## 🔐 Security Notes

- Always use test keys for development
- Never commit API keys to version control
- Use environment variables for all sensitive data
- Test webhook signature verification
- Verify OAuth state parameter for security

## 📞 Support

If you encounter issues:
1. Check ngrok status: `php artisan stripe:connect-test --check-ngrok`
2. Verify Stripe Connect app configuration
3. Check Laravel logs: `tail -f storage/logs/laravel.log`
4. Test webhook endpoint: `php artisan test:webhooks --test-endpoint`

Happy testing! 🎉