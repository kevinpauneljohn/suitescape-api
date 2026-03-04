<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PackageInquiryRequest;
use App\Models\Package;
use App\Models\PackageInquiry;
use Illuminate\Http\Request;

class PackageInquiryController extends Controller
{
    /**
     * Store a new package inquiry.
     */
    public function store(PackageInquiryRequest $request)
    {
        $validated = $request->validated();

        // Add user_id if authenticated
        if (auth()->check()) {
            $validated['user_id'] = auth()->id();
        }

        $inquiry = PackageInquiry::create($validated);

        // Load the package relationship
        $inquiry->load('package');

        return response()->json([
            'success' => true,
            'message' => 'Your inquiry has been submitted successfully. We will get back to you soon!',
            'data' => $inquiry,
        ], 201);
    }

    /**
     * Get all inquiries for the authenticated user.
     */
    public function userInquiries(Request $request)
    {
        $inquiries = PackageInquiry::where('user_id', auth()->id())
            ->with('package')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($inquiries);
    }

    /**
     * Get all inquiries (admin only).
     */
    public function index(Request $request)
    {
        $query = PackageInquiry::with(['package', 'user']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by package
        if ($request->has('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        $inquiries = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($inquiries);
    }

    /**
     * Get a specific inquiry.
     */
    public function show(PackageInquiry $inquiry)
    {
        $inquiry->load(['package', 'user']);

        // Mark as read if not already
        if (!$inquiry->read_at) {
            $inquiry->markAsRead();
        }

        return response()->json([
            'data' => $inquiry,
        ]);
    }

    /**
     * Update inquiry status (admin only).
     */
    public function updateStatus(Request $request, PackageInquiry $inquiry)
    {
        $request->validate([
            'status' => ['required', 'in:pending,read,replied,closed'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $inquiry->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ]);

        if ($request->status === 'replied' && !$inquiry->replied_at) {
            $inquiry->markAsReplied();
        }

        return response()->json([
            'success' => true,
            'message' => 'Inquiry status updated successfully.',
            'data' => $inquiry,
        ]);
    }
}
