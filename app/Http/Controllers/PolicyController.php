<?php

namespace App\Http\Controllers;

use App\Models\PolicyPage;
use App\Models\Setting;

class PolicyController extends Controller
{
    public function show(string $slug)
    {
        $allowedSlugs = ['privacy', 'terms', 'delivery', 'returns', 'contact'];
        abort_unless(in_array($slug, $allowedSlugs, true), 404);
        $policy = PolicyPage::where('slug', $slug)->where('is_published', true)->first();

        abort_if(! $policy, 404);

        return store_page('Policies/Show', [
            'slug' => $slug,
            'policy' => $policy,
            'contactEmail' => Setting::get('contact_email', ''),
            'contactPhone' => Setting::get('contact_phone', ''),
        ]);
    }
}
