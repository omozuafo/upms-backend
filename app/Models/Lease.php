<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BroadcastsBusinessEvents;

class Lease extends Model
{
    use SoftDeletes, BroadcastsBusinessEvents;
    
    // protected $connection = 'mongodb';
    // protected $collection = 'leases';

    protected $fillable = [
        'unit_id',
        'tenant_id',
        'start_date',
        'end_date',
        'rent_amount',
        'security_deposit',
        'status', // Active, Terminated, Expired
        'terms', // Description or array of terms
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }
}
