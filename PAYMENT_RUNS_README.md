# Payment Runs & Journal Entries - Implementierung

## ✅ Fertig implementiert:

### Migrations
- `2026_02_21_100000_create_payment_runs_table.php` - Einzugsläufe
- `2026_02_21_100001_create_journal_entries_table.php` - Journal (vorbereitet für doppelte Buchführung)
- `2026_02_21_100002_add_payment_run_to_membership_payments.php` - Verknüpfung

### Models
- `app/Models/Member/PaymentRun.php` - PaymentRun Model mit Beziehungen
- `app/Models/Member/JournalEntry.php` - JournalEntry Model
- `app/Models/Member/MembershipPayment.php` - Erweitert um payment_run_id und paymentRun() Beziehung

### Services
- `app/Services/Member/PaymentRunService.php` - Komplette Business-Logik für Einzugsläufe

---

## 🚀 Migration ausführen

```bash
php artisan migrate
```

---

## 🛡️ Sichere Migrations-Strategie (Produktion)

Ziel der Umstellung:
- `membership_payments` enthält nur noch die fachlichen Forderungsdaten
- Zahlungsdurchführungen liegen in der neuen Tabelle `payments`

### 1) Deploy-Reihenfolge

1. **Code deployen**, der bereits mit neuem Modell umgehen kann (Lesen/Schreiben über `payments`).
2. **Migrationen ausführen**:
   - `2026_03_06_120001_create_payments_table.php`
   - `2026_03_06_120000_update_membership_payments_structure.php`
3. Queue Worker neu starten:

```bash
php artisan queue:restart
```

### 2) Daten-Backfill (falls Altdaten vorhanden)

Vorhandene bezahlte/abgeschlossene `membership_payments` sollten in `payments` gespiegelt werden,
damit Historie konsistent bleibt. Beispiel über Tinker:

```bash
php artisan tinker
```

```php
use App\Models\Payment\MembershipPayment;
use App\Models\Payment\Payment;

MembershipPayment::query()
   ->whereIn('status', ['collected', 'cancelled', 'failed'])
   ->chunkById(500, function ($rows) {
      foreach ($rows as $membershipPayment) {
         Payment::firstOrCreate(
            [
               'source_type' => MembershipPayment::class,
               'source_id' => $membershipPayment->id,
               'reference' => 'mp-'.$membershipPayment->id,
            ],
            [
               'amount' => $membershipPayment->amount,
               'method' => 'sepa',
               'status' => $membershipPayment->status,
               'paid_at' => null,
               'payment_run_id' => null,
               'bank_account_id' => null,
            ]
         );
      }
   });
```

### 3) Smoke Checks direkt nach Deployment

- Anzahl Datensätze plausibel: `membership_payments` vs. `payments`
- Statuswerte nur: `pending | collected | failed | cancelled`
- Beispiel-Detailansicht eines Mitglieds lädt ohne Fehler
- Payment-Run-Prozess erzeugt neue Einträge in `payments`

### 4) Rollback-Hinweis

- Die `down()`-Migration stellt alte Spalten in `membership_payments` wieder her.
- Ein echtes Daten-Rollback aus `payments` zurück in alte Felder ist **nicht automatisch** enthalten.
- Für Produktion daher vor der Migration ein DB-Backup erstellen.

---

## 📝 Verwendung

### 1. Einzugslauf erstellen

```php
use App\Services\Payment\PaymentRunService;
use Illuminate\Support\Carbon;

$service = app(PaymentRunService::class);

// Neuen Einzugslauf erstellen
$paymentRun = $service->createPaymentRun(
    Carbon::parse('2026-03-15'),
    'Monatlicher Einzug März 2026'
);

// Offene Zahlungen hinzufügen
$count = $service->addPendingPayments($paymentRun);
// Oder nur Zahlungen bis zu einem bestimmten Datum:
$count = $service->addPendingPayments($paymentRun, Carbon::parse('2026-03-01'));
```

### 2. Einzugslauf einreichen

