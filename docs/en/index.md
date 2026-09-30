---
title: DeepL - Overview
description: Automatic translations with DeepL
btn: Overview
group: addons
priority: 10
---

# DeepL

This plugin translates pages and products with the DeepL API - right when you save them,
into all linked language versions.

## Setup

1. Create an account for the [DeepL API](https://www.deepl.com/pro-api) (the free
   "DeepL API Free" plan covers 500,000 characters per month).
2. Save the API key under **Settings**. It is verified with DeepL first. Free and Pro
   keys are detected automatically.
3. Activate the plugin under **Addons** - hooks are only loaded for active plugins.

The **Overview** then shows the used quota, lets you run a test translation and lists
the recent translations.

## Translating

Translations use the **translation URLs** of a page or product:

1. Create the page (or product) once in every language.
2. In the source page, enter the permalink of each language version under
   *Translations (URLs)*. For **products** this is catalog page + slug, i.e. exactly the path
   the product is reachable at in the frontend, e.g. `en/shop/shoe-max/` - the slug alone is
   not enough, since it can occur in several catalogs. A full URL including the domain works too.
3. When saving, tick the desired **DeepL** action in the hooks box.

After saving, all linked language versions are **overwritten** with the translation.
The hook checkboxes are unticked again on every save - nothing is translated unless you
explicitly select it.

### Excluding single languages

If a page or product should only be translated into some languages (e.g. because one
language version isn't supposed to have that content yet), tick the languages to skip under
*DeepL: do not translate into* in the editor's **Addons** tab. The translation URL stays in
place - the language switcher and hreflang keep working. The selection is saved with the page
or product and applies to every further save with a DeepL hook.

Languages without a translation URL are marked *(no translation URL)* there - they are not
translated anyway.

### Pages

*DeepL: Translate page into all linked languages* translates title, link name,
meta description, meta keywords and the content.

If a **target page** uses a block editor, it is switched to HTML - otherwise the editor
would load the old, untranslated blocks again on the next save.
Every translation creates an entry in the target page's version history.

### Products

| Action | Fields |
|---|---|
| whole product | everything below plus the labels for features, accessories, related products and add-ons |
| Title & teaser | title, teaser |
| Descriptions | text, additional texts 1-5 incl. labels, scope of delivery |
| Meta data | meta title, meta description, tags |

Prices, images, variants and all other fields stay unchanged.

## What is not translated?

- Elements with the attribute `translate="no"`
- Content of the HTML tags listed in *Do not translate: HTML tags*
  (default: `code, pre, kbd, samp, script, style`)
- Shortcodes like `[snippet]…[/snippet]` or `[plugin=…]…[/plugin]`, as long as
  *Protect shortcodes* is enabled

## Settings

- **Target languages** - which SwiftyEdit languages the hooks may translate into.
  The DeepL language code is shown next to each.
- **English / Portuguese** - DeepL needs a regional variant for these languages.
- **Formality** - formal or informal, where the target language supports it.
