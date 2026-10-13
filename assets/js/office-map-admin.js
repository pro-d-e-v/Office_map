/**
 * Office Map — админ: сохранение по кнопке в каждом блоке, свой маркер,
 * список дополнительных меток и поиск адреса.
 */
(function ($) {
	'use strict';

	var settings = window.OfficeMapAdmin || {};
	var geodata = window.OfficeMapData || {};
	var form = document.querySelector('form[action="options.php"]');

	// Localized strings passed from PHP; English fallbacks.
	function i18n(key, fallback) {
		var a = (settings.i18n || {})[key];
		if (a) { return a; }
		var b = (geodata.i18n || {})[key];
		return b || fallback;
	}

	if (!form) {
		return;
	}

	/* ---------------------------------------------------- сохранение */

	// Кнопки «Сохранить» — обычные submit: форма уходит в options.php,
	// WordPress сохраняет настройки и перезагружает страницу с новым превью.

	// Подсветить кнопки, пока есть несохранённые изменения.
	function markDirty() {
		$('.office-map-save').addClass('is-dirty');
	}

	form.addEventListener('input', markDirty);
	form.addEventListener('change', markDirty);

	/* ---------------------------------------------------- color picker */

	function enableStyleRow($el) {
		var $on = $el.closest('tr').find('.office-map-style-on');
		if ($on.length && !$on.prop('checked')) {
			$on.prop('checked', true);
		}
	}

	function syncStyleRowState($on) {
		$on.closest('tr').toggleClass('is-off', !$on.prop('checked'));
	}

	$(function () {
		// Все простые цветовые поля (маркер, точка, поиск и т.п.) — кроме таблицы стилей и шаблона точки.
		$('.office-map-color').each(function () {
			if ($(this).closest('#office-map-style-colors, .office-map-point-template').length) { return; }
			$(this).wpColorPicker({ change: markDirty, clear: markDirty });
		});

		$('#office-map-style-colors .office-map-color').each(function () {
			var $inp = $(this);
			$inp.wpColorPicker({
				change: function () { enableStyleRow($inp); syncStyleRowState($inp.closest('tr').find('.office-map-style-on')); markDirty(); },
				clear: function () { markDirty(); }
			});
		});

		$('#office-map-style-colors .office-map-style-on').each(function () {
			syncStyleRowState($(this));
		});
	});

	$(document).on('change', '.office-map-style-on', function () {
		syncStyleRowState($(this));
	});

	// Ползунок прозрачности ↔ числовое поле (и авто-включение слоя).
	$(document).on('input', '.office-map-opacity-range', function () {
		$(this).closest('.office-map-opacity').find('.office-map-opacity-num').val(this.value);
		enableStyleRow($(this));
		syncStyleRowState($(this).closest('tr').find('.office-map-style-on'));
		markDirty();
	});

	$(document).on('input', '.office-map-opacity-num', function () {
		var v = parseInt(this.value, 10);
		if (isNaN(v)) { v = 100; }
		v = Math.max(0, Math.min(100, v));
		$(this).closest('.office-map-opacity').find('.office-map-opacity-range').val(v);
		enableStyleRow($(this));
		syncStyleRowState($(this).closest('tr').find('.office-map-style-on'));
		markDirty();
	});

	/* ---------------------------------------------------- медиабиблиотека */

	var frame = null;
	var mediaTarget = null;

	$(document).on('click', '.office-map-upload', function (e) {
		e.preventDefault();
		mediaTarget = $(this).data('target');

		if (!frame) {
			frame = wp.media({
				title: i18n('mediaTitle', 'Marker image'),
				button: { text: i18n('mediaButton', 'Use image') },
				multiple: false
			});
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				var $input = $(mediaTarget);
				$input.val(att.id);
				$input.closest('.office-map-image-field').find('.office-map-marker-preview')
					.html('<img src="' + att.url + '" alt="">');
				markDirty();
			});
		}

		frame.open();
	});

	$(document).on('click', '.office-map-remove', function (e) {
		e.preventDefault();
		var $input = $(this).data('target') ? $($(this).data('target')) : $();
		$input.val('');
		$input.closest('.office-map-image-field').find('.office-map-marker-preview').empty();
		markDirty();
	});

	/* ---------------------------------------------------- поиск адреса */

	function targets($box) {
		var latSel = $box.data('lat');
		var lngSel = $box.data('lng');

		if (latSel && lngSel) {
			return { lat: $(latSel), lng: $(lngSel), row: $box.closest('.office-map-point') };
		}

		var $row = $box.closest('.office-map-point');
		return {
			lat: $row.find('.office-map-point-lat'),
			lng: $row.find('.office-map-point-lng'),
			row: $row
		};
	}

	function geosearch($box) {
		var $input = $box.find('.office-map-search-input');
		var $results = $box.find('.office-map-search-results');
		var q = $.trim($input.val());

		if (q.length < 3 || !geodata.ajaxurl) {
			return;
		}

		$results.html('<div class="office-map-search-hint">' + i18n('searching', 'Searching…') + '</div>').show();

		$.post(geodata.ajaxurl, {
			action: 'office_map_geocode',
			nonce: geodata.nonce,
			q: q
		}).done(function (res) {
			var list = res && res.success ? res.data : null;
			if (!list || !list.length) {
				$results.html('<div class="office-map-search-hint">' + i18n('nothingFound', 'Nothing found') + '</div>');
				return;
			}
			$results.empty();
			list.forEach(function (item) {
				$('<button type="button" class="office-map-search-result"></button>')
					.text(item.label)
					.on('click', function () {
						var t = targets($box);
						t.lat.val(item.lat);
						t.lng.val(item.lng);

						$results.hide();

						if (t.row.length) {
							var $addr = t.row.find('.office-map-point-address');
							if ($addr.length && !$.trim($addr.val())) {
								$addr.val(item.label);
							}
						} else {
							var $mainAddr = $('#office_map_address');
							if ($mainAddr.length && !$.trim($mainAddr.val())) {
								$mainAddr.val(item.label);
							}
						}

						t.lat.trigger('change');
						markDirty();
					})
					.appendTo($results);
			});
		}).fail(function () {
			$results.html('<div class="office-map-search-hint">' + i18n('searchError', 'Search error') + '</div>');
		});
	}

	$(document).on('click', '.office-map-search-btn', function (e) {
		e.preventDefault();
		geosearch($(this).closest('.office-map-searchbox'));
	});

	$(document).on('keydown', '.office-map-search-input', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			geosearch($(this).closest('.office-map-searchbox'));
		}
	});

	$(document).on('click', '.office-map-search-result', function (e) {
		e.stopPropagation();
	});

	/* ---------------------------------------------------- список меток */

	var uid = 1000;

	$(document).on('click', '#office-map-add-point', function (e) {
		e.preventDefault();
		var tpl = document.querySelector('.office-map-point-template .office-map-point');
		if (!tpl) { return; }

		var html = tpl.outerHTML.replace(/__i__/g, 'p' + (uid++));
		$('#office-map-points').append(html);

		var $row = $('#office-map-points .office-map-point').last();
		if ($.fn.wpColorPicker) {
			$row.find('.office-map-color').wpColorPicker({ change: markDirty, clear: markDirty });
		}

		markDirty();
	});

	$(document).on('click', '.office-map-point-remove', function (e) {
		e.preventDefault();
		$(this).closest('.office-map-point').remove();
		markDirty();
	});

	/* ---------------------------------------------------- компактная сетка */

	function fieldEl(key) {
		return form.querySelector('[name="office_map_options[' + key + ']"]');
	}

	// Условный показ полей по значению другого поля
	// (например, «Приближение (zoom)» — только при фиксированном зуме).
	function syncCondFields() {
		Array.prototype.forEach.call(form.querySelectorAll('[data-cond-field]'), function (el) {
			var field = el.getAttribute('data-cond-field');
			var want = el.getAttribute('data-cond-value');
			var f = form.querySelector('[name="office_map_options[' + field + ']"]');
			var cur = f ? String(f.value) : '';
			el.style.display = (cur === want) ? '' : 'none';
		});

		// «Значение» и «Единица» ширины — только для пресета «Своя».
		var wp = fieldEl('width_preset');
		var custom = wp && wp.value === 'custom';
		Array.prototype.forEach.call(form.querySelectorAll('[data-cond-width]'), function (el) {
			el.style.display = custom ? '' : 'none';
		});
	}

	form.addEventListener('change', syncCondFields);
	form.addEventListener('input', syncCondFields);
	syncCondFields();

	// Сворачиваемые блоки: состояние сохраняется вместе с настройками.
	function syncUiOpen() {
		var open = [];
		$('.office-map-section').each(function () {
			if (!$(this).hasClass('is-collapsed')) { open.push($(this).data('key')); }
		});
		$('#office-map-ui-open').val(open.join(','));
	}

	$(document).on('click', '.office-map-section__toggle button', function () {
		var $sec = $(this).closest('.office-map-section');
		var collapsed = !$sec.hasClass('is-collapsed');
		$sec.toggleClass('is-collapsed', collapsed);
		$(this).attr('aria-expanded', collapsed ? 'false' : 'true');
		syncUiOpen();
		markDirty();
	});

	syncUiOpen();

	/* ---------------------------------------------------- копировать шорткод */

	// Поля, которые можно переопределить через шорткод, и их значения по умолчанию.
	var SHORTCODE_FIELDS = [
		{ attr: 'zoom', name: 'zoom', def: '16' },
		{ attr: 'height', name: 'height', def: '420' },
		{ attr: 'title', name: 'title', def: i18n('defaultTitle', 'Our office') },
		{ attr: 'address', name: 'address', def: i18n('defaultAddress', 'Add the office address') },
		{ attr: 'style', name: 'base_style', def: 'liberty' },
		{ attr: 'palette', name: 'palette', def: 'original' },
		{ attr: 'lang', name: 'map_lang', def: 'auto' },
		{ attr: 'zoom_mode', name: 'zoom_mode', def: 'manual' },
		{ attr: 'color', name: 'marker_color', def: '#e63946' },
		{ attr: 'image', name: 'marker_image', def: '' },
		{ attr: 'marker', name: 'marker', def: true, checkbox: true },
		{ attr: 'popup', name: 'popup', def: true, checkbox: true },
		{ attr: 'scroll', name: 'scroll', def: true, checkbox: true },
		{ attr: 'fullscreen', name: 'fullscreen', def: false, checkbox: true },
		{ attr: 'search', name: 'search', def: false, checkbox: true }
	];

	function buildShortcode() {
		var attrs = [];

		function add(attr, val) {
			val = String(val).replace(/"/g, '');
			// HTML, кавычки и переводы строк нельзя надёжно передать внутри атрибута шорткода —
			// такие значения остаются в настройках (шорткод без атрибута их подхватит).
			if (/[<>\n\r]/.test(val)) { return; }
			attrs.push(attr + '="' + val + '"');
		}

		var lat = fieldEl('lat');
		var lng = fieldEl('lng');
		if (lat) { add('lat', lat.value); }
		if (lng) { add('lng', lng.value); }

		SHORTCODE_FIELDS.forEach(function (f) {
			var el = fieldEl(f.name);
			if (!el) { return; }
			if (f.checkbox) {
				if (el.checked !== !!f.def) { add(f.attr, el.checked ? 'yes' : 'no'); }
				return;
			}
			var v = el.value;
			if (v !== '' && String(v) !== String(f.def)) { add(f.attr, v); }
		});

		return '[office_map' + (attrs.length ? ' ' + attrs.join(' ') : '') + ']';
	}

	$(document).on('click', '#office-map-copy-shortcode', function () {
		var $btn = $(this);
		var text = buildShortcode();
		var original = $btn.text();

		function done() {
			$btn.text(i18n('copied', 'Copied!')).addClass('is-copied');
			setTimeout(function () { $btn.text(original).removeClass('is-copied'); }, 1500);
		}

		function fallback() {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.select();
			try { document.execCommand('copy'); } catch (e) {}
			document.body.removeChild(ta);
			done();
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done).catch(fallback);
		} else {
			fallback();
		}
	});
})(jQuery);
