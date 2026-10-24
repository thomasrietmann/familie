<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FamilyPublicLinkController extends Controller
{
    public function show(Family $family): View
    {
        $this->authorize('manageAccess', $family);

        return view('families.public-link', compact('family'));
    }

    public function enable(Request $request, Family $family): RedirectResponse
    {
        $this->authorize('manageAccess', $family);
        $request->validate(['current_password' => ['required', 'string', 'max:1024', 'current_password']]);

        if (! $family->public_token) {
            $family->generatePublicToken();
        } else {
            $family->update(['public_token_enabled' => true]);
        }

        return back()->with('status', 'Secret Link wurde aktiviert.');
    }

    public function regenerate(Request $request, Family $family): RedirectResponse
    {
        $this->authorize('manageAccess', $family);
        $request->validate(['current_password' => ['required', 'string', 'max:1024', 'current_password']]);
        $family->generatePublicToken();

        return back()->with('status', 'Secret Link wurde neu generiert.');
    }

    public function disable(Family $family): RedirectResponse
    {
        $this->authorize('manageAccess', $family);
        $family->disablePublicToken();

        return back()->with('status', 'Secret Link wurde deaktiviert.');
    }

    public function enableDashboard(Request $request, Family $family): RedirectResponse
    {
        $this->authorize('manageAccess', $family);
        $request->validate(['current_password' => ['required', 'string', 'max:1024', 'current_password']]);

        if (! $family->dashboard_public_token) {
            $family->generateDashboardPublicToken();
        } else {
            $family->update(['dashboard_public_token_enabled' => true]);
        }

        return back()->with('status', 'Dashboard Secret Link wurde aktiviert.');
    }

    public function regenerateDashboard(Request $request, Family $family): RedirectResponse
    {
        $this->authorize('manageAccess', $family);
        $request->validate(['current_password' => ['required', 'string', 'max:1024', 'current_password']]);
        $family->generateDashboardPublicToken();

        return back()->with('status', 'Dashboard Secret Link wurde neu generiert.');
    }

    public function disableDashboard(Family $family): RedirectResponse
    {
        $this->authorize('manageAccess', $family);
        $family->disableDashboardPublicToken();

        return back()->with('status', 'Dashboard Secret Link wurde deaktiviert.');
    }
}
