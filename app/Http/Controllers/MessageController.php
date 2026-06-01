<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $type = $request->query('type', 'inbox'); // 'inbox' or 'sent'

        $query = Message::with(['sender:id,name', 'receiver:id,name']);

        if ($type === 'sent') {
            $query->where('sender_id', $userId);
        } else {
            $query->where('receiver_id', $userId);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function show($id)
    {
        $userId = Auth::id();
        $message = Message::with(['sender:id,name', 'receiver:id,name'])->findOrFail($id);

        if ($message->sender_id !== $userId && $message->receiver_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($message->receiver_id === $userId && null === $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        return response()->json($message);
    }

    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
        ]);

        $sender = Auth::user();

        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $request->receiver_id,
            'subject' => $request->subject,
            'body' => $request->body,
        ]);

        // Dispatch a Notification
        Notification::create([
            'user_id' => $request->receiver_id,
            'type' => 'message',
            'title' => 'New Message',
            'message' => "You have a message from {$sender->name}",
            'reference_id' => $message->id,
        ]);

        return response()->json($message, 201);
    }
}
