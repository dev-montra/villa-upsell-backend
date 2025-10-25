<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'instagram_url',
        'hero_image_url',
        'language',
        'currency',
        'access_token',
        'tags',
        'payment_processor',
        'payout_schedule',
        'wise_account_details',
    ];

    protected $casts = [
        'tags' => 'array', // Automatically cast the JSON column to a PHP array
        'wise_account_details' => 'array', // Cast JSON column to array
    ];

    // Relationship to the owner (User)
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Define the relationship to Upsells
    public function upsells()
    {
        return $this->hasMany(Upsell::class);
    }

    // Define the relationship to Orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}