# Cloudinary Environment Setup

## Current Situation

Your image upload is working but saving to local filesystem because Cloudinary credentials are not configured. Your Cloud Name is: **djamkmbwz**

## Get Your Credentials

From the Cloudinary console you're viewing:
1. Click the dropdown showing "DJ djamkmbwz" (or your Cloud Name)
2. Click on "Settings" (gear icon in the sidebar) OR
3. Go to: https://console.cloudinary.com/settings

Find these values:
- **Cloud Name**: `djamkmbwz` (you already have this from the sidebar)
- **API Key**: A string like `123456789012345`
- **API Secret**: A longer string (be careful not to share this publicly!)

## Add to Your .env File

Open `villa-upsell-backend/.env` and add these lines:

```env
CLOUDINARY_CLOUD_NAME=djamkmbwz
CLOUDINARY_API_KEY=your_api_key_here
CLOUDINARY_API_SECRET=your_api_secret_here
```

**Important**: Replace `your_api_key_here` and `your_api_secret_here` with your actual credentials!

## Restart Backend Server

After adding the credentials, restart your Laravel backend:

1. Stop the current server (Ctrl+C in the terminal where it's running)
2. Start it again:
   ```bash
   cd villa-upsell-backend
   php artisan serve --port=8000
   ```

## Test

1. Go back to your admin panel
2. Try uploading an image again
3. Check Cloudinary console - you should now see the image!

## For Heroku Deployment

When deploying to Heroku, add these as Config Vars:

```bash
heroku config:set CLOUDINARY_CLOUD_NAME=djamkmbwz -a holidayupsell-107b4a0c998f
heroku config:set CLOUDINARY_API_KEY=your_api_key_here -a holidayupsell-107b4a0c998f
heroku config:set CLOUDINARY_API_SECRET=your_api_secret_here -a holidayupsell-107b4a0c998f
```

Or via Heroku Dashboard: https://dashboard.heroku.com/apps/holidayupsell-107b4a0c998f/settings
