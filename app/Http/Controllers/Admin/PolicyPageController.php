<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PolicyPage;
use Illuminate\Http\Request;

class PolicyPageController extends Controller
{
    public function index()
    {
        return inertia('Admin/Policies/Index', ['policies' => PolicyPage::orderBy('title')->get()]);
    }

    public function update(Request $request, PolicyPage $policyPage)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:30000'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
        $policyPage->update($validated + ['is_published' => $request->boolean('is_published')]);
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'policy.updated',
            'subject_type' => PolicyPage::class,
            'subject_id' => $policyPage->id,
            'metadata' => ['slug' => $policyPage->slug, 'is_published' => $policyPage->is_published],
        ]);

        return back()->with('success', 'Policy page saved.');
    }
}
