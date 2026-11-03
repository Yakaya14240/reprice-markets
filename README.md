# reprice-markets

Outil interne de **repricing multi-marketplace** (Sun-Chauffage / Multithermique).

Greenfield Laravel issu de l’analyse de `Clement_BOURBON_repricing` : le legacy sert de **spécification métier**, pas de base de code à migrer telle quelle.

## Stack

- PHP 8.3+ / **Laravel 13**
- MySQL 8 (prod Linux) — SQLite OK en local
- Queue database (prête pour Redis / workers)
- Auth multi-opérateurs (`admin` / `operator`) + audit trail
- Connecteurs : Mirakl (PRI01) + Kaufland (HMAC) + SFTP Lengow (`phpseclib`)

## Démarrage local

```bash
cp .env.example .env
composer install
php artisan key:generate
# SQLite rapide :
# DB_CONNECTION=sqlite  + touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Comptes seed (à changer immédiatement) :

| Email | Mot de passe | Rôle |
|-------|--------------|------|
| `admin@reprice.local` | `ChangeMeNow!` | admin |
| `operator@reprice.local` | `ChangeMeNow!` | operator |

## Secrets

**Ne jamais committer** les clés Mirakl / Kaufland / SFTP.  
Les renseigner via l’UI admin marketplaces (champs chiffrés) et `.env` pour le SFTP.

Les credentials présents dans l’ancien projet doivent être **rotatés** avant toute mise en prod.

## Architecture

```
app/
  Domain/           # Offer, PriceResolver, ZeroPriceGuard
  Services/
    Feed/           # SFTP + parser Lengow
    Marketplace/    # Mirakl / Kaufland derrière une interface
  Jobs/             # PushPricesToMarketplaceJob (queue-ready)
```

## Roadmap MVP suivante

1. Édition prix live (chargement SFTP + overrides + réécriture flux)
2. Assemblage d’offres + garde-fou prix ≤ 0 branché sur le job de push
3. Validation signature Kaufland en sandbox / prod
4. CI GitHub Actions (Pint + PHPUnit)

## Docker (serveur Linux)

```bash
docker compose up -d --build
```

Worker queue inclus (`queue` service).

## Source legacy

Référence : `scripts-mt/Clement_BOURBON_repricing` (hors repo).
