<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Order::with(['property', 'upsell', 'vendor'])
            ->whereHas('property', function($q) {
                $q->where('user_id', auth()->id());
            });

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by property
        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($orders);
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order)
    {
        // Verify the order belongs to the authenticated user's property
        if ($order->property->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'order' => $order->load(['property', 'upsell', 'vendor']),
        ]);
    }

    /**
     * Update the specified order status.
     */
    public function updateStatus(Request $request, Order $order)
    {
        // Verify the order belongs to the authenticated user's property
        if ($order->property->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,fulfilled,cancelled',
        ]);

        $order->update($validated);

        if ($validated['status'] === 'fulfilled') {
            $order->update(['fulfilled_at' => now()]);
        }

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order->load(['property', 'upsell', 'vendor']),
        ]);
    }

    /**
     * Get recent orders for dashboard.
     */
    public function recent()
    {
        $orders = Order::with(['property', 'upsell', 'vendor'])
            ->whereHas('property', function($q) {
                $q->where('user_id', auth()->id());
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json($orders);
    }

    /**
     * Get order statistics for dashboard.
     */
    public function stats()
    {
        $userId = auth()->id();
        
        $stats = [
            'total_orders' => Order::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count(),
            
            'pending_orders' => Order::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('status', 'pending')->count(),
            
            'total_revenue' => Order::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('status', '!=', 'cancelled')->sum('amount'),
            
            'monthly_revenue' => Order::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('status', '!=', 'cancelled')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount'),
        ];

        return response()->json($stats);
    }

    /**
     * Export orders to CSV.
     */
    public function export(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            
            // Get orders with filters
            $query = Order::with(['property', 'upsell', 'vendor'])
                ->whereHas('property', function($q) use ($userId) {
                    $q->where('user_id', $userId);
                });

            // Apply filters
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('date') && $request->date) {
                $query->whereDate('created_at', $request->date);
            }
            
            if ($request->has('vendor') && $request->vendor) {
                $query->whereHas('vendor', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->vendor . '%');
                });
            }

            $orders = $query->orderBy('created_at', 'desc')->get();
            
            \Log::info('Orders export request', [
                'user_id' => $userId,
                'orders_count' => $orders->count(),
                'filters' => $request->all()
            ]);

        // Prepare CSV data
        $csvData = [];
        $csvData[] = [
            'Order ID',
            'Date',
            'Property Name',
            'Upsell Title',
            'Vendor Name',
            'Guest Name',
            'Guest Email',
            'Guest Phone',
            'Amount',
            'Currency',
            'Status',
            'Payment Method',
            'Stripe Payment Intent ID',
            'Order Details',
            'Created At',
            'Updated At'
        ];

        foreach ($orders as $order) {
            $csvData[] = [
                $order->id,
                $order->created_at->format('Y-m-d'),
                $order->property->name ?? 'N/A',
                $order->upsell->title ?? 'N/A',
                $order->vendor->name ?? 'N/A',
                $order->guest_name,
                $order->guest_email,
                $order->guest_phone,
                number_format($order->amount, 2),
                $order->currency,
                ucfirst($order->status),
                $order->payment_method ?? 'Stripe',
                $order->stripe_payment_intent_id ?? 'N/A',
                json_encode($order->order_details ?? []),
                $order->created_at->format('Y-m-d H:i:s'),
                $order->updated_at->format('Y-m-d H:i:s')
            ];
        }

        // Generate CSV content
        $csvContent = '';
        foreach ($csvData as $row) {
            $csvContent .= '"' . implode('","', $row) . '"' . "\n";
        }

        // Set headers for CSV download
        $filename = 'orders_export_' . now()->format('Y-m-d') . '.csv';
        
        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
            
        } catch (\Exception $e) {
            \Log::error('Orders export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }
}