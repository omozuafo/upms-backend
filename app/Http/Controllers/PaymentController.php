<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Payment::with(['tenant', 'unit.property', 'property']);

        // Restrict tenants to only see their own payments
        if ($user->role === 'tenant') {
            $query->where('tenant_id', $user->id);
        } elseif ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->has('lease_id')) {
            $query->where('lease_id', $request->lease_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('payment_date', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|string|exists:users,id',
            'lease_id' => 'nullable|string|exists:leases,id',
            'unit_id' => 'nullable|string|exists:units,id',
            'amount' => 'required|numeric',
            'payment_date' => 'required|date',
            'type' => 'required|string', // Rent, Service Charge, etc.
            'method' => 'required|string',
            'notes' => 'nullable|string',
            'evidence' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $request->except('evidence');
        $data['receipt_number'] = 'RCP-' . strtoupper(Str::random(8)); // Auto-generate receipt
        
        // Ensure correct tenant_id if Admin is creating payment for a unit
        if (auth()->user()->role !== 'tenant' && isset($data['unit_id'])) {
            $unit = \App\Models\Unit::find($data['unit_id']);
            if ($unit && $unit->tenant_id) {
                $data['tenant_id'] = $unit->tenant_id;
            }
        }
        // Handle evidence upload
        if ($request->hasFile('evidence')) {
            $file = $request->file('evidence');
            $filename = time() . '_' . Str::random(20) . '.' . $file->extension();
            $file->move(public_path('payments'), $filename);
            $data['evidence_path'] = 'payments/' . $filename;
        }

        // Set status 'Pending' for tenants
        if (auth()->user()->role === 'tenant') {
            $data['status'] = 'Pending';
        } else {
            $data['status'] = $request->status ?? 'Paid';
        }

        $payment = Payment::create($data);

        // Automation: If Created as Paid and is Rent, mark units as Occupied
        if ($payment->status === 'Paid' && strcasecmp($payment->type, 'Rent') === 0) {
            $this->updateUnitStatusToOccupied($payment);
        }

        // Notify Admins and Accounting if it's a tenant payment
        if (auth()->user()->role === 'tenant') {
            $admins = \App\Models\User::whereIn('role', ['super_admin', 'admin', 'accounting_staff'])->get();
            foreach ($admins as $admin) {
                \App\Models\Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'payment',
                    'title' => 'New Payment Submitted',
                    'message' => 'Tenant ' . auth()->user()->name . ' submitted a payment of ₦' . number_format($payment->amount) . ' for ' . $payment->type,
                    'read' => false,
                ]);
            }

            // Notify the tenant themselves
            \App\Models\Notification::create([
                'user_id' => auth()->id(),
                'type' => 'payment_submission',
                'title' => 'Payment Details Submitted',
                'message' => 'Your payment of ₦' . number_format($payment->amount) . ' for ' . $payment->type . ' has been seamlessly sent to accounting for final approval.',
                'read' => false,
            ]);

            // Notify Landlord if payment is linked to a unit
            $landlordId = null;
            if ($payment->unit_id) {
                $unit = \App\Models\Unit::with('property')->find($payment->unit_id);
                $landlordId = $unit->property->landlord_id ?? null;
            } elseif ($payment->lease_id) {
                $lease = \App\Models\Lease::with('unit.property')->find($payment->lease_id);
                $landlordId = $lease->unit->property->landlord_id ?? null;
            }

            if ($landlordId) {
                 \App\Models\Notification::create([
                    'user_id' => $landlordId,
                    'type' => 'payment',
                    'title' => 'Rent Payment Received',
                    'message' => 'Tenant ' . auth()->user()->name . ' paid ₦' . number_format($payment->amount) . ' for ' . $payment->type,
                    'read' => false,
                ]);
            }
        }

        return response()->json($payment, 201);
    }

    public function show($id)
    {
        $payment = Payment::with(['tenant', 'unit.property'])->find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        
        if (auth()->user()->role === 'tenant' && auth()->id() != $payment->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json($payment);
    }

    public function update(Request $request, $id)
    {
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if (auth()->user()->role === 'tenant' && auth()->id() != $payment->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $oldStatus = $payment->status;
        
        $allowedFields = $request->all();
        if (auth()->user()->role === 'tenant') {
            $allowedFields = $request->only(['notes']);
        }

        $payment->update($allowedFields);
        $newStatus = $payment->status;

        // Automation: If Rent is approved (Paid), mark units as Occupied
        if ($oldStatus !== 'Paid' && $newStatus === 'Paid' && strcasecmp($payment->type, 'Rent') === 0) {
            $this->updateUnitStatusToOccupied($payment);
        }

        // Notify Tenant if status changed
        if ($oldStatus !== $newStatus && $payment->tenant_id) {
            \App\Models\Notification::create([
                'user_id' => $payment->tenant_id,
                'type' => 'payment_status',
                'title' => 'Payment Status Updated',
                'message' => 'Your payment of ₦' . number_format($payment->amount) . ' for ' . $payment->type . ' has been ' . $newStatus . '.',
                'read' => false,
            ]);
        }

        return response()->json($payment);
    }

    private function updateUnitStatusToOccupied($payment)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($payment) {
            $unitIds = $payment->unit_ids;
            
            // Auto-detect units if not explicitly provided
            if (empty($unitIds)) {
                if ($payment->unit_id) {
                    $unitIds = [$payment->unit_id];
                } else {
                    // Find all active units for this tenant in the relevant property
                    $query = \App\Models\Lease::where('tenant_id', $payment->tenant_id)
                        ->where('status', 'Active');
                    
                    if ($payment->property_id) {
                        $query->whereHas('unit', function($q) use ($payment) {
                            $q->where('property_id', $payment->property_id);
                        });
                    }
                    
                    $unitIds = $query->pluck('unit_id')->toArray();
                }
            }
            
            if (empty($unitIds)) return;

            // Calculate amount per unit if it's a rent payment
            $amountPerUnit = 0;
            if (strcasecmp($payment->type, 'Rent') === 0 && $payment->amount > 0) {
                $amountPerUnit = $payment->amount / count($unitIds);
            }
            
            foreach ($unitIds as $unitId) {
                $unit = \App\Models\Unit::find($unitId);
                if ($unit) {
                    // Safety check: only update if currently vacant or same tenant
                    if ($unit->status === 'Vacant' || $unit->tenant_id === $payment->tenant_id) {
                        $updateData = [
                            'status' => 'Occupied',
                            'tenant_id' => $payment->tenant_id,
                            'occupancy_start_date' => $payment->payment_date,
                            'rent_period' => 'Annual', // Consider making this dynamic if possible
                        ];

                        // If rent payment, update the rent_amount on the unit
                        if ($amountPerUnit > 0) {
                            $updateData['rent_amount'] = $amountPerUnit;
                        }

                        $unit->update($updateData);

                        // Also update the active lease for this unit if it exists
                        $activeLease = \App\Models\Lease::where('unit_id', $unitId)
                            ->where('tenant_id', $payment->tenant_id)
                            ->where('status', 'Active')
                            ->first();
                        
                        if ($activeLease && $amountPerUnit > 0) {
                            $activeLease->update(['rent_amount' => $amountPerUnit]);
                        }
                    }
                }
            }
        });
    }

    public function auditTrail(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'super_admin', 'accounting_staff'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payments = Payment::with(['tenant', 'property'])->where('status', 'Paid')->get()->map(function($p) {
            return [
                'id' => 'P-'.$p->id,
                'date' => $p->payment_date, // Carbon instance
                'tenant_name' => $p->tenant ? $p->tenant->name : 'Unknown',
                'type' => 'Payment Credit',
                'description' => $p->type . ' Payment (' . $p->method . ')',
                'amount' => $p->amount,
                'is_credit' => true,
                'reference' => $p->receipt_number,
                'source' => 'Payment',
            ];
        });

        $walletTx = \App\Models\WalletTransaction::with('user')->get()->map(function($w) {
            return [
                'id' => 'W-'.$w->id,
                'date' => $w->created_at, // Carbon instance
                'tenant_name' => $w->user ? $w->user->name : 'Unknown',
                'type' => $w->type === 'credit' ? 'Wallet Credit' : 'Wallet Debit',
                'description' => $w->description,
                'amount' => $w->amount,
                'is_credit' => $w->type === 'credit',
                'reference' => 'WTX-'.str_pad($w->id, 5, '0', STR_PAD_LEFT),
                'source' => 'Wallet',
            ];
        });

        // Combine and sort by date descending
        $auditTrail = $payments->concat($walletTx)->sortByDesc('date')->values();

        return response()->json($auditTrail);
    }

    public function destroy($id)
    {
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if (auth()->user()->role === 'tenant' && auth()->id() != $payment->tenant_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payment->delete();
        return response()->json(['message' => 'Payment deleted']);
    }
}
