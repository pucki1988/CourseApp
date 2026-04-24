<?php

use App\Models\User;
use App\Notifications\TestWebPushNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

test('client can fetch configured web push public key', function () {
    config()->set('webpush.vapid.public_key', 'test-public-key');

    $response = $this->getJson('/api/push/public-key');

    $response->assertOk()->assertJson([
        'publicKey' => 'test-public-key',
    ]);
});

test('public key endpoint returns service unavailable when web push is not configured', function () {
    config()->set('webpush.vapid.public_key', '');

    $response = $this->getJson('/api/push/public-key');

    $response->assertStatus(503)->assertJson([
        'message' => 'Web Push ist nicht konfiguriert.',
    ]);
});

test('authenticated user can store a push subscription', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $payload = [
        'endpoint' => 'https://example.com/push/endpoint-1',
        'keys' => [
            'p256dh' => 'public-key-value',
            'auth' => 'auth-token-value',
        ],
        'contentEncoding' => 'aes128gcm',
    ];

    $response = $this->postJson('/api/push/subscriptions', $payload);

    $response->assertCreated();

    $this->assertDatabaseHas('push_subscriptions', [
        'endpoint' => 'https://example.com/push/endpoint-1',
        'public_key' => 'public-key-value',
        'auth_token' => 'auth-token-value',
        'content_encoding' => 'aes128gcm',
        'subscribable_id' => $user->id,
        'subscribable_type' => $user->getMorphClass(),
    ]);
});

test('authenticated user can delete a push subscription', function () {
    $user = User::factory()->create();

    $user->updatePushSubscription(
        'https://example.com/push/endpoint-2',
        'public-key-value',
        'auth-token-value',
        'aes128gcm',
    );

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/push/subscriptions/unsubscribe', [
        'endpoint' => 'https://example.com/push/endpoint-2',
    ]);

    $response->assertNoContent();

    $this->assertDatabaseMissing('push_subscriptions', [
        'endpoint' => 'https://example.com/push/endpoint-2',
    ]);
});

test('push subscription endpoints require authentication', function () {
    $payload = [
        'endpoint' => 'https://example.com/push/endpoint-3',
        'keys' => [
            'p256dh' => 'public-key-value',
            'auth' => 'auth-token-value',
        ],
    ];

    $this->postJson('/api/push/subscriptions', $payload)->assertUnauthorized();
    $this->postJson('/api/push/subscriptions/unsubscribe', [
        'endpoint' => 'https://example.com/push/endpoint-3',
    ])->assertUnauthorized();
    $this->postJson('/api/push/subscriptions/test')->assertUnauthorized();
});

test('test notification is dispatched to user with active subscriptions', function () {
    Notification::fake();

    $user = User::factory()->create();

    $user->updatePushSubscription(
        'https://example.com/push/endpoint-test',
        'public-key-value',
        'auth-token-value',
        'aes128gcm',
    );

    Sanctum::actingAs($user);

    $this->postJson('/api/push/subscriptions/test')->assertAccepted();

    Notification::assertSentTo($user, TestWebPushNotification::class);
});

test('test notification returns 422 when user has no push subscriptions', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/push/subscriptions/test')
        ->assertUnprocessable()
        ->assertJson(['message' => 'Keine aktiven Push-Abonnements gefunden.']);
});