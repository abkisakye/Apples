<?php

namespace App\Http\Controllers;

use App\Support\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccessPreviewController extends Controller
{
    public function update(Request $request, AccessService $access): RedirectResponse
    {
        abort_unless(app()->environment('local') && $access->hasRole('admin'), 403);

        $validated = $request->validate([
            'preview_role' => ['required', 'in:'.implode(',', $access->roles())],
        ]);

        $request->session()->put('preview_role', $validated['preview_role']);

        return back()->with('status', 'Role preview changed to '.ucfirst(str_replace('_', ' ', $validated['preview_role'])).'.');
    }
}
