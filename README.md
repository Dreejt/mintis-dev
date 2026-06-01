# Mintis Dev

Developer-CLI voor Bedrock/WordPress projecten in het Mintis-ecosysteem. Eén `mintis` commando voor:

- **`mintis sync`** — database & uploads synchroniseren tussen development, staging en production (WP-CLI + rsync)
- **`mintis dev` / `prod` / `status`** — Mintis-packages tussen een lokale symlink en de GitHub-repo schakelen
- **`mintis setup`** — `wp-cli.yml` genereren, pre-commit guard installeren en de debug-symlink mu-plugin lokaal plaatsen
- **`mintis guard`** — pre-commit guard die voorkomt dat je een lokale dev-symlink naar productie commit

> Zit standaard ingebakken in [mintis-26](https://github.com/Dreejt/mintis-26) projecten.
>
> **Voorheen `dreejt/mintis-sync`.** De oude naam blijft werken via `replace`, maar werk je `require` en vcs-URL bij naar `dreejt/mintis-dev` (`mintis-dev.git`). Oude losse bins (`sync.sh`, `setup-wp-cli.php`) zijn vervangen door subcommando's van `mintis`.

## Quick Start

**1. Installeer het package** (dev-only)
```bash
composer require --dev dreejt/mintis-dev
```

**2. Configureer `.env`** (voor sync)
```env
SERVER_USER='ploi'
SERVER_IP='123.45.67.89'
SERVER_BASE_PATH='/home/ploi'

PROD_DOMAIN='www.jouwsite.nl'
STAGING_DOMAIN='staging.jouwsite.nl'
DEV_DOMAIN='jouwsite.test'
```

**3. Setup** (genereert `wp-cli.yml`, installeert git-hook + debug-symlink)
```bash
vendor/bin/mintis setup
```

**4. Sync**
```bash
vendor/bin/mintis sync production development   # ⬇️  pull van production naar lokaal
```

> Tip: in mintis-26-projecten zijn de meeste commando's als composer-scripts aliased, bv. `composer dev`, `composer prod`, `composer sync production development`.

---

## Dev-mode toggle

Schakel een of meer Mintis-packages tussen een **lokale path-symlink** (live editen) en de **GitHub-repo** (voor deploy/template).

```bash
mintis dev       # → lokale symlink(s); zet .dev-mode marker
mintis prod      # → terug naar GitHub; verwijdert .dev-mode
mintis status    # → toon huidige modus per package
```

### Configuratie

Definieer de te togglen packages in je project-`composer.json`:

```json
{
  "extra": {
    "mintis-dev": {
      "packages": [
        {
          "name": "dreejt/mintis-starter-plugin",
          "repository": "mintis-starter-plugin",
          "path": "../mintis-starter-plugin",
          "vcs": "https://github.com/dreejt/mintis-starter-plugin.git",
          "install-dir": "web/app/mu-plugins/mintis-starter-plugin"
        },
        {
          "name": "mintis/theme-core",
          "repository": "mintis-core",
          "path": "../mintis-core",
          "vcs": "https://github.com/Dreejt/mintis-core.git",
          "working-dir": "web/app/themes/mintis-starter",
          "update-flags": ["--no-dev"]
        }
      ]
    }
  }
}
```

| Veld | Verplicht | Betekenis |
|---|---|---|
| `name` | ✓ | Composer-package om te togglen |
| `repository` | ✓ | Key in `repositories` die geflipt wordt (path ↔ vcs) |
| `path` | ✓ | Pad naar de lokale checkout, **relatief aan de project-root** |
| `vcs` | ✓ | GitHub-URL voor prod-mode |
| `install-dir` | | Wordt vóór `prod` verwijderd zodat Composer schoon kan clonen |
| `working-dir` | | `composer.json` van een sub-package (bv. een thema); `path` wordt dan absoluut zodat de symlink ook vanuit een diepere map klopt |
| `update-flags` | | Extra vlaggen voor `composer update` (bv. `["--no-dev"]`) |

`mintis prod` bakt de `safe.bareRepository=all` git-instelling in voor de vcs-clone — nodig als je globaal `safe.bareRepository=explicit` hebt staan (anders breekt composer's bare-repo cache).

---

## Pre-commit guard

In dev-mode wijst de repository naar een lokaal path. Commit je dan `composer.json`/`composer.lock`, dan breekt de deploy (de server kan niet symlinken naar `../<package>`). De guard blokkeert dat:

```bash
mintis setup          # installeert de hook eenmalig
```

De hook (geïnstalleerd in `.git/hooks/pre-commit`, of je `core.hooksPath`) roept `mintis guard pre-commit` aan. Die blokkeert een commit alleen als `.dev-mode` actief is én er een `composer.json`/`composer.lock` (root of submap-package) gestaged staat. Andere commits gaan gewoon door.

Noodgeval: `git commit --no-verify`.

---

## Debug-symlink (mu-plugin)

`mintis setup` symlinkt `mintis-debug-symlink.php` in `web/app/mu-plugins/` (dev-only). Die mu-plugin maakt op je lokale Herd-omgeving een symlink van de PHP error_log naar `web/app/debug.log`, zodat je fouten makkelijk kunt volgen. Hij draait alleen bij `WP_ENV=development`.

---

## Sync

### Vrije syntax
```bash
mintis sync production development
mintis sync staging development
mintis sync development staging
mintis sync development production
mintis sync production staging
mintis sync staging production
```

### Opties
```bash
mintis sync production development --skip-db        # Alleen uploads
mintis sync production development --skip-assets    # Alleen database
mintis sync production development --dry-run        # Preview, geen wijzigingen
mintis sync production development --command-only   # Toon commando's, doe niets
```

> Als composer-script aliased (mintis-26): `composer sync production development -- --dry-run`.

### `wp-cli.yml`
Wordt door `mintis setup` gegenereerd uit `.env`. Commit dit bestand in git zodat het op alle machines beschikbaar is.

### Plugin management (optioneel)
Maak een `.sync` bestand (op basis van `.sync.example`) om per omgeving plugins aan/uit te schakelen na een sync:
```bash
cp vendor/dreejt/mintis-dev/.sync.example .sync
```
```bash
PLUGINS_ACTIVATE_ON_DEVELOPMENT=("wp-mail-smtp")
PLUGINS_DEACTIVATE_ON_DEVELOPMENT=("updraftplus" "nginx-helper" "redis-cache")
```
Plugins die niet bestaan op de doelomgeving worden overgeslagen. Zonder `.sync` werkt alles gewoon.

### Sync-veiligheid
- **Database backup** vóór elke sync (sync stopt als de backup mislukt)
- **Maintenance mode** automatisch aan/uit rond de sync
- **Post-sync validatie**: `wp core verify-checksums` + HTTP-bereikbaarheidscheck
- **Productie-beveiliging**: bij sync naar productie moet je de exacte domeinnaam typen
- **Pre-flight checks**: WP-CLI (lokaal + remote), rsync, SSH, `.env`

---

## Vereisten

- PHP ≥ 8.1
- WP-CLI (lokaal + remote, voor sync)
- rsync + SSH-toegang (voor sync)

## Release workflow (voor contributors)

1. Voeg bovenaan `CHANGELOG.md` een nieuw versieblok toe (de bovenste versie bepaalt de tag)
2. Commit en push naar `main`
3. De GitHub Action maakt automatisch de git tag aan

Zie [CLAUDE.md](CLAUDE.md) voor projectcontext.

## Licentie

MIT — zie [LICENSE](LICENSE). Gemaakt door [Mintis](https://mintis.nl).
