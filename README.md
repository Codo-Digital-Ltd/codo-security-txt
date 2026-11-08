# Codo Security.txt

[![CI](https://github.com/Codo-Digital-Ltd/codo-security-txt/actions/workflows/ci.yml/badge.svg)](https://github.com/Codo-Digital-Ltd/codo-security-txt/actions/workflows/ci.yml)
![Coverage](https://img.shields.io/badge/coverage-%E2%89%A595%25-brightgreen)
![PHP](https://img.shields.io/badge/PHP-7.4%E2%80%938.4-777bb4)
![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759b)

Publish a valid [RFC 9116](https://www.rfc-editor.org/rfc/rfc9116) `security.txt` from WordPress. Every field is validated, and the file can't quietly go stale. Free, with no Pro tier, upsells or telemetry.

Built and maintained by [Codo Digital](https://codo.digital). User-facing documentation is in [`readme.txt`](readme.txt) (the WordPress.org listing).

## How it works

```
GET /.well-known/security.txt ─ plugins_loaded (priority 1) ─► Endpoint::maybe_handle
GET /security.txt (optional)                                   ├─ Status: disabled | invalid | expired | expiring | ok
                                                               ├─ Builder: fields → text, or the signed message as-is
                                                               └─ send text/plain; charset=utf-8, then exit

settings form ─► FormHandler (capability + nonce) ─► Validator ─► Settings
daily cron ────► Reminder (30 d, 7 d, expired)      Site Health ─► SiteHealth (state, physical file, delivery)
```

- **No rewrite rules:** the request path is matched exactly, before the theme loads. It works with plain permalinks, needs no flush, and leaves every other `/.well-known/` path to its own handler.
- **Validation:** all RFC 9116 fields. URLs must be https, with no credentials or markup characters. Bad input is refused and the previous settings are kept.
- **Expiry:** an expired file is still served so researchers can find the contact. A date more than a year ahead is a warning, not an error.
- **Signed messages:** the clear-signed structure, Contact and Expires are checked. The signature is **not** verified, because that needs GnuPG.
- **Multisite:** subdomain sites each publish their own file. A subdirectory subsite shares its host with the main site, so only the main site serves the file.
- **Physical files:** the web server serves a real `security.txt` first, so the plugin warns about one.

Deviation from the original brief: rewrite rules and a flush are not used, for the reasons above.

## Naming and collisions

| Kind | Name |
| --- | --- |
| PHP namespace | `CodoDigital\SecurityTxt\` (PSR-4, own autoloader, no Composer at runtime) |
| Constants | `CODO_SECURITY_TXT_*` |
| Options / transients | `codo_security_txt_settings`, `codo_security_txt_reminded`, `codo_security_txt_reachability` |
| Cron hook | `codo_security_txt_daily` |
| Hooks we add | `codo_security_txt_*` |
| Admin actions / page | `codo_security_txt_save`, `_extend`, `_verify`, `codo-security-txt` |
| Text domain / slug | `codo-security-txt` |

No admin JavaScript is enqueued.

## Development

```bash
composer install
composer lint                 # WordPress Coding Standards + PHPCompatibility (7.4+)
composer test                 # PHPUnit
composer coverage             # needs pcov or Xdebug
php bin/coverage-gate.php build/clover.xml 95
```

Tests are unit tests with [Brain Monkey](https://giacomogaliano.github.io/brain-monkey/) standing in for WordPress, and cover failure paths as well as the happy path.

## Releasing

1. In a pull request, bump the version in `codo-security-txt.php` (header and `CODO_SECURITY_TXT_VERSION`) and `readme.txt` (`Stable tag` and a `= x.y.z =` changelog entry). CI fails if they disagree.
2. Merge to `main`.

The [Release workflow](.github/workflows/release.yml) then runs every check, deploys to the WordPress.org SVN repository, and tags `vX.Y.Z` with a GitHub release and zip. Until WordPress.org approves the plugin, the deploy is skipped with a notice. Pushes that don't change the version only sync `readme.txt` and `.wordpress-org/` assets.

Secrets required, in a `wordpress-org` environment: `SVN_USERNAME`, `SVN_PASSWORD`. All third-party actions are pinned to commit SHAs.

## Other security.txt plugins

A scan of wordpress.org in November 2026 found active alternatives, so this is not an empty gap:

- **Security.txt Manager** (v1.3.1), updated August 2026.
- **Generate Security.txt** (v1.0.14), updated about four months ago.

What this plugin adds: strict validation of every field, expiry handling (notices, Site Health, email reminders, one-click extend), clear-signed message support, an automatic Canonical line, delivery and physical-file checks, and multisite handling.

## Security

See [SECURITY.md](SECURITY.md).

## Licence

GPL-2.0-or-later.
