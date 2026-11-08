=== Codo Security.txt ===
Contributors: cododigital
Tags: security, security.txt, vulnerability disclosure, rfc 9116, well-known
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish a valid RFC 9116 security.txt so researchers know how to report a vulnerability. Checks every field, warns before it expires.

== Description ==

A `security.txt` file is a small text file at `/.well-known/security.txt` that tells security researchers who to contact about a vulnerability on your site. It is standardised as [RFC 9116](https://www.rfc-editor.org/rfc/rfc9116). Without one, a report may never reach you.

Codo Security.txt serves the file from your WordPress settings. There is nothing to upload and no rewrite rules to flush.

**What it does**

* Serves `/.well-known/security.txt` (and optionally the legacy `/security.txt`) as `text/plain; charset=utf-8`.
* Checks every field strictly: Contact, Expires, Encryption, Acknowledgments, Preferred-Languages, Canonical, Policy, Hiring and CSAF. URLs must be https, and bad input is refused with a clear message instead of being saved.
* Keeps the file from going stale. It shows a notice on the Dashboard and Plugins screens, adds a Site Health test, emails the site admin 30 days and 7 days before it expires and when it has expired, and has a one-click "Extend by one year" button.
* Adds the Canonical line for you.
* Can publish a PGP clear-signed message exactly as you signed it.
* Warns when a physical `security.txt` file would override the plugin, when WordPress is in a subdirectory, and on subdirectory multisite subsites.
* Checks that your web server really delivers the file, from the settings screen and Site Health.
* Works on Apache, NGINX and multisite, and does not interfere with other `/.well-known/` handlers.
* Loads no admin JavaScript, adds no tracking and has no Pro tier.

**What it does not do**

* It does not verify PGP signatures. It checks the structure of a signed message, its Contact line and its Expires date, but verifying a signature needs GnuPG.
* It cannot publish the file on a subdirectory multisite subsite. Those share a host with the main site, and only the main site can answer `/.well-known/security.txt`.

Built and maintained by [Codo Digital](https://codo.digital).

== Installation ==

1. Install and activate the plugin.
2. Go to Settings > Security.txt.
3. Enter at least one contact and an expiry date, tick "Publish" and save.
4. Use "Check delivery now" to confirm your web server serves the file.

== Frequently Asked Questions ==

= Where is the file served? =

At `https://your-site.example/.well-known/security.txt`, and at `/security.txt` unless you turn the legacy location off.

= The file returns a 404 =

Your web server is probably answering `/.well-known/` itself instead of passing the request to WordPress.

NGINX: make sure no `location ~ /\.` rule denies dotfiles before WordPress sees the request. A rule like this keeps `.well-known` working:

`location ^~ /.well-known/ { try_files $uri /index.php?$args; }`

Apache: the standard WordPress `.htaccess` already passes unknown paths to `index.php`. If you have a rule that blocks dotfiles, add an exception for `.well-known`:

`RewriteRule ^\.well-known/ - [L]`

Some hosts and CDNs also cache 404s. Purge the cache after you publish.

= A real security.txt file exists on my server =

Your web server serves a real file in preference to WordPress, so the settings here are ignored. The plugin warns you on its settings page and in Site Health. Delete the file.

= Does it work on multisite? =

Yes. Each site has its own settings. On a subdirectory network only the main site can publish the file, because every site shares one host.

= Why is an expired file still served? =

So researchers can still find your contact details. The plugin warns you loudly until you renew it.

= Why does it say my expiry is too far away? =

RFC 9116 recommends an expiry less than a year ahead, so that the file is reviewed regularly. A later date is accepted with a warning.

= Can I turn it off without deleting the settings? =

Untick "Publish", or define `CODO_SECURITY_TXT_DISABLE` as `true` in `wp-config.php`.

= Does it send data anywhere? =

No. See "External services" below.

== External services ==

The "Check delivery" button and the Site Health test make one request to your own site's `/.well-known/security.txt` to confirm the web server delivers the file. The request goes to your own domain only. No data is sent to Codo Digital or any third party.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
