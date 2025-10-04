<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Membership;

class MembershipController extends Controller
{
    public function index()
    {
        $memberships = Membership::all();
        return response()->json($memberships);
    }

    public function show($id)
    {
        $membership = Membership::find($id);
        if (!$membership) {
            return response()->json(['message' => 'Membership not found'], 404);
        }
        return response()->json($membership);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'membership_name' => 'required|string|max:50|unique:membership,membership_name',
            'description' => 'required|string|max:500',
            'tasks_per_day' => 'required|integer|min:1',
            'max_tasks' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'benefit_amount_per_task' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $membership = Membership::create($data);
        return response()->json($membership, 201);
    }

    public function update(Request $request, $id)
    {
        $membership = Membership::find($id);
        if (!$membership) {
            return response()->json(['message' => 'Membership not found'], 404);
        }

        $data = $request->validate([
            'membership_name' => 'sometimes|string|max:50|unique:membership,membership_name,' . $id,
            'description' => 'sometimes|string|max:500',
            'tasks_per_day' => 'sometimes|integer|min:1',
            'max_tasks' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'benefit_amount_per_task' => 'sometimes|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $membership->update($data);
        return response()->json($membership);
    }

    public function destroy($id)
    {
        $membership = Membership::find($id);
        if (!$membership) {
            return response()->json(['message' => 'Membership not found'], 404);
        }
        $membership->delete();
        return response()->json(['message' => 'Membership deleted']);
    }
}
