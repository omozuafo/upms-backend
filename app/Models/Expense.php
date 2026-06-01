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
        'category', // Plumbing, Electrical, Utilities, Insurance, Taxes, etc.
        'amount',
        'date',
        'description',
        'vendor', // Name of service provider
        'invoice_number',
        'status', // Paid, Pending
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
