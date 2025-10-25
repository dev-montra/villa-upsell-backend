<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Str; // Laravel's helper for generating unique strings
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $properties = Property::where('user_id', auth()->id())->get();
        
        return response()->json([
            'properties' => $properties,
        ]);
    }

    /**
     * Store a newly created property in storage.
     * This handles the core property setup and unique link generation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instagram_url' => 'nullable|url',
            'language' => 'required|string|max:10',
            'currency' => 'required|string|max:3',
        ]);

        // 1. Generate the Unique Token (Access Key)
        $accessToken = (string) Str::uuid(); // Use a UUID for a high level of uniqueness

        // 2. Create the Property
        $property = Property::create(array_merge($validated, [
            // Assuming the authenticated user is the owner
            'user_id' => auth()->id(), 
            'access_token' => $accessToken,
            'hero_image_url' => $request->input('hero_image_url', 'default.jpg'), // Placeholder
        ]));
        
        // 3. Construct the Full Unique Link for the Owner to Copy
        $uniqueLink = config('app.url') . '/checkin/' . $accessToken;

        return response()->json([
            'message' => 'Property created successfully.',
            'property' => $property,
            'unique_checkin_link' => $uniqueLink, // Display this for the owner
        ], 201);
    }
    
    // ... Implement show, update, and destroy methods for CRUD ...

    /**
     * Display the specified property.
     * This would be used to retrieve property settings for the owner dashboard.
     */
    public function show(Property $property)
    {
        // Add authorization check: Ensure the authenticated user owns this property
        if (auth()->id() !== $property->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Construct the unique link for display
        $uniqueLink = config('app.url') . '/checkin/' . $property->access_token;
        
        return response()->json([
            'property' => $property,
            'unique_checkin_link' => $uniqueLink,
        ]);
    }

    /**
     * Update the specified property in storage.
     */
    public function update(Request $request, Property $property)
    {
        // Add authorization check
        if (auth()->id() !== $property->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'instagram_url' => 'nullable|url',
            'language' => 'sometimes|string|max:10',
            'currency' => 'sometimes|string|max:3',
            'hero_image_url' => 'nullable|string',
            'tags' => 'nullable|array',
            'payment_processor' => 'sometimes|in:stripe,wise',
            'payout_schedule' => 'sometimes|in:manual,weekly,monthly',
            'wise_account_details' => 'nullable|array',
            'wise_account_details.bank_name' => 'nullable|string|max:255',
            'wise_account_details.account_holder_name' => 'nullable|string|max:255',
            'wise_account_details.account_number' => 'nullable|string|max:255',
            'wise_account_details.routing_number' => 'nullable|string|max:255',
            'wise_account_details.swift_code' => 'nullable|string|max:255',
        ]);

        // Log the incoming data for debugging
        \Log::info('Property update request', [
            'property_id' => $property->id,
            'validated_data' => $validated,
            'payment_processor' => $validated['payment_processor'] ?? 'not provided',
            'wise_account_details' => $validated['wise_account_details'] ?? 'not provided',
        ]);

        // If updating the image, delete the old image file
        if (isset($validated['hero_image_url']) && $validated['hero_image_url'] !== $property->hero_image_url) {
            if ($property->hero_image_url) {
                $this->deleteImageFile($property->hero_image_url);
            }
        }

        $property->update($validated);

        // Log the updated property for debugging
        \Log::info('Property updated successfully', [
            'property_id' => $property->id,
            'updated_payment_processor' => $property->payment_processor,
            'updated_wise_account_details' => $property->wise_account_details,
        ]);

        return response()->json([
            'message' => 'Property updated successfully',
            'property' => $property,
        ]);
    }

    /**
     * Remove the specified property from storage.
     */
    public function destroy(Property $property)
    {
        // Add authorization check
        if (auth()->id() !== $property->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete associated image file if it exists
        if ($property->hero_image_url) {
            $this->deleteImageFile($property->hero_image_url);
        }

        $property->delete();

        return response()->json([
            'message' => 'Property deleted successfully',
        ]);
    }

    /**
     * Get upsells for a specific property (public endpoint for guests)
     */
    public function upsells(Property $property)
    {
        $upsells = $property->upsells()
            ->where('is_active', true)
            ->with(['primaryVendor', 'secondaryVendor'])
            ->orderBy('sort_order')
            ->get();
        
        return response()->json([
            'upsells' => $upsells,
        ]);
    }

    /**
     * Validate access token and return property info for guests
     * This is a public endpoint used by the guest app
     */
    public function access($accessToken)
    {
        $property = Property::where('access_token', $accessToken)->first();
        
        if (!$property) {
            return response()->json([
                'message' => 'Property not found or access token is invalid',
            ], 404);
        }

        return response()->json([
            'property' => $property,
        ]);
    }

    /**
     * Delete image file from storage
     */
    private function deleteImageFile($imageUrl)
    {
        try {
            // Extract the file path from the URL
            // Handle both absolute and relative URLs
            if (str_starts_with($imageUrl, 'http')) {
                // Absolute URL: extract path after domain
                $path = parse_url($imageUrl, PHP_URL_PATH);
            } else {
                // Relative URL: use as is
                $path = $imageUrl;
            }

            // Remove leading slash and 'storage/' prefix if present
            $path = ltrim($path, '/');
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, 8); // Remove 'storage/' prefix
            }

            // Delete from public disk
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the property deletion
            \Log::warning('Failed to delete image file: ' . $imageUrl . ' - ' . $e->getMessage());
        }
    }
}