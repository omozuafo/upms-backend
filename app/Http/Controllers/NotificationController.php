<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
            
        return response()->json($notifications);
    }

    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', auth()->id())->find($id);
        
        if ($notification) {
            $notification->update(['read' => true]);
        }
        
        return response()->json(['message' => 'Marked as read']);
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', auth()->id())
            ->where('read', false)
            ->update(['read' => true]);
            
        return response()->json(['message' => 'All marked as read']);
    }

    public function markTypeAsRead(Request $request)
    {
        $types = $request->input('types', []);
        if (empty($types)) {
            return response()->json(['message' => 'No types provided'], 400);
        }

        Notification::where('user_id', auth()->id())
            ->where('read', false)
            ->whereIn('type', $types)
            ->update(['read' => true]);

        return response()->json(['message' => 'Marked as read']);
    }

    public function unreadCount()
    {
        $userId = auth()->id();
        
        $count = Notification::where('user_id', $userId)
            ->where('read', false)
            ->count();

        $maintenanceCount = Notification::where('user_id', $userId)
            ->where('read', false)
            ->whereIn('type', ['maintenance_update', 'budget_review'])
            ->count();

        $paymentCount = Notification::where('user_id', $userId)
            ->where('read', false)
            ->whereIn('type', ['payment', 'payment_status', 'payment_submission', 'wallet_funded'])
            ->count();
            
        return response()->json([
            'count' => $count,
            'maintenance_count' => $maintenanceCount,
            'payment_count' => $paymentCount
        ]);
    }
}
