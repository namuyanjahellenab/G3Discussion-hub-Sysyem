<?php

use App\Services\MlGatewayClient;
use Mockery\MockInterface;

test('the health check reports ok when the gateway is reachable', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('classify')->once()->andReturn(['PredictedCategory' => 'General Chat', 'ConfidenceScore' => 0.1]);
    });

    $response = $this->get('/health');
    $data = $response->json();

    expect($data['checks']['database']['ok'])->toBeTrue();
    expect($data['checks']['ml_gateway']['ok'])->toBeTrue();
    $response->assertStatus(200);
    expect($data['status'])->toBe('ok');
});

test('the health check reports degraded when the gateway is unreachable', function () {
    $this->mock(MlGatewayClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('classify')->once()->andReturn(null);
    });

    $response = $this->get('/health');
    $data = $response->json();

    expect($data['checks']['database']['ok'])->toBeTrue();
    expect($data['checks']['ml_gateway']['ok'])->toBeFalse();
    $response->assertStatus(503);
    expect($data['status'])->toBe('degraded');
});
