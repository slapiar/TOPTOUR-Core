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