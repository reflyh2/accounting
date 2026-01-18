<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    /**
     * Get current onboarding status for the authenticated user.
     */
    public function getStatus()
    {
        $user = Auth::user();
        
        return response()->json([
            'completed' => UserSetting::getValue($user->global_id, 'onboarding_completed', false),
            'skipped' => UserSetting::getValue($user->global_id, 'onboarding_skipped', false),
            'currentStep' => UserSetting::getValue($user->global_id, 'onboarding_step', 0),
        ]);
    }

    /**
     * Update the current step progress.
     */
    public function updateProgress(Request $request)
    {
        $request->validate([
            'step' => 'required|integer|min:0|max:10',
        ]);

        $user = Auth::user();
        UserSetting::setValue($user->global_id, 'onboarding_step', $request->step);

        return response()->json([
            'success' => true,
            'currentStep' => $request->step,
        ]);
    }

    /**
     * Mark onboarding as completed.
     */
    public function complete()
    {
        $user = Auth::user();
        
        UserSetting::setValue($user->global_id, 'onboarding_completed', true);
        UserSetting::setValue($user->global_id, 'onboarding_step', 10);

        return response()->json([
            'success' => true,
            'completed' => true,
        ]);
    }

    /**
     * Skip onboarding entirely.
     */
    public function skip()
    {
        $user = Auth::user();
        
        UserSetting::setValue($user->global_id, 'onboarding_skipped', true);

        return response()->json([
            'success' => true,
            'skipped' => true,
        ]);
    }

    /**
     * Reset onboarding state (for testing or re-watching).
     */
    public function reset()
    {
        $user = Auth::user();
        
        UserSetting::setValue($user->global_id, 'onboarding_completed', false);
        UserSetting::setValue($user->global_id, 'onboarding_skipped', false);
        UserSetting::setValue($user->global_id, 'onboarding_step', 0);

        return response()->json([
            'success' => true,
            'reset' => true,
        ]);
    }
}
