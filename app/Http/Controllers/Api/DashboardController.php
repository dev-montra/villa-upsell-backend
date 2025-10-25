<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Upsell;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     */
    public function stats()
    {
        $userId = auth()->id();
        
        $stats = [
            'total_properties' => Property::where('user_id', $userId)->count(),
            'total_upsells' => Upsell::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('is_active', true)->count(),
            'total_orders' => Order::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count(),
            'total_revenue' => Order::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('status', '!=', 'cancelled')->sum('amount'),
            'monthly_revenue' => Order::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('status', '!=', 'cancelled')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount'),
            'conversion_rate' => $this->calculateConversionRate($userId),
            'active_vendors' => Vendor::where('is_active', true)->count(),
            'pending_orders' => Order::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('status', 'pending')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get recent orders for dashboard.
     */
    public function recentOrders()
    {
        $orders = Order::with(['property', 'upsell', 'vendor'])
            ->whereHas('property', function($q) {
                $q->where('user_id', auth()->id());
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json($orders);
    }

    /**
     * Get revenue analytics.
     */
    public function revenueAnalytics(Request $request)
    {
        $userId = auth()->id();
        $period = (int) $request->get('period', 30); // days
        
        $revenue = Order::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays($period))
            ->selectRaw('DATE(created_at) as date, COALESCE(SUM(amount), 0) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill in missing dates with zero revenue
        $startDate = now()->subDays($period);
        $endDate = now();
        $filledData = collect();
        
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $existingData = $revenue->firstWhere('date', $dateStr);
            
            $filledData->push([
                'date' => $dateStr,
                'total' => $existingData ? (float) $existingData->total : 0.0
            ]);
        }

        return response()->json($filledData);
    }

    /**
     * Get upsell performance analytics.
     */
    public function upsellAnalytics()
    {
        $userId = auth()->id();
        
        $upsells = Upsell::withCount(['orders as total_orders'])
            ->withSum(['orders as total_revenue' => function($query) {
                $query->where('status', '!=', 'cancelled');
            }])
            ->whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->where('is_active', true)
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->map(function($upsell) {
                return [
                    'id' => $upsell->id,
                    'title' => $upsell->title,
                    'total_orders' => (int) $upsell->total_orders,
                    'total_revenue' => (float) ($upsell->total_revenue ?? 0)
                ];
            });

        return response()->json($upsells);
    }

    /**
     * Export accounting data to CSV.
     */
    public function exportAccountingData(Request $request)
    {
        $userId = auth()->id();
        $period = (int) $request->get('period', 30); // days
        $format = $request->get('format', 'csv'); // csv or excel
        
        // Get orders with related data
        $orders = Order::with(['property', 'upsell', 'vendor'])
            ->whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->where('created_at', '>=', now()->subDays($period))
            ->orderBy('created_at', 'desc')
            ->get();

        // Prepare CSV data
        $csvData = [];
        $csvData[] = [
            'Date',
            'Order ID',
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
            'Commission Rate (%)',
            'Commission Amount',
            'Vendor Amount',
            'Platform Fee',
            'Created At',
            'Updated At'
        ];

        foreach ($orders as $order) {
            $commissionRate = 10; // Default 10% commission
            $commissionAmount = $order->amount * ($commissionRate / 100);
            $vendorAmount = $order->amount - $commissionAmount;
            $platformFee = $commissionAmount;

            $csvData[] = [
                $order->created_at->format('Y-m-d'),
                $order->id,
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
                $commissionRate,
                number_format($commissionAmount, 2),
                number_format($vendorAmount, 2),
                number_format($platformFee, 2),
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
        $filename = 'accounting_data_' . now()->format('Y-m-d') . '_' . $period . 'days.csv';
        
        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Get accounting summary data.
     */
    public function getAccountingSummary(Request $request)
    {
        $userId = auth()->id();
        $period = (int) $request->get('period', 30); // days
        
        $orders = Order::whereHas('property', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->where('created_at', '>=', now()->subDays($period))
            ->where('status', '!=', 'cancelled')
            ->get();

        $totalRevenue = $orders->sum('amount');
        $commissionRate = 10; // Default 10% commission
        $totalCommission = $totalRevenue * ($commissionRate / 100);
        $totalVendorAmount = $totalRevenue - $totalCommission;
        $totalOrders = $orders->count();

        return response()->json([
            'period_days' => $period,
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'commission_rate' => $commissionRate,
            'total_commission' => $totalCommission,
            'total_vendor_amount' => $totalVendorAmount,
            'average_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
            'date_range' => [
                'start' => now()->subDays($period)->format('Y-m-d'),
                'end' => now()->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Calculate conversion rate.
     */
    private function calculateConversionRate($userId)
    {
        $totalVisitors = 1000; // This would come from analytics in a real app
        $totalOrders = Order::whereHas('property', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();

        return $totalVisitors > 0 ? round(($totalOrders / $totalVisitors) * 100, 2) : 0;
    }
}