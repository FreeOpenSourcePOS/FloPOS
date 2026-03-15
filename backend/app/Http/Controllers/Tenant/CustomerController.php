<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Customer;
use App\Models\Tenant\LoyaltyLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->boolean('vip')) {
            $query->vip();
        }

        // Attach wallet balance via subquery (avoids N+1)
        $now = now()->toDateTimeString();
        $query->selectRaw("customers.*, COALESCE((
            SELECT SUM(CASE WHEN ll.type='credit' THEN ll.amount ELSE -ll.amount END)
            FROM loyalty_ledger ll
            WHERE ll.customer_id = customers.id
              AND (ll.type = 'debit' OR ll.expires_at IS NULL OR ll.expires_at > ?)
        ), 0) as wallet_balance", [$now]);

        $customers = $query->orderBy('name')->paginate($request->get('per_page', 20));

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'country_code' => 'nullable|string|max:5',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'anniversary' => 'nullable|date',
            'preferences' => 'nullable|array',
            'notes' => 'nullable|string',
            'gstin' => 'nullable|string|size:15',
            'customer_state_code' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Auto-extract state code from GSTIN (first 2 digits)
        if (!empty($data['gstin']) && empty($data['customer_state_code'])) {
            $data['customer_state_code'] = substr($data['gstin'], 0, 2);
        }

        $customer = Customer::create($data);

        return response()->json(['customer' => $customer], 201);
    }

    public function show(int $id): JsonResponse
    {
        $customer = Customer::with(['orders' => fn($q) => $q->latest()->limit(10)])->findOrFail($id);

        return response()->json(['customer' => $customer]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'phone' => 'sometimes|string|max:20',
            'country_code' => 'nullable|string|max:5',
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'anniversary' => 'nullable|date',
            'preferences' => 'nullable|array',
            'notes' => 'nullable|string',
            'gstin' => 'nullable|string|size:15',
            'customer_state_code' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Auto-extract state code from GSTIN (first 2 digits)
        if (!empty($data['gstin']) && empty($data['customer_state_code'])) {
            $data['customer_state_code'] = substr($data['gstin'], 0, 2);
        }

        $customer->update($data);

        return response()->json(['customer' => $customer->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }

    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customers = Customer::search($request->q)->limit(10)->get();

        return response()->json(['customers' => $customers]);
    }

    public function wallet(int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $balance = LoyaltyLedger::getBalance($id);

        $ledger = LoyaltyLedger::where('customer_id', $id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'balance' => $balance,
            'ledger'  => $ledger,
        ]);
    }
}
