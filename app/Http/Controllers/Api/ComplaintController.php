<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    /**
     * Submit a new complaint
     */
    public function submitComplaint(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'contact_type' => ['required', 'in:email,phone'],
            'contact' => ['required', 'string', 'max:255'],
            'severity_level' => ['required', 'in:low,medium,high'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate contact format based on type
        if ($request->contact_type === 'email' && !filter_var($request->contact, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email format',
                'error' => 'InvalidEmail'
            ], 422);
        }

        if ($request->contact_type === 'phone' && !preg_match('/^[\+]?[1-9][\d]{0,15}$/', $request->contact)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone format',
                'error' => 'InvalidPhone'
            ], 422);
        }

        try {
            $complaint = Complaint::create([
                'user_uuid' => $user->uuid,
                'contact_type' => $request->contact_type,
                'contact' => $request->contact,
                'severity_level' => $request->severity_level,
                'description' => $request->description,
                'is_active' => true,
                'is_resolved' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Complaint submitted successfully',
                'data' => [
                    'complaint' => [
                        'id' => $complaint->id,
                        'uuid' => $complaint->uuid,
                        'user_uuid' => $complaint->user_uuid,
                        'contact_type' => $complaint->contact_type,
                        'contact' => $complaint->contact,
                        'severity_level' => $complaint->severity_level,
                        'description' => $complaint->description,
                        'admin_response' => $complaint->admin_response,
                        'is_active' => $complaint->is_active,
                        'is_resolved' => $complaint->is_resolved,
                        'assigned_to' => $complaint->assigned_to,
                        'resolved_at' => $complaint->resolved_at,
                        'created_at' => $complaint->created_at,
                        'updated_at' => $complaint->updated_at,
                        'time_since_created' => $complaint->time_since_created,
                        'time_since_resolved' => $complaint->time_since_resolved,
                        'formatted_created_date' => $complaint->formatted_created_date,
                        'formatted_resolved_date' => $complaint->formatted_resolved_date,
                        'severity_color' => $complaint->severity_color,
                        'status_text' => $complaint->status_text,
                        'priority_score' => $complaint->priority_score,
                        'is_anonymous' => $complaint->isAnonymous(),
                        'is_assigned' => $complaint->isAssigned(),
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit complaint',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's complaints
     */
    public function getUserComplaints(Request $request)
    {
        $user = $request->user();
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $status = $request->get('status'); // active, inactive, resolved, unresolved
        $severity = $request->get('severity'); // low, medium, high

        $query = Complaint::with(['assignedAdmin'])
            ->where('user_uuid', $user->uuid)
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->inactive();
        } elseif ($status === 'resolved') {
            $query->resolved();
        } elseif ($status === 'unresolved') {
            $query->unresolved();
        }

        if ($severity && in_array($severity, ['low', 'medium', 'high'])) {
            $query->bySeverity($severity);
        }

        $complaints = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'complaints' => $complaints->items(),
                'pagination' => [
                    'current_page' => $complaints->currentPage(),
                    'last_page' => $complaints->lastPage(),
                    'per_page' => $complaints->perPage(),
                    'total' => $complaints->total(),
                    'has_more' => $complaints->hasMorePages(),
                ],
                'summary' => [
                    'total_complaints' => Complaint::where('user_uuid', $user->uuid)->count(),
                    'active_complaints' => Complaint::where('user_uuid', $user->uuid)->active()->count(),
                    'resolved_complaints' => Complaint::where('user_uuid', $user->uuid)->resolved()->count(),
                    'unresolved_complaints' => Complaint::where('user_uuid', $user->uuid)->unresolved()->count(),
                    'high_priority_complaints' => Complaint::where('user_uuid', $user->uuid)->bySeverity('high')->unresolved()->count(),
                ]
            ]
        ]);
    }

    /**
     * Get specific complaint details
     */
    public function getComplaint(Request $request, $id)
    {
        $user = $request->user();

        $complaint = Complaint::with(['user', 'assignedAdmin'])
            ->where('id', $id)
            ->where('user_uuid', $user->uuid)
            ->first();

        if (!$complaint) {
            return response()->json([
                'success' => false,
                'message' => 'Complaint not found',
                'error' => 'ComplaintNotFound'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'complaint' => [
                    'id' => $complaint->id,
                    'uuid' => $complaint->uuid,
                    'user_uuid' => $complaint->user_uuid,
                    'contact_type' => $complaint->contact_type,
                    'contact' => $complaint->contact,
                    'severity_level' => $complaint->severity_level,
                    'description' => $complaint->description,
                    'admin_response' => $complaint->admin_response,
                    'is_active' => $complaint->is_active,
                    'is_resolved' => $complaint->is_resolved,
                    'assigned_to' => $complaint->assigned_to,
                    'resolved_at' => $complaint->resolved_at,
                    'created_at' => $complaint->created_at,
                    'updated_at' => $complaint->updated_at,
                    'time_since_created' => $complaint->time_since_created,
                    'time_since_resolved' => $complaint->time_since_resolved,
                    'formatted_created_date' => $complaint->formatted_created_date,
                    'formatted_resolved_date' => $complaint->formatted_resolved_date,
                    'severity_color' => $complaint->severity_color,
                    'status_text' => $complaint->status_text,
                    'priority_score' => $complaint->priority_score,
                    'is_anonymous' => $complaint->isAnonymous(),
                    'is_assigned' => $complaint->isAssigned(),
                ],
                'user' => $complaint->user ? [
                    'uuid' => $complaint->user->uuid,
                    'name' => $complaint->user->name,
                    'email' => $complaint->user->email,
                    'phone' => $complaint->user->phone,
                ] : null,
                'assigned_admin' => $complaint->assignedAdmin ? [
                    'uuid' => $complaint->assignedAdmin->uuid,
                    'name' => $complaint->assignedAdmin->name,
                    'email' => $complaint->assignedAdmin->email,
                ] : null,
            ]
        ]);
    }

    /**
     * Get complaint statistics for user
     */
    public function getComplaintStats(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_complaints' => Complaint::where('user_uuid', $user->uuid)->count(),
            'active_complaints' => Complaint::where('user_uuid', $user->uuid)->active()->count(),
            'resolved_complaints' => Complaint::where('user_uuid', $user->uuid)->resolved()->count(),
            'unresolved_complaints' => Complaint::where('user_uuid', $user->uuid)->unresolved()->count(),
            'high_priority_complaints' => Complaint::where('user_uuid', $user->uuid)->bySeverity('high')->unresolved()->count(),
            'medium_priority_complaints' => Complaint::where('user_uuid', $user->uuid)->bySeverity('medium')->unresolved()->count(),
            'low_priority_complaints' => Complaint::where('user_uuid', $user->uuid)->bySeverity('low')->unresolved()->count(),
            'resolution_rate' => 0,
            'average_resolution_time_hours' => 0,
        ];

        // Calculate resolution rate
        if ($stats['total_complaints'] > 0) {
            $stats['resolution_rate'] = round(($stats['resolved_complaints'] / $stats['total_complaints']) * 100, 2);
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Submit anonymous complaint (no authentication required)
     */
    public function submitAnonymousComplaint(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact_type' => ['required', 'in:email,phone'],
            'contact' => ['required', 'string', 'max:255'],
            'severity_level' => ['required', 'in:low,medium,high'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate contact format based on type
        if ($request->contact_type === 'email' && !filter_var($request->contact, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email format',
                'error' => 'InvalidEmail'
            ], 422);
        }

        if ($request->contact_type === 'phone' && !preg_match('/^[\+]?[1-9][\d]{0,15}$/', $request->contact)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone format',
                'error' => 'InvalidPhone'
            ], 422);
        }

        try {
            $complaint = Complaint::create([
                'user_uuid' => null, // Anonymous complaint
                'contact_type' => $request->contact_type,
                'contact' => $request->contact,
                'severity_level' => $request->severity_level,
                'description' => $request->description,
                'is_active' => true,
                'is_resolved' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Anonymous complaint submitted successfully',
                'data' => [
                    'complaint' => [
                        'id' => $complaint->id,
                        'uuid' => $complaint->uuid,
                        'contact_type' => $complaint->contact_type,
                        'contact' => $complaint->contact,
                        'severity_level' => $complaint->severity_level,
                        'description' => $complaint->description,
                        'is_active' => $complaint->is_active,
                        'is_resolved' => $complaint->is_resolved,
                        'created_at' => $complaint->created_at,
                        'time_since_created' => $complaint->time_since_created,
                        'formatted_created_date' => $complaint->formatted_created_date,
                        'severity_color' => $complaint->severity_color,
                        'status_text' => $complaint->status_text,
                        'is_anonymous' => $complaint->isAnonymous(),
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit complaint',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a complaint
     */
    public function updateComplaint(Request $request, $id)
    {
        $user = $request->user();

        // Find the complaint and ensure it belongs to the user
        $complaint = Complaint::where('id', $id)
            ->where('user_uuid', $user->uuid)
            ->first();

        if (!$complaint) {
            return response()->json([
                'success' => false,
                'message' => 'Complaint not found or you do not have permission to update it',
                'error' => 'ComplaintNotFound'
            ], 404);
        }

        // Check if complaint is still editable (not resolved)
        if ($complaint->is_resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update resolved complaints',
                'error' => 'ComplaintResolved'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'contact_type' => ['sometimes', 'in:email,phone'],
            'contact' => ['sometimes', 'string', 'max:255'],
            'severity_level' => ['sometimes', 'in:low,medium,high'],
            'description' => ['sometimes', 'string', 'min:10', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate contact format if contact_type or contact is being updated
        if ($request->has('contact_type') || $request->has('contact')) {
            $contactType = $request->contact_type ?? $complaint->contact_type;
            $contact = $request->contact ?? $complaint->contact;

            if ($contactType === 'email' && !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email format',
                    'error' => 'InvalidEmail'
                ], 422);
            }

            if ($contactType === 'phone' && !preg_match('/^[\+]?[1-9][\d]{0,15}$/', $contact)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone format',
                    'error' => 'InvalidPhone'
                ], 422);
            }
        }

        try {
            // Get only the fields that were provided in the request
            $updateData = $request->only([
                'contact_type',
                'contact',
                'severity_level',
                'description'
            ]);

            // Update the complaint
            $complaint->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Complaint updated successfully',
                'data' => [
                    'complaint' => [
                        'id' => $complaint->id,
                        'uuid' => $complaint->uuid,
                        'user_uuid' => $complaint->user_uuid,
                        'contact_type' => $complaint->contact_type,
                        'contact' => $complaint->contact,
                        'severity_level' => $complaint->severity_level,
                        'description' => $complaint->description,
                        'admin_response' => $complaint->admin_response,
                        'is_active' => $complaint->is_active,
                        'is_resolved' => $complaint->is_resolved,
                        'assigned_to' => $complaint->assigned_to,
                        'resolved_at' => $complaint->resolved_at,
                        'created_at' => $complaint->created_at,
                        'updated_at' => $complaint->updated_at,
                        'time_since_created' => $complaint->time_since_created,
                        'time_since_resolved' => $complaint->time_since_resolved,
                        'formatted_created_date' => $complaint->formatted_created_date,
                        'formatted_resolved_date' => $complaint->formatted_resolved_date,
                        'severity_color' => $complaint->severity_color,
                        'status_text' => $complaint->status_text,
                        'priority_score' => $complaint->priority_score,
                        'is_anonymous' => $complaint->isAnonymous(),
                        'is_assigned' => $complaint->isAssigned(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Complaint update error: ' . $e->getMessage(), [
                'complaint_id' => $id,
                'user_uuid' => $user->uuid,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update complaint',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
