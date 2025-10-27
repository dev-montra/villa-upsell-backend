# How to Check Cloudinary Upload Logs

## Steps to Debug Image Upload

1. **Upload an image** in the admin panel (try to upload any image)

2. **Check the logs** by running this command in the backend directory:
   ```powershell
   Get-Content storage/logs/laravel.log -Tail 50
   ```

3. Look for these log messages:
   - "Cloudinary URL check: Found" or "Not found"
   - "Attempting Cloudinary upload"
   - "Cloudinary upload successful" or "Cloudinary upload failed"
   - Any error messages

## Common Issues

### If logs show "Cloudinary URL check: Not found"
- The .env file isn't being loaded
- Run: `php artisan config:clear`

### If logs show "Cloudinary upload failed"
- The error message will tell us what's wrong
- Common causes:
  - Invalid credentials
  - Network issues
  - Cloudinary account restrictions

### If logs show filesystem fallback being used
- Cloudinary upload failed silently
- Check the error message before the fallback
