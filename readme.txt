=== Cross-Strait Phrase Switch ===
Contributors: roy
Tags: chinese, traditional, simplified, opencc, localization
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Converts Taiwan and Mainland Chinese vocabulary on the front end, not only Traditional/Simplified character shapes.

== Description ==

Many Chinese conversion tools only swap glyphs (体/體). Readers in Taiwan and Mainland China also use different words: 軟件 vs 軟體, 信息 vs 資訊, 默認 vs 預設.

This plugin converts the visible page with OpenCC phrase tables (`s2twp` / `tw2sp`) plus an optional extra dictionary you provide.

= Features =

* Theme-independent toggle: shortcode, widget, or floating button
* Optional Accept-Language default (zh-TW / zh-HK → Taiwan phrases)
* Optional extra dictionaries (JSON or 國教院-style NDJSON) compiled in wp-admin, never on page view
* Code blocks are left unchanged
* Works with themes that already ship an OpenCC toggle (including Puock)

National-academy terminology files are **not** bundled. Upload your own files from Settings if you have the right to use them.

= Shortcode =

`[csps_toggle]`

== Installation ==

1. Upload the `cross-strait-phrase-switch` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open Settings → Cross-Strait Phrases.
4. Optional: upload extra dictionary JSON files and click Compile.

== Frequently Asked Questions ==

= Does this replace AutoHan? =

AutoHan (and similar plugins) mainly map character shapes. This plugin also maps vocabulary.

= Why is the OpenCC script large? =

The converter and phrase tables run in the browser so cached HTML (and CDN pages) can still switch locale.

= Can I bundle 國教院 dictionaries? =

Not in the plugin zip. Compile them on your site from files you are allowed to use.

== Changelog ==

= 1.0.0 =
* First public generic release.
