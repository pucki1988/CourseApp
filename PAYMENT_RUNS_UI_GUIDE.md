# Payment Runs UI - Bedienungsanleitung

## 🎯 Zugriff

Die Payment Runs UI ist erreichbar unter:
- **URL:** `/payment-runs`
- **Menü:** Mitglieder verwalten → Einzugsläufe

## 📊 Hauptansicht

### Statistik-Dashboard (oben)
Zeigt drei wichtige Kennzahlen:
- **Offene Zahlungen** - Anzahl der noch nicht eingezogenen Zahlungen
- **Gesamtbetrag offen** - Summe aller offenen Zahlungen
- **Älteste offene Zahlung** - Datum der ältesten fälligen Zahlung

### Einzugsläufe-Liste
Zeigt alle erstellten Einzugsläufe mit:
- Referenz (z.B. "SEPA-2026-03-01")
- Status-Badge (Entwurf/Eingereicht/Abgeschlossen/Storniert)
- Ausführungsdatum
- Anzahl Zahlungen und Gesamtbetrag
- Notizen
- Zeitstempel (eingereicht/abgeschlossen)

## 🔄 Workflow

### 1. Neuen Einzugslauf erstellen

1. Klicken Sie auf **"Neuer Einzugslauf"**
2. Wählen Sie das **Ausführungsdatum** (Standard: 7 Tage in der Zukunft)
3. Geben Sie optional **Bankkonto-Referenz** ein (z.B. "Sparkasse-Hauptkonto")
4. Fügen Sie optional **Notizen** hinzu
5. Klicken Sie auf **"Erstellen"**

**Was passiert:**
- Neuer PaymentRun wird mit Status "Entwurf" erstellt
- Automatisch werden ALLE offenen Zahlungen mit Bankverbindung bis zum gewählten Datum hinzugefügt
- Sie sehen eine Erfolgsmeldung mit der Anzahl der hinzugefügten Zahlungen

### 2. Zahlungen anzeigen

Klicken Sie auf **"Zahlungen"** bei einem Run, um:
- Alle enthaltenen Zahlungen zu sehen
- Details zu jedem Zahler anzuzeigen
- IBAN (maskiert) zu sehen
- Status jeder Zahlung zu prüfen

### 3. Einzugslauf einreichen

**Nur bei Status "Entwurf" möglich**

Klicken Sie auf **"Einreichen"**

**Was passiert:**
- Status wechselt zu "Eingereicht"
- Automatisch wird ein **Journal-Eintrag** erstellt mit:
  - Soll-Konto: 1200 (Bank)
  - Haben-Konto: 4000 (Mitgliedsbeiträge)
  - Bank-Referenz: wie eingegeben
  - Betrag: Summe aller Zahlungen
- Zeitstempel "Eingereicht am" wird gesetzt

### 4. Einzugslauf abschließen

**Nur bei Status "Eingereicht" möglich**

Klicken Sie auf **"Abschließen"**

**Was passiert:**
- Status wechselt zu "Abgeschlossen"
- ALLE Zahlungen im Run werden automatisch als "bezahlt" markiert
- `paid_at` Zeitstempel wird gesetzt
- Zeitstempel "Abgeschlossen am" wird gesetzt

### 5. Einzugslauf stornieren

**Möglich bei Status "Entwurf" oder "Eingereicht"**

Klicken Sie auf **"Stornieren"**

**Was passiert:**
- Status wechselt zu "Storniert"
- Verknüpfung zu allen Zahlungen wird entfernt (payment_run_id wird auf NULL gesetzt)
- Zahlungen bleiben im Status "pending" und können einem neuen Run hinzugefügt werden

## 🎨 Status-Badges

- **Grau (Entwurf)** - Run wurde erstellt, kann noch bearbeitet werden
- **Blau (Eingereicht)** - Run wurde eingereicht, Journal-Eintrag erstellt
- **Grün (Abgeschlossen)** - Alle Zahlungen als bezahlt markiert
- **Rot (Storniert)** - Run wurde storniert, Zahlungen freigegeben

## 📋 Verfügbare Aktionen nach Status

| Status | Zahlungen anzeigen | Einreichen | Abschließen | Stornieren |
|--------|-------------------|------------|-------------|------------|
| Entwurf | ✅ | ✅ | ❌ | ✅ |
| Eingereicht | ✅ | ❌ | ✅ | ✅ |
| Abgeschlossen | ✅ | ❌ | ❌ | ❌ |
| Storniert | ✅ | ❌ | ❌ | ❌ |

## 💡 Tipps

1. **Vor dem Einreichen prüfen:**
   - Klicken Sie auf "Zahlungen" um die Liste zu kontrollieren
   - Prüfen Sie den Gesamtbetrag

2. **Regelmäßige Einzüge:**
   - Erstellen Sie monatliche Runs immer zum selben Datum
   - Nutzen Sie aussagekräftige Notizen (z.B. "Monatlicher Einzug März 2026")

3. **Bank-Referenz:**
   - Verwenden Sie konsistente Namen (z.B. immer "Sparkasse-Hauptkonto")
   - Später können Sie Reports nach Bank filtern

4. **Stornierung:**
   - Bei Fehlern: Einfach stornieren und neu erstellen
   - Die Zahlungen bleiben erhalten und können neu zugeordnet werden

## 🔍 Nächste Features (geplant)

- SEPA-XML Export
- Einzelne Zahlungen aus Run entfernen
- Filter und Suche
- Export als PDF/Excel
- Dashboard mit Statistiken
- Rücklastschriften verwalten

---

**Navigation:** Mitglieder verwalten → Einzugsläufe
