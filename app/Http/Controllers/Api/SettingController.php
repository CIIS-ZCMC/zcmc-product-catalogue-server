<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Retrieve all application settings.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'require_password_on_edit' => SystemSetting::getBool('require_password_on_edit', false),
        ]);
    }

    /**
     * Update application settings (Administrator only).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'require_password_on_edit' => 'required|boolean',
        ]);

        $newValue = (bool) $validated['require_password_on_edit'];
        $oldValue = SystemSetting::getBool('require_password_on_edit', false);

        SystemSetting::set('require_password_on_edit', $newValue);

        if ($oldValue !== $newValue) {
            $statusLabel = $newValue ? 'enabled' : 'disabled';
            ActivityLog::record(
                'UPDATED',
                "Security setting 'require_password_on_edit' was {$statusLabel} by administrator.",
                'SystemSetting',
                null,
                'require_password_on_edit',
                ['require_password_on_edit' => $oldValue],
                ['require_password_on_edit' => $newValue]
            );
        }

        return response()->json([
            'message' => 'Settings updated successfully.',
            'settings' => [
                'require_password_on_edit' => $newValue,
            ],
        ]);
    }
}
