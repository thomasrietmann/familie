<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FamilyPublicLinkController extends Controller
{
    public function show(Family $family): View
    {
        $this->authorize('update', $family);

        return view('families.public-link', compact('family'));
    }

    public function enable(Family $family): RedirectResponse
    {
        $this->authorize('update', $family);

        if (! $family->public_token) {
            $family->generatePublicToken();
        } else {
            $family->update(['public_token_enabled' => true]);
        }

        return back()->with('status', 'Secret Link wurde aktiviert.');
    }

    public function regenerate(Family $family): RedirectResponse
    {
        $this->authorize('update', $family);
        $family->generatePublicToken();

        return back()->with('status', 'Secret Link wurde neu generiert.');
    }

    public function disable(Family $family): RedirectResponse
    {
        $this->authorize('update', $family);
        $family->disablePublicToken();

        return back()->with('status', 'Secret Link wurde deaktiviert.');
    }
}
