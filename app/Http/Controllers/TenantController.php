<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TenantController extends Controller
{
    /**
     * Display a listing of tenants.
     */
    public function index()
    {
        // Restrict access: Tenants cannot view the list of other tenants
        if (auth()->user()->role === 'tenant') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tenants = User::where('role', 'tenant')->get();
        return response()->json($tenants);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            // Add other tenant specific fields here if needed
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $tenant = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'tenant',
            // 'tenant_details' => $request->tenant_details ?? [], // Flexible field
        ]);

        return response()->json($tenant, 201);
    }

    /**
     * Display the specified tenant.
     */
    public function show($id)
    {
        $tenant = User::where('role', 'tenant')
            ->with([
                'unit.property.landlord',
                'unit.lease' => function($query) {
                    $query->where('status', 'Active');
                },
            ])
            ->find($id);
        
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        if (auth()->user()->role === 'tenant' && auth()->id() != $tenant->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get payments for this tenant
        $payments = \App\Models\Payment::where('tenant_id', $id)
            ->orderBy('payment_date', 'desc')
            ->limit(10)
            ->get();

        // Get maintenance issues reported by this tenant
        $issues = \App\Models\Issue::where('reported_by', $id)
            ->with(['property', 'unit'])
            ->orderBy('reported_at', 'desc')
            ->get();

        // Calculate payment statistics
        $totalPaid = \App\Models\Payment::where('tenant_id', $id)
            ->where('status', 'Paid')
            ->sum('amount');
        
        $pendingPayments = \App\Models\Payment::where('tenant_id', $id)
            ->where('status', 'Pending')
            ->sum('amount');

        $outstandingBreakdown = \App\Models\Payment::where('tenant_id', $id)
            ->where('status', 'Pending')
            ->select('type', \Illuminate\Support\Facades\DB::raw('SUM(amount) as total'))
            ->groupBy('type')
            ->get();

        $paymentCategories = ['Rent', 'Service Charge', 'Power/Electricity', 'Diesel', 'Asset Replacement'];
        $paymentCategoryStatus = [];
        foreach ($paymentCategories as $category) {
            $paidTotal = \App\Models\Payment::where('tenant_id', $id)
                ->where('type', $category)
                ->where('status', 'Paid')
                ->sum('amount');
            
            $paymentCategoryStatus[] = [
                'category' => $category,
                'status' => $paidTotal > 0 ? 'Paid' : 'Unpaid',
                'amount_paid' => $paidTotal,
            ];
        }

        // Remove password from response
        $tenant->makeHidden(['password']);

        return response()->json([
            'tenant' => $tenant,
            'payments' => $payments,
            'issues' => $issues,
            'stats' => [
                'total_paid' => $totalPaid,
                'pending_payments' => $pendingPayments,
                'outstanding_breakdown' => $outstandingBreakdown,
                'payment_category_status' => $paymentCategoryStatus,
                'maintenance_requests' => $issues->count(),
            ]
        ]);
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, $id)
    {
        $tenant = User::where('role', 'tenant')->find($id);
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        if (auth()->user()->role === 'tenant' && auth()->id() != $tenant->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'email' => 'string|email|max:255|unique:users,email,'.$id.',id',
            'password' => 'string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except(['password', 'role']); // Prevent role change via this endpoint
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $tenant->update($data);

        return response()->json($tenant);
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy($id)
    {
        $tenant = User::where('role', 'tenant')->find($id);
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        if (auth()->user()->role === 'tenant') {
            return response()->json(['message' => 'Unauthorized'], 403); // Tenants cannot delete accounts directly
        }

        $tenant->delete();
        return response()->json(['message' => 'Tenant deleted']);
    }
    /**
     * Get the active property context for the tenant based on approved rent payment.
     */
    public function activeContext()
    {
        $user = auth()->user();

        // 1. Find the latest APPROVED rent payment
        $latestRentPayment = \App\Models\Payment::where('tenant_id', $user->id)
            ->where('type', 'Rent')
            ->where('status', 'Paid') // Approved status
            ->orderBy('payment_date', 'desc')
            ->with(['unit.property'])
            ->first();

        if (!$latestRentPayment) {
            return response()->json([
                'has_active_context' => false,
                'message' => 'No active rent payment found.',
            ]);
        }

        // 2. Extract context
        $unit = $latestRentPayment->unit;
        $property = $unit ? $unit->property : null;

        if (!$unit || !$property) {
             // Fallback: Check if lease exists directly (edge case handling)
             return response()->json([
                'has_active_context' => false,
                'message' => 'Rent payment found but unit/property data is missing.',
            ]);
        }

        return response()->json([
            'has_active_context' => true,
            'context' => [
                'property_id' => $property->id,
                'property_name' => $property->name,
                'unit_id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'rent_payment_id' => $latestRentPayment->id,
                'rent_payment_date' => $latestRentPayment->payment_date,
            ]
        ]);
    }
}
