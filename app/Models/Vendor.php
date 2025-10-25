<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'whatsapp_number',
        'phone',
        'description',
        'service_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationship to Upsells (as primary vendor)
    public function primaryUpsells()
    {
        return $this->hasMany(Upsell::class, 'primary_vendor_id');
    }

    // Relationship to Upsells (as secondary vendor)
    public function secondaryUpsells()
    {
        return $this->hasMany(Upsell::class, 'secondary_vendor_id');
    }

    // Relationship to Orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
