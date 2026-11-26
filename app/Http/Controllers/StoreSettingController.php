<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreSettingController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('settings.store', ['settings' => StoreSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:150'],
            'store_address' => ['required', 'string', 'max:1000'],
        ]);

        StoreSetting::current()->update($validated);

        return to_route('settings.store.edit')->with('status', 'Company store details saved.');
    }
}
