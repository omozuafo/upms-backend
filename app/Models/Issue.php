<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BroadcastsBusinessEvents;

class Issue extends Model
{
    use SoftDeletes, BroadcastsBusinessEvents;

    protected $fillable = [
        'title',
        'description',
        'property_id',
        'unit_id',
        'reported_by',
        'assigned_to',
        'priority', // Low, Medium, High, Critical
        'status', // Open, In Progress, Resolved, Closed
        'images',
        'reported_at',
        'resolved_at',
        'budget_cost',
        'maintenance_report',
        'account_review_status',
        'tenant_budget_split',
    ];

    protected $casts = [
        'images' => 'array',
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
        'budget_cost' => 'decimal:2',
        'tenant_budget_split' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
