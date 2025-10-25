# Villa Upsell - Production Configuration Guide

## Environment Variables Setup

Update your `.env` file with the following real production keys:

### Stripe Configuration (Real Keys)
```env
STRIPE_KEY=pk_live_your-stripe-publishable-key
STRIPE_SECRET=sk_live_your-stripe-secret-key
STRIPE_SECRET_KEY=sk_live_your-stripe-secret-key
STRIPE_WEBHOOK_SECRET=whsec_your-stripe-webhook-secret
STRIPE_CONNECT_CLIENT_ID=ca_your-stripe-connect-client-id
```

### SendGrid Configuration
```env
SENDGRID_API_KEY=SG.your-sendgrid-api-key-here
MAIL_MAILER=sendgrid
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="Villa Upsell"
```

### Twilio Configuration (Real Keys)
```env
TWILIO_SID=your-twilio-account-sid
TWILIO_TOKEN=your-twilio-auth-token
TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
```

### Wise Configuration (Real Keys)
```env
WISE_TOKEN=your-wise-api-token
WISE_PROFILE_ID=your-wise-profile-id
WISE_ENVIRONMENT=live
```

## Installation Steps

1. **Install SendGrid Package**
   ```bash
   composer install
   ```

2. **Update Configuration**
   - Copy the environment variables above to your `.env` file
   - Replace placeholder values with your actual API keys

3. **Test Configuration**
   ```bash
   # Test email notifications
   php artisan notif:test --email=test@example.com
   
   # Test WhatsApp notifications
   php artisan notif:test --whatsapp=+31612345678
   
   # Test both
   php artisan notif:test --email=test@example.com --whatsapp=+31612345678
   ```

## Features Implemented

### ✅ Stripe Payment Processing
- Real Stripe keys integration
- Payment intent creation
- Webhook handling for payment events
- Connect account management for property owners

### ✅ SendGrid Email Notifications
- Automatic vendor notifications on new bookings
- Guest confirmation emails with invoices
- Fallback to default mailer if SendGrid fails
- Professional HTML email templates

### ✅ WhatsApp Notifications
- Real Twilio integration for WhatsApp messages
- Vendor notifications for new bookings
- Guest confirmation messages
- Proper error handling and logging

### ✅ Production Ready
- All services configured for production use
- Comprehensive error handling
- Detailed logging for debugging
- Fallback mechanisms for reliability

## Testing

Use the test command to verify all integrations:
```bash
php artisan notif:test --email=your-email@example.com --whatsapp=+your-phone-number
```

This will test both SendGrid email delivery and Twilio WhatsApp messaging.