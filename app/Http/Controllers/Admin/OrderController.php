<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::with('user')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) =>
                $q->where('order_number', 'like', "%{$s}%")
                  ->orWhereHas('user', fn ($u) =>
                      $u->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                  )
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders'  => $orders,
            'filters' => $request->only('search', 'status'),
        ]);
    }
}
