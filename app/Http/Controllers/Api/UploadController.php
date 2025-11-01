<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Cloudinary\Cloudinary;
use Cloudinary\Transformation\Resize;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    private function getCloudinary()
    {
        // Configure base Cloudinary instance
        $cloudinaryUrl = env('CLOUDINARY_URL');
        
        if ($cloudinaryUrl) {
            $cloudinary = new Cloudinary($cloudinaryUrl);
        } else {
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key' => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => [
                    'secure' => true,
                ],
            ]);
        }
        
        // For local development, disable SSL verification
        if (app()->environment('local')) {
            $cloudinary->configuration->cloud->httpOptions = [
                'verify' => false
            ];
        }
        
        return $cloudinary;
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        try {
            $file = $request->file('image');
            
            // Check if Cloudinary is configured
            $cloudinaryUrl = env('CLOUDINARY_URL');
            \Log::info('Cloudinary URL check: ' . ($cloudinaryUrl ? 'Found' : 'Not found'));
            
            if ($cloudinaryUrl || (env('CLOUDINARY_CLOUD_NAME') && env('CLOUDINARY_API_KEY') && env('CLOUDINARY_API_SECRET'))) {
                // Upload to Cloudinary
                try {
                    $cloudinary = $this->getCloudinary();
                    
                    \Log::info('Attempting Cloudinary upload');
                    
                    // For local development, disable SSL verification
                    $httpClientOptions = [];
                    if (app()->environment('local')) {
                        $httpClientOptions = [
                            'verify' => false
                        ];
                    }
                    
                    $result = $cloudinary->uploadApi()->upload(
                        $file->getRealPath(),
                        [
                            'public_id' => 'villa-upsell/' . Str::uuid(),
                            'folder' => 'villa-upsell',
                            'resource_type' => 'image',
                            'overwrite' => true,
                            'http_options' => $httpClientOptions,
                        ]
                    );
                    
                    $url = $result['secure_url'];
                    $publicId = $result['public_id'];
                    
                    \Log::info('Cloudinary upload successful: ' . $url);
                    
                    return response()->json([
                        'success' => true,
                        'url' => $url,
                        'public_id' => $publicId,
                    ]);
                } catch (\Exception $cloudinaryException) {
                    \Log::error('Cloudinary upload failed: ' . $cloudinaryException->getMessage());
                    throw $cloudinaryException; // Re-throw to prevent fallback
                }
            }
            
            // Return error if Cloudinary not configured
            return response()->json([
                'success' => false,
                'message' => 'Cloudinary is not configured. Please set CLOUDINARY_URL in your .env file.',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Image upload failed: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteImage(Request $request)
    {
        $request->validate([
            'public_id' => 'required|string',
        ]);

        try {
            $publicId = $request->input('public_id');
            $cloudinary = $this->getCloudinary();
            
            // Delete from Cloudinary
            $result = $cloudinary->uploadApi()->destroy($publicId);
            
            if ($result['result'] === 'ok') {
                return response()->json([
                    'success' => true,
                    'message' => 'Image deleted successfully',
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image from Cloudinary',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Helper method to extract public_id from a Cloudinary URL
     */
    public static function extractPublicIdFromUrl($url)
    {
        if (!is_string($url)) {
            return null;
        }
        
        // Check if it's a Cloudinary URL
        if (str_contains($url, 'cloudinary.com')) {
            // Extract public_id from URL pattern: https://res.cloudinary.com/{cloud_name}/image/upload/{public_id}.{ext}
            if (preg_match('/\/upload\/v\d+\/(.+?)\.(jpg|jpeg|png|gif|webp)/i', $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Helper method to delete image by URL
     */
    public function deleteImageByUrl($url)
    {
        try {
            $publicId = self::extractPublicIdFromUrl($url);
            
            if ($publicId) {
                $cloudinary = $this->getCloudinary();
                $result = $cloudinary->uploadApi()->destroy($publicId);
                return $result['result'] === 'ok';
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}