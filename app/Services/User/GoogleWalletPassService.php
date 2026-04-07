<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Arr;
use RuntimeException;

class GoogleWalletPassService
{
    public function generateSaveLink(User $user): string
    {
        $token = $user->checkinToken?->token;

        if (!$token) {
            throw new RuntimeException('Kein aktiver Check-in-Token vorhanden.');
        }

        $config = config('services.google_wallet', []);

        $issuerId = (string) Arr::get($config, 'issuer_id', '');
        $configuredClassId = (string) Arr::get($config, 'class_id', '');
        $classSuffix = (string) Arr::get($config, 'class_suffix', 'fitnesspass');
        $serviceAccountEmail = (string) Arr::get($config, 'service_account_email', '');
        $privateKey = (string) Arr::get($config, 'private_key', '');
        $origin = (string) Arr::get($config, 'origin', config('app.url'));

        if ($issuerId === '' || $serviceAccountEmail === '' || $privateKey === '') {
            throw new RuntimeException('Google Wallet ist nicht vollständig konfiguriert.');
        }

        $normalizedKey = str_replace("\\n", "\n", $privateKey);
        $now = time();
        $classId = $configuredClassId !== ''
            ? $configuredClassId
            : $issuerId.'.'.$this->sanitizeIdentifier($classSuffix);
        $objectId = $issuerId.'.user_'.$user->id.'_fitnesspass';

        $claims = [
            'iss' => $serviceAccountEmail,
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => $now,
            'origins' => $origin ? [$origin] : [],
            'payload' => [
                'genericObjects' => [[
                    'id' => $objectId,
                    'classId' => $classId,
                    'state' => 'ACTIVE',
                    'cardTitle' => [
                        'defaultValue' => [
                            'language' => 'de-DE',
                            'value' => 'Fitnesspass',
                        ],
                    ],
                    'header' => [
                        'defaultValue' => [
                            'language' => 'de-DE',
                            'value' => $user->name ?: $user->email,
                        ],
                    ],
                    'subheader' => [
                        'defaultValue' => [
                            'language' => 'de-DE',
                            'value' => 'Check-in',
                        ],
                    ],
                    'barcode' => [
                        'type' => 'QR_CODE',
                        'value' => $token,
                        'alternateText' => $token,
                    ],
                    'hexBackgroundColor' => '#1E3A8A',
                ]],
            ],
        ];

        $jwt = $this->signJwt($claims, $normalizedKey);

        return 'https://pay.google.com/gp/v/save/'.$jwt;
    }

    private function signJwt(array $claims, string $privateKey): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES));
        $signingInput = $encodedHeader.'.'.$encodedPayload;

        $signature = '';
        $success = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new RuntimeException('Google Wallet JWT konnte nicht signiert werden.');
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function sanitizeIdentifier(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9._-]/', '_', $value) ?? '';

        return trim($sanitized, '_') ?: 'fitnesspass';
    }
}
