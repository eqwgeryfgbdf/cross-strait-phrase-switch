/**
 * Front-end phrase conversion for Cross-Strait Phrase Switch.
 * Heavy OpenCC / dictionary scripts load only when conversion is needed.
 *
 * @package CrossStraitPhraseSwitch
 */
(function (global) {
	'use strict';

	var settings = global.CSPS_SETTINGS || {};
	var cookieName = settings.cookieName || 'csps_locale';
	var cookiePath = settings.cookiePath || '/';
	var usePuock = !!settings.usePuock;
	var toggleLabel = settings.toggleLabel || '繁中/簡中 切換';
	var taiwanUiFixes = [['釋出評論', '發佈評論']];
	var mainlandUiFixes = [
		['這幺', '这么'],
		['这幺', '这么'],
		['怎幺', '怎么'],
		['那幺', '那么'],
		['資料來源', '资料来源'],
		['资料来源', '资料来源']
	];

	var converterReady = false;
	var converterLoading = null;
	var standaloneHandler = null;

	function asPairs(value) {
		return Array.isArray(value) ? value : [];
	}

	function getCookie(name) {
		var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function setCookie(name, value) {
		var expires = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toUTCString();
		var cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=' + cookiePath + '; SameSite=Lax';
		if (global.location && global.location.protocol === 'https:') {
			cookie += '; Secure';
		}
		document.cookie = cookie;
	}

	function getLocale() {
		var stored = getCookie(cookieName);
		if (stored === 'tw' || stored === 'cn') {
			return stored;
		}
		if (usePuock) {
			return getCookie('pk_lang') === 'tc' ? 'tw' : 'cn';
		}
		return settings.initialLocale === 'tw' ? 'tw' : 'cn';
	}

	function persistLocale(locale) {
		setCookie(cookieName, locale);
		if (usePuock) {
			setCookie('pk_lang', locale === 'tw' ? 'tc' : 'sc');
		}
	}

	function loadScript(url) {
		return new Promise(function (resolve, reject) {
			if (!url) {
				resolve();
				return;
			}
			var script = document.createElement('script');
			script.src = url;
			script.async = false;
			script.onload = function () {
				resolve();
			};
			script.onerror = function () {
				reject(new Error('Cross-Strait Phrase Switch: failed to load ' + url));
			};
			document.head.appendChild(script);
		});
	}

	function bootConverter() {
		var OpenCC = global.OpenCC;
		if (!OpenCC || typeof OpenCC.Converter !== 'function' || typeof OpenCC.HTMLConverter !== 'function') {
			throw new Error('Cross-Strait Phrase Switch: OpenCC is not available after script load.');
		}

		var bundledDicts = global.CSPS_BUNDLED_DICTS || {};
		var userDicts = global.CSPS_USER_DICTS || {};
		var toTaiwanPairs = taiwanUiFixes
			.concat(asPairs(userDicts.toTaiwan))
			.concat(asPairs(bundledDicts.toTaiwan));
		var toMainlandPairs = mainlandUiFixes
			.concat(asPairs(userDicts.toMainland))
			.concat(asPairs(bundledDicts.toMainland));

		var customToTaiwan = typeof OpenCC.CustomConverter === 'function'
			? OpenCC.CustomConverter(toTaiwanPairs)
			: function (text) { return text; };
		var customToMainland = typeof OpenCC.CustomConverter === 'function'
			? OpenCC.CustomConverter(toMainlandPairs)
			: function (text) { return text; };

		var s2twp;
		var tw2sp;
		if (typeof OpenCC.ConverterFactory === 'function' && OpenCC.Locale && OpenCC.Locale.from && OpenCC.Locale.to) {
			s2twp = OpenCC.ConverterFactory(OpenCC.Locale.from.cn, OpenCC.Locale.to.twp);
			tw2sp = OpenCC.ConverterFactory(OpenCC.Locale.from.twp, OpenCC.Locale.to.cn);
		} else {
			s2twp = OpenCC.Converter({ from: 'cn', to: 'twp' });
			tw2sp = OpenCC.Converter({ from: 'twp', to: 'cn' });
		}

		function toTaiwan(text) {
			return customToTaiwan(s2twp(text));
		}

		function toMainland(text) {
			return customToMainland(tw2sp(text))
				.replace(/這幺/g, '这么')
				.replace(/这幺/g, '这么')
				.replace(/怎幺/g, '怎么')
				.replace(/那幺/g, '那么');
		}

		var originalConverter = OpenCC.Converter;
		OpenCC.Converter = function (options) {
			var fromLocale = options && options.from;
			var toLocale = options && options.to;
			if (fromLocale === 'cn' && (toLocale === 'tw' || toLocale === 'twp')) {
				return toTaiwan;
			}
			if ((fromLocale === 'tw' || fromLocale === 'twp') && toLocale === 'cn') {
				return toMainland;
			}
			return originalConverter.call(this, options);
		};

		function markIgnored(root) {
			if (!root || typeof root.querySelectorAll !== 'function') {
				return;
			}
			var nodes = root.querySelectorAll('pre, code, kbd, samp, .wp-block-code, .highlight');
			var index;
			for (index = 0; index < nodes.length; index += 1) {
				nodes[index].classList.add('ignore-opencc');
			}
		}

		var originalHTMLConverter = OpenCC.HTMLConverter;
		OpenCC.HTMLConverter = function (converter, root, fromLang, toLang) {
			var sourceLang = fromLang || 'zh-CN';
			var taiwanHandler = originalHTMLConverter(toTaiwan, root, sourceLang, toLang || 'zh-TW');
			var mainlandHandler = originalHTMLConverter(toMainland, root, sourceLang, 'zh-CN');
			return {
				convert: function () {
					markIgnored(root);
					taiwanHandler.convert();
				},
				restore: function () {
					markIgnored(root);
					taiwanHandler.restore();
					mainlandHandler.convert();
				}
			};
		};

		function ensureStandaloneHandler() {
			if (standaloneHandler) {
				return standaloneHandler;
			}
			var root = document.documentElement;
			root.lang = 'zh-CN';
			standaloneHandler = OpenCC.HTMLConverter(toTaiwan, root, 'zh-CN', 'zh-TW');
			return standaloneHandler;
		}

		function applyConvertedLocale(locale) {
			var handler = ensureStandaloneHandler();
			if (locale === 'tw') {
				handler.convert();
			} else {
				handler.restore();
			}
		}

		global.crossStraitPhraseSwitch.internalApply = applyConvertedLocale;
		converterReady = true;
	}

	function ensureConverter() {
		if (converterReady) {
			return Promise.resolve();
		}
		if (converterLoading) {
			return converterLoading;
		}
		var urls = [];
		if (settings.openCcUrl) {
			urls.push(settings.openCcUrl);
		}
		if (settings.bundledUrl) {
			urls.push(settings.bundledUrl);
		}
		if (settings.userDictUrl) {
			urls.push(settings.userDictUrl);
		}
		if (urls.length === 0) {
			converterLoading = Promise.reject(new Error('Cross-Strait Phrase Switch: converter asset URLs are missing.'));
			return converterLoading;
		}
		converterLoading = urls.reduce(function (chain, url) {
			return chain.then(function () {
				return loadScript(url);
			});
		}, Promise.resolve()).then(function () {
			bootConverter();
		}).catch(function (error) {
			converterLoading = null;
			if (typeof console !== 'undefined' && typeof console.error === 'function') {
				console.error(error && error.message ? error.message : error);
			}
			throw error;
		});
		return converterLoading;
	}

	function applyLocale(locale) {
		if (!converterReady || typeof global.crossStraitPhraseSwitch.internalApply !== 'function') {
			return;
		}
		global.crossStraitPhraseSwitch.internalApply(locale);
	}

	function toggleLocale() {
		var nextLocale = getLocale() === 'tw' ? 'cn' : 'tw';
		persistLocale(nextLocale);
		applyLocale(nextLocale);
		return nextLocale;
	}

	function relabelToggles() {
		var nodes = document.querySelectorAll('.csps-toggle, .sc-tc-toggle');
		var index;
		var node;
		for (index = 0; index < nodes.length; index += 1) {
			node = nodes[index];
			node.setAttribute('title', toggleLabel);
			node.setAttribute('aria-label', toggleLabel);
			node.setAttribute('data-bs-original-title', toggleLabel);
		}
	}

	function onDocumentClick(event) {
		var target = event.target;
		if (!target || !target.closest) {
			return;
		}
		var toggle = target.closest('.csps-toggle, .sc-tc-toggle');
		if (!toggle) {
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		ensureConverter().then(function () {
			toggleLocale();
		}).catch(function (error) {
			if (typeof console !== 'undefined' && typeof console.error === 'function') {
				console.error(error && error.message ? error.message : error);
			}
		});
	}

	function init() {
		relabelToggles();
		if (getLocale() === 'tw') {
			ensureConverter().then(function () {
				applyLocale('tw');
			}).catch(function (error) {
				if (typeof console !== 'undefined' && typeof console.error === 'function') {
					console.error(error && error.message ? error.message : error);
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
	document.addEventListener('click', onDocumentClick, true);

	global.crossStraitPhraseSwitch = {
		getLocale: getLocale,
		toggle: function () {
			return ensureConverter().then(function () {
				return toggleLocale();
			});
		},
		apply: function (locale) {
			return ensureConverter().then(function () {
				applyLocale(locale);
			});
		}
	};
})(window);
