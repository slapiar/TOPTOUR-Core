# Changelog

Vsetky dolezite zmeny v projekte TOPTOUR Core budu evidovane v tomto subore.

Format vychadza z Keep a Changelog a projekt pouziva semanticke verzovanie.

## [v1.0.0] - 2026-04-19

### Pridane
- Inicializovana cista a modularna struktura WordPress pluginu TOPTOUR Core.
- Vytvoreny hlavny bootstrap subor pluginu [toptour-core.php](toptour-core.php) s:
  - plugin headerom,
  - bezpecnostnou kontrolou `ABSPATH`,
  - definiciou konstant `TOPTOUR_CORE_PATH`, `TOPTOUR_CORE_URL`, `TOPTOUR_CORE_VERSION`,
  - jednoduchym nacitanim loadera.
- Vytvoreny jednoduchy loader [includes/class-loader.php](includes/class-loader.php), ktory:
  - nacita subory modulov,
  - vytvori instancie modulov,
  - zavola metodu `init()` kazdeho modulu, ak je dostupna.
- Pridane zakladne modulove triedy pripravene na buduce hooky:
  - [modules/offers/class-offers.php](modules/offers/class-offers.php)
  - [modules/managers/class-managers.php](modules/managers/class-managers.php)
  - [modules/reservations/class-reservations.php](modules/reservations/class-reservations.php)
- Vytvorena adresarova struktura pre dalsi rozvoj:
  - [includes](includes)
  - [modules](modules)
  - [admin](admin)
  - [public](public)
  - [templates](templates)
  - [assets/css](assets/css)
  - [assets/js](assets/js)
  - [assets/img](assets/img)
  - [languages](languages)
- Do prazdnych adresarov pridane `.gitkeep` subory pre zachovanie struktury v Gite.

### Poznamka
- Verzie pluginu sa odteraz inkrementuju od zakladnej verzie `v1.0.0`.

### Added
- Manager profile fields (phone, bio, image)
- Frontend manager contact card on product pages

## Snapshot – 2026-04-20

State:
- customers module implemented
- inquiry → customer sync active

Notes:
- DB upgrade mechanism not implemented yet
- admin edit not syncing customers

Backup:
- toptour-core-1.1.0-2026-04-20-source.tar.gz

## [unreleased] - Release safety hardening

Changed:
- release script now requires a clean git workspace
- release script no longer auto-commits unrelated changes

Notes:
- release ZIP still contains the full plugin
- archive/backup artifacts are excluded from packaging

## [1.1.1] - Customer sync on admin request edit

Changed:
- when an inquiry/request is edited in admin, current customer data is synced again to `toptour_customers`

Notes:
- email remains the customer identity
- no customer merge logic yet when email changes

## [1.1.22] - Admin customers overview

Added:
- new admin page for customer overview
- paginated customer list from `toptour_customers`
- basic search by name, email, and phone

Notes:
- first version is read-only
- default sorting is by last activity descending

## [1.1.23] - Customer edit and delete actions

Added:
- row actions for editing and deleting customer records
- admin form for editing customer name, email, phone, and status

Notes:
- delete affects only the `toptour_customers` table
- request/inquiry records are not modified
- no merge logic is introduced in this phase

## [1.1.25] - Preserve customers list context after edit/delete

Changed:
- customer edit and delete actions now preserve current list context
- pagination and search state are retained after returning to the overview

Notes:
- preserved context currently includes page number and search term only