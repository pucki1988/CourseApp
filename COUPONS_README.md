# Rabattcoupons für Kurse

Unter **Kursverwaltung → Rabattcoupons** können Benutzer mit `courses.manage`
Coupons anlegen sowie aktivieren und deaktivieren. Unterstützt werden
Prozentrabatte, Festbeträge in EUR und „Buy X Get Y“.
Optional lassen sich Gültigkeitszeitraum sowie Einlösungsgrenzen insgesamt und
pro Benutzer festlegen. Coupon-Codes werden in Großbuchstaben gespeichert.
Bestehende Gutscheine für Wallet-Guthaben sind davon unabhängig.

Für Buy X Get Y lässt sich der Rabatt für die Y-Termine als Prozentsatz
festlegen (standardmäßig 100 %). Optional begrenzt „Maximal rabattierte Termine
pro Buchung“ die Anzahl der Y-Termine, auf die der Rabatt innerhalb einer
Buchung angewendet wird. Ein leeres Feld bedeutet keine Begrenzung; es ist
unabhängig von der Einlösungsgrenze pro Benutzer.

Vor dem Einsatz müssen die Datenbankmigrationen ausgeführt werden:

```shell
php artisan migrate
```

## Kursbuchungs-API

Die separate Buchungs-App prüft den eingegebenen Code vor dem Kauf mit
`POST /api/booking/{course}/coupon/validate` (authentifiziert). Bei
Terminbuchungen müssen dieselben `slots` wie bei der Buchung übergeben werden:

```json
{
  "slots": [1, 2, 3],
  "coupon_code": "AKTION"
}
```

Bei Erfolg liefert die API beispielsweise
`{"coupon_code":"AKTION","coupon_discount":"5.00","total_price":"25.00"}`.
Die Beträge sind EUR nach Mitgliederrabatt und Coupon, vor Treuepunkten.
Ungültige oder nicht anwendbare Coupons und ungültige Terminauswahlen liefern
HTTP 422 mit Feldfehlern. Die Prüfung reserviert oder verbraucht den Coupon
nicht. Die App muss das Ergebnis bei Änderungen des Codes oder der Termine
verwerfen und erneut prüfen; bei Fehlern darf sie den Coupon nicht anwenden.
Beim Kauf wird der Code erneut geprüft, da sich seine Gültigkeit zwischenzeitlich
ändern kann. Ein Fehler beim Kauf muss ebenfalls angezeigt werden.

Die separate Buchungs-App kann beim bestehenden Aufruf `POST /api/booking/{course}`
das optionale Feld `coupon_code` übergeben, zum Beispiel:

```json
{
  "slots": [1, 2, 3],
  "coupon_code": "AKTION"
}
```

Das Feld wird auch für Ganzkursbuchungen unterstützt. Die API prüft den Coupon
serverseitig; ungültige, abgelaufene, ausgeschöpfte oder nicht anwendbare Codes
führen zu HTTP 422 mit einer Fehlermeldung. Bei erfolgreicher Buchung enthalten
die Buchungsdaten `coupon_code`, `coupon_discount` und den rabattierten
`total_price`. Die Buchungsliste enthält dieselben Coupon-Felder.

Der Coupon wird nach dem Mitgliederrabatt einmal auf die gesamte Buchung
angewandt, vor dem Einlösen von Treuepunkten. Bei Buy X Get Y zählt jeder
ausgewählte Termin als eine Einheit; der Rabatt wird anhand des Durchschnittspreises
aller ausgewählten Termine nach Mitgliederrabatt berechnet. Eine Ganzkursbuchung zählt als eine
Einheit. Der Couponrabatt und ggf. eingesetzte Punkte werden für spätere
Teilstornierungen centgenau anteilig auf die Termine verteilt. Bereits
zurückgegebene Treuepunkte werden nicht erneut erstattet.

Eine Einlösung zählt bei erfolgreicher Erstellung der Buchung, auch wenn eine
Zahlung später abgebrochen oder die Buchung storniert wird. Schlägt die
Buchungserstellung selbst fehl, wird auch die Einlösung zurückgerollt. Ergibt
sich durch einen Coupon ein Zahlbetrag von null, wird die Buchung ohne externen
Zahlungsanbieter als bezahlt verbucht.
