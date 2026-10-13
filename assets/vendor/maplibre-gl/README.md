# MapLibre GL JS (bundled)

This directory contains a local copy of MapLibre GL JS, bundled with the plugin so that
no code is loaded from a third-party CDN.

* Library: MapLibre GL JS
* Version: 5.24.0
* Source: https://github.com/maplibre/maplibre-gl-js
* Package: https://www.npmjs.com/package/maplibre-gl
* License: 3-Clause BSD (see `LICENSE.txt`)

Files:

* `maplibre-gl.js` — distribution build (readable, non-minified).
* `maplibre-gl.css` — stylesheet.

The `js` file is the readable distribution build, so no separate non-minified copy is
needed. To update, replace the files with the matching versions from the package above
and keep this file in sync.
