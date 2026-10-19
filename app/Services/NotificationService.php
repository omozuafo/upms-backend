<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send notifications to one or more user IDs.
     *
     * @param int|array $userIds
     * @param array $payload
     * @return void
     */
    public static function send($userIds, array $payload): void
    {
        $ids = is_array($userIds) ? array_unique(array_filter($userIds)) : [$userIds];

        if (empty($ids)) {
            return;
        }

        foreach ($ids as $userId) {
            try {
                Notification::create([
                    'user_id' => $userId,
                    'type' => $payload['type'] ?? 'general',
                    'title' => $payload['title'] ?? 'Notification',
                    'message' => $payload['message'] ?? '',
                    'read' => false,
                    'reference_id' => $payload['reference_id'] ?? null,
                ]);
            } catch (\Exception $e) {
                Log::warning("NotificationService failed for user {$userId}: " . $e->getMessage());
            }
        }
    }
}
