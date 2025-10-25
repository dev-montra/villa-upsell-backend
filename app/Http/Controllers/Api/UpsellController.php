<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Upsell;
use App\Models\Property;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UpsellController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $upsells = Upsell::with(['property', 'primaryVendor', 'secondaryVendor'])
            ->whereHas('property', function($query) {
                $query->where('user_id', auth()->id());
            })
            ->orderBy('sort_order')
            ->get();
        
        return response()->json([
            'upsells' => $upsells,
        ]);
    }

    /**
     * Store a newly created upsell in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'primary_vendor_id' => 'required|exists:vendors,id',
            'secondary_vendor_id' => 'nullable|exists:vendors,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:100',
            'image_url' => 'nullable|string',
            'availability_rules' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Verify the property belongs to the authenticated user
        $property = Property::where('id', $validated['property_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $upsell = Upsell::create($validated);

        return response()->json([
            'message' => 'Upsell created successfully.',
            'upsell' => $upsell->load(['property', 'primaryVendor', 'secondaryVendor']),
        ], 201);
    }

    /**
     * Display the specified upsell.
     */
    public function show(Upsell $upsell)
    {
        // Verify the upsell belongs to the authenticated user's property
        if ($upsell->property->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'upsell' => $upsell->load(['property', 'primaryVendor', 'secondaryVendor']),
        ]);
    }

    /**
     * Update the specified upsell in storage.
     */
    public function update(Request $request, Upsell $upsell)
    {
        // Verify the upsell belongs to the authenticated user's property
        if ($upsell->property->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'property_id' => 'sometimes|exists:properties,id',
            'primary_vendor_id' => 'sometimes|exists:vendors,id',
            'secondary_vendor_id' => 'nullable|exists:vendors,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'category' => 'sometimes|string|max:100',
            'image_url' => 'nullable|string',
            'availability_rules' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        // If updating the image, delete the old image file
        if (isset($validated['image_url']) && $validated['image_url'] !== $upsell->image_url) {
            if ($upsell->image_url) {
                $this->deleteImageFile($upsell->image_url);
            }
        }

        $upsell->update($validated);

        return response()->json([
            'message' => 'Upsell updated successfully',
            'upsell' => $upsell->load(['property', 'primaryVendor', 'secondaryVendor']),
        ]);
    }

    /**
     * Remove the specified upsell from storage.
     */
    public function destroy(Upsell $upsell)
    {
        // Verify the upsell belongs to the authenticated user's property
        if ($upsell->property->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete associated image file if it exists
        if ($upsell->image_url) {
            $this->deleteImageFile($upsell->image_url);
        }

        $upsell->delete();

        return response()->json([
            'message' => 'Upsell deleted successfully',
        ]);
    }

    /**
     * Update the sort order of upsells.
     */
    public function updateSortOrder(Request $request)
    {
        $validated = $request->validate([
            'upsells' => 'required|array',
            'upsells.*.id' => 'required|exists:upsells,id',
            'upsells.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['upsells'] as $upsellData) {
            Upsell::where('id', $upsellData['id'])
                ->whereHas('property', function($query) {
                    $query->where('user_id', auth()->id());
                })
                ->update(['sort_order' => $upsellData['sort_order']]);
        }

        return response()->json([
            'message' => 'Sort order updated successfully',
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
            // Log error but don't fail the upsell deletion
            \Log::warning('Failed to delete image file: ' . $imageUrl . ' - ' . $e->getMessage());
        }
    }
}