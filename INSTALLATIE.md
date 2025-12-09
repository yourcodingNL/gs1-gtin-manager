# GS1 GTIN Manager - Installatie Instructies

## STAP 1: GitHub Repository Aanmaken

1. **Ga naar GitHub.com** en login
2. **Klik rechtsboven** op het + icoon
3. **Klik** "New repository"
4. **Repository naam:** `gs1-gtin-manager`
5. **Zet op Public** (belangrijk voor updates!)
6. **Klik** "Create repository"

## STAP 2: Code Aanpassen

**OPEN** `gs1-gtin-manager.php`

**ZOEK** regel 11:
```php
Plugin URI: https://github.com/yourusername/gs1-gtin-manager
```

**VERVANG** `yourusername` **MET** je GitHub gebruikersnaam

**ZOEK** regel 20:
```php
GitHub Plugin URI: yourusername/gs1-gtin-manager
```

**VERVANG** `yourusername` **MET** je GitHub gebruikersnaam

**ZOEK** regel 31:
```php
new GS1_GTIN_GitHub_Updater(__FILE__, 'yourusername', 'gs1-gtin-manager');
```

**VERVANG** `yourusername` **MET** je GitHub gebruikersnaam

**VOORBEELD:**
Als je GitHub username `SebastiaanK` is:
```php
Plugin URI: https://github.com/SebastiaanK/gs1-gtin-manager
GitHub Plugin URI: SebastiaanK/gs1-gtin-manager
new GS1_GTIN_GitHub_Updater(__FILE__, 'SebastiaanK', 'gs1-gtin-manager');
```

## STAP 3: Upload Naar GitHub

**OPEN** Terminal/Command Prompt

**GA** naar de plugin map:
```bash
cd /pad/naar/gs1-gtin-manager
```

**VOER UIT:**
```bash
git init
git add .
git commit -m "First release v1.0.0"
git branch -M main
git remote add origin https://github.com/JOUW-USERNAME/gs1-gtin-manager.git
git push -u origin main
```

**VERVANG** `JOUW-USERNAME` **MET** je GitHub gebruikersnaam

## STAP 4: Eerste Release Maken

1. **GA** naar je GitHub repository
2. **KLIK** op "Releases" (rechterkant)
3. **KLIK** "Create a new release"
4. **TAG:** Typ `v1.0.0`
5. **TITLE:** `Version 1.0.0`
6. **DESCRIPTION:** Typ:
```
Initial release

Features:
- GTIN assignment
- GS1 registration
- Range management
- Extensive logging
```
7. **KLIK** "Publish release"

## STAP 5: Plugin Installeren in WordPress

### Optie A: Via Upload
1. **GA** naar WordPress admin
2. **GA** naar Plugins → Add New
3. **KLIK** "Upload Plugin"
4. **KIES** de gs1-gtin-manager.zip
5. **KLIK** "Install Now"
6. **KLIK** "Activate"

### Optie B: Via FTP
1. **UPLOAD** de `gs1-gtin-manager` map naar `/wp-content/plugins/`
2. **GA** naar Plugins in WordPress
3. **ACTIVEER** "GS1 GTIN Manager"

## STAP 6: Configuratie

### API Credentials Instellen
1. **GA** naar Producten → GS1 GTIN Beheer
2. **KLIK** tab "Instellingen"
3. **VUL IN:**
   - **API Modus:** Sandbox
   - **API Token (Sandbox):** [JE SANDBOX TOKEN]
   - **Account Nummer:** 1162186 (jouw GS1 nummer)
   - **Standaard Contract:** [JE CONTRACT NUMMER]

4. **KLIK** "Instellingen Opslaan"
5. **KLIK** "Test API Verbinding"
   - ✅ Zie je "API verbinding succesvol"? → Goed!
   - ❌ Zie je error? → Check token + account nummer

### GTIN Ranges Synchroniseren
1. **KLIK** tab "GTIN Ranges"
2. **KLIK** "🔄 Sync Ranges van GS1 API"
3. **WACHT** totdat ranges zichtbaar zijn
4. **CHECK** dat je ranges ziet met start/eind nummers

