<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class IssueController extends Controller
{
    public function index(Request $request)
    {
        $query = Issue::with(['property', 'unit.tenant', 'reporter', 'assignee']);

        // Filter based on user role
        $user = Auth::user();
        if ($user && $user->role === 'tenant') {
            $query->where('reported_by', $user->id);
        } elseif ($user && in_array($user->role, ['admin', 'super_admin'])) {
            // Admins should see issues reviewed or rejected by maintenance staff
            $query->whereNotNull('account_review_status');
        } elseif ($user && $user->role === 'accounting_staff') {
            // Accounting should only see issues submitted for budget by maintenance staff
            $query->whereNotNull('budget_cost');
        }

        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        return response()->json($query->orderBy('reported_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'property_id' => 'required|string|exists:properties,id',
            'unit_id' => 'nullable|string|exists:units,id',
            'priority' => 'required|string|in:Low,Medium,High,Critical',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except('image'); // Exclude 'image' from direct assignment
        $data['reported_by'] = Auth::id(); // Automatically set reporter
        $data['status'] = 'Open';
        $data['reported_at'] = now();

        // Handle Image Upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('issues', 'public');
            $data['images'] = [$path]; // Store as array to match model cast
        } else {
             $data['images'] = [];
        }

        // Handle instant budget submission for Property-Wide splits
        if ($request->has('tenant_budget_split') && $request->has('budget_cost')) {
            $data['budget_cost'] = $request->budget_cost;
            $data['tenant_budget_split'] = is_string($request->tenant_budget_split) 
                ? json_decode($request->tenant_budget_split, true) 
                : $request->tenant_budget_split;
            $data['account_review_status'] = 'Pending';
            $data['maintenance_report'] = $request->description; // Re-use description as report
        }

        $issue = Issue::create($data);

        // Create notification for tenant
        \App\Models\Notification::create([
            'user_id' => Auth::id(),
            'type' => 'maintenance_update',
            'title' => 'Maintenance Request Received',
            'message' => "Your maintenance request '{$issue->title}' has been received and is being reviewed.",
            'data' => ['issue_id' => $issue->id, 'status' => 'Open'],
        ]);

        // Notify all maintenance staff
        $maintenanceStaffs = \App\Models\User::where('role', 'maintenance_staff')->get();
        foreach ($maintenanceStaffs as $staff) {
            \App\Models\Notification::create([
                'user_id' => $staff->id,
                'type' => 'maintenance_update',
                'title' => 'New Maintenance Request',
                'message' => "A new maintenance request '{$issue->title}' has been submitted.",
                'data' => ['issue_id' => $issue->id, 'status' => 'Open'],
            ]);
        }

        // Notify accounting staff if budget is instantly submitted
        if (isset($data['budget_cost'])) {
            $accountRoles = ['accounting_staff', 'admin', 'super_admin'];
            $accountStaffs = \App\Models\User::whereIn('role', $accountRoles)->get();
            foreach ($accountStaffs as $staff) {
                \App\Models\Notification::create([
                    'user_id' => $staff->id,
                    'type' => 'budget_review',
                    'title' => 'Pending Budget Review',
                    'message' => "A maintenance budget of ₦" . number_format((float)$issue->budget_cost, 2) . " has been submitted for '{$issue->title}'.",
                    'data' => ['issue_id' => $issue->id, 'status' => 'Pending'],
                ]);
            }
        }

        return response()->json($issue, 201);
    }

    public function show($id)
    {
        $issue = Issue::with(['property', 'unit.tenant', 'reporter', 'assignee'])->find($id);
        if (!$issue) {
            return response()->json(['message' => 'Issue not found'], 404);
        }
        
        if (Auth::user() && Auth::user()->role === 'tenant' && Auth::id() != $issue->reported_by) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json($issue);
    }

    public function update(Request $request, $id)
    {
        $issue = Issue::find($id);
        if (!$issue) {
            return response()->json(['message' => 'Issue not found'], 404);
        }

        if (Auth::user() && Auth::user()->role === 'tenant' && Auth::id() != $issue->reported_by) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $allowedFields = $request->all();
        if (Auth::user() && Auth::user()->role === 'tenant') {
            $allowedFields = $request->only(['title', 'description', 'priority', 'images']);
        }

        $issue->update($allowedFields);

        if ($request->status === 'Resolved' && !$issue->resolved_at) {
            $issue->update(['resolved_at' => now()]);
        }

        return response()->json($issue);
    }

    public function destroy($id)
    {
        $issue = Issue::find($id);
        if (!$issue) {
            return response()->json(['message' => 'Issue not found'], 404);
        }

        if (Auth::user() && Auth::user()->role === 'tenant' && Auth::id() != $issue->reported_by) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $issue->delete();
        return response()->json(['message' => 'Issue deleted']);
    }

    public function stats()
    {
        $query = Issue::query();

        // Filter based on user role
        $user = Auth::user();
        if ($user && $user->role === 'tenant') {
            $query->where('reported_by', $user->id);
        } elseif ($user && in_array($user->role, ['admin', 'super_admin'])) {
            $query->whereNotNull('account_review_status');
        } elseif ($user && $user->role === 'accounting_staff') {
            $query->whereNotNull('budget_cost');
        }

        return response()->json([
            'total' => (clone $query)->count(),
            'open' => (clone $query)->where('status', 'Open')->count(),
            'in_progress' => (clone $query)->where('status', 'In Progress')->count(),
            'resolved' => (clone $query)->where('status', 'Resolved')->count(),
        ]);
    }

    public function budgetIssue(Request $request, $id)
    {
        $request->validate([
            'budget_cost' => 'required|numeric|min:0',
            'maintenance_report' => 'required|string',
            'tenant_budget_split' => 'nullable|array',
            'tenant_budget_split.*.tenant_id' => 'required_with:tenant_budget_split|exists:users,id',
            'tenant_budget_split.*.amount' => 'required_with:tenant_budget_split|numeric|min:0',
        ]);

        $issue = Issue::find($id);
        if (!$issue) return response()->json(['message' => 'Issue not found'], 404);

        $issue->update([
            'budget_cost' => $request->budget_cost,
            'maintenance_report' => $request->maintenance_report,
            'tenant_budget_split' => $request->tenant_budget_split,
            'account_review_status' => 'Pending'
        ]);

        $issue->load(['property', 'unit.tenant', 'reporter', 'assignee']);

        // Notify accounting staff
        $accountRoles = ['accounting_staff', 'admin', 'super_admin'];
        $accountStaffs = \App\Models\User::whereIn('role', $accountRoles)->get();
        foreach ($accountStaffs as $staff) {
            \App\Models\Notification::create([
                'user_id' => $staff->id,
                'type' => 'budget_review',
                'title' => 'Pending Budget Review',
                'message' => "A maintenance budget of ₦" . number_format((float)$issue->budget_cost, 2) . " has been submitted for '{$issue->title}'.",
                'data' => ['issue_id' => $issue->id, 'status' => 'Pending'],
            ]);
        }

        // Notify the maintenance staff who submitted the budget
        if (Auth::user() && Auth::user()->role === 'maintenance_staff') {
            \App\Models\Notification::create([
                'user_id' => Auth::id(),
                'type' => 'maintenance_update',
                'title' => 'Budget Submitted Successfully',
                'message' => "Your budget of ₦" . number_format((float)$issue->budget_cost, 2) . " for '{$issue->title}' has been sent to accounting.",
                'data' => ['issue_id' => $issue->id, 'status' => 'Pending'],
            ]);
        }

        return response()->json($issue);
    }

    public function rejectIssue($id)
    {
        $issue = Issue::find($id);
        if (!$issue) return response()->json(['message' => 'Issue not found'], 404);

        $issue->update([
            'status' => 'Closed',
            'account_review_status' => 'Maintenance Rejected'
        ]);

        $issue->load(['property', 'unit.tenant', 'reporter', 'assignee']);

        // Notify the tenant
        if ($issue->reported_by) {
            \App\Models\Notification::create([
                'user_id' => $issue->reported_by,
                'type' => 'maintenance_update',
                'title' => 'Maintenance Request Rejected',
                'message' => "Your maintenance request '{$issue->title}' has been rejected and closed.",
                'data' => ['issue_id' => $issue->id, 'status' => 'Closed'],
            ]);
        }

        return response()->json($issue);
    }

    public function accountAction(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['admin', 'super_admin', 'accounting_staff'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate(['action' => 'required|in:Accept,Reject,Dispute']);

        $issue = Issue::find($id);
        if (!$issue) return response()->json(['message' => 'Issue not found'], 404);

        if ($request->action === 'Accept') {
            $issue->account_review_status = 'Accepted';
            $issue->status = 'In Progress';

            if (!empty($issue->tenant_budget_split) && is_array($issue->tenant_budget_split)) {
                // Handle split charges
                foreach ($issue->tenant_budget_split as $split) {
                    $tenant = \App\Models\User::find($split['tenant_id']);
                    if ($tenant && $split['amount'] > 0) {
                        $tenant->wallet_balance -= $split['amount'];
                        $tenant->save();

                        \App\Models\WalletTransaction::create([
                            'user_id' => $tenant->id,
                            'amount' => $split['amount'],
                            'type' => 'debit',
                            'description' => "Maintenance Cost Deducted (Split): {$issue->title}",
                            'admin_id' => Auth::id(),
                        ]);
                    }
                }
            } else {
                // Fallback to single tenant / reporter logic
                $tenant = ($issue->unit && $issue->unit->tenant_id) ? \App\Models\User::find($issue->unit->tenant_id) : \App\Models\User::find($issue->reported_by);
                if ($tenant) {
                    $tenant->wallet_balance -= $issue->budget_cost;
                    $tenant->save();

                    \App\Models\WalletTransaction::create([
                        'user_id' => $tenant->id,
                        'amount' => $issue->budget_cost,
                        'type' => 'debit',
                        'description' => "Maintenance Cost Deducted: {$issue->title}",
                        'admin_id' => Auth::id(),
                    ]);
                }
            }
        } elseif ($request->action === 'Reject') {
            $issue->account_review_status = 'Rejected';
            $issue->status = 'Closed';
        } elseif ($request->action === 'Dispute') {
            $issue->account_review_status = 'Disputed';
        }

        $issue->save();
        $issue->load(['property', 'unit.tenant', 'reporter', 'assignee']);
        return response()->json($issue);
    }
}
