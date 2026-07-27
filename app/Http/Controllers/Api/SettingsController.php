<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    /**
     * PUT /api/settings
     * Updates Name (-> UserName), Email, and optionally password for the
     * authenticated desktop user. Desktop only has one "name" concept
     * (UserName) — no separate name/username split like the web Settings page.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if ($request->filled('name')) {
            $user->UserName = $request->name;
        }

        if ($request->filled('email')) {
            $user->Email = $request->email;
        }

        if ($request->filled('current_password') && $request->filled('new_password')) {
            if (!Hash::check($request->current_password, $user->PasswordHash)) {
                return response()->json(['message' => 'Current password is incorrect.'], 422);
            }
            $user->PasswordHash = Hash::make($request->new_password);
        }

        if ($request->filled('theme_color')) {
            $user->ThemeColor = $request->theme_color;
        }

        $user->save();

        return response()->json(\App\Support\ApiResponseCasing::withPascalAliases([
            'message' => 'Settings updated.',
            'user' => [
                'id' => $user->UserID,
                'email' => $user->Email,
                'name' => $user->UserName,
                'theme_color' => $user->ThemeColor,
            ],
        ]));
    }

    /**
     * POST /api/logout
     * Revokes the current Sanctum token so it can't be reused.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * POST /api/logout-all-devices
     * The Sanctum equivalent of the web's "Log out of all devices" - the web
     * version clears session rows (session-cookie auth), this revokes every
     * token issued to the user (token auth), same end result either way.
     */
    public function logoutAllDevices(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out of all devices.']);
    }
}
