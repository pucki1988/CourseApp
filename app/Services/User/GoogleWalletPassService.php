<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleWalletPassService
{
    public function generateSaveLink(User $user): string
    {
        $token = $user->checkinToken?->token;

        if (!$token) {
            throw new RuntimeException('Kein aktiver Check-in-Token vorhanden.');
        }

        $resolved = $this->resolveConfig($user);
        $now = time();
        $classId = $resolved['class_id'];
        $objectId = $resolved['object_id'];

        $claims = [
            'iss' => $resolved['service_account_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => $now,
            'origins' => $resolved['origin'] ? [$resolved['origin']] : [],
            'payload' => [
                // Include the class in the JWT to avoid "class not found" mismatches.
                'genericClasses' => [[
                    'id' => $classId,
                    'issuerName' => $resolved['issuer_name'],
                    'reviewStatus' => 'UNDER_REVIEW',
                    'classTemplateInfo' => $this->buildClassTemplateInfo(),
                ]],
                'genericObjects' => [[
                    'id' => $objectId,
                    'classId' => $classId,
                    'state' => 'ACTIVE',
                    'logo' => [
                        'sourceUri' => [
                            'uri' => 'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet.png',
                        ],
                    ],
                    'heroImage' => [
                        'sourceUri' => [
                            'uri' => 'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/sportkurse_hero.png',
                        ],
                    ],
                    'cardTitle' => [
                        'defaultValue' => [
                            'language' => 'de-DE',
                            'value' => $resolved['pass_title'],
                        ],
                    ],
                    'header' => [
                        'defaultValue' => [
                            'language' => 'de-DE',
                            'value' => $user->name ?: $user->email,
                        ],
                    ],
                    'textModulesData' => $this->buildTextModulesData($user),
                    'barcode' => [
                        'type' => 'QR_CODE',
                        'value' => $token,
                        'alternateText' => '',
                    ],
                    'hexBackgroundColor' => $resolved['bg_hex_color'],
                ]],
            ],
        ];

        $jwt = $this->signJwt($claims, $resolved['private_key']);

        return 'https://pay.google.com/gp/v/save/'.$jwt;
    }

    public function getObjectStatus(User $user): array
    {
        $resolved = $this->resolveConfig($user);
        $accessToken = $this->fetchGoogleAccessToken($resolved);

        $response = Http::withToken($accessToken)
            ->get('https://walletobjects.googleapis.com/walletobjects/v1/genericObject/'.rawurlencode($resolved['object_id']));

        if ($response->status() === 404 || !$response->json('hasUsers')) {
            return [
                'exists' => false,
                'object_id' => $resolved['object_id'],
            ];
        }

        if (!$response->successful()) {
            throw new RuntimeException('Google Wallet Objektstatus konnte nicht geladen werden: '.$response->body());
        }

        return [
            'exists' => true,
            'object_id' => $resolved['object_id'],
            'class_id' => $resolved['class_id'],
            'pass_type' => $resolved['pass_type'],
            'state' => $response->json('state'),
        ];
    }

    public function getClass(User $user): array
    {
        $resolved = $this->resolveConfig($user);
        $accessToken = $this->fetchGoogleAccessToken($resolved);

        $response = Http::withToken($accessToken)
            ->get('https://walletobjects.googleapis.com/walletobjects/v1/genericClass/'.rawurlencode($resolved['class_id']));

        if ($response->status() === 404) {
            return [
                'found' => false,
                'class_id' => $resolved['class_id'],
            ];
        }

        if (!$response->successful()) {
            throw new RuntimeException('Google Wallet Klasse konnte nicht geladen werden: '.$response->body());
        }

        return [
            'found' => true,
            'class' => $response->json(),
        ];
    }

    public function patchClass(User $user): array
    {
        $resolved = $this->resolveConfig($user);
        $accessToken = $this->fetchGoogleAccessToken($resolved);

        $payload = [
            'issuerName' => $resolved['issuer_name'],
            'reviewStatus' => 'UNDER_REVIEW',
            'classTemplateInfo' => $this->buildClassTemplateInfo(),
        ];

        $response = Http::withToken($accessToken)
            ->patch('https://walletobjects.googleapis.com/walletobjects/v1/genericClass/'.rawurlencode($resolved['class_id']), $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Google Wallet Klasse konnte nicht aktualisiert werden: '.$response->body());
        }

        return [
            'patched' => true,
            'class_id' => $resolved['class_id'],
            'class' => $response->json(),
        ];
    }

    public function deactivateObject(User $user): array
    {
        $resolved = $this->resolveConfig($user);
        $accessToken = $this->fetchGoogleAccessToken($resolved);

        $response = Http::withToken($accessToken)
            ->patch('https://walletobjects.googleapis.com/walletobjects/v1/genericObject/'.rawurlencode($resolved['object_id']), [
                'state' => 'INACTIVE',
            ]);

        if ($response->status() === 404) {
            return [
                'deactivated' => false,
                'message' => 'Objekt nicht gefunden.',
                'object_id' => $resolved['object_id'],
            ];
        }

        if (!$response->successful()) {
            throw new RuntimeException('Google Wallet Objekt konnte nicht deaktiviert werden: '.$response->body());
        }

        return [
            'deactivated' => true,
            'object_id' => $resolved['object_id'],
            'state' => 'INACTIVE',
        ];
    }

    public function listObjectsForClass(User $user, int $maxResults = 20, ?string $pageToken = null): array
    {
        $resolved = $this->resolveConfig($user);
        $accessToken = $this->fetchGoogleAccessToken($resolved);

        $query = [
            'classId' => $resolved['class_id'],
            'maxResults' => max(1, min($maxResults, 1000)),
        ];

        if (!empty($pageToken)) {
            $query['pageToken'] = $pageToken;
        }

        $response = Http::withToken($accessToken)
            ->get('https://walletobjects.googleapis.com/walletobjects/v1/genericObject', $query);

        if (!$response->successful()) {
            throw new RuntimeException('Google Wallet Objekte konnten nicht geladen werden: '.$response->body());
        }

        return [
            'class_id' => $resolved['class_id'],
            'objects' => $response->json('resources', []),
            'next_page_token' => $response->json('pagination.nextPageToken'),
        ];
    }

    public function updateObject(User $user, array $overrides = []): array
    {
        $token = $user->checkinToken?->token;
        


        if (!$token) {
            throw new RuntimeException('Kein aktiver Check-in-Token vorhanden.');
        }

        $resolved = $this->resolveConfig($user);
        $accessToken = $this->fetchGoogleAccessToken($resolved);

        $payload = [
            'state' => Arr::get($overrides, 'state', 'ACTIVE'),
            'header' => [
                'defaultValue' => [
                    'language' => 'de-DE',
                    'value' => Arr::get($overrides, 'header', $user->name ?: $user->email),
                ],
            ],
            'subheader' => null,
            'messages' => null,
            'textModulesData' => $this->buildTextModulesData($user, $overrides),
            'barcode' => [
                        'type' => 'QR_CODE',
                        'value' => $token,
                        'alternateText' => '',
            ],
            'logo' => [
                        'sourceUri' => [
                            'uri' => 'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet.png',
                        ],
            ],
            'heroImage' => [
                        'sourceUri' => [
                            'uri' => 'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/sportkurse_hero.png',
                        ],
                    ]
        ];

        if (array_key_exists('hex_background_color', $overrides)) {
            $payload['hexBackgroundColor'] = $resolved['bg_hex_color'] = Arr::get($overrides, 'hex_background_color', $resolved['bg_hex_color']);
        }

        $response = Http::withToken($accessToken)
            ->patch('https://walletobjects.googleapis.com/walletobjects/v1/genericObject/'.rawurlencode($resolved['object_id']), $payload);

        if ($response->status() === 404) {
            return [
                'updated' => false,
                'message' => 'Objekt nicht gefunden.',
                'object_id' => $resolved['object_id'],
            ];
        }

        if (!$response->successful()) {
            throw new RuntimeException('Google Wallet Objekt konnte nicht aktualisiert werden: '.$response->body());
        }

        return [
            'updated' => true,
            'object_id' => $resolved['object_id'],
            'state' => Arr::get($payload, 'state'),
            'object' => $response->json(),
        ];
    }

    public function updateLoyaltyPoints(User $user): array
    {
        $resolved = $this->resolveConfig($user);
        $accessToken = $this->fetchGoogleAccessToken($resolved);
        $loyaltyPoints = $this->currentSportLoyaltyPoints($user);

        $response = Http::withToken($accessToken)
            ->patch('https://walletobjects.googleapis.com/walletobjects/v1/genericObject/'.rawurlencode($resolved['object_id']), [
                'textModulesData' => $this->buildTextModulesData($user, [
                    'treuepunkte' => $loyaltyPoints,
                ]),
            ]);

        if ($response->status() === 404) {
            return [
                'updated' => false,
                'message' => 'Objekt nicht gefunden.',
                'object_id' => $resolved['object_id'],
                'treuepunkte' => $loyaltyPoints,
            ];
        }

        if (!$response->successful()) {
            throw new RuntimeException('Google Wallet Treuepunkte konnten nicht aktualisiert werden: '.$response->body());
        }

        return [
            'updated' => true,
            'object_id' => $resolved['object_id'],
            'treuepunkte' => $loyaltyPoints,
            'object' => $response->json(),
        ];
    }

    public function updateQrToken(User $user): array
    {
        $resolved = $this->resolveConfig($user);
        $accessToken = $this->fetchGoogleAccessToken($resolved);
        $token = $user->checkinToken?->token;

        $response = Http::withToken($accessToken)
            ->patch('https://walletobjects.googleapis.com/walletobjects/v1/genericObject/'.rawurlencode($resolved['object_id']), [
                'barcode' => [
                        'type' => 'QR_CODE',
                        'value' => $token,
                        'alternateText' => '',
                ],
            ]);

        if ($response->status() === 404) {
            return [
                'updated' => false,
                'message' => 'Objekt nicht gefunden.',
                'object_id' => $resolved['object_id'],
            ];
        }

        if (!$response->successful()) {
            throw new RuntimeException('Google Wallet QR-Code konnte nicht aktualisiert werden: '.$response->body());
        }

        return [
            'updated' => true,
            'object_id' => $resolved['object_id'],
            'object' => $response->json(),
        ];
    }

    private function buildClassTemplateInfo(): array
    {
        return [
            'cardTemplateOverride' => [
                'cardRowTemplateInfos' => [[
                    'twoItems' => [
                        'startItem' => [
                            'firstValue' => [
                                'fields' => [[
                                    'fieldPath' => "object.textModulesData['vereinsmitglied']",
                                ]],
                            ],
                        ],
                        'endItem' => [
                            'firstValue' => [
                                'fields' => [[
                                    'fieldPath' => "object.textModulesData['treuepunkte']",
                                ]],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
    }

    public function broadcastMessage(string $header, string $body): array
    {
        $config = $this->resolveBaseConfig();
        $accessToken = $this->fetchGoogleAccessToken($config);
        $classId = $config['class_id'];

        // Collect all object IDs via pagination
        $objectIds = [];
        $pageToken = null;

        do {
            $query = [
                'classId' => $classId,
                'maxResults' => 1000,
            ];

            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $listResponse = Http::withToken($accessToken)
                ->get('https://walletobjects.googleapis.com/walletobjects/v1/genericObject', $query);

            if (!$listResponse->successful()) {
                throw new RuntimeException('Google Wallet Objekte konnten nicht geladen werden: '.$listResponse->body());
            }

            foreach ($listResponse->json('resources', []) as $obj) {
                $objectIds[] = $obj['id'];
            }

            $pageToken = $listResponse->json('pagination.nextPageToken');
        } while ($pageToken !== null);

        $messageId = 'broadcast_'.time();
        $startAt = now()->utc()->toIso8601String();
        $endAt = now()->utc()->addHours(2)->toIso8601String();
        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($objectIds as $objectId) {
            $msgResponse = Http::withToken($accessToken)
                ->post(
                    'https://walletobjects.googleapis.com/walletobjects/v1/genericObject/'.rawurlencode($objectId).'/addMessage',
                    [
                        'message' => [
                            'id' => $messageId,
                            'header' => $header,
                            'body' => $body,
                            'messageType' => 'TEXT_AND_NOTIFY',
                            'displayInterval' => [
                                'start' => [
                                    'date' => $startAt,
                                ],
                                'end' => [
                                    'date' => $endAt,
                                ],
                            ],
                        ],
                    ]
                );

            if ($msgResponse->successful()) {
                $sent++;
            } else {
                $failed++;
                $errors[] = [
                    'object_id' => $objectId,
                    'error' => $msgResponse->json('error.message', $msgResponse->body()),
                ];
            }
        }

        return [
            'total' => count($objectIds),
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    private function resolveBaseConfig(): array
    {
        $config = config('services.google_wallet', []);

        $issuerId = (string) Arr::get($config, 'issuer_id', '');
        $configuredClassId = (string) Arr::get($config, 'fitness_class_id', '');
        $configuredMemberClassId = (string) Arr::get($config, 'member_class_id', '');
        $classSuffix = (string) Arr::get($config, 'fitness_class_suffix', 'fitnesspass');
        $memberClassSuffix = (string) Arr::get($config, 'member_class_suffix', 'memberpass');
        $issuerName = (string) Arr::get($config, 'issuer_name', config('app.name', 'DJK SG Schönbrunn'));
        $serviceAccountEmail = (string) Arr::get($config, 'service_account_email', '');
        $privateKey = (string) Arr::get($config, 'private_key', '');
        $origin = (string) Arr::get($config, 'origin', config('app.url'));

        if ($issuerId === '' || $serviceAccountEmail === '' || $privateKey === '') {
            throw new RuntimeException('Google Wallet ist nicht vollständig konfiguriert.');
        }

        $normalizedKey = str_replace("\\n", "\n", $privateKey);

        return [
            'issuer_id' => $issuerId,
            'fitness_class_id' => $configuredClassId,
            'member_class_id' => $configuredMemberClassId,
            'fitness_class_suffix' => $classSuffix,
            'member_class_suffix' => $memberClassSuffix,
            'issuer_name' => $issuerName,
            'service_account_email' => $serviceAccountEmail,
            'private_key' => $normalizedKey,
            'origin' => $origin,
        ];
    }

    private function resolveConfig(User $user): array
    {
        $base = $this->resolveBaseConfig();
        $passType = $user->isMember() ? 'memberpass' : 'fitnesspass';
        $passTitle = $user->isMember() ? 'Mitgliederpass' : 'Fitnesspass';
        $hexColor = $user->isMember() ? '#ffc11e' : '#333';


        $classId = $user->isMember()
            ? ($base['member_class_id'] !== ''
                ? $base['member_class_id']
                : $base['issuer_id'].'.'.$this->sanitizeIdentifier($base['member_class_suffix']))
            : ($base['fitness_class_id'] !== ''
                ? $base['fitness_class_id']
                : $base['issuer_id'].'.'.$this->sanitizeIdentifier($base['fitness_class_suffix']));

        return array_merge($base, [
            'class_id' => $classId,
            'object_id' => $base['issuer_id'].'.user_'.$user->id.'_'.$passType,
            'pass_type' => $passType,
            'pass_title' => $passTitle,
            'bg_hex_color' => $hexColor,
        ]);
    }

    private function fetchGoogleAccessToken(array $resolved): string
    {
        $now = time();
        $oauthClaims = [
            'iss' => $resolved['service_account_email'],
            'scope' => 'https://www.googleapis.com/auth/wallet_object.issuer',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $assertion = $this->signJwt($oauthClaims, $resolved['private_key']);

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if (!$tokenResponse->successful()) {
            throw new RuntimeException('Google OAuth Token konnte nicht geladen werden: '.$tokenResponse->body());
        }

        $accessToken = (string) $tokenResponse->json('access_token');

        if ($accessToken === '') {
            throw new RuntimeException('Google OAuth Token leer.');
        }

        return $accessToken;
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

    private function buildTextModulesData(User $user, array $overrides = []): array
    {
        return [
            [
                'id' => 'vereinsmitglied',
                'header' => 'Vereinsmitglied',
                'body' => (string) Arr::get($overrides, 'vereinsmitglied', $user->isMember() ? 'Ja' : 'Nein'),
            ],
            [
                'id' => 'treuepunkte',
                'header' => 'Treuepunkte',
                'body' => (string) Arr::get($overrides, 'treuepunkte', $this->currentSportLoyaltyPoints($user)),
            ],
        ];
    }

    private function currentSportLoyaltyPoints(User $user): int
    {
        return (int) ($user->loyaltyAccount?->balanceByOrigin('sport') ?? 0);
    }

    private function sanitizeIdentifier(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9._-]/', '_', $value) ?? '';

        return trim($sanitized, '_') ?: 'fitnesspass';
    }
}
