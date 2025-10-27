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
        // Check if CLOUDINARY_URL is set (standard Cloudinary format)
        if (env('CLOUDINARY_URL')) {
            return new Cloudinary(env('CLOUDINARY_URL'));
        }
        
        // Otherwise use individual variables
        return new Cloudinary([
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

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        try {
            $file = $request->file('image');
            
            // Check if Cloudinary is configured
            if (env('CLOUDINARY_URL') || (env('CLOUDINARY_CLOUD_NAME') && env('CLOUDINARY_API_KEY') && env('CLOUDINARY_API_SECRET'))) {
                // Upload to Cloudinary
                $cloudinary = $this->getCloudinary();
                
                $result = $cloudinary->uploadApi()->upload(
                    $file->getRealPath(),
                    [
                        'public_id' => 'villa-upsell/' . Str::uuid(),
                        'folder' => 'villa-upsell',
                        'resource_type' => 'image',
                        'overwrite' => true,
                    ]
                );
                
                $url = $result['secure_url'];
                $publicId = $result['public_id'];
                
                return response()->json([
                    'success' => true,
                    'url' => $url,
                    'public_id' => $publicId,
                ]);
            } else {
                // Fallback to local filesystem for development
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('images', $filename, 'public');
                $url = '/storage/' . $path;
                
                return response()->json([
                    'success' => true,
                    'url' => $url,
                    'public_id' => null,
                ]);
            }
        } catch (\Exception $e) {
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