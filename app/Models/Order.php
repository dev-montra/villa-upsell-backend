<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'upsell_id',
        'vendor_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_passport',
        'amount',
        'currency',
        'status',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'order_details',
        'fulfilled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'order_details' => 'array',
        'fulfilled_at' => 'datetime',
    ];

    // Relationship to Property
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // Relationship to Upsell
    public function upsell()
    {
        return $this->belongsTo(Upsell::class);
    }

    // Relationship to Vendor
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    // Scope for filtering by status
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Scope for filtering by property
    public function scopeByProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }
}
