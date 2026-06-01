<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Lease::with(['unit.property', 'tenant']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->has('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unit_id' => 'required|string|exists:units,id',
            'tenant_id' => 'required|string|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'rent_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Check if unit is already occupied or has active lease?
        // Logic: if status is Active, check overlap.
        
        $lease = Lease::create(array_merge($request->all(), ['status' => 'Active']));
        
        // Update unit status to Occupied and sync rent amount
        $lease->unit->update([
            'status' => 'Occupied', 
            'tenant_id' => $lease->tenant_id,
            'rent_amount' => $lease->rent_amount
        ]);

        // Notify Landlord
        $unit = $lease->unit->load('property');
        if ($unit->property->landlord_id) {
            \App\Models\Notification::create([
                'user_id' => $unit->property->landlord_id,
                'type' => 'occupancy',
                'title' => 'Unit Occupied',
                'message' => "Unit {$unit->unit_number} at {$unit->property->name} is now occupied.",
                'read' => false,
            ]);
        }

        return response()->json($lease, 201);
    }

    public function show($id)
    {
        $lease = Lease::with(['unit.property', 'tenant'])->find($id);
        if (!$lease) {
            return response()->json(['message' => 'Lease not found'], 404);
        }
        return response()->json($lease);
    }

    public function update(Request $request, $id)
    {
        $lease = Lease::find($id);
        if (!$lease) {
            return response()->json(['message' => 'Lease not found'], 404);
        }

        $lease->update($request->all());
        return response()->json($lease);
    }

    public function destroy($id)
    {
        $lease = Lease::find($id);
        if (!$lease) {
            return response()->json(['message' => 'Lease not found'], 404);
        }

        $lease->delete();
        return response()->json(['message' => 'Lease deleted']);
    }

    public function terminate($id)
    {
        $lease = Lease::find($id);
        if (!$lease) {
            return response()->json(['message' => 'Lease not found'], 404);
        }
        
        $lease->update(['status' => 'Terminated', 'end_date' => now()]);
        
        // Update unit status to Vacant
        $lease->unit->update(['status' => 'Vacant', 'tenant_id' => null]);

        // Notify Landlord
        $unit = $lease->unit->load('property');
        if ($unit->property->landlord_id) {
            \App\Models\Notification::create([
                'user_id' => $unit->property->landlord_id,
                'type' => 'occupancy',
                'title' => 'Unit Vacant',
                'message' => "Unit {$unit->unit_number} at {$unit->property->name} is now vacant.",
                'read' => false,
            ]);
        }

        return response()->json(['message' => 'Lease terminated', 'lease' => $lease]);
    }
}
