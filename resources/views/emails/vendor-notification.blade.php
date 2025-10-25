<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Service Booking</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .booking-details {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
        }
        .detail-value {
            color: #333;
        }
        .total-amount {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        .total-amount h3 {
            margin: 0;
            color: #2d5a2d;
            font-size: 24px;
        }
        .special-requests {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🍽️ New Service Booking</h1>
        <p>You have received a new booking for your services!</p>
    </div>

    <div class="content">
        <div class="booking-details">
            <h2>📋 Booking Details</h2>
            
            <div class="detail-row">
                <span class="detail-label">Service:</span>
                <span class="detail-value">{{ $upsell->title }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Property:</span>
                <span class="detail-value">{{ $property->name }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Order Number:</span>
                <span class="detail-value">#{{ $order->id }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Guest Count:</span>
                <span class="detail-value">{{ $order->order_details['guest_count'] ?? 1 }} {{ ($order->order_details['guest_count'] ?? 1) == 1 ? 'guest' : 'guests' }}</span>
            </div>
            
            @if(isset($guestInfo['name']) && $guestInfo['name'])
            <div class="detail-row">
                <span class="detail-label">Guest Name:</span>
                <span class="detail-value">{{ $guestInfo['name'] }}</span>
            </div>
            @endif
            
            @if(isset($guestInfo['email']) && $guestInfo['email'])
            <div class="detail-row">
                <span class="detail-label">Guest Email:</span>
                <span class="detail-value">{{ $guestInfo['email'] }}</span>
            </div>
            @endif
            
            @if(isset($guestInfo['phone']) && $guestInfo['phone'])
            <div class="detail-row">
                <span class="detail-label">Guest Phone:</span>
                <span class="detail-value">{{ $guestInfo['phone'] }}</span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="detail-label">Scheduled Date:</span>
                <span class="detail-value">{{ isset($order->order_details['scheduled_date']) ? \Carbon\Carbon::parse($order->order_details['scheduled_date'])->format('F j, Y \a\t g:i A') : 'TBD' }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Unit Price:</span>
                <span class="detail-value">${{ number_format($order->order_details['unit_price'] ?? $order->amount, 2) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value">{{ ucfirst($order->order_details['payment_method'] ?? 'stripe') }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value">#{{ $order->id }}</span>
            </div>
        </div>

        <div class="total-amount">
            <h3>Total Amount: ${{ number_format($order->amount, 2) }}</h3>
        </div>

        @if($order->order_details['special_requests'] || $order->order_details['menu_preferences'])
        <div class="special-requests">
            <h3>Special Requests & Preferences</h3>
            @if($order->order_details['special_requests'])
                <p><strong>Special Requests:</strong> {{ $order->order_details['special_requests'] }}</p>
            @endif
            @if($order->order_details['menu_preferences'])
                <p><strong>Menu Preferences:</strong> {{ $order->order_details['menu_preferences'] }}</p>
            @endif
        </div>
        @endif

        <div class="booking-details">
            <h3>Contact Information</h3>
            <div class="detail-row">
                <span class="detail-label">Guest Name:</span>
                <span class="detail-value">{{ $order->guest_name }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Guest Email:</span>
                <span class="detail-value">{{ $order->guest_email }}</span>
            </div>
            @if($order->guest_phone)
            <div class="detail-row">
                <span class="detail-label">Guest Phone:</span>
                <span class="detail-value">{{ $order->guest_phone }}</span>
            </div>
            @endif
        </div>

        <div class="footer">
            <p>This booking has been automatically confirmed and payment has been processed.</p>
            <p>Please prepare for the scheduled service and contact the guest if needed.</p>
            <p><strong>Villa Upsell Platform</strong></p>
        </div>
    </div>
</body>
</html>