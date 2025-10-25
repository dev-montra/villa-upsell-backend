# 🚀 Villa Upsell - Complete Deployment Roadmap
## Step-by-Step Guide to Production Deployment

---

## 📋 **PRE-DEPLOYMENT CHECKLIST**

### ✅ **Completed Tasks:**
- [x] Project cleaned (test commands removed)
- [x] SSL bypass configured for local development only
- [x] SendGrid email integration working
- [x] WhatsApp notifications working
- [x] Wise Profile ID obtained: `58946812`

### 🔄 **Required Before Deployment:**
- [ ] Production API keys (Stripe Live, Twilio, SendGrid)
- [ ] Domain name registered
- [ ] Laravel Forge account
- [ ] Cloud provider account (DigitalOcean/AWS/Linode)

---

## 🏗️ **PHASE 1: INFRASTRUCTURE SETUP**

### **Step 1.1: Laravel Forge Account Setup**
1. **Sign up for Laravel Forge**
   - Go to https://forge.laravel.com
   - Create account with GitHub integration
   - Connect your GitHub repository

2. **Connect Cloud Provider**
   - Choose provider: DigitalOcean (recommended), AWS, or Linode
   - Connect your cloud account
   - Ensure you have billing set up

### **Step 1.2: Create Production Server**
1. **In Forge Dashboard:**
   - Click "Create Server"
   - **Provider**: DigitalOcean (or your choice)
   - **Size**: 4GB RAM, 2 CPU (minimum for production)
   - **Region**: Choose closest to your users
   - **PHP Version**: 8.2 or 8.3
   - **Database**: PostgreSQL
   - **Redis**: Enable
   - **SSL**: Enable Let's Encrypt
   - **Server Name**: `villa-upsell-production`

2. **Wait for server provisioning** (5-10 minutes)

### **Step 1.3: Server Access Setup**
1. **SSH into your server:**
   ```bash
   ssh forge@your-server-ip-address
   ```

2. **Install additional PHP extensions:**
   ```bash
   sudo apt update
   sudo apt install php8.2-pgsql php8.2-curl php8.2-gd php8.2-mbstring php8.2-xml php8.2-zip php8.2-bcmath
   ```

3. **Restart PHP-FPM:**
   ```bash
   sudo service php8.2-fpm restart
   ```

---

## 🗄️ **PHASE 2: DATABASE SETUP**

### **Step 2.1: PostgreSQL Configuration**
1. **Access PostgreSQL:**
   ```bash
   sudo -u postgres psql
   ```

2. **Create production database:**
   ```sql
   CREATE DATABASE villa_upsell_production;
   CREATE USER villa_user WITH PASSWORD 'your_secure_password_here';
   GRANT ALL PRIVILEGES ON DATABASE villa_upsell_production TO villa_user;
   \q
   ```

3. **Test connection:**
   ```bash
   psql -h localhost -U villa_user -d villa_upsell_production
   ```

### **Step 2.2: Redis Configuration**
1. **Check Redis status:**
   ```bash
   sudo systemctl status redis-server
   ```

2. **Configure Redis (if needed):**
   ```bash
   sudo nano /etc/redis/redis.conf
   # Ensure: bind 127.0.0.1
   # Restart: sudo systemctl restart redis-server
   ```

---

## 🌐 **PHASE 3: DOMAIN & DNS SETUP**

### **Step 3.1: Domain Configuration**
1. **Register domains** (if not already done):
   - `your-domain.com` (main frontend)
   - `api.your-domain.com` (backend API)

2. **Configure DNS records:**
   ```
   Type: A
   Name: @
   Value: your-server-ip-address
   TTL: 300

   Type: A
   Name: api
   Value: your-server-ip-address
   TTL: 300

   Type: A
   Name: www
   Value: your-server-ip-address
   TTL: 300
   ```

