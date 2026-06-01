<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BroadcastsBusinessEvents;

class Payment extends Model
{
    use SoftDeletes, BroadcastsBusinessEvents;

    protected $fillable = [
        'tenant_id',
        'lease_id',
        'property_id',
        'unit_id',
        'unit_ids',
        'type', // Rent, Service Charge, etc.
        'amount',
        'payment_date',
        'method', // Cash, Bank Transfer, Card, etc.
        'reference',
        'status', // Paid, Pending, Overdue
        'description',
        'receipt_number',
        'notes',
        'evidence_path',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'unit_ids' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
