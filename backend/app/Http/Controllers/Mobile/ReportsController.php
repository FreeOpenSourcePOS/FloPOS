<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    /**
     * Today's summary: revenue, order count, covers.
     * GET /api/reports/summary?tenant_id=1
     */
    public function summary(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tz = $tenant->timezone ?? 'UTC';

        $today = now($tz)->startOfDay()->utc();
        $todayEnd = now($tz)->endOfDay()->utc();

        $bills = DB::connection('tenant')
            ->table('bills')
            ->whereBetween('created_at', [$today, $todayEnd])
            ->whereIn('status', ['paid', 'partially_paid'])
            ->selectRaw('COUNT(*) as bill_count, COALESCE(SUM(total_amount), 0) as revenue')
            ->first();

        $covers = DB::connection('tenant')
            ->table('orders')
            ->whereBetween('created_at', [$today, $todayEnd])
            ->whereNotIn('status', ['cancelled'])
            ->sum('covers');

        $pendingOrders = DB::connection('tenant')
            ->table('orders')
            ->whereIn('status', ['pending', 'preparing'])
            ->count();

        return response()->json([
            'date' => now($tz)->toDateString(),
            'revenue' => (float) $bills->revenue,
            'bill_count' => (int) $bills->bill_count,
            'covers' => (int) $covers,
            'pending_orders' => $pendingOrders,
            'currency' => $tenant->currency,
        ]);
    }

    /**
     * Hourly/daily sales breakdown.
     * GET /api/reports/sales?tenant_id=1&period=today|week|month
     */
    public function sales(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tz = $tenant->timezone ?? 'UTC';
        $period = $request->query('period', 'today');

        [$start, $end, $groupFormat] = match ($period) {
            'week'  => [now($tz)->startOfWeek()->utc(), now($tz)->endOfWeek()->utc(), 'YYYY-MM-DD'],
            'month' => [now($tz)->startOfMonth()->utc(), now($tz)->endOfMonth()->utc(), 'YYYY-MM-DD'],
            default => [now($tz)->startOfDay()->utc(), now($tz)->endOfDay()->utc(), 'HH24'],
        };

        $data = DB::connection('tenant')
            ->table('bills')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['paid', 'partially_paid'])
            ->selectRaw("TO_CHAR(created_at AT TIME ZONE 'UTC' AT TIME ZONE ?, ?) as period, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue", [$tz, $groupFormat])
            ->groupByRaw("TO_CHAR(created_at AT TIME ZONE 'UTC' AT TIME ZONE ?, ?)", [$tz, $groupFormat])
            ->orderBy('period')
            ->get();

        return response()->json([
            'period' => $period,
            'data' => $data->map(fn($r) => [
                'label' => $r->period,
                'orders' => (int) $r->orders,
                'revenue' => (float) $r->revenue,
            ]),
            'currency' => $tenant->currency,
        ]);
    }

    /**
     * Top-selling products.
     * GET /api/reports/top-products?tenant_id=1&limit=10
     */
    public function topProducts(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tz = $tenant->timezone ?? 'UTC';
        $limit = min((int) $request->query('limit', 10), 50);
        $period = $request->query('period', 'today');

        [$start, $end] = match ($period) {
            'week'  => [now($tz)->startOfWeek()->utc(), now($tz)->endOfWeek()->utc()],
            'month' => [now($tz)->startOfMonth()->utc(), now($tz)->endOfMonth()->utc()],
            default => [now($tz)->startOfDay()->utc(), now($tz)->endOfDay()->utc()],
        };

        $products = DB::connection('tenant')
            ->table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereBetween('o.created_at', [$start, $end])
            ->whereNotIn('o.status', ['cancelled'])
            ->selectRaw('oi.product_name, SUM(oi.quantity) as qty_sold, SUM(oi.total_price) as revenue')
            ->groupBy('oi.product_name')
            ->orderByDesc('qty_sold')
            ->limit($limit)
            ->get();

        return response()->json([
            'period' => $period,
            'products' => $products->map(fn($p) => [
                'name' => $p->product_name,
                'qty_sold' => (int) $p->qty_sold,
                'revenue' => (float) $p->revenue,
            ]),
            'currency' => $tenant->currency,
        ]);
    }

    /**
     * Recent orders.
     * GET /api/reports/recent-orders?tenant_id=1&limit=20
     */
    public function recentOrders(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $limit = min((int) $request->query('limit', 20), 100);

        $orders = DB::connection('tenant')
            ->table('orders as o')
            ->leftJoin('bills as b', 'b.order_id', '=', 'o.id')
            ->select('o.id', 'o.order_number', 'o.status', 'o.covers', 'o.table_name', 'o.created_at', 'b.total_amount', 'b.status as bill_status')
            ->orderByDesc('o.created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'orders' => $orders->map(fn($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'covers' => $o->covers,
                'table_name' => $o->table_name,
                'total_amount' => $o->total_amount ? (float) $o->total_amount : null,
                'bill_status' => $o->bill_status,
                'created_at' => $o->created_at,
            ]),
            'currency' => $tenant->currency,
        ]);
    }

    /**
     * Table occupancy.
     * GET /api/reports/tables?tenant_id=1
     */
    public function tables(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $tables = DB::connection('tenant')
            ->table('tables')
            ->select('id', 'name', 'capacity', 'status', 'current_order_id')
            ->orderBy('name')
            ->get();

        $total = $tables->count();
        $occupied = $tables->where('status', 'occupied')->count();
        $available = $tables->where('status', 'available')->count();
        $reserved = $tables->where('status', 'reserved')->count();

        return response()->json([
            'summary' => [
                'total' => $total,
                'occupied' => $occupied,
                'available' => $available,
                'reserved' => $reserved,
                'occupancy_rate' => $total > 0 ? round($occupied / $total * 100, 1) : 0,
            ],
            'tables' => $tables->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'capacity' => $t->capacity,
                'status' => $t->status,
            ]),
        ]);
    }
}
