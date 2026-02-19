<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamController extends Controller
{
    /**
     * Display the organazitaions management page.
     */
    public function organizationIndex(Request $request)
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;
        
        // For UI demonstration, we was mocking organazitaions.
        // Now using empty list as requested.
        $organizations = [];

        return Inertia::render('Organization/Index', [
            'subscription' => $subscription,
            'organizations' => $organizations,
        ]);
    }

    /**
     * Display the team management page for a specific organization.
     */
    public function index(Request $request, $organizationId)
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;
        
        $organization = \App\Models\Organization::findOrFail($organizationId);

        // For UI preview, we'll return an empty members list or just the current user
        // as requested to remove "fucking hardcoded members".
        $members = [
            [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'Owner',
                'is_you' => true,
                'enabled_mfa' => false,
                'avatar' => null,
            ]
        ];

        return Inertia::render('Team', [
            'subscription' => $subscription,
            'organization_id' => $organizationId,
            'organization_name' => $organization->name,
            'members' => $members,
        ]);
    }
}
