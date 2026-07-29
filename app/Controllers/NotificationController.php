<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function page(): void
    {
        $this->view('notifications.index', ['pageTitle' => 'Notifications']);
    }

    /**
     * GET /api/notifications?unread=1&limit=20 — the header bell polls this.
     */
    public function index(): void
    {
        $model = new Notification();
        $limit = min(100, max(1, (int) Request::query('limit', 20)));

        Response::json([
            'items'  => $model->forUser(Auth::id(), $limit, Request::query('unread') === '1'),
            'unread' => $model->unreadCount(Auth::id()),
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     */
    public function read(string $id): void
    {
        (new Notification())->markRead((int) $id, Auth::id());
        Response::json(null, 200, 'Notification marked as read.');
    }

    /**
     * POST /api/notifications/read-all
     */
    public function readAll(): void
    {
        (new Notification())->markAllRead(Auth::id());
        Response::json(null, 200, 'All notifications marked as read.');
    }
}
