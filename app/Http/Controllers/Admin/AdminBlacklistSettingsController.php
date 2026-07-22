<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlacklistSetting;
use Illuminate\Http\Request;

class AdminBlacklistSettingsController extends Controller
{
    public function edit()
    {
        $settings = BlacklistSetting::current();

        return view('admin.blacklist.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $unitRule = 'in:seconds,minutes,hours,days';

        $validated = $request->validate([
            'InactivityValue' => 'required|integer|min:1',
            'InactivityUnit' => "required|{$unitRule}",
            'AutoBlacklistEnabled' => 'nullable|boolean',
            'FirstWarningValue' => "required_if:AutoBlacklistEnabled,1|nullable|integer|min:1",
            'FirstWarningUnit' => "required_if:AutoBlacklistEnabled,1|nullable|{$unitRule}",
            'SecondWarningValue' => "required_if:AutoBlacklistEnabled,1|nullable|integer|min:1",
            'SecondWarningUnit' => "required_if:AutoBlacklistEnabled,1|nullable|{$unitRule}",
            'BlacklistAfterSecondWarningValue' => "required_if:AutoBlacklistEnabled,1|nullable|integer|min:1",
            'BlacklistAfterSecondWarningUnit' => "required_if:AutoBlacklistEnabled,1|nullable|{$unitRule}",
            'BlacklistDurationValue' => "required_if:AutoBlacklistEnabled,1|nullable|integer|min:1",
            'BlacklistDurationUnit' => "required_if:AutoBlacklistEnabled,1|nullable|{$unitRule}",
        ]);

        $validated['AutoBlacklistEnabled'] = $request->boolean('AutoBlacklistEnabled');

        BlacklistSetting::current()->update($validated);

        return redirect()->route('admin.blacklist.settings')->with('success', 'Blacklist settings saved.');
    }
}
