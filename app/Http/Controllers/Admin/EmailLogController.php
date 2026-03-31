<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = EmailLog::with('user:id,name,email')
            ->orderByDesc('sent_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('to', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/EmailLog/Index', [
            'logs' => $logs,
            'filters' => ['search' => $request->input('search')],
        ]);
    }

    public function show(EmailLog $emailLog): Response
    {
        return Inertia::render('Admin/EmailLog/Show', [
            'log' => $emailLog->load('user:id,name,email'),
        ]);
    }
}
