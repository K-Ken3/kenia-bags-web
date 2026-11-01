<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        return inertia('Admin/Dashboard', [
            'summary' => [
                'orders_today' => Order::whereDate('created_at', Carbon::today())->count(),
                'awaiting_payment' => Order::whereIn('order_status', ['awaiting_payment', 'payment_review'])->count(),
                'to_dispatch' => Order::whereIn('order_status', ['confirmed', 'packing'])->count(),
                'paid_revenue_rwf' => (int) Order::where('payment_status', 'paid')->sum('total_rwf'),
            ],
            'recentOrders' => Order::latest()->limit(8)->get([
                'id', 'public_reference', 'customer_name', 'order_status', 'payment_status',
                'total_rwf', 'created_at',
            ]),
        ]);
    }
}
