<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_number',
        'type',
        'price_per_night',
        'is_available',
        'description',
        'max_occupancy',
        'image_path',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'price_per_night' => 'decimal:2',
    ];
}
