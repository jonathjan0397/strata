<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\StripeClient;

class PaymentMethodController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function index(Request $request): Response
    {
        $methods = $request->user()
            ->paymentMethods()
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Client/PaymentMethods/Index', [
            'methods' => $methods,
        ]);
    }

    /**
     * Return a Stripe SetupIntent client_secret so the frontend can collect card details.
     */
    public function setupIntent(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $this->ensureStripeCustomer($user);

        $intent = $this->stripe->setupIntents->create([
            'customer'             => $user->stripe_customer_id,
            'payment_method_types' => ['card'],
        ]);

        return response()->json(['clientSecret' => $intent->client_secret]);
    }

    /**
     * Store a payment method after Stripe.js confirms the SetupIntent.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'string'],
        ]);

        $user = $request->user();
        $this->ensureStripeCustomer($user);

        // Attach to customer in Stripe
        $this->stripe->paymentMethods->attach($data['payment_method_id'], [
            'customer' => $user->stripe_customer_id,
        ]);

        // Fetch card details
        $pm = $this->stripe->paymentMethods->retrieve($data['payment_method_id']);

        $isFirst = $user->paymentMethods()->doesntExist();

        DB::transaction(function () use ($user, $pm, $isFirst) {
            $user->paymentMethods()->create([
                'stripe_payment_method_id' => $pm->id,
                'brand'                    => $pm->card->brand,
                'last4'                    => $pm->card->last4,
                'exp_month'                => $pm->card->exp_month,
                'exp_year'                 => $pm->card->exp_year,
                'is_default'               => $isFirst,
            ]);
        });

        return redirect()->route('client.payment-methods.index')
            ->with('success', 'Card saved successfully.');
    }

    public function setDefault(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $user = $request->user();
        abort_unless($paymentMethod->user_id === $user->id, 403);

        DB::transaction(function () use ($user, $paymentMethod) {
            $user->paymentMethods()->update(['is_default' => false]);
            $paymentMethod->update(['is_default' => true]);
        });

        return back()->with('success', 'Default card updated.');
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $user = $request->user();
        abort_unless($paymentMethod->user_id === $user->id, 403);

        // Detach from Stripe
        $this->stripe->paymentMethods->detach($paymentMethod->stripe_payment_method_id);

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->delete();

        // If we deleted the default, promote the next card
        if ($wasDefault) {
            $user->paymentMethods()->oldest()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Card removed.');
    }

    // -----------------------------------------------------------------------

    private function ensureStripeCustomer(\App\Models\User $user): void
    {
        if ($user->stripe_customer_id) {
            return;
        }

        $customer = $this->stripe->customers->create([
            'email' => $user->email,
            'name'  => $user->name,
        ]);

        $user->update(['stripe_customer_id' => $customer->id]);
    }
}
