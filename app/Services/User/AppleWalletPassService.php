<?php

namespace App\Services\User;

use App\Models\AppleWalletDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use PKPass\PKPass;
use RuntimeException;

class AppleWalletPassService
{
    public function generate(User $user): string
    {
        $token = $user->checkinToken?->token;

        if (!$token) {
            throw new RuntimeException('Kein aktiver Check-in-Token vorhanden.');
        }

        $config = $this->resolveConfig($user);

        $pass = new PKPass();

        // Certificate
        if (!empty($config['certificate_content'])) {
            $pass->setCertificateString(base64_decode($config['certificate_content']));
        } elseif (!empty($config['certificate_path'])) {
            $certificatePath = $this->resolveCertificatePath($config['certificate_path']);

            if (!file_exists($certificatePath)) {
                throw new RuntimeException('Apple Wallet Zertifikat nicht gefunden: '.$certificatePath);
            }

            $pass->setCertificatePath($certificatePath);
        } else {
            throw new RuntimeException('Apple Wallet ist nicht vollständig konfiguriert (kein Zertifikat).');
        }

        $pass->setCertificatePassword($config['certificate_password']);

        // Pass JSON
        $pass->setData($this->buildPassData($user, $token, $config));

        // Images (logo, icon required by Apple)
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet_apple_logo.png',
            'logo.png'
        );
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet_apple_logo.png',
            'logo@2x.png'
        );
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet_apple_icon.png',
            'icon.png'
        );
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet_apple_icon.png',
            'icon@2x.png'
        );
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet_apple_thumbnail.png',
            'thumbnail.png'
        );
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet_apple_thumbnail.png',
            'thumbnail@2x.png'
        );

        $pkpassContent = $pass->create();

        if ($pkpassContent === false) {
            throw new RuntimeException('Apple Wallet Pass konnte nicht erstellt werden.');
        }

        return $pkpassContent;
    }

    private function buildPassData(User $user, string $token, array $config): array
    {
        $isMember = $user->isMember();
        $loyaltyPoints = $this->currentSportLoyaltyPoints($user);
        $serialNumber = $this->serialNumberForUser($user);

        $bgColor = $isMember ? 'rgb(255, 193, 30)' : 'rgb(51, 51, 51)';
        $fgColor = $isMember ? 'rgb(0, 0, 0)' : 'rgb(255, 255, 255)';

        return [
            'formatVersion' => 1,
            'passTypeIdentifier' => $config['pass_type_identifier'],
            'teamIdentifier' => $config['team_identifier'],
            'serialNumber' => $serialNumber,
            'webServiceURL' => rtrim($config['web_service_url'], '/') . '/',
            'authenticationToken' => $this->authenticationTokenForSerial($serialNumber),
            'organizationName' => $config['organization_name'],
            'description' => $isMember ? 'Mitgliederpass' : 'Fitnesspass',
            'backgroundColor' => $bgColor,
            'foregroundColor' => $fgColor,
            'labelColor' => $fgColor,
            'logoText' => $isMember ? 'Mitgliederpass' : 'Fitnesspass',
            'generic' => [
                'primaryFields' => [
                    [
                        'key' => 'name',
                        'label' => 'Name',
                        'value' => $user->name ?: $user->email,
                    ],
                ],
                'secondaryFields' => [
                    [
                        'key' => 'vereinsmitglied',
                        'label' => 'Vereinsmitglied',
                        'value' => $isMember ? 'Ja' : 'Nein',
                    ],
                    [
                        'key' => 'treuepunkte',
                        'label' => 'Treuepunkte',
                        'value' => (string) $loyaltyPoints,
                    ],
                ],
            ],
            'barcodes' => [
                [
                    'message' => $token,
                    'format' => 'PKBarcodeFormatQR',
                    'messageEncoding' => 'iso-8859-1',
                ],
            ],
            'barcode' => [
                'message' => $token,
                'format' => 'PKBarcodeFormatQR',
                'messageEncoding' => 'iso-8859-1',
            ],
        ];
    }

    private function currentSportLoyaltyPoints(User $user): int
    {
        return (int) ($user->loyaltyAccount?->balanceByOrigin('sport') ?? 0);
    }

    public function serialNumberForUser(User $user): string
    {
        $kind = $user->isMember() ? 'memberpass' : 'fitnesspass';

        return 'user_'.$user->id.'_'.$kind;
    }

    public function extractUserIdFromSerialNumber(string $serialNumber): ?int
    {
        if (!preg_match('/^user_(\d+)_(memberpass|fitnesspass)$/', $serialNumber, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public function authenticationTokenForSerial(string $serialNumber): string
    {
        $salt = (string) config('services.apple_wallet.auth_token_salt', '');

        if ($salt === '') {
            throw new RuntimeException('APPLE_WALLET_AUTH_TOKEN_SALT fehlt.');
        }

        return hash('sha256', $serialNumber.'|'.$salt);
    }

    public function extractAuthenticationToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');

        if (!preg_match('/^ApplePass\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $token = trim((string) $matches[1]);

        return $token !== '' ? $token : null;
    }

    public function hasValidAuthenticationToken(Request $request, string $serialNumber): bool
    {
        $providedToken = $this->extractAuthenticationToken($request);

        if (!$providedToken) {
            return false;
        }

        return hash_equals($this->authenticationTokenForSerial($serialNumber), $providedToken);
    }

    public function markPassUpdatedForUser(User $user): void
    {
        $serialNumber = $this->serialNumberForUser($user);

        AppleWalletDevice::query()
            ->where('serial_number', $serialNumber)
            ->update(['updated_at' => now()]);
    }

    private function resolveCertificatePath(string $certificatePath): string
    {
        if ($certificatePath === '') {
            return $certificatePath;
        }

        if ($this->isAbsolutePath($certificatePath)) {
            return $certificatePath;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $certificatePath));
    }

    private function isAbsolutePath(string $path): bool
    {
        return Str::startsWith($path, ['/', '\\']) || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }

    private function resolveConfig(User $user): array
    {
        $config = config('services.apple_wallet', []);

        $passTypeIdentifier = (string) Arr::get($config, 'pass_type_identifier', '');
        $teamIdentifier = (string) Arr::get($config, 'team_identifier', '');
        $organizationName = (string) Arr::get($config, 'organization_name', config('app.name', 'DJK SG Schönbrunn'));
        $certificatePath = (string) Arr::get($config, 'certificate_path', '');
        $certificateContent = (string) Arr::get($config, 'certificate_content', '');
        $certificatePassword = (string) Arr::get($config, 'certificate_password', '');
        $webServiceUrl = (string) Arr::get($config, 'web_service_url', '');
        $authTokenSalt = (string) Arr::get($config, 'auth_token_salt', '');

        if ($passTypeIdentifier === '' || $teamIdentifier === '') {
            throw new RuntimeException('Apple Wallet ist nicht vollständig konfiguriert (passTypeIdentifier oder teamIdentifier fehlt).');
        }

        if ($certificatePath === '' && $certificateContent === '') {
            throw new RuntimeException('Apple Wallet ist nicht vollständig konfiguriert (kein Zertifikat).');
        }

        if ($webServiceUrl === '' || $authTokenSalt === '') {
            throw new RuntimeException('Apple Wallet ist nicht vollständig konfiguriert (web_service_url oder auth_token_salt fehlt).');
        }

        return [
            'pass_type_identifier' => $passTypeIdentifier,
            'team_identifier' => $teamIdentifier,
            'organization_name' => $organizationName,
            'certificate_path' => $certificatePath,
            'certificate_content' => $certificateContent,
            'certificate_password' => $certificatePassword,
            'web_service_url' => $webServiceUrl,
            'auth_token_salt' => $authTokenSalt,
        ];
    }
}
