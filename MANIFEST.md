# TOPTOUR Core Manifest

## 1. Účel projektu
TOPTOUR Core je interný modulárny WordPress plugin určený ako jadro systému CK TOPTOUR.
Nie je to univerzálny travel plugin ani generický booking engine.
Jeho účelom je podporiť predaj, správu a prezentáciu ponúk cestovného ruchu nad WooCommerce.

## 2. Základná architektúra
- Ponuka je reprezentovaná ako WooCommerce produkt rozšírený o vlastné meta polia.
- Zodpovedný manažér je reprezentovaný ako referencia na WordPress používateľa (`assigned_manager_user_id`).
- Plugin je modulárny. Každá funkcionalita patrí do samostatného modulu.
- Jadro pluginu nesmie obsahovať business logiku jednotlivých modulov.

## 3. Adresárová štruktúra
- `/includes/` shared utilities, loader, helpery
- `/modules/` funkčné moduly
- `/admin/` admin logika
- `/public/` frontend logika
- `/templates/` PHP šablóny výstupu
- `/assets/` CSS, JS, obrázky
- `/languages/` preklady

## 4. Zásady vývoja
- Jeden líder určuje architektúru, naming, scope a smer vývoja.
- Copilot alebo iný AI nástroj je len vykonávateľ presne definovaných technických úloh.
- AI nesmie samovoľne meniť architektúru, naming, verziovanie, changelog ani rozsah funkcionality.
- Každá zmena musí byť malá, izolovaná a kontrolovateľná.
- Nevytvárať obrovské súbory. Nevytvárať monolitický plugin.
- Neprepisovať celý systém kvôli malej zmene.

## 5. Produktové pravidlá
- Nepoužívať CTA „Kúpiť teraz“.
- Preferované CTA sú „Overiť dostupnosť“ a „Rezervovať“.
- Systém je navrhnutý pre logiku cestovnej kancelárie, nie pre klasický e-shop.
- Dôraz sa kladie na osobný kontakt, flexibilitu a priradeného manažéra.

## 6. Technická disciplína
- Verzionovanie začína od 1.0.0 a pokračuje konzistentne.
- Changelog sa upravuje len na základe výslovného pokynu.
- Každý modul má jednu hlavnú zodpovednosť.
- Business logika, renderovanie a assets musia zostať oddelené.
- Dočasné riešenia nesmú byť považované za finálnu architektúru.

## 7. Rozhodovacia priorita
Pri každom rozhodnutí platí toto poradie:
1. stabilita
2. čitateľnosť
3. modularita
4. rozšíriteľnosť
5. rýchlosť implementácie
6. Projekt nepoužíva PHP namespaces vo verzii 1.x. Používa prefix Toptour_.
Rýchlosť nikdy nesmie rozbiť architektúru.