```php
// Run einreichen (erstellt automatisch Journal-Entry)
$service->submitPaymentRun($paymentRun, 'Sparkasse-Hauptkonto');
```

### 3. Einzugslauf abschließen

```php
// Alle Zahlungen als "paid" markieren
$service->completePaymentRun($paymentRun);
```

### 4. Einzelne Zahlung aktualisieren

```php
$payment = MembershipPayment::find($paymentId);
$service->updatePaymentStatus($payment, 'paid');
// oder
$service->updatePaymentStatus($payment, 'cancelled');
```

### 5. Übersicht offener Zahlungen

```php
// Alle offenen Zahlungen
$pending = $service->getPendingPayments();

// Zusammenfassung
$summary = $service->getPendingPaymentsSummary();
/*
[
    'count' => 150,
    'total_amount' => 12500.00,
    'oldest_date' => '2026-01-15',
    'newest_date' => '2026-03-01',
]
*/
```

---

## 🔄 Workflow

```
1. DRAFT (Entwurf)
   ↓ createPaymentRun()
   ↓ addPendingPayments()
   
2. SUBMITTED (Eingereicht)
   ↓ submitPaymentRun() → erstellt JournalEntry
   
3. COMPLETED (Abgeschlossen)
   ↓ completePaymentRun() → markiert alle Zahlungen als "paid"
   
4. CANCELLED (Storniert)
   → cancelPaymentRun() → entfernt Verknüpfung zu Zahlungen
```

---

## 🏦 Journal Entries (Buchführung)

Jede `submitPaymentRun()` Operation erstellt automatisch einen Journal Entry:

```php
JournalEntry {
    entry_date: '2026-03-15',
    entry_type: 'payment_run',
    reference: 'SEPA-2026-03-01',
    description: 'SEPA Einzug SEPA-2026-03-01',
    amount: 12500.00,
    debit_account: '1200',  // Bank
    credit_account: '4000', // Mitgliedsbeiträge
    bank_reference: 'Sparkasse-Hauptkonto',
}
```

**Später erweiterbar:**
- Kontenrahmen (SKR03/SKR04)
- Detaillierte Soll/Haben-Buchungssätze
- Kostenstellen
- DATEV-Export

---

## 📊 Datenbank-Struktur

### payment_runs
- `reference` - Eindeutige Referenz (z.B. "SEPA-2026-03-01")
- `execution_date` - Ausführungsdatum
- `status` - draft, submitted, completed, cancelled
- `total_amount` - Gesamtbetrag (automatisch berechnet)
- `payment_count` - Anzahl Zahlungen (automatisch berechnet)

### journal_entries
- `entry_date` - Buchungsdatum
- `entry_type` - Typ (payment_run, manual, correction)
- `reference` - Referenz (z.B. zum PaymentRun)
- `debit_account` - Soll-Konto
- `credit_account` - Haben-Konto
- `bank_reference` - Konkretes Bankkonto
- `amount` - Betrag

### membership_payments
- `payment_run_id` - Zuordnung zum Einzugslauf (nullable)

---

## 🎯 Nächste Schritte (optional)

1. **UI erstellen** - Livewire Komponente für PaymentRun-Verwaltung
2. **SEPA-XML Export** - Generierung von SEPA-XML Dateien
3. **Dashboard** - Übersicht offener/abgeschlossener Einzüge
4. **Reports** - Auswertungen nach Bank, Zeitraum, etc.
5. **Rücklastschriften** - Handling von fehlgeschlagenen Zahlungen

---

## ✅ Teste das System

```bash
# Tinker öffnen
php artisan tinker

# Test-Einzugslauf erstellen
$service = app(\App\Services\Payment\PaymentRunService::class);
$run = $service->createPaymentRun(now()->addDays(7));
$service->addPendingPayments($run);

# Anzeigen
$run->refresh();
echo "Zahlungen: {$run->payment_count}\n";
echo "Betrag: {$run->total_amount} €\n";
```
