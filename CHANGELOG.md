# Changelog

All notable changes to this project are documented here. The same list, formatted
for the WordPress plugin directory, lives in `readme.txt`.

## [1.9.0]

* English is now the source language; all user-facing strings are translatable
  (text domain `office-map`).
* MapLibre GL JS is bundled locally (5.24.0) — no code is loaded from a third-party CDN.
* The on-map address search is off by default and documented as an external service.
* Geocoder requests use a proper User-Agent with a contact address.
* Added `uninstall.php` to clean up options and transients on deletion.
* Modernized plugin headers; requires WordPress 6.0 and PHP 7.4.
* Added `languages/office-map.pot` for translators.

## [1.8.0]

* The open/closed state of the settings blocks is saved with the settings.

## [1.7.1]

* The "Zoom" field is shown only in "Fixed zoom" mode.
* Marker protection: a snapshot of the last state with markers is kept, with a
  "Restore markers" button.

## [1.7.0]

* Import and export of settings as JSON.
* On-map search field styling (background, text, border, button, radius).

## [1.6.0]

* Per-block "Save" buttons and dirty-state highlighting.

## [1.5.0]

* "Map styling" section: color and opacity for 17 layer categories, plus layer
  visibility toggles and terrain opacity.

## [1.4.0]

* Per-marker icon and color for additional markers.

## [1.3.0]

* Map language selection, compact settings page, collapsible sections.

## [1.2.0]

* Width presets, on-map address search, multiple markers, fullscreen button.

## [1.1.0]

* Custom marker image via the media library, live preview.

## [1.0.0]

* First release.