### **Step 3.2: SSL Certificate Setup**
1. **In Forge Dashboard:**
   - Go to your server
   - Click "Sites" tab
   - Add domains for SSL certificates

---

## 📦 **PHASE 4: BACKEND DEPLOYMENT**

### **Step 4.1: Create Backend Site in Forge**
1. **In Forge Dashboard:**
   - Click "Sites" tab
   - Click "Create Site"
   - **Domain**: `api.your-domain.com`
   - **Directory**: `/home/forge/api.your-domain.com`
   - **App**: Create new Laravel app

2. **Connect Repository:**
   - **Repository**: `your-github-username/villa-upsell-backend`
   - **Branch**: `main`
   - **Deploy Script**: Custom (see below)

### **Step 4.2: Custom Deploy Script**
Replace the default deploy script with this:

```bash
cd /home/forge/api.your-domain.com

# Pull latest code
git pull origin main

# Install/update dependencies
$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Run database migrations
$FORGE_PHP artisan migrate --force

# Clear and cache config
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache

# Restart queue workers
$FORGE_PHP artisan queue:restart

# Restart PHP-FPM
sudo service php8.2-fpm restart
```

### **Step 4.3: Environment Configuration**
1. **Create production .env file:**
   ```bash
   nano /home/forge/api.your-domain.com/.env
   ```

2. **Add production environment variables:**
   ```env
   # Application
   APP_NAME="Villa Upsell"
   APP_ENV=production
   APP_KEY=base64:your_generated_app_key
   APP_DEBUG=false
   APP_TIMEZONE=UTC
   APP_URL=https://api.your-domain.com
   APP_FRONTEND_URL=https://your-domain.com

   # Database
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=villa_upsell_production
   DB_USERNAME=villa_user
   DB_PASSWORD=your_secure_password_here

   # Mail Configuration
   MAIL_MAILER=sendgrid
   MAIL_FROM_ADDRESS=support@holidayupsell.com
   MAIL_FROM_NAME="Villa Upsell"

   # SendGrid (Production)
   SENDGRID_API_KEY=your_production_sendgrid_key

   # Stripe (Production - LIVE KEYS)
   STRIPE_KEY=pk_live_your_live_publishable_key
   STRIPE_SECRET_KEY=sk_live_your_live_secret_key
   STRIPE_CONNECT_CLIENT_ID=ca_your_live_connect_client_id
   STRIPE_WEBHOOK_SECRET=whsec_your_live_webhook_secret

   # Twilio (Production)
   TWILIO_ACCOUNT_SID=your_production_twilio_sid
   TWILIO_AUTH_TOKEN=your_production_twilio_token
   TWILIO_WHATSAPP_FROM=whatsapp:+your_production_whatsapp_number

   # Wise (Production)
   WISE_API_KEY=your_production_wise_api_key
   WISE_TOKEN=your_production_wise_token
   WISE_PROFILE_ID=58946812

   # Session & Cache
   SESSION_DRIVER=database
   SESSION_LIFETIME=120
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis

   # Redis
   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379

   # Logging
   LOG_CHANNEL=stack
   LOG_LEVEL=error
   ```

3. **Generate application key:**
   ```bash
   cd /home/forge/api.your-domain.com
   php artisan key:generate
   ```

### **Step 4.4: Initial Deployment**
1. **Trigger deployment in Forge Dashboard**
2. **Check deployment logs for errors**
3. **Test API endpoint:**
   ```bash
   curl -X GET https://api.your-domain.com/api/properties/access/test-token
   ```

---

## 🎨 **PHASE 5: FRONTEND DEPLOYMENT**

### **Step 5.1: Create Frontend Site in Forge**
1. **In Forge Dashboard:**
   - Click "Sites" tab
   - Click "Create Site"
   - **Domain**: `your-domain.com`
   - **Directory**: `/home/forge/your-domain.com`
   - **App**: Static site

### **Step 5.2: Frontend Build Process**
1. **Local build:**
   ```bash
   cd villa-upsell-guest
   npm install
   npm run build
   ```

