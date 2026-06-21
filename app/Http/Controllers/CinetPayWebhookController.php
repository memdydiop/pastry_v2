<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CinetPayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('CinetPay webhook received', $request->all());

        $transactionId = $request->input('transaction_id');
        $status = $request->input('status');
        $amount = $request->input('amount');
        $customerEmail = $request->input('customer_email');
        $customerPhone = $request->input('customer_phone_number');
        $metadata = $request->input('metadata', []);

        if ($status !== 'ACCEPTED') {
            Log::info('CinetPay payment not accepted', ['transaction_id' => $transactionId, 'status' => $status]);
            return response()->json(['message' => 'Payment not accepted'], 200);
        }

        $sessionData = session()->get("onboarding.{$transactionId}");

        if (!$sessionData) {
            Log::warning('No onboarding session data for transaction', ['transaction_id' => $transactionId]);
            return response()->json(['message' => 'Session not found'], 200);
        }

        $plan = Plan::find($sessionData['plan_id']);

        if (!$plan) {
            Log::error('Plan not found during webhook', ['plan_id' => $sessionData['plan_id']]);
            return response()->json(['message' => 'Plan not found'], 200);
        }

        $tenantId = (string) Str::uuid();

        $tenant = Tenant::create([
            'id' => $tenantId,
            'data' => [
                'company_name' => $sessionData['company_name'],
                'email' => $customerEmail,
                'phone' => $customerPhone,
            ],
        ]);

        $tenant->domains()->create([
            'domain' => Str::slug($sessionData['company_name']) . '.' . config('app.central_domain'),
            'is_primary' => true,
        ]);

        $tenant->subscriptions()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        session()->forget("onboarding.{$transactionId}");

        Log::info('Tenant created successfully from onboarding', [
            'tenant_id' => $tenantId,
            'company' => $sessionData['company_name'],
            'plan' => $plan->name,
        ]);

        return response()->json(['message' => 'Tenant created successfully'], 200);
    }
}
