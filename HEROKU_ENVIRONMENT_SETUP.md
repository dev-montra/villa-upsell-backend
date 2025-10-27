# Heroku Environment Variables Setup

## CRITICAL FIX for "Invalid Access Token" Error

The CORS and 404 errors occur because the frontend apps need to know the backend URL. Follow these steps:

## Step-by-Step Fix

### 1. Backend Environment Variables (Already Configured ✅)
The backend is already deployed with your Heroku PostgreSQL database.

### 2. Guest Frontend Environment Variables ⚠️ NEEDS CONFIGURATION

You need to add ONE environment variable to your guest frontend deployment:

1. Go to your Guest project on Heroku: https://dashboard.heroku.com/apps/[your-guest-app-name]/settings
2. Click "Reveal Config Vars"
3. Add this environment variable:

```
VITE_API_URL=https://holidayupsell-107b4a0c998f.herokuapp.com/api
```

Replace `holidayupsell-107b4a0c998f.herokuapp.com` with your actual backend Heroku URL if it's different.

### 3. Admin Frontend Environment Variables ⚠️ NEEDS CONFIGURATION

1. Go to your Admin project on Heroku: https://dashboard.heroku.com/apps/[your-admin-app-name]/settings
2. Click "Reveal Config Vars"
3. Add this environment variable:

```
VITE_API_URL=https://holidayupsell-107b4a0c998f.herokuapp.com/api
```

Replace `holidayupsell-107b4a0c998f.herokuapp.com` with your actual backend Heroku URL if it's different.

### 4. Commit and Push the CORS Fix

After adding the environment variables, you need to:

1. Commit the CORS middleware fix:
```bash
cd villa-upsell-backend
git add app/Http/Middleware/CorsMiddleware.php
git commit -m "Fix CORS middleware to handle preflight requests"
git push heroku main
```

### 5. Redeploy Frontend Apps

Since you've added environment variables, you need to redeploy:

1. **Guest App**: Go to https://dashboard.heroku.com/apps/[your-guest-app-name]/deploy and click "Deploy Branch" or push:
```bash
cd villa-upsell-guest
git push heroku main
```

2. **Admin App**: Go to https://dashboard.heroku.com/apps/[your-admin-app-name]/deploy and click "Deploy Branch" or push:
```bash
cd villa-upsell-admin
git push heroku main
```

## Why This Fixes The Issue

1. **Environment Variable**: The frontend apps are falling back to `http://127.0.0.1:8000/api` because `VITE_API_URL` is not set in production. Setting it to your Heroku backend URL will fix this.

2. **CORS Preflight**: The improved CORS middleware now properly handles OPTIONS requests, which are sent before the actual GET/POST requests.

3. **404 Error**: The 404 error was happening because the frontend was trying to connect to a non-existent local backend instead of your Heroku backend.

## After Setup

Test the flow:
1. Go to Admin panel → Properties → Click "Preview"
2. You should be redirected to the Guest page without "Invalid Access Token" error
3. The property data should load correctly
