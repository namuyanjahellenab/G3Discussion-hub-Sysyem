<?php

test('the health check reports the database as ok and flags the ML gateway when unreachable', function () {
    $response = $this->get('/health');

    $data = $response->json();

    expect($data['checks']['database']['ok'])->toBeTrue();
    // No gateway is running during tests, so this should honestly report
    // "degraded" rather than silently claiming everything is fine.
    expect($data['checks']['ml_gateway']['ok'])->toBeFalse();
    $response->assertStatus(503);
    expect($data['status'])->toBe('degraded');
});
