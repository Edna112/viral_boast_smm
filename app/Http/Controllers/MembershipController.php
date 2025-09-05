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
        $membership = Membership::create($request->all());
        return response()->json($membership, 201);
    }

    public function update(Request $request, $id)
    {
        $membership = Membership::find($id);
        if (!$membership) {
            return response()->json(['message' => 'Membership not found'], 404);
        }
        $membership->update($request->all());
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
