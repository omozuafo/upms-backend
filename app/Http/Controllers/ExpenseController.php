<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with('property');

        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        return response()->json($query->orderBy('date', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|string|exists:properties,id',
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'category' => 'required|string',
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $expense = Expense::create($request->all());

        return response()->json($expense, 201);
    }

    public function show($id)
    {
        $expense = Expense::with('property')->find($id);
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
}
