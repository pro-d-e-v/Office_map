=== Office Map – OpenStreetMap Map Without API Key ===
Contributors: prodevel
Tags: map, maps, openstreetmap, maplibre, location
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful vector maps with OpenStreetMap and MapLibre GL — no API keys, no ads, no tracking. Shortcode, widget and a visual style editor.

== Description ==

**Office Map** adds a fast, modern **vector** map to your site using **MapLibre GL JS** and open **OpenStreetMap** data. No Google or Yandex account, no API keys, no ads, no tracking.

It is perfect for an office or contact page, or for several locations. Drop the `[office_map]` shortcode anywhere, or add the widget to a sidebar.

= Why it stands out =

* **Vector maps (MapLibre GL JS).** Crisp at any zoom with smooth panning and rotation — the feel of commercial maps, at zero cost.
* **Truly key-free.** No credit card, no quota and no "invalid API key" failures.
* **Privacy-friendly by default.** The map loads without accounts or analytics, and the optional address search can be turned off completely.
* **Fine-grained styling.** Recolor 17 layer categories (roads, water, parks, buildings, labels, POI and more) and hide anything you don't need.
* **One-click look presets** inspired by Google Maps and Yandex Maps.
* **Multilingual labels** (Russian, English, Belarusian, German, Polish and more).

= Features =

* `[office_map]` shortcode and a widget.
* Visual settings page with a live preview.
* Multiple markers, each with its own icon, color and rich popup.
* Optional on-map address search with server-side rate limiting and one-day caching.
* Width presets ("block", "full width", custom in % or px), fixed zoom or auto-fit to all markers.
* Fullscreen button, mouse-wheel zoom toggle and custom marker images.
* Import / export of all settings as JSON, to move a configuration between sites.
* Graceful fallback: if the tile server is unreachable, the office title and address are still shown.

= Shortcode =

The simplest form uses the settings from the plugin page:

`[office_map]`

You can override individual options:

`[office_map lat="53.9006" lng="27.5590" zoom="16" height="420" title="Our office" style="liberty" palette="google"]`

= External services =

This plugin relies on the following third-party services. Nothing is sent to them unless a feature that needs them is used.

* **OpenFreeMap** (`tiles.openfreemap.org`) — vector map tiles and styles, built on OpenStreetMap data. Loaded whenever a map is displayed.
* **Nominatim** (`nominatim.openstreetmap.org`) — OpenStreetMap geocoding. Used **only** when the on-map search is enabled (off by default) or when you use the address search on the settings page.
* **Photon** (`photon.komoot.io`) — a fallback geocoder by Komoot, used only if Nominatim returns no result.

Map data © [OpenStreetMap](https://www.openstreetmap.org/copyright) contributors.

= Privacy =

* The map itself is served without cookies or tracking.
* The on-map search is disabled by default. When enabled, the typed query is sent to the geocoding services listed above; requests are rate-limited and cached.
* No data is collected by the plugin author.

== Installation ==

1. Install the plugin from the WordPress plugin directory, or upload the `office-map` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" screen.
3. Open **Office Map** in the admin menu and set your coordinates and address.
4. Add the `[office_map]` shortcode to the page with your office, or use the "Office Map" widget.

== Frequently Asked Questions ==

= Do I need an API key? =

No. OpenFreeMap tiles and OpenStreetMap data are open and require no account or key.

= Is attribution required? =

Yes. Attribution for OpenStreetMap and OpenFreeMap is added automatically and must not be removed.

= Can I show several maps on one page? =

Yes. Each map gets a unique identifier and works independently.

= Can I use it without the address search? =

Yes. The search is off by default; the map works fully without it, and no request is made to any geocoder.

= Where do the map tiles come from? =

From OpenFreeMap, which serves OpenStreetMap-based vector tiles. No OpenStreetMap editing or hosting account is required.

== Changelog ==

= 1.9.0 =
* English is now the source language; all user-facing strings are translatable (text domain `office-map`).
* MapLibre GL JS is now bundled locally (5.24.0) — no code is loaded from a third-party CDN.
* The on-map address search is off by default and is clearly documented as an external service.
* Geocoder requests now use a proper User-Agent with a contact address.
* Added `uninstall.php` to clean up options and transients on deletion.
* Modernized the plugin headers; requires WordPress 6.0 and PHP 7.4.
* Added `languages/office-map.pot` for translators.

= 1.8.0 =
* The open/closed state of the settings blocks is saved with the settings. Only the first block is open by default; open the ones you need and press "Save" — they stay open after reload.

= 1.7.1 =
* The "Zoom" field is shown only in "Fixed zoom" mode; it is hidden in "Fit all markers" mode.
* Marker protection: a snapshot of the last state with markers is kept and a "Restore markers" button appears if the markers are lost.

= 1.7.0 =
* Import and export of settings as JSON: move the address, markers, colors and all styling to another site (images are exported as URLs).
* On-map search field styling: the field and the button are now on one line.
* A new "Search on the map" subsection in "Map styling": background, text, border, button background/text and corner radius.

= 1.6.3 =
* Settings blocks reordered logically: "Main address" (coordinates and search), then "Additional markers", "Size and zoom", "Appearance", "Map styling", "Behaviour".

= 1.6.2 =
* Protection against losing settings: an empty or broken form submission no longer overwrites the saved data (coordinates, points, styles).
* A backup of the previous settings is created before each save.
* Added a "Roll back the previous settings" button on the settings page.

= 1.5.0 =
* New "Map styling" section: color and opacity for 17 layer categories (land, water, rivers, parks, forest, sand, ice, buildings, roads, road casings, highways, highway casings, rail, borders, labels, label halo, POI icons).
* Layer visibility: hide POI, buildings (2D), 3D buildings, terrain, borders, rail and transit, all labels; terrain opacity.

= 1.4.0 =
* Each additional marker can have its own icon (media library) and its own color.
* The additional-marker address became a textarea with HTML support, like the main one.

= 1.3.0 =
* New: map language selection — feature labels (from OpenMapTiles `name:xx` fields) and the geocoder response language.
* Compact settings page: latitude/longitude and other fields in rows; width "Value/Unit" visible only for the "Custom" preset.
* Settings sections can be collapsed (state remembered).
* "Copy shortcode with current settings" button.

= 1.2.0 =
* Map width setting: "Block" and "Full width" presets, plus a custom width in % or px.
* Address search: OpenStreetMap-based geocoder on the settings page and on the map itself.
* Several markers with popups and a "Fit all markers" zoom mode.
* A "Fullscreen" button for the map.

= 1.1.0 =
* Upload a custom marker image through the WordPress media library.
* Live preview: the map updates automatically as fields change.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.9.0 =
All UI strings are now translatable, MapLibre GL JS is bundled locally, and the on-map search is off by default. No action is required.
