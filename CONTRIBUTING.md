# Contributing

Issues and pull requests are welcome.

```bash
composer install
composer lint
composer test
```

- Follow the WordPress Coding Standards (`composer lint` must pass, and PHP 7.4 must stay supported).
- Add tests for new behaviour, including the failure paths. CI requires 95% line coverage.
- When you change the version, update `codo-security-txt.php` (header and `CODO_SECURITY_TXT_VERSION`) and `readme.txt` (`Stable tag` and the changelog) together. `php bin/check-version.php` checks them.
- Report security issues privately. See [SECURITY.md](SECURITY.md).
