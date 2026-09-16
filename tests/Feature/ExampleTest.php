<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

test('the entry route sends a guest to sign in', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('the entry route sends a signed-in user to the dashboard', function () {
    actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
