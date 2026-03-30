<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class WidgetSnippetsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Widgets/Index', [
            'appUrl' => rtrim(config('app.url'), '/'),
        ]);
    }
}
