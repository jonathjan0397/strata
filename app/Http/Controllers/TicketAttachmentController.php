<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    public function download(Request $request, TicketAttachment $attachment)
    {
        $user = $request->user();

        // Staff/admin can download any attachment; clients can only access their own tickets.
        if (! $user->hasAnyRole(['super-admin', 'admin', 'staff'])) {
            abort_unless($attachment->ticket->user_id === $user->id, 403);
        }

        if (! Storage::disk('public')->exists($attachment->path)) {
            abort(404, 'Attachment not found.');
        }

        return Storage::disk('public')->download($attachment->path, $attachment->filename);
    }
}
