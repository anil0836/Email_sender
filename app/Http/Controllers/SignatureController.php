<?php

namespace App\Http\Controllers;

use App\Models\UserSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SignatureController extends Controller
{
    /**
     * Display a listing of the user's signatures.
     */
    public function index(Request $request)
    {
        $userId = session('user_id') ?: Auth::id();
        $signatures = UserSignature::where('user_id', $userId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('signatures.index', compact('signatures'));
    }

    /**
     * Store a newly created signature.
     */
    public function store(Request $request)
    {
        $userId = session('user_id') ?: Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sender_name' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'content' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default');

        // If this is user's first signature or marked as default, make it default
        $existingCount = UserSignature::where('user_id', $userId)->count();
        if ($isDefault || $existingCount === 0) {
            $isDefault = true;
            UserSignature::where('user_id', $userId)->update(['is_default' => false]);
        }

        $signature = UserSignature::create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'sender_name' => $validated['sender_name'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'website' => $validated['website'] ?? null,
            'address' => $validated['address'] ?? null,
            'content' => $validated['content'] ?? '',
            'is_default' => $isDefault,
            'is_active' => true,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Signature created successfully!',
                'signature' => $signature,
                'rendered_html' => $signature->renderHtml(),
            ]);
        }

        return redirect()->route('signatures.index')->with('success', 'Signature created successfully!');
    }

    /**
     * Display the specified signature.
     */
    public function show(int $id)
    {
        $userId = session('user_id') ?: Auth::id();
        $signature = UserSignature::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'signature' => $signature,
            'rendered_html' => $signature->renderHtml(),
        ]);
    }

    /**
     * Update the specified signature.
     */
    public function update(Request $request, int $id)
    {
        $userId = session('user_id') ?: Auth::id();
        $signature = UserSignature::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sender_name' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'content' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default');
        if ($isDefault) {
            UserSignature::where('user_id', $userId)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $signature->update([
            'name' => $validated['name'],
            'sender_name' => $validated['sender_name'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'website' => $validated['website'] ?? null,
            'address' => $validated['address'] ?? null,
            'content' => $validated['content'] ?? ($signature->content ?? ''),
            'is_default' => $isDefault ? true : $signature->is_default,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Signature updated successfully!',
                'signature' => $signature,
                'rendered_html' => $signature->renderHtml(),
            ]);
        }

        return redirect()->route('signatures.index')->with('success', 'Signature updated successfully!');
    }

    /**
     * Remove the specified signature.
     */
    public function destroy(Request $request, int $id)
    {
        $userId = session('user_id') ?: Auth::id();
        $signature = UserSignature::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $wasDefault = $signature->is_default;
        $signature->delete();

        // If was default, promote another signature to default if exists
        if ($wasDefault) {
            $nextDefault = UserSignature::where('user_id', $userId)->orderBy('created_at', 'desc')->first();
            if ($nextDefault) {
                $nextDefault->update(['is_default' => true]);
            }
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Signature deleted successfully.',
            ]);
        }

        return redirect()->route('signatures.index')->with('success', 'Signature deleted successfully.');
    }

    /**
     * Mark a signature as the default for the user.
     */
    public function setDefault(Request $request, int $id)
    {
        $userId = session('user_id') ?: Auth::id();
        $signature = UserSignature::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        UserSignature::where('user_id', $userId)->update(['is_default' => false]);
        $signature->update(['is_default' => true]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Default signature updated.',
            ]);
        }

        return redirect()->route('signatures.index')->with('success', 'Default signature updated.');
    }
}
