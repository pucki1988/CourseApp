<?php

namespace App\Services\User;

use App\Models\User;
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
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet.png',
            'logo.png'
        );
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet.png',
            'logo@2x.png'
        );
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet.png',
            'icon.png'
        );
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/logo_wallet.png',
            'icon@2x.png'
        );
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/sportkurse_hero.png',
            'strip.png'
        );
        $pass->addRemoteFile(
            'https://www.djk-sg-schoenbrunn.de/templates/sonderseite/images/sportkurse_hero.png',
            'strip@2x.png'
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

        $bgColor = $isMember ? 'rgb(255, 193, 30)' : 'rgb(51, 51, 51)';
        $fgColor = $isMember ? 'rgb(0, 0, 0)' : 'rgb(255, 255, 255)';

        return [
            'formatVersion' => 1,
            'passTypeIdentifier' => $config['pass_type_identifier'],
            'teamIdentifier' => $config['team_identifier'],
            'serialNumber' => 'user_' . $user->id . '_' . ($isMember ? 'memberpass' : 'fitnesspass'),
            'organizationName' => $config['organization_name'],
            'description' => $isMember ? 'Mitgliederpass' : 'Fitnesspass',
            'backgroundColor' => $bgColor,
            'foregroundColor' => $fgColor,
            'labelColor' => $fgColor,
            'logoText' => $config['organization_name'],
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

        if ($passTypeIdentifier === '' || $teamIdentifier === '') {
            throw new RuntimeException('Apple Wallet ist nicht vollständig konfiguriert (passTypeIdentifier oder teamIdentifier fehlt).');
        }

        if ($certificatePath === '' && $certificateContent === '') {
            throw new RuntimeException('Apple Wallet ist nicht vollständig konfiguriert (kein Zertifikat).');
        }

        return [
            'pass_type_identifier' => $passTypeIdentifier,
            'team_identifier' => $teamIdentifier,
            'organization_name' => $organizationName,
            'certificate_path' => $certificatePath,
            'certificate_content' => $certificateContent,
            'certificate_password' => $certificatePassword,
        ];
    }
}
