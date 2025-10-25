<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vendors = Vendor::orderBy('name')->get();
        
        return response()->json([
            'vendors' => $vendors,
        ]);
    }

    /**
     * Store a newly created vendor in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:vendors,email',
            'whatsapp_number' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'service_type' => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        $vendor = Vendor::create($validated);

        return response()->json([
            'message' => 'Vendor created successfully.',
            'vendor' => $vendor,
        ], 201);
    }

    /**
     * Display the specified vendor.
     */
    public function show(Vendor $vendor)
    {
        return response()->json([
            'vendor' => $vendor,
        ]);
    }

    /**
     * Update the specified vendor in storage.
     */
    public function update(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:vendors,email,' . $vendor->id,
            'whatsapp_number' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'service_type' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $vendor->update($validated);

        return response()->json([
            'message' => 'Vendor updated successfully',
            'vendor' => $vendor,
        ]);
    }

    /**
     * Remove the specified vendor from storage.
     */
    public function destroy(Vendor $vendor)
    {
        // Check if vendor is being used by any upsells
        if ($vendor->primaryUpsells()->count() > 0 || $vendor->secondaryUpsells()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete vendor. It is being used by one or more upsells.',
            ], 422);
        }

        $vendor->delete();

        return response()->json([
            'message' => 'Vendor deleted successfully',
        ]);
    }
}