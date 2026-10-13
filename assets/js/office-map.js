/**
 * Office Map — map initialization and updates (MapLibre GL JS + OpenFreeMap).
 *
 * Each map config lives in the data-config attribute of its container.
 * window.OfficeMap.apply(el, cfg) — live update (settings page).
 */
(function () {
	'use strict';

	var STYLE_BASE = 'https://tiles.openfreemap.org/styles/';

	// Translations passed from PHP (OfficeMap.localize). English fallbacks.
	var I18N = {
		searchPlaceholder: 'Search address…',
		searchButton: 'Find',
		searching: 'Searching…',
		nothingFound: 'Nothing found',
		searchError: 'Search error'
	};

	function t(key) {
		var data = window.OfficeMapData;
		return (data && data.i18n && data.i18n[key]) ? data.i18n[key] : I18N[key];
	}

	var PALETTES = {
		google: {
			land: '#f5f5f5', water: '#aadaff', waterway: '#a5d5f7',
			park: '#c9f0c4', forest: '#b4e6a8', sand: '#f2e9c9', ice: '#ffffff',
			building: '#e4e4e4', road: '#ffffff', road_casing: '#d9d9d9',
			highway: '#ffd166', highway_casing: '#e6b84a', rail: '#d0d0d0',
			boundary: '#a3a3a3', text: '#5f6368', text_halo: '#ffffff', poi: '#5f6368'
		},
		yandex: {
			land: '#f6f3ee', water: '#a9d6f2', waterway: '#9ecff0',
			park: '#b9e2a4', forest: '#a5d894', sand: '#f0e2b6', ice: '#ffffff',
			building: '#e8e4dc', road: '#ffffff', road_casing: '#d9d2c7',
			highway: '#ffd966', highway_casing: '#e0bd4d', rail: '#cccccc',
			boundary: '#b8a894', text: '#3b3b3b', text_halo: '#ffffff', poi: '#4d4d4d'
		}
	};

	/* ---------------------------------------------------------------- palette */

	function categoryOf(layer) {
		var id = String(layer.id || '').toLowerCase();
		var sl = String(layer['source-layer'] || '').toLowerCase();
		var s = id + ' ' + sl;

		if (layer.type === 'background' || id === 'background' || sl === 'background') {
			return 'land';
		}
		if (sl === 'waterway' || /waterway/.test(s)) {
			return 'waterway';
		}
		if (sl === 'water' || /\bwater\b|\b(ocean|sea|lake|river)\b/.test(s)) {
			return 'water';
		}
		if (sl === 'landcover' || sl === 'landuse') {
			if (/wood|forest/.test(s)) { return 'forest'; }
			if (/sand|beach|dune/.test(s)) { return 'sand'; }
			if (/ice|glacier/.test(s)) { return 'ice'; }
			if (/grass|park|garden|meadow|green|pitch|cemetery/.test(s)) { return 'park'; }
			return 'land';
		}
		if (sl === 'park') { return 'park'; }
		if (sl === 'building' || /building/.test(s)) { return 'building'; }
		if (/rail|transit|aeroway|runway|taxiway|airport/.test(s)) { return 'rail'; }
		if (sl === 'transportation' || /road|street|highway|bridge|tunnel|motorway|path|track/.test(s)) { return 'road'; }
		if (/boundary|admin/.test(s)) { return 'boundary'; }
		if (layer.type === 'symbol' || /label|place|poi|housenumber|text/.test(s)) { return 'label'; }
		return null;
	}

	function isCasing(layer) { return /casing|outline|halo|shadow/.test(String(layer.id || '').toLowerCase()); }
	function isMajor(layer) { return /major|motorway|trunk|primary|highway/.test(String(layer.id || '').toLowerCase()); }

	function recolor(style, palette) {
		if (!style || !style.layers) { return style; }

		style.layers.forEach(function (layer) {
			var cat = categoryOf(layer);
			if (!cat) { return; }
			var p = layer.paint || (layer.paint = {});

			switch (cat) {
				case 'land':
					if (layer.type === 'background') { p['background-color'] = palette.land; }
					else if (layer.type === 'fill') { p['fill-color'] = palette.land; }
					break;
				case 'water':
					if (layer.type === 'fill') { p['fill-color'] = palette.water; }
					else if (layer.type === 'line') { p['line-color'] = palette.water; }
					break;
				case 'waterway':
					if (layer.type === 'line') { p['line-color'] = palette.waterway; }
					break;
				case 'park':
				case 'forest':
				case 'sand':
				case 'ice':
					if (layer.type === 'fill') { p['fill-color'] = palette[cat]; p['fill-opacity'] = 1; }
					break;
				case 'building':
					if (layer.type === 'fill') { p['fill-color'] = palette.building; p['fill-outline-color'] = 'rgba(0,0,0,0.06)'; }
					break;
				case 'road':
					if (layer.type === 'line') {
						if (isCasing(layer)) { p['line-color'] = isMajor(layer) ? palette.highway_casing : palette.road_casing; }
						else { p['line-color'] = isMajor(layer) ? palette.highway : palette.road; }
					}
					break;
				case 'rail':
					if (layer.type === 'line') { p['line-color'] = palette.rail; }
					break;
				case 'boundary':
					if (layer.type === 'line') { p['line-color'] = palette.boundary; }
					break;
				case 'label':
					if (layer.type === 'symbol') {
						var layout = layer.layout || {};
						if (layout['text-field']) { p['text-color'] = palette.text; p['text-halo-color'] = palette.text_halo; }
						if (layout['icon-image'] && palette.poi) { p['icon-color'] = palette.poi; }
					}
					break;
			}
		});

		return style;
	}

	/* -------------------------------------------------------------- overrides */

	/**
	 * Whether the overrides contain anything that affects the look.
	 *
	 * @param {Object} ov Overrides.
	 * @return {boolean}
	 */
	function hasOverrides(ov) {
		if (!ov) { return false; }
		if (ov.colors && Object.keys(ov.colors).length) { return true; }
		if (ov.hidePoi || ov.hideBuildings || ov.hide3d || ov.hideTerrain
			|| ov.hideBoundary || ov.hideRail || ov.hideLabels) { return true; }
		if (typeof ov.terrainOpacity === 'number' && ov.terrainOpacity !== 100) { return true; }
		return false;
	}

	/**
	 * Which styling categories a layer affects.
	 *
	 * @param {Object} layer Style layer.
	 * @return {string[]}
	 */
	function overrideKeys(layer) {
		var keys = [];
		var id = String(layer.id || '').toLowerCase();
		var sl = String(layer['source-layer'] || '').toLowerCase();
		var s = id + ' ' + sl;
		var type = layer.type;

		if (type === 'background' || id === 'background') { keys.push('land'); return keys; }

		if (sl === 'waterway' || /waterway/.test(s)) { keys.push('waterway'); }
		else if (sl === 'water' || /\bwater\b|\b(ocean|sea|lake|river)\b/.test(s)) { keys.push('water'); }

		if (sl === 'landcover' || sl === 'landuse' || sl === 'park') {
			if (/wood|forest/.test(s)) { keys.push('forest'); }
			else if (/sand|beach|dune/.test(s)) { keys.push('sand'); }
			else if (/ice|glacier/.test(s)) { keys.push('ice'); }
			else if (sl === 'park' || /grass|park|garden|meadow|green|pitch|cemetery/.test(s)) { keys.push('park'); }
			else { keys.push('land'); }
		}

		if (sl === 'building' || /building/.test(s)) { keys.push('building'); }

		if (/rail|transit|aeroway|runway|taxiway|airport/.test(s)) { keys.push('rail'); }

		if (sl === 'transportation' || /road|street|bridge|tunnel|motorway|path|track/.test(s)) {
			var casing = isCasing(layer);
			var major = isMajor(layer);
			if (casing) { keys.push(major ? 'highway_casing' : 'road_casing'); }
			else { keys.push(major ? 'highway' : 'road'); }
		}

		if (/boundary|admin/.test(s)) { keys.push('boundary'); }

		if (type === 'symbol') {
			var layout = layer.layout || {};
			if (layout['text-field']) { keys.push('text', 'text_halo'); }
			if (layout['icon-image']) { keys.push('poi'); }
		}

		return keys;
	}

	function applyColorOverride(layer, key, o) {
		var p = layer.paint || (layer.paint = {});
		var t = layer.type;
		var op = (o.opacity == null) ? null : Math.max(0, Math.min(1, o.opacity / 100));

		if (key === 'text') { p['text-color'] = o.color; return; }
		if (key === 'text_halo') { p['text-halo-color'] = o.color; return; }
		if (key === 'poi') { p['icon-color'] = o.color; return; }
		if (t === 'background') { p['background-color'] = o.color; return; }

		if (t === 'fill') {
			p['fill-color'] = o.color;
			if (op != null) { p['fill-opacity'] = op; }
		} else if (t === 'line') {
			p['line-color'] = o.color;
			if (op != null) { p['line-opacity'] = op; }
		} else if (t === 'fill-extrusion') {
			p['fill-extrusion-color'] = o.color;
			if (op != null) { p['fill-extrusion-opacity'] = op; }
		}
	}

	function hideLayer(layer) {
		layer.layout = layer.layout || {};
		layer.layout.visibility = 'none';
	}

	/**
	 * Apply manual overrides (colors and visibility) on top of the style.
	 *
	 * @param {Object} style Style.
	 * @param {Object} ov    Overrides from the config.
	 * @return {Object}
	 */
	function applyOverrides(style, ov) {
		if (!style || !style.layers || !ov) { return style; }

		var colors = ov.colors || {};

		style.layers.forEach(function (layer) {
			var id = String(layer.id || '').toLowerCase();
			var sl = String(layer['source-layer'] || '').toLowerCase();
			var s = id + ' ' + sl;

			if (ov.hideLabels && layer.type === 'symbol' && layer.layout && layer.layout['text-field']) { hideLayer(layer); }
			if (ov.hidePoi && layer.type === 'symbol' && (sl === 'poi' || /poi/.test(s))) { hideLayer(layer); }
			if (ov.hideBuildings && layer.type === 'fill' && (sl === 'building' || /building/.test(s))) { hideLayer(layer); }
			if (ov.hide3d && layer.type === 'fill-extrusion') { hideLayer(layer); }
			if (ov.hideTerrain && layer.type === 'raster') { hideLayer(layer); }
			if (ov.hideBoundary && /boundary|admin/.test(s)) { hideLayer(layer); }
			if (ov.hideRail && /rail|transit/.test(s)) { hideLayer(layer); }

			if (layer.type === 'raster' && typeof ov.terrainOpacity === 'number' && ov.terrainOpacity !== 100) {
				var rp = layer.paint || (layer.paint = {});
				rp['raster-opacity'] = Math.max(0, Math.min(1, ov.terrainOpacity / 100));
			}

			if (colors && Object.keys(colors).length) {
				overrideKeys(layer).forEach(function (k) {
					if (colors[k]) { applyColorOverride(layer, k, colors[k]); }
				});
			}
		});

		return style;
	}

	function resolveStyle(cfg) {
		var url = STYLE_BASE + (cfg.baseStyle || 'liberty');
		var palette = cfg.palette && cfg.palette !== 'original' ? PALETTES[cfg.palette] : null;
		var lang = cfg.lang && cfg.lang !== 'auto' ? cfg.lang : '';
		var ov = cfg.overrides && hasOverrides(cfg.overrides) ? cfg.overrides : null;
		if (!palette && !lang && !ov) { return Promise.resolve(url); }
		return fetch(url)
			.then(function (r) { return r.json(); })
			.then(function (style) {
				if (palette) { recolor(style, palette); }
				if (lang) { localizeStyle(style, lang); }
				if (ov) { applyOverrides(style, ov); }
				return style;
			})
			.catch(function () { return url; });
	}

	/**
	 * Labels in the selected language: in text layers, replace fields like
	 * name / name:latin / name:nonlatin with name:<lang>, falling back to the
	 * original value. The substitution is recursive inside the expression, so
	 * it is safe for both simple ['get', ...] and complex ['format', ...].
	 *
	 * @param {Object} style Style.
	 * @param {string} lang  Language code (ru, en, ...).
	 * @return {Object}
	 */
	function localizeStyle(style, lang) {
		if (!style || !style.layers || !lang) { return style; }

		var field = 'name:' + lang;

		style.layers.forEach(function (layer) {
			if (layer.type !== 'symbol' || !layer.layout) { return; }
			var tf = layer.layout['text-field'];
			if (!tf) { return; }
			layer.layout['text-field'] = localizeExpr(tf, field);
		});

		return style;
	}

	function isNameGet(expr) {
		return Array.isArray(expr) && expr[0] === 'get'
			&& typeof expr[1] === 'string' && /^name(:[\w-]+)?$/.test(expr[1]);
	}

	function localizeExpr(expr, field) {
		if (!Array.isArray(expr)) { return expr; }

		if (isNameGet(expr)) {
			if (expr[1] === field) { return expr; }
			return ['coalesce', ['get', field], expr];
		}

		return expr.map(function (child) { return localizeExpr(child, field); });
	}

	/* -------------------------------------------------------------- elements */

	function fallback(el, cfg) {
		if (!el) { return; }
		el.innerHTML = '<div class="office-map__fallback">'
			+ '<strong>' + (cfg.title || '') + '</strong><br>'
			+ nl2br(cfg.address || '')
			+ '</div>';
	}

	/**
	 * HTML-aware text: line breaks → <br>.
	 * The content has already been sanitized on the server (wp_kses_post).
	 *
	 * @param {string} value Value.
	 * @return {string}
	 */
	function nl2br(value) {
		return String(value == null ? '' : value).replace(/\r\n|\r|\n/g, '<br>');
	}

	function buildMarkerEl(url) {
		var img = document.createElement('img');
		img.className = 'office-map__marker';
		img.src = url;
		img.alt = '';
		return img;
	}

	function buildPopup(m, cfg) {
		var box = document.createElement('div');
		box.className = 'office-map__popup';

		if (m.title) {
			var t = document.createElement('strong');
			t.textContent = m.title;
			box.appendChild(t);
		}
		if (m.address) {
			if (m.title) { box.appendChild(document.createElement('br')); }
			var ad = document.createElement('span');
			ad.className = 'office-map__popup-address';
			ad.innerHTML = nl2br(m.address);
			box.appendChild(ad);
		}

		return new maplibregl.Popup({ offset: (m.image || cfg.markerImage) ? 46 : 28, closeButton: false })
			.setDOMContent(box);
	}

	/* -------------------------------------------------------------- markers */

	function ensureMarkers(st, cfg) {
		if (st.markers && st.markers.length) {
			st.markers.forEach(function (item) {
				try { item.marker.remove(); } catch (e) {}
			});
		}
		st.markers = [];

		(cfg.markers || []).forEach(function (m) {
			var img = m.image || cfg.markerImage;
			var color = m.color || cfg.markerColor || '#e63946';

			var marker = img
				? new maplibregl.Marker({ element: buildMarkerEl(img), anchor: 'bottom' })
				: new maplibregl.Marker({ color: color });

			marker.setLngLat([m.lng, m.lat]).addTo(st.map);

			var popup = null;
			if (m.title || m.address) {
				popup = buildPopup(m, cfg).setLngLat([m.lng, m.lat]);
				marker.setPopup(popup);
				if (m.open) { popup.addTo(st.map); }
			}

			st.markers.push({ marker: marker, popup: popup });
		});
	}

	function markerSig(cfg) {
		return [
			cfg.marker ? 1 : 0,
			cfg.markerColor,
			cfg.markerImage,
			JSON.stringify(cfg.markers || [])
		].join('|');
	}

	/* -------------------------------------------------------------- camera */

	function applyCamera(map, cfg) {
		var markers = cfg.markers || [];

		if (cfg.zoomMode === 'fit' && markers.length) {
			var bounds = new maplibregl.LngLatBounds();
			markers.forEach(function (m) { bounds.extend([m.lng, m.lat]); });
			map.fitBounds(bounds, { padding: 60, maxZoom: 17, duration: 0 });
		} else {
			map.jumpTo({ center: [cfg.lng, cfg.lat], zoom: cfg.zoom });
		}
	}

	/* -------------------------------------------------------------- width */

	function applyWidth(el, cfg) {
		var w = cfg.width || { preset: 'block' };
		// In preview mode, do not apply "full width" (it is clipped by the container).
		var preset = (el.classList.contains('office-map--preview') && w.preset === 'full') ? 'block' : w.preset;

		el.classList.toggle('office-map--full', preset === 'full');

		if (preset === 'full') {
			el.style.width = '100vw';
			el.style.maxWidth = '100vw';
			el.style.marginLeft = 'calc(50% - 50vw)';
		} else if (preset === 'custom') {
			el.style.width = (w.value || 100) + (w.unit || '%');
			el.style.maxWidth = 'none';
			el.style.marginLeft = '';
		} else {
			el.style.width = '100%';
			el.style.maxWidth = '';
			el.style.marginLeft = '';
		}
	}

	/* -------------------------------------------------------------- search control */

	function applySearchStyle(container, s) {
		if (!container || !s) { return; }
		var set = function (name, val) { if (val) { container.style.setProperty(name, val); } };
		set('--om-search-bg', s.bg);
		set('--om-search-text', s.text);
		set('--om-search-border', s.border);
		set('--om-search-btn-bg', s.btnBg);
		set('--om-search-btn-text', s.btnText);
		if (s.radius != null) { container.style.setProperty('--om-search-radius', s.radius + 'px'); }
	}

	function SearchControl(searchStyle) {
		this._container = null;
		this._results = null;
		this._map = null;
		this._marker = null;
		this._style = searchStyle || null;
	}

	SearchControl.prototype.onAdd = function (map) {
		this._map = map;

		var c = document.createElement('div');
		c.className = 'maplibregl-ctrl maplibregl-ctrl-group office-map-search';

		var input = document.createElement('input');
		input.type = 'text';
		input.className = 'office-map-search-input';
		input.placeholder = t('searchPlaceholder');

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'office-map-search-btn';
		btn.textContent = t('searchButton');

		var results = document.createElement('div');
		results.className = 'office-map-search-results office-map-search-results--map';

		var row = document.createElement('div');
		row.className = 'office-map-search__row';
		row.appendChild(input);
		row.appendChild(btn);

		c.appendChild(row);
		c.appendChild(results);

		if (this._style) { applySearchStyle(c, this._style); }

		var self = this;
		function run() { self._search(input.value, results); }

		btn.addEventListener('click', function (e) { e.stopPropagation(); run(); });
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); run(); }
		});
		['mousedown', 'dblclick', 'wheel', 'touchstart'].forEach(function (ev) {
			c.addEventListener(ev, function (e) { e.stopPropagation(); });
		});

		this._container = c;
		this._results = results;
		return c;
	};

	SearchControl.prototype.onRemove = function () {
		if (this._marker) { this._marker.remove(); this._marker = null; }
		return this._container;
	};

	SearchControl.prototype._search = function (q, results) {
		if (!q || q.length < 3) { return; }
		var self = this;
		var data = window.OfficeMapData || {};

		if (!data.ajaxurl) { return; }

		results.innerHTML = '<div class="office-map-search-hint">' + t('searching') + '</div>';
		results.style.display = 'block';

		var body = 'action=office_map_geocode'
			+ '&nonce=' + encodeURIComponent(data.nonce || '')
			+ '&q=' + encodeURIComponent(q);

		fetch(data.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body
		})
			.then(function (r) { return r.json(); })
			.then(function (res) {
				var list = res && res.success ? res.data : null;
				if (!list || !list.length) {
					results.innerHTML = '<div class="office-map-search-hint">' + t('nothingFound') + '</div>';
					return;
				}
				results.innerHTML = '';
				list.forEach(function (item) {
					var row = document.createElement('button');
					row.type = 'button';
					row.className = 'office-map-search-result';
					row.textContent = item.label;
					row.addEventListener('click', function (e) { e.stopPropagation(); self._go(item, results); });
					results.appendChild(row);
				});
			})
			.catch(function () {
				results.innerHTML = '<div class="office-map-search-hint">' + t('searchError') + '</div>';
			});
	};

	SearchControl.prototype._go = function (item, results) {
		results.style.display = 'none';
		this._map.flyTo({ center: [item.lng, item.lat], zoom: 16 });

		if (this._marker) { this._marker.remove(); }
		this._marker = new maplibregl.Marker({ color: '#1a73e8' }).setLngLat([item.lng, item.lat]).addTo(this._map);
	};

	/* -------------------------------------------------------------- controls */

	function ensureControls(st, cfg) {
		if (!st.nav) {
			st.nav = new maplibregl.NavigationControl();
			st.map.addControl(st.nav, 'top-right');
		}

		if (cfg.fullscreen && !st.fs) {
			st.fs = new maplibregl.FullscreenControl();
			st.map.addControl(st.fs, 'top-right');
		} else if (!cfg.fullscreen && st.fs) {
			st.map.removeControl(st.fs);
			st.fs = null;
		}

		if (cfg.search && !st.search) {
			st.search = new SearchControl(cfg.searchStyle);
			st.map.addControl(st.search, 'top-left');
		} else if (!cfg.search && st.search) {
			st.map.removeControl(st.search);
			st.search = null;
		}

		if (st.search) {
			applySearchStyle(st.search._container, cfg.searchStyle);
		}
	}

	/* -------------------------------------------------------------- lifecycle */

	function createMap(el, cfg) {
		el.__officeMapPending = true;

		resolveStyle(cfg).then(function (styleIn) {
			var map = new maplibregl.Map({
				container: el,
				style: styleIn,
				center: [cfg.lng, cfg.lat],
				zoom: cfg.zoom,
				attributionControl: true,
				scrollZoom: cfg.scroll
			});

			var st = { map: map, cfg: cfg, nav: null, fs: null, search: null, markers: [] };
			el.__officeMap = st;
			el.__officeMapPending = false;

			ensureControls(st, cfg);

			map.on('load', function () {
				ensureMarkers(st, st.cfg);
				applyCamera(map, st.cfg);
			});

			if (el.__officeMapQueued) {
				var queued = el.__officeMapQueued;
				el.__officeMapQueued = null;
				applyConfig(el, queued);
			}
		});
	}

	function applyConfig(el, cfg) {
		if (!el || !cfg) { return; }

		applyWidth(el, cfg);
		if (typeof cfg.height === 'number' && cfg.height > 0) {
			el.style.height = cfg.height + 'px';
		}

		if (typeof maplibregl === 'undefined') {
			fallback(el, cfg);
			return;
		}

		var st = el.__officeMap;

		if (!st) {
			if (el.__officeMapPending) {
				el.__officeMapQueued = cfg;
			} else {
				createMap(el, cfg);
			}
			return;
		}
		el.__officeMapQueued = null;

		var prev = st.cfg;
		var styleChanged = prev.baseStyle !== cfg.baseStyle || prev.palette !== cfg.palette
			|| prev.lang !== cfg.lang
			|| JSON.stringify(prev.overrides || null) !== JSON.stringify(cfg.overrides || null);
		var markersChanged = markerSig(prev) !== markerSig(cfg);
		var cameraChanged = prev.lat !== cfg.lat || prev.lng !== cfg.lng || prev.zoom !== cfg.zoom
			|| prev.zoomMode !== cfg.zoomMode;

		st.cfg = cfg;

		if (styleChanged) {
			resolveStyle(cfg).then(function (styleIn) { st.map.setStyle(styleIn); });
		}

		ensureControls(st, cfg);

		if (markersChanged) {
			ensureMarkers(st, cfg);
		}

		if (cameraChanged || markersChanged) {
			applyCamera(st.map, cfg);
		}
	}

	function buildFromData(el) {
		var cfg;
		try { cfg = JSON.parse(el.getAttribute('data-config')); } catch (e) { return; }
		if (cfg) { applyConfig(el, cfg); }
	}

	function boot() {
		var els = document.querySelectorAll('.office-map[data-config]');
		Array.prototype.forEach.call(els, buildFromData);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.OfficeMap = {
		apply: applyConfig,
		recolor: recolor,
		applyOverrides: applyOverrides,
		palettes: PALETTES,
		styleBase: STYLE_BASE
	};
})();
