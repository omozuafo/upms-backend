<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BroadcastsBusinessEvents;

class Expense extends Model
{
    use SoftDeletes, BroadcastsBusinessEvents;

    // protected $connection = 'mongodb';
    // protected $collection = 'expenses';

    protected $fillable = [
        'property_id',
        'category',
        'purpose',
        'amount',
        'date',
        'description',
        'vendor',
        'invoice_number',
        'receipt_number',
        'account_name',
        'account_number',
        'payment_timestamp',
        'status', // Pending, Approved, Rejected, Paid
        'created_by',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'payment_timestamp' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
