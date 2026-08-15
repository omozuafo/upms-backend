<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with(['property', 'creator']);

        if ($request->has('property_id') && $request->property_id !== 'All') {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('category') && $request->category !== 'All') {
            $query->where('category', $request->category);
        }

        if ($request->has('status') && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'amount' => 'required|numeric|min:0',
            'purpose' => 'nullable|string|max:255',
            'receipt_number' => 'nullable|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'payment_timestamp' => 'nullable|date',
            'date' => 'required|date',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->all();
        $data['created_by'] = Auth::id() ?? auth('api')->id();
        $data['status'] = $data['status'] ?? 'Pending';
        if (empty($data['category'])) {
            $data['category'] = $data['purpose'] ?? 'General Expense';
        }

        $expense = Expense::create($data);
        $expense->load(['property', 'creator']);

        // Notify Admins about new expense submission
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'expense',
                'title' => 'New Expense Submission',
                'message' => 'New expense claim (' . ($expense->receipt_number ? '#' . $expense->receipt_number : '₦' . number_format($expense->amount)) . ') submitted for review.',
                'read' => false,
                'reference_id' => $expense->id,
            ]);
        }

        return response()->json($expense, 201);
    }

    public function show($id)
    {
        $expense = Expense::with(['property', 'creator'])->find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }
        return response()->json($expense);
    }

    public function update(Request $request, $id)
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        $expense->update($request->all());
        $expense->load(['property', 'creator']);
        return response()->json($expense);
    }

    public function destroy($id)
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        $expense->delete();
        return response()->json(['message' => 'Expense deleted']);
    }

    public function approve($id)
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        $expense->update([
            'status' => 'Approved',
            'rejection_reason' => null
        ]);

        // Notify accounting staff who submitted it
        $targetUserIds = [];
        if ($expense->created_by) {
            $targetUserIds[] = $expense->created_by;
        } else {
            $targetUserIds = User::where('role', 'accounting_staff')->pluck('id')->toArray();
        }

        foreach (array_unique($targetUserIds) as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => 'expense',
                'title' => 'Expense Approved',
                'message' => 'Your expense claim (' . ($expense->receipt_number ? '#' . $expense->receipt_number : '₦' . number_format($expense->amount)) . ') for ' . ($expense->purpose ?? $expense->category) . ' has been APPROVED by admin.',
                'read' => false,
                'reference_id' => $expense->id,
            ]);
        }

        return response()->json([
            'message' => 'Expense approved successfully',
            'expense' => $expense->fresh(['property', 'creator'])
        ]);
    }

    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        $expense->update([
            'status' => 'Rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Notify accounting staff with reason
        $targetUserIds = [];
        if ($expense->created_by) {
            $targetUserIds[] = $expense->created_by;
        } else {
            $targetUserIds = User::where('role', 'accounting_staff')->pluck('id')->toArray();
        }

        foreach (array_unique($targetUserIds) as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => 'expense',
                'title' => 'Expense Rejected',
                'message' => 'Your expense claim (' . ($expense->receipt_number ? '#' . $expense->receipt_number : '₦' . number_format($expense->amount)) . ') was REJECTED. Reason: ' . $request->rejection_reason,
                'read' => false,
                'reference_id' => $expense->id,
            ]);
        }

        return response()->json([
            'message' => 'Expense rejected',
            'expense' => $expense->fresh(['property', 'creator'])
        ]);
    }
}
