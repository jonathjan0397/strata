<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __invoke(): Response
    {
        $announcements = Announcement::where('published', true)
            ->orderByDesc('published_at')
            ->paginate(10);

        return Inertia::render('Client/Announcements', [
            'announcements' => $announcements,
        ]);
    }
}
