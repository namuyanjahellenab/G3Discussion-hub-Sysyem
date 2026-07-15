<?php

use App\Models\User;

test('a student cannot reach admin-only destructive routes', function () {
    $student = User::factory()->create();

    $this->actingAs($student)->post(route('admin.warning.store'), ['UserID' => $student->UserID, 'Reason' => 'x'])->assertForbidden();
    $this->actingAs($student)->get(route('admin.blacklist'))->assertForbidden();
    $this->actingAs($student)->get(route('admin.flagged-content.index'))->assertForbidden();
});

test('a lecturer cannot reach admin-only destructive routes either', function () {
    $lecturer = User::factory()->create(['Role' => 'Lecturer']);

    $this->actingAs($lecturer)->get(route('admin.blacklist'))->assertForbidden();
    $this->actingAs($lecturer)->get(route('admin.flagged-content.index'))->assertForbidden();
});
