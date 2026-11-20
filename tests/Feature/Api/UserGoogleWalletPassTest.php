<?php

use App\Models\User;
use App\Services\User\GoogleWalletPassService;
use Laravel\Sanctum\Sanctum;

test('authenticated user can request google wallet pass link', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $mock = Mockery::mock(GoogleWalletPassService::class);
    $mock->shouldReceive('getObjectStatus')
        ->once()
        ->withArgs(fn (User $resolvedUser) => $resolvedUser->is($user))
        ->andReturn(['exists' => false, 'object_id' => 'issuer.user']);
    $mock->shouldReceive('generateSaveLink')
        ->once()
        ->withArgs(fn (User $resolvedUser) => $resolvedUser->is($user))
        ->andReturn('https://pay.google.com/gp/v/save/mock-jwt');

    app()->instance(GoogleWalletPassService::class, $mock);

    $response = $this->getJson('/api/me/google-wallet-pass');

    $response->assertOk()->assertJson([
        'has_pass' => false,
        'save_link' => 'https://pay.google.com/gp/v/save/mock-jwt',
        'object_id' => 'issuer.user',
    ]);
});

test('google wallet endpoint requires authentication', function () {
    $response = $this->getJson('/api/me/google-wallet-pass');

    $response->assertUnauthorized();
});
