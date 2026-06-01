<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $perPage = $request->get('per_page', 12); // Default 12 items per page
        
        $query = Property::with('units:id,property_id,status')
            ->withCount(['units', 'units as tenants_count' => function($query) {
                $query->whereNotNull('tenant_id');
            }]);

        // Filter for landlords
        if ($user->role === 'landlord') {
            $query->where('landlord_id', $user->id);
        }

        $properties = $query->paginate($perPage);
        
        return response()->json($properties);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized. Only Admins can create properties.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'type' => 'required|string',
            'status' => 'required|string',
            'units_count' => 'required|integer|min:0',
            'landlord_id' => 'required|string|exists:users,id', // Ensure user exists
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max per image
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Check for duplicate property name
        if (Property::where('name', $request->name)->exists()) {
            return response()->json(['error' => 'A property with this name already exists.'], 400);
        }

        try {
            DB::beginTransaction();

            $propertyData = $request->only(['name', 'address', 'type', 'status', 'description']);
            // Explicitly cast units_count
            $propertyData['units_count'] = (int) $request->units_count;
            $propertyData['landlord_id'] = $request->landlord_id;
            
            // Handle image uploads
            $imagePaths = [];
            if ($request->hasFile('images')) {
                $images = $request->file('images');
                
                // Limit to 4 images
                if (count($images) > 4) {
                    return response()->json(['error' => 'Maximum 4 images allowed'], 400);
                }
                
                foreach ($images as $image) {
                    // Store the image in public/properties folder
                    $path = $image->store('properties', 'public');
                    $imagePaths[] = $path;
                }
            }
            
            $propertyData['images'] = json_encode($imagePaths);
            
            $property = Property::create($propertyData);

            // Auto-generate units if units_count > 0
            $unitsCount = (int) $request->units_count;
            if ($unitsCount > 0) {
                $units = [];
                for ($i = 1; $i <= $unitsCount; $i++) {
                    $units[] = [
                        'property_id' => $property->id,
                        'unit_number' => 'Unit ' . $i,
                        'type' => $request->type ?? 'Apartment', // Default to property type or Apartment
                        'status' => 'Vacant',
                        'rent_amount' => 0.00, // Default rent, can be updated later
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                
                // Chunk inserts to avoid placeholder limits
                foreach (array_chunk($units, 50) as $chunk) {
                    \App\Models\Unit::insert($chunk);
                }
                
                // Verify creation
                $createdCount = \App\Models\Unit::where('property_id', $property->id)->count();
                if ($createdCount !== $unitsCount) {
                     throw new \Exception("Failed to create all units. Requested: $unitsCount, Created: $createdCount");
                }
            }

            DB::commit();
            return response()->json($property, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            // Cleanup uploaded images if needed, but for now simple rollback is enough for DB.
            return response()->json(['error' => 'Failed to create property: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $property = Property::with([
            'landlord:id,name,email,phone,company_name',
            'units.tenant:id,name,email,phone',
            'units.lease' => function($query) {
                $query->where('status', 'Active');
            }
        ])->find($id);
        
        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        // Calculate occupancy statistics
        $totalUnits = $property->units->count();
        $occupiedUnits = $property->units->where('status', 'Occupied')->count();
        $vacantUnits = $property->units->where('status', 'Vacant')->count();
        $occupancyRate = $totalUnits > 0 ? ($occupiedUnits / $totalUnits) * 100 : 0;

        // Get unique tenants (some might have multiple units)
        // Get unique tenants with their lease expiration
        $propertyId = $id;
        $tenants = $property->units()
            ->with(['tenant:id,name,email,phone', 'activeLease:id,unit_id,end_date'])
            ->whereHas('tenant')
            ->get()
            ->map(function($unit) use ($propertyId) {
                $tenant = $unit->tenant;
                // Attach rent expiration if active lease exists
                if ($unit->activeLease) {
                    $tenant->rent_expiration = $unit->activeLease->end_date->format('Y-m-d');
                }

                $paid = \App\Models\Payment::where('tenant_id', $tenant->id)
                    ->where('property_id', $propertyId)
                    ->where('status', 'Paid')
                    ->sum('amount');
                    
                $pending = \App\Models\Payment::where('tenant_id', $tenant->id)
                    ->where('property_id', $propertyId)
                    ->where('status', 'Pending')
                    ->sum('amount');
                    
                $tenant->total_paid = $paid;
                $tenant->outstanding_balance = $pending;
                $tenant->unit_number = $unit->unit_number;

                return $tenant;
            })
            ->unique('id')
            ->values();

        return response()->json([
            'property' => $property,
            'stats' => [
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'vacant_units' => $vacantUnits,
                'occupancy_rate' => round($occupancyRate, 2),
            ],
            'tenants' => $tenants,
        ]);
    }

    public function update(Request $request, $id)
    {
        $property = Property::find($id);
        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        $property->update($request->all());
        return response()->json($property);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['error' => 'Unauthorized. Only Admins can delete properties.'], 403);
        }

        $property = Property::find($id);
        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        $property->delete();
        return response()->json(['message' => 'Property deleted']);
    }
}
