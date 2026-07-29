<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\User;
use App\Services\AuditService;

class SettingsController extends Controller
{
    public function page(): void
    {
        $this->view('settings.index', ['pageTitle' => 'Settings']);
    }

    /**
     * POST /api/settings/profile  {name, phone}
     */
    public function updateProfile(): void
    {
        $v = Validator::make(Request::all(), [
            'name'  => 'required|max:100',
            'phone' => 'max:20',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        (new User())->update(Auth::id(), [
            'name'  => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);

        AuditService::log('update', 'users', Auth::id(), 'Profile updated');
        Response::json(null, 200, 'Profile updated.');
    }

    /**
     * POST /api/settings/password  {current_password, new_password}
     */
    public function changePassword(): void
    {
        $current = (string) Request::input('current_password', '');
        $new     = (string) Request::input('new_password', '');

        if (strlen($new) < 8) {
            Response::error('The new password must be at least 8 characters.', 422);
        }

        $user = Auth::user();
        if (!password_verify($current, $user['password'])) {
            Response::error('The current password is incorrect.', 422);
        }

        (new User())->update(Auth::id(), ['password' => password_hash($new, PASSWORD_DEFAULT)]);
        AuditService::log('update', 'users', Auth::id(), 'Password changed');
        Response::json(null, 200, 'Password changed.');
    }

    /**
     * POST /api/settings/preferences  {theme, notify_email, notify_inapp}
     */
    public function updatePreferences(): void
    {
        $theme = (string) Request::input('theme', 'auto');
        if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
            Response::error('Invalid theme.', 422);
        }

        (new User())->update(Auth::id(), [
            'theme'        => $theme,
            'notify_email' => Request::input('notify_email') ? 1 : 0,
            'notify_inapp' => Request::input('notify_inapp') ? 1 : 0,
        ]);

        Response::json(null, 200, 'Preferences saved.');
    }
}
