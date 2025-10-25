<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuestCheckIn;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GuestController extends Controller
{
    /**
     * Handle guest check-in form submission
     */
    public function checkIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|string|max:20',
            'passport_url' => 'nullable|string',
            'check_in_time' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find the property by access token
        $property = Property::where('access_token', $request->access_token)->first();
        
        if (!$property) {
            return response()->json([
                'message' => 'Invalid access token',
            ], 404);
        }

        // Check if guest has already checked in
        $existingCheckIn = GuestCheckIn::where('access_token', $request->access_token)
            ->where('email', $request->email)
            ->first();

        if ($existingCheckIn) {
            return response()->json([
                'message' => 'Guest has already checked in',
                'check_in' => $existingCheckIn,
            ], 409);
        }

        // Create new guest check-in record
        $guestCheckIn = GuestCheckIn::create([
            'access_token' => $request->access_token,
            'property_id' => $property->id,
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'passport_url' => $request->passport_url,
            'check_in_time' => $request->check_in_time,
            'additional_data' => $request->additional_data ?? null,
        ]);

        return response()->json([
            'message' => 'Check-in completed successfully',
            'check_in' => $guestCheckIn->load('property'),
        ], 201);
    }

    /**
     * Get guest check-in status
     * This method now checks if ANY guest has checked in for this property
     * In a real-world scenario, you might want to use session-based tracking
     */
    public function getCheckInStatus(Request $request, $accessToken)
    {
        // For now, we'll check if any guest has checked in for this property
        // In production, you might want to use session-based tracking or cookies
        $guestCheckIn = GuestCheckIn::where('access_token', $accessToken)
            ->with('property')
            ->first();

        if (!$guestCheckIn) {
            return response()->json([
                'message' => 'No check-in found',
            ], 404);
        }

        return response()->json([
            'check_in' => $guestCheckIn,
        ]);
    }

    /**
     * Check if a specific guest (by email) has already checked in
     */
    public function checkSpecificGuestStatus(Request $request, $accessToken)
    {
        $email = $request->query('email');
        
        if (!$email) {
            return response()->json([
                'message' => 'Email parameter is required',
            ], 400);
        }

        $guestCheckIn = GuestCheckIn::where('access_token', $accessToken)
            ->where('email', $email)
            ->with('property')
            ->first();

        if (!$guestCheckIn) {
            return response()->json([
                'message' => 'No check-in found for this guest',
            ], 404);
        }

        return response()->json([
            'check_in' => $guestCheckIn,
        ]);
    }
}
