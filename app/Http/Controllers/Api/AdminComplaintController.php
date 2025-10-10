<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminComplaintController extends Controller
{
    /**
     * Get all complaints with filtering and pagination
     */
    public function getAllComplaints(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 20);
            $status = $request->get('status'); // pending, resolved, rejected
            $priority = $request->get('priority'); // low, medium, high, urgent
            $search = $request->get('search');

            $query = Complaint::with(['user', 'assignedAdmin'])
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($status && in_array($status, ['pending', 'resolved', 'rejected'])) {
                if ($status === 'pending') {
                    $query->where('is_resolved', false);
                } elseif ($status === 'resolved') {
                    $query->where('is_resolved', true);
                }
            }

            // Filter by priority (using severity_level)
            if ($priority && in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
                $query->where('severity_level', $priority);
            }

            // Search functionality
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
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
                        'total_complaints' => Complaint::count(),
                        'pending_complaints' => Complaint::where('is_resolved', false)->count(),
                        'resolved_complaints' => Complaint::where('is_resolved', true)->count(),
                        'urgent_complaints' => Complaint::where('severity_level', 'urgent')->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve complaints',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific complaint details
     */
    public function getComplaintById(Request $request, $id)
    {
        try {
            $complaint = Complaint::with(['user', 'assignedAdmin'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'complaint' => [
                        'id' => $complaint->id,
                        'uuid' => $complaint->uuid,
                        'description' => $complaint->description,
                        'status' => $complaint->is_resolved ? 'resolved' : 'pending',
                        'priority' => $complaint->severity_level,
                        'category' => $complaint->contact_type,
                        'created_at' => $complaint->created_at,
                        'updated_at' => $complaint->updated_at,
                        'resolved_at' => $complaint->resolved_at,
                        'admin_response' => $complaint->admin_response,
                        'is_active' => $complaint->is_active,
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
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Complaint not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update complaint status and add admin response
     */
    public function updateComplaintStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:pending,resolved,rejected'],
            'admin_response' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:low,medium,high,urgent']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $complaint = Complaint::findOrFail($id);
            $admin = $request->user();

            $updateData = [];

            if ($request->status === 'resolved') {
                $updateData['is_resolved'] = true;
                $updateData['resolved_at'] = now();
            } elseif ($request->status === 'pending') {
                $updateData['is_resolved'] = false;
                $updateData['resolved_at'] = null;
            }

            if ($request->admin_response) {
                $updateData['admin_response'] = $request->admin_response;
            }

            if ($request->priority) {
                $updateData['severity_level'] = $request->priority;
            }

            // Assign to admin if not already assigned
            if (!$complaint->assigned_to) {
                $updateData['assigned_to'] = $admin->uuid;
            }

            $complaint->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Complaint status updated successfully',
                'data' => [
                    'complaint' => [
                        'id' => $complaint->id,
                        'status' => $complaint->is_resolved ? 'resolved' : 'pending',
                        'priority' => $complaint->severity_level,
                        'admin_response' => $complaint->admin_response,
                        'assigned_to' => $complaint->assigned_to,
                        'resolved_at' => $complaint->resolved_at,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update complaint',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete complaint
     */
    public function deleteComplaint(Request $request, $id)
    {
        try {
            $complaint = Complaint::findOrFail($id);
            $complaint->delete();

            return response()->json([
                'success' => true,
                'message' => 'Complaint deleted successfully',
                'data' => [
                    'deleted_complaint' => [
                        'id' => $complaint->id,
                        'description' => $complaint->description,
                        'status' => $complaint->is_resolved ? 'resolved' : 'pending',
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete complaint',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get complaint statistics and analytics
     */
    public function getComplaintStats(Request $request)
    {
        try {
            $stats = [
                'total_complaints' => Complaint::count(),
                'pending_complaints' => Complaint::where('is_resolved', false)->count(),
                'resolved_complaints' => Complaint::where('is_resolved', true)->count(),
                'urgent_complaints' => Complaint::where('severity_level', 'urgent')->count(),
                'high_priority_complaints' => Complaint::where('severity_level', 'high')->count(),
                'complaints_this_month' => Complaint::whereMonth('created_at', now()->month)->count(),
                'complaints_last_month' => Complaint::whereMonth('created_at', now()->subMonth()->month)->count(),
                'average_resolution_time' => $this->getAverageResolutionTime(),
                'complaints_by_category' => Complaint::selectRaw('contact_type, COUNT(*) as count')
                    ->groupBy('contact_type')
                    ->get()
                    ->pluck('count', 'contact_type'),
                'complaints_by_status' => [
                    'pending' => Complaint::where('is_resolved', false)->count(),
                    'resolved' => Complaint::where('is_resolved', true)->count(),
                ],
                'complaints_by_priority' => Complaint::selectRaw('severity_level, COUNT(*) as count')
                    ->groupBy('severity_level')
                    ->get()
                    ->pluck('count', 'severity_level'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get complaint statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update complaint statuses
     */
    public function bulkUpdateComplaints(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'complaint_ids' => ['required', 'array', 'min:1'],
            'complaint_ids.*' => ['integer', 'exists:complaints,id'],
            'status' => ['required', 'in:pending,resolved,rejected'],
            'admin_response' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = $request->user();
            $complaintIds = $request->complaint_ids;
            $status = $request->status;
            $adminResponse = $request->admin_response;

            $updateData = [];

            if ($status === 'resolved') {
                $updateData['is_resolved'] = true;
                $updateData['resolved_at'] = now();
            } elseif ($status === 'pending') {
                $updateData['is_resolved'] = false;
                $updateData['resolved_at'] = null;
            }

            if ($adminResponse) {
                $updateData['admin_response'] = $adminResponse;
            }

            // Assign to admin if not already assigned
            $updateData['assigned_to'] = $admin->uuid;

            $updatedCount = Complaint::whereIn('id', $complaintIds)->update($updateData);

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} complaints",
                'data' => [
                    'updated_count' => $updatedCount,
                    'complaint_ids' => $complaintIds,
                    'new_status' => $status,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update complaints',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate average resolution time for resolved complaints
     */
    private function getAverageResolutionTime()
    {
        $resolvedComplaints = Complaint::where('is_resolved', true)
            ->whereNotNull('resolved_at')
            ->get();

        if ($resolvedComplaints->isEmpty()) {
            return 0;
        }

        $totalHours = $resolvedComplaints->sum(function($complaint) {
            return $complaint->created_at->diffInHours($complaint->resolved_at);
        });

        return round($totalHours / $resolvedComplaints->count(), 2);
    }
}
