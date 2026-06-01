<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Broadcasting\PrivateChannel;

trait BroadcastsBusinessEvents
{
    use BroadcastsEvents;

    /**
     * Set the broadcast event name consistently for all models (e.g. "property.created", "tenant.updated")
     *
     * @param  string  $event
     * @return string
     */
    public function broadcastAs(string $event): string
    {
        return strtolower(class_basename($this)) . '.' . $event;
    }

    /**
     * Get the channels that model events should broadcast on.
     *
     * @param  string  $event
     * @return array<int, \Illuminate\Broadcasting\Channel|\Illuminate\Database\Eloquent\Model>
     */
    public function broadcastOn(string $event): array
    {
        $channels = [new PrivateChannel('admin.updates')];

        // Broadcast to relevant user channels for specific access
        $relatedUserIds = [];

        // If the model itself is a User, broadcast to its own channel
        if ($this instanceof \App\Models\User) {
            $relatedUserIds[] = $this->id;
        }

        if (isset($this->tenant_id)) {
            $relatedUserIds[] = $this->tenant_id;
        }
        
        if (isset($this->landlord_id)) {
            $relatedUserIds[] = $this->landlord_id;
        }

        if (isset($this->reported_by)) {
            $relatedUserIds[] = $this->reported_by;
        }

        if (isset($this->assigned_to)) {
            $relatedUserIds[] = $this->assigned_to;
        }

        // Add unique user-specific channels
        foreach (array_unique($relatedUserIds) as $userId) {
            if ($userId) {
                $channels[] = new PrivateChannel('user.updates.' . $userId);
            }
        }

        return $channels;
    }
}
