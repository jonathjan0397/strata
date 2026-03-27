<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/EmailTemplates/Index', [
            'templates' => EmailTemplate::orderBy('slug')->get(),
        ]);
    }

    public function edit(EmailTemplate $emailTemplate): Response
    {
        return Inertia::render('Admin/EmailTemplates/Form', [
            'template' => $emailTemplate,
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'subject'    => ['required', 'string', 'max:255'],
            'body_html'  => ['required', 'string'],
            'body_plain' => ['nullable', 'string'],
            'active'     => ['boolean'],
        ]);

        $emailTemplate->update($request->only('name', 'subject', 'body_html', 'body_plain', 'active'));

        return back()->with('success', 'Template updated.');
    }
}
