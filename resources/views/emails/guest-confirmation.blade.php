<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Confirmation</title>
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
        .confirmation-details {
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
        .invoice-section {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .invoice-table th,
        .invoice-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .invoice-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
        }
        .service-provider {
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
        .success-badge {
            background: #d4edda;
            color: #155724;
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-block;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ Booking Confirmed!</h1>
        <p>Your service has been successfully booked</p>
        <div class="success-badge">Payment Processed Successfully</div>
    </div>

    <div class="content">
        <div class="confirmation-details">
            <h2>Booking Confirmation</h2>
            
            <div class="detail-row">
                <span class="detail-label">Service:</span>
                <span class="detail-value">{{ $upsell->title }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Property:</span>
                <span class="detail-value">{{ $property->name }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Guest Count:</span>
                <span class="detail-value">{{ $order->order_details['guest_count'] ?? 1 }} {{ ($order->order_details['guest_count'] ?? 1) == 1 ? 'guest' : 'guests' }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Scheduled Date:</span>
                <span class="detail-value">{{ isset($order->order_details['scheduled_date']) ? \Carbon\Carbon::parse($order->order_details['scheduled_date'])->format('F j, Y \a\t g:i A') : 'TBD' }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value">#{{ $order->id }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value">{{ ucfirst($order->order_details['payment_method'] ?? 'stripe') }}</span>
            </div>
        </div>

        <div class="service-provider">
            <h3>Service Provider</h3>
            <div class="detail-row">
                <span class="detail-label">Company:</span>
                <span class="detail-value">{{ $vendor->name }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Service Type:</span>
                <span class="detail-value">{{ $vendor->service_type }}</span>
            </div>
            @if($vendor->phone)
            <div class="detail-row">
                <span class="detail-label">Contact:</span>
                <span class="detail-value">{{ $vendor->phone }}</span>
            </div>
            @endif
        </div>

        <div class="invoice-section">
            <h3>📄 Invoice / Factuur</h3>
            <div class="detail-row">
                <span class="detail-label">Invoice Number:</span>
                <span class="detail-value">{{ $invoiceData['invoice_number'] ?? 'INV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Invoice Date:</span>
                <span class="detail-value">{{ $invoiceData['date'] ?? $order->created_at->format('d/m/Y') }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Due Date:</span>
                <span class="detail-value">{{ $invoiceData['due_date'] ?? $order->created_at->addDays(30)->format('d/m/Y') }}</span>
            </div>
            
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($invoiceData['items']) && count($invoiceData['items']) > 0)
                        @foreach($invoiceData['items'] as $item)
                        <tr>
                            <td>{{ $item['title'] }}</td>
                            <td>{{ $item['quantity'] }}</td>
                            <td>{{ $invoiceData['currency'] ?? 'EUR' }} {{ number_format($item['unit_price'], 2) }}</td>
                            <td>{{ $invoiceData['currency'] ?? 'EUR' }} {{ number_format($item['total'], 2) }}</td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td>{{ $upsell->title }}</td>
                            <td>{{ $order->order_details['guest_count'] ?? 1 }}</td>
                            <td>{{ $invoiceData['currency'] ?? 'EUR' }} {{ number_format($order->order_details['unit_price'] ?? $order->amount, 2) }}</td>
                            <td>{{ $invoiceData['currency'] ?? 'EUR' }} {{ number_format($order->amount, 2) }}</td>
                        </tr>
                    @endif
                    
                    @if(isset($invoiceData['subtotal']) && isset($invoiceData['tax_amount']))
                    <tr>
                        <td colspan="3"><strong>Subtotal</strong></td>
                        <td><strong>{{ $invoiceData['currency'] ?? 'EUR' }} {{ number_format($invoiceData['subtotal'], 2) }}</strong></td>
                    </tr>
                    <tr>
                        <td colspan="3"><strong>VAT ({{ $invoiceData['tax_rate'] ?? 21 }}%)</strong></td>
                        <td><strong>{{ $invoiceData['currency'] ?? 'EUR' }} {{ number_format($invoiceData['tax_amount'], 2) }}</strong></td>
                    </tr>
                    @endif
                    
                    <tr class="total-row">
                        <td colspan="3"><strong>Total Amount</strong></td>
                        <td><strong>{{ $invoiceData['currency'] ?? 'EUR' }} {{ number_format($invoiceData['total'] ?? $order->amount, 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>
            
            <p><strong>Payment Status:</strong> <span style="color: green;">✓ Paid</span></p>
            <p><strong>Payment Method:</strong> {{ ucfirst($order->order_details['payment_method'] ?? 'stripe') }}</p>
        </div>

        @if($order->order_details['special_requests'] || $order->order_details['menu_preferences'])
        <div class="confirmation-details">
            <h3>Your Special Requests</h3>
            @if($order->order_details['special_requests'])
                <p><strong>Special Requests:</strong> {{ $order->order_details['special_requests'] }}</p>
            @endif
            @if($order->order_details['menu_preferences'])
                <p><strong>Menu Preferences:</strong> {{ $order->order_details['menu_preferences'] }}</p>
            @endif
        </div>
        @endif

        <div class="confirmation-details">
            <h3>What's Next?</h3>
            <ul>
                <li>Your service provider has been notified and will prepare for your booking</li>
                <li>You will receive a WhatsApp confirmation shortly</li>
                <li>If you need to make any changes, please contact the property management</li>
                <li>Enjoy your enhanced villa experience!</li>
            </ul>
        </div>

        <div class="footer">
            <p>Thank you for choosing our premium services!</p>
            <p>This is your official booking confirmation and invoice.</p>
            <p><strong>Villa Upsell Platform</strong></p>
            <p>For support, contact: support@villa-upsell.com</p>
        </div>
    </div>
</body>
</html>