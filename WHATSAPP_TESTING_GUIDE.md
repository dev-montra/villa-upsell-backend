# WhatsApp Notification Testing Guide

## 🎯 **Complete Setup for Twilio WhatsApp Sandbox Testing**

Based on your Twilio Sandbox settings, here's the complete setup:

### **Step 1: Configure Twilio Webhook URL**

In your Twilio Sandbox settings (the page you showed), update:

**"When a message comes in" field:**
```
https://90eaa2c3aa2d.ngrok-free.app/api/webhooks/twilio/message
```

**Method:** POST (keep as is)

**"Status callback URL" field:**
```
https://90eaa2c3aa2d.ngrok-free.app/api/webhooks/twilio/status
```

**Method:** GET (keep as is)

### **Step 2: Verify Your .env Configuration**

Make sure your `.env` file has:

```env
# Twilio Configuration
TWILIO_SID=ACc23...  # Your Account SID
TWILIO_TOKEN=your_test_token  # Your Auth Token
TWILIO_WHATSAPP_FROM=whatsapp:+15076099701  # Your Sandbox number
```

### **Step 3: Test WhatsApp Notifications**

#### **Option A: Simple Test (Recommended)**
```bash
php artisan test:whatsapp-simple --phone=+17472666722
```

#### **Option B: Full Payment Flow Test**
```bash
php artisan test:whatsapp-payment-flow --phone=+17472666722 --email=test@example.com
```

#### **Option C: Existing Integration Test**
```bash
php artisan test:integrations --whatsapp=+17472666722
```

### **Step 4: Test Incoming Messages**

1. **Send a message to your Sandbox number** `+1 415 523 8886` with the code `join settle-wife`
2. **Send test messages** like "hello", "help", "status" to see automated responses
3. **Check your Laravel logs** at `storage/logs/laravel.log` for incoming message details

### **Step 5: Test Payment Success Notifications**

To simulate a payment success and trigger WhatsApp notifications:

1. **Use Stripe CLI** (if you have it set up):
   ```bash
   stripe listen --forward-to localhost:8000/api/webhooks/stripe
   ```

2. **Or use the test command**:
   ```bash
   php artisan test:whatsapp-payment-flow --phone=+17472666722 --email=test@example.com
   ```

### **Step 6: Verify Everything Works**

✅ **Check these things:**

1. **WhatsApp Message Received**: You should get a message on `+17472666722`
2. **Laravel Logs**: Check `storage/logs/laravel.log` for success messages
3. **ngrok Logs**: Check your ngrok console for webhook requests
4. **Twilio Console**: Check message logs in Twilio dashboard

### **Troubleshooting**

#### **If WhatsApp messages aren't received:**

1. **Check Sandbox Participation**: Make sure `+17472666722` has joined the sandbox
2. **Verify Webhook URL**: Ensure the ngrok URL is correct and accessible
3. **Check .env Configuration**: Verify all Twilio credentials are correct
4. **Check Laravel Logs**: Look for error messages in `storage/logs/laravel.log`

#### **Common Issues:**

- **"Invalid phone number"**: Use international format `+17472666722`
- **"Webhook not receiving"**: Check ngrok is running and URL is correct
- **"Twilio not configured"**: Verify `.env` file has correct credentials

### **Expected Results**

When everything works correctly:

1. **Outbound Messages**: You'll receive WhatsApp notifications on `+17472666722`
2. **Inbound Messages**: Sending messages to `+1 415 523 8886` will trigger automated responses
3. **Logs**: Laravel logs will show successful message sending
4. **Webhooks**: ngrok will show incoming webhook requests from Twilio

### **Next Steps After Testing**

Once WhatsApp notifications are working:

1. **Test with real payment flow** using Stripe CLI
2. **Test email notifications** with SendGrid
3. **Deploy to production** with proper SSL certificates
4. **Set up monitoring** for webhook failures

---

## 🚀 **Quick Test Commands**

```bash
# Simple WhatsApp test
php artisan test:whatsapp-simple --phone=+17472666722

# Full payment flow test
php artisan test:whatsapp-payment-flow --phone=+17472666722 --email=test@example.com

# Check logs
tail -f storage/logs/laravel.log
```

**Your webhook URL should be:**
```
https://90eaa2c3aa2d.ngrok-free.app/api/webhooks/twilio/message
```