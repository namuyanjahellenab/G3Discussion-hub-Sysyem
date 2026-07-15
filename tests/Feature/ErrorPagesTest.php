<?php

use App\Models\User;

beforeEach(function () {
    // Custom error views only render when debug mode is off — with
    // APP_DEBUG=true (the local default) Laravel shows the Ignition/Whoops
    // trace page instead, regardless of whether resources/views/errors/*
    // exist.
    config(['app.debug' => false]);
});

test('a 404 shows the custom on-brand error page, not a blank Laravel default', function () {
    $response = $this->get('/this-route-does-not-exist');

    $response->assertStatus(404);
    $response->assertSee('Page not found');
    $response->assertSee('Discussion Hub', false);
});

test('a 403 shows the custom error page with the actual reason', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($student)->get(route('admin.dashboard'));

    $response->assertStatus(403);
    $response->assertSee('access to this', false);
});
