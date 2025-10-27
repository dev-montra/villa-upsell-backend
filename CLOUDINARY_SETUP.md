# Cloudinary Integration Setup Guide

## What Was Changed

The upload system has been migrated from local filesystem storage to Cloudinary cloud storage.

### Files Modified
1. `app/Http/Controllers/Api/UploadController.php` - Now uploads to Cloudinary
2. `app/Http/Controllers/Api/PropertyController.php` - Deletes Cloudinary images
3. `app/Http/Controllers/Api/UpsellController.php` - Deletes Cloudinary images

## Setup Instructions

### Step 1: Get Your Cloudinary Credentials

1. Go to https://cloudinary.com and sign up or log in
2. Once logged in, go to Dashboard
3. Copy your credentials from the "Account Details" section:
   - Cloud Name
   - API Key
   - API Secret

### Step 2: Add Environment Variables

Add these variables to your `.env` file:

```env
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
```

### Step 3: For Heroku Deployment

Add the environment variables to your Heroku backend app:

```bash
heroku config:set CLOUDINARY_CLOUD_NAME=your_cloud_name -a holidayupsell-107b4a0c998f
heroku config:set CLOUDINARY_API_KEY=your_api_key -a holidayupsell-107b4a0c998f
heroku config:set CLOUDINARY_API_SECRET=your_api_secret -a holidayupsell-107b4a0c998f
```

Or via Heroku Dashboard:
1. Go to: https://dashboard.heroku.com/apps/holidayupsell-107b4a0c998f/settings
2. Click "Reveal Config Vars"
3. Add:
   - `CLOUDINARY_CLOUD_NAME`
   - `CLOUDINARY_API_KEY`
   - `CLOUDINARY_API_SECRET`

### Step 4: Install Composer Dependencies

The Cloudinary package has already been installed. If you need to reinstall:

```bash
cd villa-upsell-backend
composer require cloudinary/cloudinary_php
```

### Step 5: Deploy

Commit the changes and push to Heroku:

```bash
git add .
git commit -m "Add Cloudinary integration for image storage"
git push heroku main
```

## How It Works

### Uploading Images
1. When a user uploads an image (Property, Upsell, or Guest check-in)
2. The image is sent to the backend `/upload-image` endpoint
3. Backend uploads to Cloudinary and returns the secure URL
4. The URL is stored in the database

### Deleting Images
When a Property or Upsell is deleted:
1. The system checks if the image URL is from Cloudinary
2. If yes, deletes from Cloudinary
3. If no (legacy filesystem image), deletes from local storage
4. This ensures backward compatibility

### Automatic Deletion
- When you delete a Property, its hero_image_url is deleted from Cloudinary
- When you delete an Upsell, its image_url is deleted from Cloudinary
- When you update and replace an image, the old image is deleted

## Benefits

✅ **Cloud Storage**: Images stored in the cloud, not on Heroku's filesystem  
✅ **CDN Delivery**: Fast global delivery via Cloudinary's CDN  
✅ **Automatic Optimization**: Cloudinary automatically optimizes images  
✅ **Secure URLs**: All images have secure HTTPS URLs  
✅ **Backward Compatible**: Old filesystem images still work  
✅ **Automatic Cleanup**: Images deleted when items are deleted  

## Cost

Cloudinary has a generous free tier:
- 25 GB storage
- 25 GB monthly bandwidth
- Suitable for most applications

Upgrade if you need more: https://cloudinary.com/pricing

## Testing

1. Upload a new Property with a hero image
2. Check the database - the URL should be: `https://res.cloudinary.com/...`
3. Delete the Property
4. Verify the image is removed from Cloudinary

## Troubleshooting

**Error: "Cloudinary credentials not set"**
- Make sure you've added the environment variables
- Restart your Heroku dyno after adding config vars

**Error: "Upload failed"**
- Check your Cloudinary credentials are correct
- Check file size limits (5MB max)
- Check file type (jpeg, png, jpg, gif, webp only)
