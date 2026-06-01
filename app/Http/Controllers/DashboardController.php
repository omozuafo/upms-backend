<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Unit;
use App\Models\Lease;
use App\Models\User;
use App\Models\Payment;
use App\Models\Issue;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = Auth::user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        switch ($user->role) {
            case 'super_admin':
            case 'admin':
                return $this->superAdminStats();
            case 'accounting_staff':
                return $this->accountingStaffStats();
            case 'landlord':
                return $this->landlordStats($user->id);
            case 'tenant':
                return $this->tenantStats($user->id);
            case 'maintenance_staff':
                return $this->maintenanceStaffStats($user->id);
            default:
                return response()->json(['error' => 'Unauthorized'], 403);
        }
    }
    
    private function accountingStaffStats()
    {
        try {
            $totalRevenue = Payment::where('status', 'Paid')->sum('amount') ?? 0;
            $outstandingDebts = Payment::where('status', 'Pending')->sum('amount') ?? 0;
            $totalExpenses = Expense::sum('amount') ?? 0;
            $tenantWalletReserves = User::where('role', 'tenant')->sum('wallet_balance') ?? 0;

            $recentPayments = Payment::with(['tenant', 'property'])
                ->where('status', 'Paid')
                ->orderBy('payment_date', 'desc')
                ->take(5)
                ->get();

            $pendingPayments = Payment::with(['tenant', 'property'])
                ->where('status', 'Pending')
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();
                
            $currentYear = now()->year;
            $payments = Payment::where('status', 'Paid')
                ->whereYear('payment_date', $currentYear)
                ->get();
                
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $monthlyRevenue = [];
            
            foreach ($months as $index => $monthName) {
                $monthNum = $index + 1;
                $revenueForMonth = $payments->filter(function($payment) use ($monthNum) {
                    return \Carbon\Carbon::parse($payment->payment_date)->month === $monthNum;
                })->sum('amount');
                
                $monthlyRevenue[] = [
                    'name' => $monthName,
                    'revenue' => (float)$revenueForMonth
                ];
            }

            return response()->json([
                'total_revenue' => $totalRevenue,
                'outstanding_debts' => $outstandingDebts,
                'total_expenses' => $totalExpenses,
                'tenant_wallet_reserves' => $tenantWalletReserves,
                'recent_payments' => $recentPayments,
                'pending_payments' => $pendingPayments,
                'monthly_revenue' => $monthlyRevenue,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'total_revenue' => 0,
                'outstanding_debts' => 0,
                'total_expenses' => 0,
                'tenant_wallet_reserves' => 0,
                'recent_payments' => [],
                'pending_payments' => [],
                'monthly_revenue' => [],
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function maintenanceStaffStats($userId)
    {
        // Maintenance staff sees limited stats related to their assigned issues
        $assignedIssues = Issue::where('assigned_to', $userId)->count();
        $openIssues = Issue::where('assigned_to', $userId)->where('status', 'Open')->count();
        $inProgressIssues = Issue::where('assigned_to', $userId)->where('status', 'In Progress')->count();
        $resolvedIssues = Issue::where('assigned_to', $userId)->where('status', 'Resolved')->count();
        
        return response()->json([
            'total_properties' => 0,
            'total_units' => 0,
            'occupied_units' => 0,
            'vacant_units' => 0,
            'occupancy_rate' => 0,
            'active_tenants' => 0,
            'active_leases' => 0,
            'total_revenue' => 0,
            'outstanding_payments' => 0,
            'pending_issues' => $openIssues + $inProgressIssues,
            'assigned_issues' => $assignedIssues,
            'open_issues' => $openIssues,
            'in_progress_issues' => $inProgressIssues,
            'resolved_issues' => $resolvedIssues,
        ]);
    }
    
    private function superAdminStats()
    {
        try {
            // Total properties
            $totalProperties = Property::count();
            
            // Total units
            $totalUnits = Unit::count();
            $occupiedUnits = Unit::where('status', 'Occupied')->count();
            $vacantUnits = Unit::where('status', 'Vacant')->count();
            $occupancyRate = $totalUnits > 0 ? ($occupiedUnits / $totalUnits) * 100 : 0;
            
            // Active tenants (users with role tenant who have active leases)
            $activeTenants = Lease::where('status', 'Active')
                ->distinct('tenant_id')
                ->count('tenant_id');
            
            // Active leases
            $activeLeases = Lease::where('status', 'Active')->count();
            
            // Expiring leases (within 30 days)
            $expiringLeases = Lease::where('status', 'Active')
                ->whereDate('end_date', '<=', now()->addDays(30))
                ->whereDate('end_date', '>=', now())
                ->count();
            
            // Revenue calculations
            $totalRevenue = Payment::where('status', 'Paid')->sum('amount') ?? 0;
            $activeRent = Lease::where('status', 'Active')->sum('rent_amount') ?? 0;
            $paidTotal = Payment::where('status', 'Paid')->sum('amount') ?? 0;
            $outstandingPayments = max(0, $activeRent - $paidTotal);
            
            // Pending maintenance
            $pendingIssues = Issue::whereIn('status', ['Open', 'In Progress'])->count();
            
            return response()->json([
                'total_properties' => $totalProperties,
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'vacant_units' => $vacantUnits,
                'occupancy_rate' => round($occupancyRate, 2),
                'active_tenants' => $activeTenants,
                'active_leases' => $activeLeases,
                'expiring_leases' => $expiringLeases,
                'total_revenue' => $totalRevenue,
                'outstanding_payments' => $outstandingPayments,
                'pending_issues' => $pendingIssues,
            ]);
        } catch (\Exception $e) {
            // Return default values if there's an error
            return response()->json([
                'total_properties' => 0,
                'total_units' => 0,
                'occupied_units' => 0,
                'vacant_units' => 0,
                'occupancy_rate' => 0,
                'active_tenants' => 0,
                'active_leases' => 0,
                'expiring_leases' => 0,
                'total_revenue' => 0,
                'outstanding_payments' => 0,
                'pending_issues' => 0,
                'error_message' => 'Database may be empty or tables not migrated',
            ]);
        }
    }
    
    private function landlordStats($userId)
    {
        // Get landlord's properties
        $properties = Property::where('landlord_id', $userId)->pluck('id');
        
        if ($properties->isEmpty()) {
            return response()->json([
                'total_properties' => 0,
                'total_units' => 0,
                'occupied_units' => 0,
                'vacant_units' => 0,
                'occupancy_rate' => 0,
                'active_tenants' => 0,
                'active_leases' => 0,
                'total_revenue' => 0,
                'outstanding_payments' => 0,
                'pending_issues' => 0,
            ]);
        }
        
        // Total properties
        $totalProperties = $properties->count();
        
        // Units for landlord's properties
        $totalUnits = Unit::whereIn('property_id', $properties)->count();
        $occupiedUnits = Unit::whereIn('property_id', $properties)
            ->where('status', 'Occupied')->count();
        $vacantUnits = Unit::whereIn('property_id', $properties)
            ->where('status', 'Vacant')->count();
        $occupancyRate = $totalUnits > 0 ? ($occupiedUnits / $totalUnits) * 100 : 0;
        
        // Active tenants in landlord's properties
        $unitIds = Unit::whereIn('property_id', $properties)->pluck('id');
        $activeTenants = Lease::whereIn('unit_id', $unitIds)
            ->where('status', 'Active')
            ->distinct('tenant_id')
            ->count('tenant_id');
        
        // Active leases
        $activeLeases = Lease::whereIn('unit_id', $unitIds)
            ->where('status', 'Active')->count();
        
        // Revenue for landlord's properties
        $totalRevenue = Payment::whereIn('property_id', $properties)
            ->where('status', 'Paid')->sum('amount');
        
        // Pending issues
        $pendingIssues = Issue::whereIn('property_id', $properties)
            ->whereIn('status', ['Open', 'In Progress'])->count();
        
        return response()->json([
            'total_properties' => $totalProperties,
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'vacant_units' => $vacantUnits,
            'occupancy_rate' => round($occupancyRate, 2),
            'active_tenants' => $activeTenants,
            'active_leases' => $activeLeases,
            'total_revenue' => $totalRevenue,
            'outstanding_payments' => 0,
            'pending_issues' => $pendingIssues,
        ]);
    }
    
    private function tenantStats($userId)
    {
        // Get the tenant user to access wallet_balance
        $tenant = User::find($userId);
        
        // Get tenant's active lease
        $lease = Lease::where('tenant_id', $userId)
            ->where('status', 'Active')
            ->with(['unit.property'])
            ->first();
        
        // Check if rent has been paid (Relaxed check: Any paid rent allows other payments)
        $rentPaid = Payment::where('tenant_id', $userId)
            ->where('type', 'Rent')
            ->where('status', 'Paid')
            ->exists();

        // Payment history (Global for tenant, not just lease-bound)
        $payments = Payment::where('tenant_id', $userId)
            ->with(['unit.property']) // Eager load for context recovery
            ->orderBy('payment_date', 'desc')
            ->get();
        
        $totalPaid = $payments->where('status', 'Paid')->sum('amount');
        $lastPayment = $payments->first();

        $response = [
            'has_active_lease' => false,
            'rent_paid' => $rentPaid,
            'wallet_balance' => $tenant->wallet_balance ?? 0,
            'payments' => [
                'total_paid' => $totalPaid,
                'last_payment' => $lastPayment,
                'payment_history' => $payments,
            ],
        ];

        if ($lease) {
            // Get all active leases for the tenant to show multiple units if applicable
            $allLeases = Lease::where('tenant_id', $userId)
                ->where('status', 'Active')
                ->with(['unit.property'])
                ->get();
            
            $lease = $allLeases->first();
            $daysUntilExpiration = now()->diffInDays($lease->end_date, false);
            
            $response['has_active_lease'] = true;
            $response['property'] = [
                'id' => $lease->unit->property->id,
                'name' => $lease->unit->property->name,
                'address' => $lease->unit->property->address,
            ];
            
            // Collect all units from all active leases
            $response['units'] = $allLeases->map(function($l) {
                return [
                    'id' => $l->unit->id,
                    'unit_number' => $l->unit->unit_number,
                    'rent_amount' => $l->rent_amount,
                ];
            });
            
            $response['unit'] = [
                'id' => $lease->unit->id,
                'unit_number' => $allLeases->map(function($l) {
                    return str_replace('Unit ', '', $l->unit->unit_number);
                })->implode(', '),
                'rent_amount' => $allLeases->sum('rent_amount'),
            ];
            
            $response['lease'] = [
                'start_date' => $lease->start_date,
                'end_date' => $lease->end_date,
                'status' => $lease->status,
                'days_until_expiration' => $daysUntilExpiration,
            ];
        } elseif ($rentPaid) {
            // Fallback: Populate details from latest approved Rent payment
            $allRentPayments = $payments->where('type', 'Rent')->where('status', 'Paid');
            $latestRent = $allRentPayments->first();
            
            if ($latestRent && $latestRent->unit && $latestRent->unit->property) {
                // Check if there are multiple units in the latest payment or multiple payments
                $unitIds = [];
                $occupiedUnits = Unit::where('tenant_id', $userId)->where('status', 'Occupied')->with('property')->get();
                
                if ($occupiedUnits->isNotEmpty()) {
                    $response['has_active_lease'] = true;
                    $firstUnit = $occupiedUnits->first();
                    $response['property'] = [
                        'id' => $firstUnit->property->id,
                        'name' => $firstUnit->property->name,
                        'address' => $firstUnit->property->address,
                    ];
                    $response['units'] = $occupiedUnits->map(function($u) {
                        return [
                            'id' => $u->id,
                            'unit_number' => $u->unit_number,
                            'rent_amount' => $u->rent_amount,
                        ];
                    });
                    $response['unit'] = [
                        'id' => $firstUnit->id,
                        'unit_number' => $occupiedUnits->map(function($u) {
                            return str_replace('Unit ', '', $u->unit_number);
                        })->implode(', '),
                        'rent_amount' => $occupiedUnits->sum('rent_amount'),
                    ];
                    $paymentDate = \Carbon\Carbon::parse($latestRent->payment_date);
                    $endDate = $paymentDate->copy()->addDays(365);
                    $response['lease'] = [
                        'start_date' => $paymentDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'status' => 'Active (Rent Paid)',
                        'days_until_expiration' => now()->diffInDays($endDate, false),
                    ];
                }
            } else {
                 $response['message'] = 'No active lease found';
            }
        } else {
             $response['message'] = 'No active lease found';
        }

        return response()->json($response);
    }
}