### GPC Mappings (Optioneel)
1. **KLIK** tab "Instellingen"
2. **SCROLL** naar "GPC Categorie Mappings"
3. **KLIK** "Nieuwe Mapping Toevoegen"
4. **SELECTEER** WooCommerce categorie (bijv. "Sportartikelen")
5. **VUL IN:**
   - GPC Code: `10005896` (voorbeeld voor sportartikelen)
   - GPC Titel: `Sportartikelen`
6. **KLIK** "Opslaan"

**ZOEK GPC CODES:**
https://gpc-browser.gs1.org/

## STAP 7: Eerste GTIN Toewijzen (TEST)

1. **GA** naar tab "Overzicht"
2. **ZOEK** een product
3. **VINK AAN** het product
4. **KLIK** "GTIN Toewijzen aan Geselecteerde"
5. **CHECK** dat GTIN verschijnt in tabel

## STAP 8: Test Registratie (SANDBOX)

1. **SELECTEER** een product met GTIN
2. **KLIK** "Registreren bij GS1"

**STAP 1:** Zie producten lijst → **KLIK** "Volgende →"

**STAP 2:** Check/pas data aan:
- Beschrijving ✅
- Merknaam ✅
- GPC Code ✅
- Verpakkingstype ✅

**VINK AAN** "Ik heb alle data gecontroleerd"
**KLIK** "Registreren bij GS1"

**STAP 3:** Zie registratie bevestiging
**CHECK** Invocation ID

3. **GA** naar tab "Registratie Status"
4. **KLIK** "Status Checken" voor je registratie
5. **CHECK** of status updated naar "Geregistreerd"

## STAP 9: Naar LIVE (Als alles werkt)

⚠️ **PAS ALS SANDBOX PERFECT WERKT!**

1. **GA** naar Instellingen
2. **VERANDER** API Modus naar **Live**
3. **VUL IN** API Token (Live)
4. **KLIK** "Instellingen Opslaan"
5. **KLIK** "Test API Verbinding"
6. **SYNC** Ranges opnieuw

## Updates Publiceren

### Nieuwe Versie Maken

**AANPASSEN** in `gs1-gtin-manager.php`:
```php
// Regel 9:
Version: 1.0.1  // VERHOOG NUMMER
```

**AANPASSEN** in dezelfde file:
```php
// Regel 29:
define('GS1_GTIN_VERSION', '1.0.1');  // ZELFDE NUMMER
```

### Naar GitHub Pushen

```bash
git add .
git commit -m "Update to v1.0.1"
git push
```

### Release Maken
1. **GA** naar GitHub repository
2. **KLIK** "Releases"
3. **KLIK** "Create a new release"
4. **TAG:** `v1.0.1`
5. **TITLE:** `Version 1.0.1`
6. **DESCRIPTION:** Wat is er nieuw?
7. **KLIK** "Publish release"

### In WordPress
- Binnen 12 uur ziet gebruiker update melding
- 1-click update
- Geen dubbele mappen!

## Troubleshooting

### "API verbinding mislukt"
→ **CHECK** API token correct is
→ **CHECK** Account nummer klopt
→ **GA** naar Logs tab voor details

### "Geen GTIN ranges gevonden"
→ **KLIK** "Sync Ranges" button
→ **CHECK** API credentials correct
→ **CHECK** Logs tab

### "Plugin update niet zichtbaar"
→ **CHECK** GitHub repository is Public
→ **CHECK** Release is Published
→ **CHECK** Version nummer verhoogd in code
→ **WACHT** 12 uur (cache)

### "Registratie blijft pending"
→ **GA** naar Registratie Status tab
→ **KLIK** "Status Checken" button
→ **CHECK** WP Cron werkt (vraag hosting)

## Support

📧 **Email:** info@yourcoding.nl
🌐 **Website:** https://www.yourcoding.nl

**Logs Versturen:**
1. **GA** naar Logs tab
2. **KLIK** op log bestand van vandaag
3. **COPY/PASTE** inhoud naar email

---

**Gemaakt door YoCo - Sebastiaan Kalkman**
Voor Jokasport B.V.
