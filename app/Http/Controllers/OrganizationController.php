<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    /**
     * Display the roadmap page.
     */
    public function roadmap()
    {
        return Inertia::render('Organization/Roadmap');
    }

    /**
     * Display a listing of the organizations.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;
        
        $organizations = $user->organizations()->get()->map(function ($org) {
            return [
                'id' => $org->id,
                'name' => $org->name,
                'team_count' => 0, // Placeholder for now
                'created_at' => $org->created_at->format('M d, Y'),
            ];
        });

        return Inertia::render('Organization/Index', [
            'subscription' => $subscription,
            'organizations' => $organizations,
        ]);
    }

    /**
     * Store a newly created organization.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();

        $organization = Organization::create([
            'name' => $request->name,
            'owner_id' => $user->id,
        ]);

        // Attach the owner as a full_auditor member
        $organization->members()->attach($user->id, [
            'role' => 'full_auditor',
            'email' => $user->email,
            'name' => $user->name,
        ]);

        // Set as active context automatically
        $user->update(['active_organization_id' => $organization->id]);

        return redirect()->route('organizations.index');
    }

    /**
     * Switch the active organization context.
     */
    public function switch(Request $request)
    {
        $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        $user = $request->user();

        // If shifting back to personal (null)
        if (!$request->organization_id) {
            $user->update(['active_organization_id' => null]);
            return back();
        }

        // Verify user is a member of this organization
        $isMember = $user->organizations()->where('organization_id', $request->organization_id)->exists();

        if ($isMember) {
            $user->update(['active_organization_id' => $request->organization_id]);
        }

        return back();
    }
}
