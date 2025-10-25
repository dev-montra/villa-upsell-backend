<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Upsell extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'primary_vendor_id',
        'secondary_vendor_id',
        'title',
        'description',
        'price',
        'category',
        'image_url',
        'availability_rules',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'availability_rules' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationship to Property
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // Relationship to Primary Vendor
    public function primaryVendor()
    {
        return $this->belongsTo(Vendor::class, 'primary_vendor_id');
    }

    // Relationship to Secondary Vendor
    public function secondaryVendor()
    {
        return $this->belongsTo(Vendor::class, 'secondary_vendor_id');
    }

    // Relationship to Orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
