# Production Deployment Configuration

## Environment Variables (.env)

Ensure these production environment variables are set:

```env
# Application
APP_NAME="Villa Upsell"
APP_ENV=production
APP_KEY=base64:your_app_key_here
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=your_db_host
DB_PORT=5432
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Mail Configuration
MAIL_MAILER=sendgrid
MAIL_FROM_ADDRESS=support@holidayupsell.com
MAIL_FROM_NAME="Villa Upsell"

# SendGrid
SENDGRID_API_KEY=your_sendgrid_api_key

# Stripe
STRIPE_KEY=pk_live_your_live_publishable_key
STRIPE_SECRET_KEY=sk_live_your_live_secret_key
STRIPE_CONNECT_CLIENT_ID=ca_your_live_connect_client_id
STRIPE_WEBHOOK_SECRET=whsec_your_live_webhook_secret

# Twilio
TWILIO_ACCOUNT_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_WHATSAPP_FROM=whatsapp:+your_twilio_whatsapp_number

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

## Production Checklist

### ✅ Completed Cleanup Tasks:
- [x] Removed all test Artisan commands
- [x] Removed test API controllers
- [x] Removed test routes from api.php
- [x] SSL bypass code properly configured for local development only
- [x] Mail driver set to sendgrid for production

### 🔧 Production Setup Required:

1. **Database Migration**:
   ```bash
   php artisan migrate --force
   ```

2. **Cache Configuration**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Queue Configuration** (if using queues):
   ```bash
   php artisan queue:work --daemon
   ```

4. **Webhook Configuration**:
   - Stripe Dashboard: Add webhook endpoint `https://your-domain.com/api/webhooks/stripe`
   - Twilio Dashboard: Configure WhatsApp webhook `https://your-domain.com/api/webhooks/twilio/message`

5. **SSL Certificate**: Ensure HTTPS is properly configured on your server

6. **File Permissions**:
   ```bash
   chmod -R 755 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

## Security Notes

- SSL bypass code is only active in `local` environment
- All test routes and controllers have been removed
- Production mail driver uses SendGrid with proper SSL verification
- WhatsApp messaging uses direct cURL with SSL verification in production

## Monitoring

Monitor these logs in production:
- `storage/logs/laravel.log` - Application logs
- SendGrid delivery logs
- Twilio message delivery logs
- Stripe webhook logs