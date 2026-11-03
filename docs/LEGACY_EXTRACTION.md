# Extraction depuis Clement_BOURBON_repricing

## Conservé

- Mapping prix logique ↔ colonne CSV ↔ channel / shop
- Marketplaces : Leclerc (2861), Leroy Merlin (2062 multi-channel), Bricodepot (2102 ES / 2227 PT), Kaufland
- Format Mirakl PRI01 (mono / multi-channel / multi-shop)
- Garde-fou : bloquer push si prix ≤ 0
- Concept SFTP flux Lengow TSV

## Non repris

- PHP procédural / god file `gestionprix.php`
- Credentials en clair
- Double base MySQL (3306 / 3307)
- État CSV en session PHP
- Scripts de debug Kaufland
- CSV de 22 Mo / dumps / fichiers Looping

## Seeds

`MarketplaceSeeder` crée le référentiel non secret (URLs, shop IDs, mappings colonnes).  
Les API keys restent vides jusqu’à saisie admin.
