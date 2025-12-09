# GS1 GTIN Manager voor WooCommerce

WordPress plugin voor het beheren van GS1 GTIN codes via de GS1 Nederland API.

## Ontwikkeld door
**YoCo - YourCoding.nl**
- Sebastiaan Kalkman
- info@yourcoding.nl
- https://www.yourcoding.nl

## Features

✅ **GTIN Toewijzing**
- Automatisch GTINs toewijzen uit je GS1 ranges
- Bulk toewijzing aan meerdere producten
- Externe GTINs registreren

✅ **GS1 Registratie**
- 2-staps registratieproces met data controle
- Automatische data mapping vanuit WooCommerce
- Bulk registratie bij GS1 Nederland API

✅ **GTIN Ranges Beheer**
- Automatische sync met GS1 API
- Real-time overzicht van beschikbare nummers
- Progress tracking per range

✅ **Uitgebreide Logging**
- Dedicated log systeem (niet debug.log)
- Database logs voor snelle filtering
- Bestandslogs voor details

✅ **Auto-Updates via GitHub**
- Automatische plugin updates
- Correcte directory structure
- Geen dubbele mappen

## Installatie

### Stap 1: Download
Download de plugin van GitHub releases of clone de repository:
```bash
git clone https://github.com/JOUW-USERNAME/gs1-gtin-manager.git
```

### Stap 2: Upload naar WordPress
1. Ga naar je WordPress installatie
2. Navigeer naar `/wp-content/plugins/`
3. Upload de `gs1-gtin-manager` map
4. Activeer de plugin in WordPress admin

### Stap 3: Configuratie
1. Ga naar **Producten → GS1 GTIN Beheer → Instellingen**
2. Vul in:
   - **API Token Sandbox**: Je test API token
   - **API Token Live**: Je productie API token  
   - **Account Nummer**: Je GS1 account nummer (bijv. 1162186)
   - **Standaard Contract**: Je contract nummer
3. Klik **Test API Verbinding**
4. Schakel **API Modus** naar **Live** wanneer je klaar bent om te gaan

### Stap 4: Sync GTIN Ranges
1. Ga naar tab **GTIN Ranges**
2. Klik **Sync Ranges van GS1 API**
3. Je ranges zijn nu beschikbaar

### Stap 5: GPC Mappings (Optioneel)
1. Ga naar **Instellingen** tab
2. Scroll naar **GPC Categorie Mappings**
3. Koppel WooCommerce categorieën aan GPC codes
4. Zoek GPC codes op: https://gpc-browser.gs1.org/

## Gebruik

### GTIN Toewijzen

1. Ga naar **Producten → GS1 GTIN Beheer**
2. Gebruik filters om producten te vinden:
   - Zoek op naam/SKU/GTIN
   - Filter op merk
   - Filter op status
3. Selecteer producten
4. Klik **GTIN Toewijzen aan Geselecteerde**

### Registreren bij GS1

**Stap 1: Selecteer Producten**
1. Selecteer producten met toegewezen GTINs
2. Klik **Registreren bij GS1**

**Stap 2: Controleer Data**
- Product naam → Wordt omschrijving
- Merk → Van `pa_brand` attribuut
- GPC → Van categorie mapping
- Verpakkingstype → Standaard "Doos"
- Netto inhoud → Van weight veld
- Pas aan waar nodig

**Stap 3: Bevestig**
- Vink aan "Ik heb alle data gecontroleerd"
- Klik **Registreren bij GS1**

**Stap 4: Volg Status**
- Registratie wordt automatisch gecheckt (elk uur)
- Check **Registratie Status** tab voor voortgang

## GitHub Auto-Updates

### Voor Developers

**Release maken:**
```bash
git tag -a v1.0.1 -m "Release v1.0.1"
git push origin v1.0.1
```

**GitHub Release:**
1. Ga naar GitHub repository
2. Klik **Releases** → **Create new release**
3. Tag: `v1.0.1`
4. Beschrijving: changelog
5. Publish

**WordPress Update:**
- Plugin checkt automatisch op updates
- Users zien update melding
- 1-click update zonder dubbele mappen

### GitHub Repository Setup

**VERVANG IN gs1-gtin-manager.php:**
```php
// Regel 11:
Plugin URI: https://github.com/JOUW-USERNAME/gs1-gtin-manager

// Regel 20:
GitHub Plugin URI: JOUW-USERNAME/gs1-gtin-manager
```

**VERVANG** `JOUW-USERNAME` **MET** je GitHub username

**VERVANG IN main plugin file:**
```php
new GS1_GTIN_GitHub_Updater(__FILE__, 'JOUW-USERNAME', 'gs1-gtin-manager');
```

## Database Tabellen

Plugin maakt deze tabellen aan:
- `wp_gs1_gtin_assignments` - GTIN toewijzingen
- `wp_gs1_gtin_ranges` - Beschikbare ranges
- `wp_gs1_gtin_logs` - Logging
- `wp_gs1_gtin_gpc_mappings` - Categorie mappings

## Logs

### Bestandslogs
Locatie: `/wp-content/uploads/gs1-gtin-logs/`
- Dagelijkse bestanden
- Volledige request/response data
- Download via admin

### Database Logs
- Laatste 100 entries
- Filter op level (info/warning/error/debug)
- View context data

## Troubleshooting

### API Verbinding Mislukt
✅ Check API token correct is
✅ Check API modus (sandbox/live)
✅ Check logs voor details

### GTINs Niet Toegewezen
✅ Check of ranges gesynchroniseerd zijn
✅ Check of range niet uitgeput is
✅ Check logs voor errors

### Registratie Blijft Pending
✅ Check **Registratie Status** tab
✅ Klik **Status Checken** button
✅ WP Cron moet actief zijn

### Update Werkt Niet
✅ Check GitHub repository public is
✅ Check releases gepubliceerd zijn
✅ Check plugin URI klopt in code

## Support

📧 Email: info@yourcoding.nl
🌐 Website: https://www.yourcoding.nl

## Changelog

### v1.0.0 (2024-12-09)
- Eerste release
- GTIN toewijzing
- GS1 registratie
- Auto-updates via GitHub
- Uitgebreide logging

## License

GPL v2 or later

## Credits

Ontwikkeld door **YoCo - Sebastiaan Kalkman**
Voor Jokasport B.V.
