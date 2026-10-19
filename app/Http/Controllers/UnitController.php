<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $query = Unit::with(['property', 'tenant']);

        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = (int) $request->get('per_page', 15);
            return response()->json($query->paginate($perPage));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|string|exists:properties,id',
            'unit_number' => 'required|string',
            'type' => 'required|string',
            'status' => 'required|string',
            'rent_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $unit = Unit::create($request->all());
        
        // Update property units count ? Or calculate on fly.
        // Simple increment for now if needed, but MongoDB doesn't enforce relations.
        
        return response()->json($unit, 201);
    }

    public function show($id)
    {
        $unit = Unit::with(['property', 'tenant'])->find($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit not found'], 404);
        }
        return response()->json($unit);
    }

    public function update(Request $request, $id)
    {
        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit not found'], 404);
        }

        $unit->update($request->all());
        return response()->json($unit);
    }

    public function destroy($id)
    {
        $unit = Unit::find($id);
        if (!$unit) {
            return response()->json(['message' => 'Unit not found'], 404);
        }

        $unit->delete();
        return response()->json(['message' => 'Unit deleted']);
    }
}
