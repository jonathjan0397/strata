<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Client/Quotes/Index', [
            'quotes' => Quote::where('user_id', $request->user()->id)
                ->whereIn('status', ['sent', 'accepted', 'declined', 'expired'])
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, Quote $quote): Response
    {
        abort_unless($quote->user_id === $request->user()->id, 403);
        abort_if($quote->status === 'draft', 404);

        $quote->load('items');

        return Inertia::render('Client/Quotes/Show', ['quote' => $quote]);
    }

    public function accept(Request $request, Quote $quote): RedirectResponse
    {
        abort_unless($quote->user_id === $request->user()->id, 403);
        abort_unless($quote->status === 'sent', 422);

        $quote->update(['status' => 'accepted']);

        return back()->with('success', 'Quote accepted. Our team will be in touch shortly to finalise your order.');
    }

    public function decline(Request $request, Quote $quote): RedirectResponse
    {
        abort_unless($quote->user_id === $request->user()->id, 403);
        abort_unless(in_array($quote->status, ['sent', 'accepted']), 422);

        $quote->update(['status' => 'declined']);

        return back()->with('success', 'Quote declined.');
    }
}
