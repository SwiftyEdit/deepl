# DeepL

Automatic translations of pages and products for SwiftyEdit with the
[DeepL API](https://www.deepl.com/pro-api).

See the plugin's own **Hilfe/Help** tab (Addons > DeepL) for the full walkthrough.

## Features

- **Backend hooks** for `page.updated` and `product.updated`: tick a DeepL action when saving
  and the record is translated into all linked language versions (translation URLs).
  Products can be translated completely or only title & teaser / descriptions / meta data.
- **API key management** in the backend - the key is verified with DeepL before it is stored,
  kept in the plugin's own SQLite database (`data/deepl.sqlite3`, git-ignored) and only ever
  shown masked. Free and Pro keys are detected automatically.
- **Settings**: target languages, English/Portuguese variant, formality, HTML tags that are
  never translated, shortcode protection.
- **Overview**: character quota of the current billing period, test translation,
  log of the recent translations.
- HTML-aware: `translate="no"` is respected, SwiftyEdit shortcodes stay untouched.

## Requirements

- SwiftyEdit build 26-0215 or newer
- PHP 8.1+ with the curl extension
- A DeepL API key (Free or Pro)

## Upgrading from 1.x

The key file `deepl_auth.php` of version 1.x is imported into the database automatically
and deleted. The old per-language hook checkboxes are replaced by one action per record
type - the target languages are now chosen once under Settings.

## Development

The DeepL PHP library is bundled in `vendor/` (installed via Composer, `composer.json`),
together with Guzzle 7 as its PSR-18 HTTP client / PSR-17 factories. deepl-php finds them
via `php-http/discovery`; the SwiftyEdit core no longer ships Guzzle (since build 26-0233),
so the plugin has to bring its own.
Update it with:

```
composer update --no-dev
```
