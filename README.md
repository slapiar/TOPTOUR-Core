# TOPTOUR-Core
Srdce pluginu pre WP
Pri vývoji je manifest nadradený README.

## Safe Checkpoints Workflow (záznam)
Ak nie je v repozitári samostatný presný popis checkpoint workflow, používa sa tento bezpečný postup:

1. Načítať MANIFEST a potvrdiť hranice scope pred začiatkom zmien.
2. Najprv prečítať cieľové súbory a identifikovať presné integračné body.
3. Implementovať malé, izolované zmeny iba v dohodnutých súboroch.
4. Zachovať architektúru, naming, scope a verziovanie bez samovoľných úprav.
5. Po každom logickom kroku overiť syntax (PHP lint) upravených súborov.
6. Výstup reportovať striktne: čo sa zmenilo, ktoré súbory sa zmenili, návrh commit správy.

Tento postup bol použitý aj pri implementácii prvého customers modulu.