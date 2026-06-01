<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BroadcastsBusinessEvents;

class Property extends Model
{
    use SoftDeletes, BroadcastsBusinessEvents;
    
    // protected $connection = 'mongodb';
    // protected $collection = 'properties';

    protected $fillable = [
        'name',
        'address',
        'type', // Residential, Commercial, etc.
        'status', // Active, Inactive, Under Maintenance
        'units_count',
        'landlord_id', // Reference to User (Landlord)
        'description',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
