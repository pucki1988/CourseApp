# Trainer-Abrechnung

## Übersicht

Das System ermöglicht die automatische Abrechnung von Trainern basierend auf den durchgeführten Terminen eines Monats. Die Abrechnung erfolgt per E-Mail an jeden Trainer mit einer detaillierten Aufstellung aller Termine und der Vergütung.

## Funktionen

- ✅ Automatische Berechnung der monatlichen Vergütung pro Trainer
- ✅ Berücksichtigung der Teilnehmerzahl pro Termin
- ✅ Verwendung der individuellen Vergütungsstufen (CoachCompensationTier)
- ✅ E-Mail-Versand mit detaillierter Aufstellung
- ✅ Unterstützung für Test-Modus (Dry-Run)
- ✅ Einzelne oder alle Trainer abrechnen

## Verwendung

### Abrechnung für den letzten Monat

```bash
php artisan coaches:generate-billing
```

Dieser Befehl:
- Rechnet automatisch den **vorherigen Monat** ab
- Verarbeitet **alle aktiven Trainer**
- Sendet E-Mails an die Trainer

### Abrechnung für einen bestimmten Monat

```bash
php artisan coaches:generate-billing --month=2024-03
```

Rechnet den angegebenen Monat ab (Format: YYYY-MM).

### Abrechnung für einen einzelnen Trainer

```bash
php artisan coaches:generate-billing --coach=5
```

Rechnet nur den Trainer mit der ID 5 ab.

### Test-Modus (ohne E-Mail-Versand)

```bash
php artisan coaches:generate-billing --dry-run
```

Zeigt die Abrechnungen an, **ohne E-Mails zu versenden**. Ideal zum Testen.

### Kombinationen

```bash
# Bestimmter Monat für einen Trainer im Test-Modus
php artisan coaches:generate-billing --month=2024-02 --coach=3 --dry-run

# Alle Trainer für einen bestimmten Monat
php artisan coaches:generate-billing --month=2024-01
```

## Abrechnungslogik

### Welche Termine werden abgerechnet?

1. **Nur Termine des Trainers**: Termine von Kursen, bei denen der Trainer zugeordnet ist
2. **Zeitraum**: Nur Termine im angegebenen Monat
3. **Status**: Nur Termine mit Status `completed` oder `active`
4. **Teilnehmer**: Nur gebuchte Plätze (`status = 'booked'` oder `'checked_in'`)

### Vergütungsberechnung

Die Vergütung wird pro Termin basierend auf der **Teilnehmerzahl** berechnet:

1. System ermittelt die Anzahl der Teilnehmer für den Termin
2. Sucht die passende Vergütungsstufe (`CoachCompensationTier`) für den Trainer
3. Verwendet die Vergütung der Stufe, die zur Teilnehmerzahl passt (`min_participants` bis `max_participants`)
4. Summiert alle Vergütungen des Monats

**Beispiel:**

```
Trainer hat Vergütungsstufen:
- 1-5 Teilnehmer: 30€
- 6-10 Teilnehmer: 45€
- 11-20 Teilnehmer: 60€

Termine im Februar:
- 05.02. Yoga: 4 Teilnehmer → 30€
- 12.02. Yoga: 8 Teilnehmer → 45€
- 19.02. Yoga: 12 Teilnehmer → 60€
- 26.02. Yoga: 0 Teilnehmer → 0€ (keine passende Stufe)

Gesamt: 135€
```

## E-Mail

Die E-Mail enthält:

1. **Zusammenfassung**:
   - Abrechnungszeitraum
   - Anzahl der Termine
   - Gesamtvergütung

2. **Detaillierte Auflistung**:
   - Datum des Termins
   - Kursname
   - Uhrzeit (Start - Ende)
   - Anzahl Teilnehmer
   - Vergütung für diesen Termin

3. **Gesamtsumme**

### Voraussetzungen für E-Mail-Versand

- Der Trainer muss einem **User** zugeordnet sein (`coach.user_id`)
- Der User muss eine **gültige E-Mail-Adresse** haben

Trainer ohne E-Mail-Adresse werden übersprungen.

## Automatisierung mit Cron

Um die Abrechnung automatisch jeden Monat durchzuführen, kann der Befehl in der `app/Console/Kernel.php` oder Laravel 11+ `routes/console.php` eingetragen werden:

### Laravel 10 und älter (Kernel.php)

```php
protected function schedule(Schedule $schedule)
{
    // Jeden 1. des Monats um 8:00 Uhr
    $schedule->command('coaches:generate-billing')
        ->monthlyOn(1, '08:00');
}
```

### Laravel 11+ (routes/console.php)

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('coaches:generate-billing')
    ->monthlyOn(1, '08:00');
```

## Komponenten

### 1. Service-Methode (`CoachService::calculateMonthlyBilling`)

Berechnet die Abrechnungsdaten für einen Trainer und Monat.

**Rückgabe:**
```php
[
    'coach' => Coach,
    'year' => 2024,
    'month' => 3,
    'month_name' => 'März 2024',
    'billing_items' => [...],
    'total_compensation' => 450.00,
    'total_slots' => 15
]
```

### 2. Command (`GenerateCoachBilling`)

Laravel Artisan Command zur Ausführung der Abrechnung.

### 3. Mail (`CoachMonthlyBillingMail`)

Mailable-Klasse für den E-Mail-Versand.

### 4. Email-Template (`emails/coaches/monthly-billing.blade.php`)

Blade-Template für die E-Mail mit professionellem Design.

## Troubleshooting

### "No coaches found to bill"

- Stellen Sie sicher, dass aktive Trainer (`active = true`) existieren
- Bei Verwendung von `--coach`: Prüfen Sie, ob die ID korrekt ist

### "Skipped: No email address found"

- Trainer muss einem User zugeordnet sein
- User muss eine E-Mail-Adresse haben
- Lösung: `user_id` für den Trainer setzen

### Keine Vergütung berechnet (0€)

- Prüfen Sie, ob Vergütungsstufen (`CoachCompensationTier`) für den Trainer definiert sind
- Stellen Sie sicher, dass die Stufen die tatsächlichen Teilnehmerzahlen abdecken
- Prüfen Sie, ob Teilnehmer mit Status `booked` oder `checked_in` existieren

### E-Mail wird nicht versendet

- Prüfen Sie die Mail-Konfiguration in `config/mail.php`
- Testen Sie mit `--dry-run`, ob die Daten korrekt berechnet werden
- Prüfen Sie die Logs in `storage/logs/laravel.log`

## Erweiterungsmöglichkeiten

### PDF-Anhang

Sie können die E-Mail um einen PDF-Anhang erweitern:

```php
// In CoachMonthlyBillingMail.php
use Illuminate\Mail\Mailables\Attachment;

public function attachments(): array
{
    return [
        Attachment::fromData(fn () => $this->generatePDF(), 'abrechnung.pdf')
            ->withMime('application/pdf'),
    ];
}
```

### CC an Verwaltung

```php
// In GenerateCoachBilling.php
Mail::to($coach->user->email)
    ->cc('verwaltung@example.com')
    ->send(new CoachMonthlyBillingMail($billingData));
```

### Export als CSV

```php
// Nach dem Billing-Prozess
$csvData = $this->coachService->exportBillingToCSV($year, $month);
Storage::put("billing/{$year}-{$month}.csv", $csvData);
```

## Fragen?

Bei Fragen oder Problemen wenden Sie sich an Ihren Entwickler oder Administrator.
