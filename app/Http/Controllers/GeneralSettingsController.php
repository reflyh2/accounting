<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class GeneralSettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $settings = UserSetting::getAllForUser($user->global_id);

        // Default dashboard preferences
        $dashboardPreferences = $settings['dashboard_preferences'] ?? [
            'default_period' => 'month',
            'visible_cards' => [
                'sales_orders' => true,
                'sales_invoices' => true,
                'purchase_orders' => true,
                'purchase_invoices' => true,
                'receivables' => true,
                'payables' => true,
            ],
            'show_charts' => true,
            'show_recent_documents' => true,
        ];

        return Inertia::render('Settings/General', [
            'dashboardPreferences' => $dashboardPreferences,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'dashboard_preferences' => 'required|array',
            'dashboard_preferences.default_period' => 'required|in:week,month,quarter,year',
            'dashboard_preferences.visible_cards' => 'required|array',
            'dashboard_preferences.visible_cards.*' => 'boolean',
            'dashboard_preferences.show_charts' => 'boolean',
            'dashboard_preferences.show_recent_documents' => 'boolean',
        ]);

        UserSetting::setValue(
            $user->global_id,
            'dashboard_preferences',
            $validated['dashboard_preferences']
        );

        return redirect()->back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
