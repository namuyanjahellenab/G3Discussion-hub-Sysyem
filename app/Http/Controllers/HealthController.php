<?php

namespace App\Http\Controllers;

use App\Services\MlGatewayClient;
use Illuminate\Support\Facades\DB;

/**
 * Laravel's default /up only confirms the Laravel process itself booted —
 * it says nothing about the ML gateway, a separate Python process that
 * Export/Share/spam-check/classification/recommendations all depend on
 * (see DEPLOYMENT.md). Without this, the gateway silently going down is
 * invisible until a user notices a degraded feature.
 */
class HealthController extends Controller
{
    public function status()
    {
        $checks = [
            'app' => ['ok' => true],
            'database' => $this->checkDatabase(),
            'ml_gateway' => $this->checkMlGateway(),
        ];

        $allOk = collect($checks)->every(fn ($check) => $check['ok']);

        return response()->json([
            'status' => $allOk ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $allOk ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkMlGateway(): array
    {
        $baseUrl = config('services.ml_gateway.url');
        $token = config('services.ml_gateway.token');

        if (!$baseUrl || !$token) {
            return ['ok' => false, 'error' => 'ML_GATEWAY_URL or ML_GATEWAY_TOKEN not configured'];
        }

        // A lightweight, cheap call — classify a trivial string rather than
        // pinging a nonexistent root route the Flask app doesn't define.
        $result = app(MlGatewayClient::class)->classify('health check');

        return $result !== null
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Gateway unreachable or returned an error (see storage/logs/laravel.log)'];
    }
}
