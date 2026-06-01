<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BroadcastsBusinessEvents;

class Unit extends Model
{
    use SoftDeletes, BroadcastsBusinessEvents;

    protected $fillable = [
        'property_id',
        'unit_number',
        'floor',
        'type', // 1BHK, 2BHK, Studio, etc.
        'status', // Occupied, Vacant
        'rent_amount',
        'description',
        'tenant_id',
        'occupancy_start_date',
        'rent_period',
    ];

    protected $casts = [
        'rent_amount' => 'decimal:2',
        'occupancy_start_date' => 'date',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function activeLease()
    {
        return $this->hasOne(Lease::class)->where('status', 'Active');
    }

    public function lease()
    {
        return $this->hasOne(Lease::class)->where('status', 'Active');
    }

    public function leases()
    {
        return $this->hasMany(Lease::class);
    }
}
