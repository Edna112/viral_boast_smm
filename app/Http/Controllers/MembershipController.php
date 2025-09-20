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
            'membership_name' => 'required|string|max:255|unique:membership,membership_name',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'benefits' => 'nullable|numeric|min:0',
            'tasks_per_day' => 'required|numeric|min:1',
            'max_tasks' => 'required|numeric|min:1',
            'task_link' => 'nullable|url',
            'reward_multiplier' => 'nullable|numeric|min:0.1|max:10',
            'priority_level' => 'nullable|integer|min:1|max:10',
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
            'membership_name' => 'sometimes|string|max:255|unique:membership,membership_name,' . $id,
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'benefits' => 'nullable|numeric|min:0',
            'tasks_per_day' => 'sometimes|numeric|min:1',
            'max_tasks' => 'sometimes|numeric|min:1',
            'task_link' => 'nullable|url',
            'reward_multiplier' => 'nullable|numeric|min:0.1|max:10',
            'priority_level' => 'nullable|integer|min:1|max:10',
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
