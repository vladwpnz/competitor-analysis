<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalysisController extends Controller
{
    public function home(): View
    {
        return view('home');
    }

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'website' => ['required', 'url', 'max:2048'],
            'google_business' => ['required', 'string', 'max:255'],
        ]);

        session([
            'analysis.website' => $validated['website'],
            'analysis.google_business' => $validated['google_business'],
        ]);

        return redirect()->route('competitors');
    }

    public function competitors(): View|RedirectResponse
    {
        $website = session('analysis.website');
        $googleBusiness = session('analysis.google_business');

        if (!$website || !$googleBusiness) {
            return redirect()->route('home');
        }

        return view('competitors', [
            'website' => $website,
            'googleBusiness' => $googleBusiness,
        ]);
    }
}