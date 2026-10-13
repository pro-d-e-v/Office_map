# Office Map – OpenStreetMap Map Without API Key

Interactive **vector** maps for WordPress, built on **MapLibre GL JS** and open
**OpenStreetMap** data (OpenFreeMap tiles). No API keys, no ads, no tracking.

* Shortcode: `[office_map]`
* Widget: "Office Map"
* Visual settings page with a live preview
* 17 stylable layer categories, ready-made look presets, multilingual labels
* Optional address search (off by default)
* Import / export of all settings as JSON

## Requirements

* WordPress 6.0+
* PHP 7.4+

## Installation (from source)

1. Copy the `office-map` folder into `wp-content/plugins/`.
2. Activate the plugin in **Plugins**.
3. Open **Office Map** in the admin menu and set your coordinates and address.
4. Add `[office_map]` to a page, or use the "Office Map" widget.

## Development

* Source language for strings is **English**; the text domain is `office-map`.
* Regenerate the translation template:

  ```sh
  wp i18n make-pot . languages/office-map.pot --domain=office-map
  ```

* MapLibre GL JS is bundled under `assets/vendor/maplibre-gl/` (3-Clause BSD).

## External services

* **OpenFreeMap** (`tiles.openfreemap.org`) — vector tiles and styles.
* **Nominatim** (`nominatim.openstreetmap.org`) — geocoding, only when search is enabled.
* **Photon** (`photon.komoot.io`) — fallback geocoder.

Map data © [OpenStreetMap](https://www.openstreetmap.org/copyright) contributors.

## License

GPL-2.0-or-later. See `readme.txt` for the full license text location.