2. **Upload to server:**
   ```bash
   # Upload dist folder contents to /home/forge/your-domain.com/public
   scp -r dist/* forge@your-server-ip:/home/forge/your-domain.com/public/
   ```

### **Step 5.3: Frontend Environment Configuration**
1. **Update frontend environment variables:**
   ```env
   VITE_API_URL=https://api.your-domain.com
   VITE_STRIPE_PUBLISHABLE_KEY=pk_live_your_live_publishable_key
   ```

---

## 🔗 **PHASE 6: WEBHOOK CONFIGURATION**

### **Step 6.1: Stripe Webhooks**
1. **In Stripe Dashboard:**
   - Go to Webhooks section
   - Click "Add endpoint"
   - **URL**: `https://api.your-domain.com/api/webhooks/stripe`
   - **Events**: Select these events:
     - `payment_intent.succeeded`
     - `charge.succeeded`
     - `payment_intent.payment_failed`
     - `charge.failed`
   - Copy the **Signing Secret** to your `.env` file

2. **Test webhook:**
   ```bash
   # Install Stripe CLI on your local machine
   stripe listen --forward-to https://api.your-domain.com/api/webhooks/stripe
   ```

### **Step 6.2: Twilio Webhooks**
1. **In Twilio Console:**
   - Go to WhatsApp Sandbox settings
   - **Webhook URL**: `https://api.your-domain.com/api/webhooks/twilio/message`
   - **Status Callback URL**: `https://api.your-domain.com/api/webhooks/twilio/status`

2. **Test webhook:**
   - Send test WhatsApp message to your sandbox number
   - Check logs: `tail -f /home/forge/api.your-domain.com/storage/logs/laravel.log`

### **Step 6.3: Wise Webhooks** (if needed)
1. **In Wise Dashboard:**
   - Configure webhook endpoints for payment status updates
   - **URL**: `https://api.your-domain.com/api/webhooks/wise`

---

## 🔒 **PHASE 7: SECURITY & SSL**

### **Step 7.1: SSL Certificate Installation**
1. **In Forge Dashboard:**
   - Go to your sites
   - Click "SSL" tab
   - Click "Let's Encrypt"
   - Add domains:
     - `api.your-domain.com`
     - `your-domain.com`

2. **Verify SSL:**
   ```bash
   curl -I https://api.your-domain.com
   curl -I https://your-domain.com
   ```

### **Step 7.2: Security Headers**
1. **Add to Nginx configuration:**
   ```bash
   sudo nano /etc/nginx/sites-available/api.your-domain.com
   ```

2. **Add security headers:**
   ```nginx
   add_header X-Frame-Options "SAMEORIGIN" always;
   add_header X-XSS-Protection "1; mode=block" always;
   add_header X-Content-Type-Options "nosniff" always;
   add_header Referrer-Policy "no-referrer-when-downgrade" always;
   add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
   ```

3. **Restart Nginx:**
   ```bash
   sudo systemctl restart nginx
   ```

---

## 📊 **PHASE 8: MONITORING & QUEUES**

### **Step 8.1: Queue Workers Setup**
1. **In Forge Dashboard:**
   - Go to "Daemons" tab
   - Add new daemon:
     - **Command**: `php /home/forge/api.your-domain.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600`
     - **User**: `forge`
     - **Directory**: `/home/forge/api.your-domain.com`

2. **Start daemon:**
   ```bash
   sudo supervisorctl start villa-upsell-worker
   ```

### **Step 8.2: Log Configuration**
1. **Set up log rotation:**
   ```bash
   sudo nano /etc/logrotate.d/laravel
   ```

2. **Add log rotation config:**
   ```
   /home/forge/api.your-domain.com/storage/logs/*.log {
       daily
       missingok
       rotate 14
       compress
       notifempty
       create 644 forge forge
   }
   ```

