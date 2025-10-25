<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestCheckIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_token',
        'property_id',
        'full_name',
        'email',
        'phone_number',
        'passport_url',
        'check_in_time',
        'additional_data',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'additional_data' => 'array',
    ];

    /**
     * Get the property that owns the guest check-in.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
