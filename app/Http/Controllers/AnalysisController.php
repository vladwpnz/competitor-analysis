<?php

namespace App\Http\Controllers;

use App\Services\WebsiteScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AnalysisController extends Controller
{
    public function home(): View
    {
        return view('home');
    }

    public function start(
        Request $request,
        WebsiteScanner $websiteScanner
    ): RedirectResponse {
        $validated = $request->validate([
            'website' => ['required', 'url', 'max:2048'],
            'google_business' => ['required', 'string', 'max:255'],
        ]);

        try {
            $websiteScan = $websiteScanner->scan(
                $validated['website']
            );
        } catch (RuntimeException $exception) {
            return back()
                ->withErrors([
                    'website' => $exception->getMessage(),
                ])
                ->withInput();
        }

        session([
            'analysis.website' => $validated['website'],
            'analysis.google_business' => $validated['google_business'],
            'analysis.website_scan' => $websiteScan,
        ]);

        return redirect()->route('competitors');
    }

    public function competitors(): View|RedirectResponse
    {
        $website = session('analysis.website');
        $googleBusiness = session('analysis.google_business');
        $websiteScan = session('analysis.website_scan');

        if (
            ! $website
            || ! $googleBusiness
            || ! is_array($websiteScan)
        ) {
            return redirect()->route('home');
        }

        return view('competitors', [
            'website' => $website,
            'googleBusiness' => $googleBusiness,
            'websiteScan' => $websiteScan,
        ]);
    }
}