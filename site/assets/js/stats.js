/** Keep ESL's AMD globals away from Joomla's initial script loading. */
(function () {
	'use strict';
	function loadScript(url) {
		return new Promise(function (resolve, reject) {
			var script = document.createElement('script');
			script.src = url;
			script.onload = resolve;
			script.onerror = function () { reject(new Error('Statistics chart script could not be loaded.')); };
			document.head.appendChild(script);
		});
	}
	function start() {
		var options = Joomla.getOptions('flexicontent.stats');
		if (!options) return;
		loadScript(options.loader).then(function () {
			if (typeof window.require !== 'function' || typeof window.require.config !== 'function') throw new Error('Statistics chart loader is unavailable.');
			if (options.packages.length) window.require.config({ packages: options.packages });
			return options.scripts.reduce(function (ready, url) { return ready.then(function () { return loadScript(url); }); }, Promise.resolve());
		}).then(function () {
			document.dispatchEvent(new CustomEvent('flexicontent:stats-ready'));
		}).catch(function () {
			Joomla.renderMessages({ warning: [Joomla.Text._('FLEXI_STATS_CHARTS_UNAVAILABLE')] });
		});
	}
	// Let every existing load handler finish before exposing an AMD loader.
	if (document.readyState === 'complete') setTimeout(start, 0);
	else window.addEventListener('load', function () { setTimeout(start, 0); }, { once: true });
})();
