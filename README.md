# casamento/rbac

Modules + roles + permissions management package extracted from the casamento platform. Reusable across Laravel projects.

## Status

**WIP — being extracted from casamento.** PR 1 only ships the skeleton; subsequent PRs move models, controllers, and the frontend package across.

## Layout

```
.
├── composer.json        # PHP package manifest
├── config/rbac.php      # publishable config (prefix, guard, tenant model)
├── database/migrations/ # modules, languages, permissions, etc.
├── routes/api.php       # Module + Role + Language admin routes
├── src/                 # PHP source (PSR-4: Casamento\Rbac\)
├── tests/               # Pest feature tests
├── frontend/            # NPM package @casamento/admin-rbac
└── README.md
```

## Backend install (host Laravel app)

In the host app's `composer.json`:

```json
"require": {
    "casamento/rbac": "*"
},
"repositories": [
    { "type": "path", "url": "../modularize", "options": { "symlink": true } }
]
```

Then:

```bash
composer require casamento/rbac:*
php artisan vendor:publish --tag=rbac-config
php artisan migrate
```

Edit `config/rbac.php` to point `tenant_model` at your tenant class (e.g. `App\Models\Organization::class`) or leave `null` for single-tenant setups.

### Windows note

`"symlink": true` requires Developer Mode (Settings → Privacy & Security → For developers). Without it, Composer falls back to copying — every change in `../modularize` requires `composer update casamento/rbac` in the host app.

## Frontend install

See `frontend/README.md`.