### **Step 8.3: Monitoring Setup**
1. **Install monitoring tools:**
   ```bash
   # Optional: Install New Relic
   sudo apt install newrelic-php5
   ```

2. **Set up server monitoring in Forge Dashboard**

---

## 🧪 **PHASE 9: TESTING & VALIDATION**

### **Step 9.1: Backend API Testing**
1. **Test authentication:**
   ```bash
   curl -X POST https://api.your-domain.com/api/register \
     -H "Content-Type: application/json" \
     -d '{"name":"Test User","email":"test@example.com","password":"password123"}'
   ```

2. **Test property access:**
   ```bash
   curl -X GET https://api.your-domain.com/api/properties/access/test-token
   ```

3. **Test payment creation:**
   ```bash
   curl -X POST https://api.your-domain.com/api/guest/payments/create-intent \
     -H "Content-Type: application/json" \
     -d '{"access_token":"test-token","cart_items":[]}'
   ```

### **Step 9.2: Webhook Testing**
1. **Test Stripe webhook:**
   ```bash
   # Use Stripe CLI
   stripe trigger payment_intent.succeeded
   ```

2. **Test Twilio webhook:**
   - Send WhatsApp message to sandbox number
   - Check server logs for incoming webhook

3. **Test email notifications:**
   - Make test payment
   - Verify email delivery

### **Step 9.3: End-to-End Testing**
1. **Complete payment flow:**
   - Access property via frontend
   - Add upsells to cart
   - Complete payment
   - Verify notifications (email + WhatsApp)

2. **Error handling testing:**
   - Test with invalid payment methods
   - Test with network failures
   - Verify error responses

---

## 🚀 **PHASE 10: GO LIVE**

### **Step 10.1: Final Pre-Launch Checklist**
- [ ] All webhooks configured and tested
- [ ] SSL certificates active
- [ ] Database migrations completed
- [ ] Queue workers running
- [ ] Monitoring set up
- [ ] Backup strategy in place
- [ ] Error handling tested
- [ ] Performance tested

### **Step 10.2: DNS Final Configuration**
1. **Update DNS records:**
   ```
   Type: A
   Name: @
   Value: your-server-ip-address
   TTL: 300

   Type: A
   Name: api
   Value: your-server-ip-address
   TTL: 300
   ```

2. **Test DNS propagation:**
   ```bash
   nslookup your-domain.com
   nslookup api.your-domain.com
   ```

### **Step 10.3: Production Launch**
1. **Switch to production API keys:**
   - Update Stripe to live keys
   - Update Twilio to production credentials
   - Update SendGrid to production key

2. **Final deployment:**
   - Trigger deployment in Forge
   - Monitor logs for errors
   - Test all functionality

---

## 📞 **PHASE 11: POST-DEPLOYMENT**

### **Step 11.1: Backup Strategy**
1. **Database backups:**
   ```bash
   # Create backup script
   sudo nano /home/forge/backup-db.sh
   ```

2. **Backup script content:**
   ```bash
   #!/bin/bash
   DATE=$(date +%Y%m%d_%H%M%S)
   pg_dump -h localhost -U villa_user villa_upsell_production > /home/forge/backups/db_backup_$DATE.sql
   # Keep only last 7 days
   find /home/forge/backups -name "db_backup_*.sql" -mtime +7 -delete
   ```

3. **Set up cron job:**
   ```bash
   crontab -e
   # Add: 0 2 * * * /home/forge/backup-db.sh
   ```

### **Step 11.2: Monitoring & Maintenance**
1. **Set up log monitoring:**
   ```bash
   # Monitor error logs
   tail -f /home/forge/api.your-domain.com/storage/logs/laravel.log | grep ERROR
   ```

2. **Performance monitoring:**
   - Monitor server resources
   - Monitor database performance
   - Monitor API response times

### **Step 11.3: Security Updates**
1. **Regular updates:**
   ```bash
   sudo apt update && sudo apt upgrade
   ```

