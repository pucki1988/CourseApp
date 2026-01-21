<?php

namespace App\Services\Member;

use App\Models\Member\Member;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Cookie\CookieJar;

class MemberImportService
{
    public function importData(): int
    {
        $html = $this->fetchViaPost();
        $rows = $this->extract($html);

        return $this->importNewMembers($rows);
    }

    protected function fetch(string $url): string
    {
        return Http::get($url)->body();
    }

    protected function fetchViaPost(): string
    {

        $params=["Vereinsnummer" => config('members.external_system_club_id'),"Benutzername"=>config('members.external_system_user'),"Passwort"=> config('members.external_system_password')];
        $loginUrl=config('members.external_system_url',"https://212.58.87.29/eingabe.php");
       

        $jar = new CookieJar();

        // 1️⃣ POST: Login / Session starten
        $response = Http::withOptions([
                'allow_redirects' => true,  // folgt 302 Redirect
                'verify' => false,          // SSL IP/Hostname umgehen
                'cookies' => $jar,          // speichert PHPSESSID
            ])->withHeaders([
                'User-Agent' => 'PostmanRuntime/7.51.0',
                'Cache-Control' => 'no-cache',
             ])
            ->attach('Vereinsnummer', $params['Vereinsnummer'])
            ->attach('Benutzername', $params['Benutzername'])
            ->attach('Passwort', $params['Passwort'])
            ->post($loginUrl, $params);

        // Optional Debug: HTML nach POST + Redirect
        file_put_contents(storage_path('import_debug_post.html'), $response->body());
        
        // 2️⃣ GET: Mitgliederliste mit Session-Cookie
    

        if (!$response->successful()) {
            throw new \RuntimeException('Import-Quelle nicht erreichbar');
        }

        return $response->body();

    }

    protected function extract(string $html): array
    {
        preg_match('/var\s+_grid_Mitglied\s*=\s*(\[\{.*?\}\])<\/script>/s', $html, $matches);

        if (empty($matches[1])) {
            throw new \RuntimeException('_grid_Mitglied nicht gefunden');
        }
        

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    protected function importNewMembers(array $rows): int
    {
        $importIds = collect($rows)
            ->pluck('lID')
            ->filter()
            ->values()
            ->toArray();

        // vorhandene IDs einmalig laden → performant
        $existingIds = Member::pluck('external_id')->toArray();

        $newRows = array_filter($rows, fn ($row) =>
            !in_array($row['lID'], $existingIds)
        );
        // Zu löschende IDs
        $idsToDelete = array_diff($existingIds, $importIds);
        $count=0;

        DB::transaction(function () use ($newRows, $idsToDelete, &$count) {
            // ❌ Entfernen
            if (!empty($idsToDelete)) {
                Member::whereIn('external_id', $idsToDelete)->delete();
            }
            
            foreach ($newRows as $row) {
                Member::create(
                    $this->transform($row)
                );
                $count++;
            }
        });

        return $count;
    }

    protected function transform(array $row): array
    {
        [$lastName,$firstName] = $this->splitName($row['strxName']);

        return [
            'external_id'  => $row['lID'],
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'entry_date'     => $this->parseDate($row['codtEintrittsdatum']),
            'city'           => $row['strxOrt'] ?? null,
            'zip_code'       => $row['strxPostleitzahl'] ?? null,
            'street'         => $row['strxStrasse'] ?? null,
            'gender'         => $this->mapGender($row['lGeschlecht'] ?? null),
            'birth_date'     => $this->parseDate($row['codtGeburtsdatum']), // falls später vorhanden
        ];
    }

    protected function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
        ];
    }

    protected function mapGender(?string $value): ?string
    {
        return match ($value) {
            '1' => 'male',
            '2' => 'female',
            '3' => 'diverse',
            default => null,
        };
    }

    protected function parseDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        return substr($date, 0, 10); // YYYY-MM-DD
    }
}