2. **Dependency updates:**
   ```bash
   cd /home/forge/api.your-domain.com
   composer update
   ```

---

## 🆘 **TROUBLESHOOTING GUIDE**

### **Common Issue 1: SSL Certificate Problems**
```bash
# Check certificate status
sudo certbot certificates

# Renew certificates
sudo certbot renew --dry-run
sudo certbot renew
```

### **Common Issue 2: Database Connection Issues**
```bash
# Check PostgreSQL status
sudo systemctl status postgresql

# Check connection
psql -h localhost -U villa_user -d villa_upsell_production

# Check database permissions
sudo -u postgres psql -c "\du"
```

### **Common Issue 3: Queue Workers Not Processing**
```bash
# Check supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart all

# Check queue logs
tail -f /home/forge/api.your-domain.com/storage/logs/laravel.log | grep queue
```

### **Common Issue 4: Webhook Failures**
```bash
# Check webhook logs
tail -f /home/forge/api.your-domain.com/storage/logs/laravel.log | grep webhook

# Test webhook manually
curl -X POST https://api.your-domain.com/api/webhooks/stripe \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: test" \
  -d '{"test": "data"}'
```

### **Common Issue 5: Email/WhatsApp Not Working**
```bash
# Check notification logs
tail -f /home/forge/api.your-domain.com/storage/logs/laravel.log | grep -E "(SendGrid|WhatsApp|Twilio)"

# Test email sending
php artisan tinker
# Then: Mail::raw('Test', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

---

## 📋 **DEPLOYMENT CHECKLIST**

### **Pre-Deployment:**
- [ ] Production API keys obtained
- [ ] Domain registered
- [ ] Laravel Forge account ready
- [ ] Cloud provider account ready

### **Infrastructure:**
- [ ] Server created in Forge
- [ ] Database configured
- [ ] Redis configured
- [ ] DNS records set up

### **Backend:**
- [ ] Site created in Forge
- [ ] Repository connected
- [ ] Deploy script configured
- [ ] Environment variables set
- [ ] Application key generated
- [ ] Database migrated

### **Frontend:**
- [ ] Site created in Forge
- [ ] Frontend built and uploaded
- [ ] Environment variables updated

### **Webhooks:**
- [ ] Stripe webhook configured
- [ ] Twilio webhook configured
- [ ] Wise webhook configured (if needed)
- [ ] All webhooks tested

### **Security:**
- [ ] SSL certificates installed
- [ ] Security headers configured
- [ ] Firewall configured

### **Monitoring:**
- [ ] Queue workers running
- [ ] Log rotation configured
- [ ] Monitoring tools installed
- [ ] Backup strategy implemented

### **Testing:**
- [ ] API endpoints tested
- [ ] Webhooks tested
- [ ] End-to-end flow tested
- [ ] Error handling tested

### **Go Live:**
- [ ] Production API keys active
- [ ] DNS propagated
- [ ] Final deployment completed
- [ ] All functionality verified

---

## ⏱️ **ESTIMATED TIMELINE**

- **Phase 1-3 (Infrastructure)**: 1-2 hours
- **Phase 4-5 (Deployment)**: 1-2 hours
- **Phase 6-7 (Webhooks & Security)**: 1 hour
- **Phase 8-9 (Monitoring & Testing)**: 1-2 hours
- **Phase 10-11 (Go Live & Post-Deployment)**: 1 hour

**Total Estimated Time**: 5-8 hours

---

## 🎯 **SUCCESS CRITERIA**

Your deployment is successful when:
- [ ] All API endpoints respond correctly
- [ ] Webhooks receive and process events
- [ ] Email notifications are delivered
- [ ] WhatsApp notifications are sent
- [ ] Payment processing works end-to-end
- [ ] SSL certificates are active
- [ ] Monitoring and backups are working
- [ ] Performance is acceptable

**Ready to start with Phase 1?** 🚀