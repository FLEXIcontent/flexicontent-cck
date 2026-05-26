<?php
/**
 * FLEXIcontent – pagination.php
 * Supports: classic pagination + infinite scroll (button or auto-scroll)
 *
 * Parameter options (add in the category view profile):
 *   infinite_scroll        : 0 = disabled (default), 1 = "Load more" button, 2 = auto-scroll
 *   show_pagination        : unchanged behaviour (0 = hidden, 1 = shown, 2 = auto)
 *   show_pagination_results: unchanged behaviour
 */

defined('_JEXEC') or die;

// ── Infinite scroll parameters ───────────────────────────────────────────────
$infiniteMode   = (int) $this->params->get('infinite_scroll', 0);
// 0 = classique, 1 = bouton "Charger plus", 2 = auto-scroll
$infiniteMode   = 1;

$showPagination = (int) $this->params->get('show_pagination', 2);

// ── Pagination data ───────────────────────────────────────────────────────────
// FCPagination extends JPagination: use public properties directly (no get() method)
$pageNav      = $this->pageNav;
$itemsPerPage = (int) $pageNav->limit;
$totalItems   = (int) $pageNav->total;
$limitStart   = (int) $pageNav->limitstart;
$currentPage  = ($itemsPerPage > 0) ? (int) floor($limitStart / $itemsPerPage) + 1 : 1;
$totalPages   = ($itemsPerPage > 0) ? (int) ceil($totalItems / $itemsPerPage)   : 1;
$nextStart    = $limitStart + $itemsPerPage;
$remaining    = max(0, $totalItems - $nextStart);
$nextBatch    = min($itemsPerPage, $remaining);
$hasMore      = $currentPage < $totalPages;

// Base URL without limitstart – compatible with Joomla 4/5/6
$uriClass   = class_exists('Joomla\CMS\Uri\Uri') ? 'Joomla\CMS\Uri\Uri' : 'JUri';
$currentUrl = $uriClass::getInstance()->toString(['scheme','host','path','query']);
// Strip any existing limitstart so JS can append it cleanly
$baseUrl = preg_replace('/([?&])limitstart=\d+(&|$)/', '$1', $currentUrl);
$baseUrl = rtrim($baseUrl, '?&');
?>

<?php if ($infiniteMode === 0) : ?>
<!-- ══ CLASSIC PAGINATION ══════════════════════════════════════════════════ -->
<?php if ($showPagination !== 0) : ?>
<div class="pagination fc-pagination-classic">

	<?php if ($this->params->get('show_pagination_results', 1)) : ?>
	<p class="counter pull-right">
		<?php echo $pageNav->getPagesCounter(); ?>
	</p>
	<?php endif; ?>

	<?php echo $pageNav->getPagesLinks(); ?>

</div>
<?php endif; ?>

<?php else : ?>
<!-- ══ INFINITE SCROLL (mode <?php echo $infiniteMode; ?>) ═════════════════ -->

<!-- Root items container – new items will be appended here -->
<!-- .featured-block and .standard-block are resolved automatically -->

<?php if ($hasMore) : ?>
<div id="fc-infinite-wrap"
	 data-mode="<?php echo $infiniteMode; ?>"
	 data-next-start="<?php echo $nextStart; ?>"
	 data-limit="<?php echo $itemsPerPage; ?>"
	 data-total="<?php echo $totalItems; ?>"
	 data-base-url="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES); ?>"
	 data-next-batch="<?php echo $nextBatch; ?>">

	<?php if ($infiniteMode === 1) : ?>
	<!-- "Load more" button -->
	<div class="fc-loadmore-wrapper text-center my-3">
		<button id="fc-load-more-btn"
				class="btn btn-outline-primary fc-load-more-btn"
				type="button">
			<?php echo JText::sprintf('COM_FLEXICONTENT_LOAD_MORE_X', $nextBatch); ?>
		</button>
	</div>
	<?php endif; ?>

	<?php if ($infiniteMode === 2) : ?>
	<!-- Sentinel element for IntersectionObserver (auto-scroll mode) -->
	<div id="fc-scroll-sentinel" class="fc-scroll-sentinel" aria-hidden="true">
		<div class="fc-spinner spinner-border text-secondary" role="status" style="display:none">
			<span class="visually-hidden"><?php echo JText::_('COM_FLEXICONTENT_LOADING'); ?></span>
		</div>
	</div>
	<?php endif; ?>

</div>
<?php endif; ?>

<!-- Results counter – displayed in all modes -->
<?php if ($this->params->get('show_pagination_results', 1)) : ?>
<p class="fc-pagination-counter text-muted small text-center mt-2" id="fc-items-counter">
	<?php echo $pageNav->getPagesCounter(); ?>
</p>
<?php endif; ?>

<!-- Inline script: FCInfiniteScroll logic + bootstrap -->
<script>
(function (window, document) {
	'use strict';

	// ── Internal state ───────────────────────────────────────────────────────
	var cfg       = {};
	var loading   = false;  // true while a fetch is in flight
	var exhausted = false;  // true when no more pages to load
	var currentController = null; // AbortController for the active fetch

	// ── DOM elements ─────────────────────────────────────────────────────────
	// Two target containers: .featured-block and .standard-block
	var featuredEl = null;
	var standardEl = null;
	var wrapEl     = null;
	var btnEl      = null;
	var sentinelEl = null;
	var counterEl  = null;
	var observer   = null;

	// ── Helpers ──────────────────────────────────────────────────────────────

	function buildUrl(start) {
		var base = cfg.baseUrl;
		var sep  = base.indexOf('?') !== -1 ? '&' : '?';
		return base + sep + 'limitstart=' + start;
	}

	function updateBtnLabel(remaining) {
		if (!btnEl) return;
		var nextBatch = Math.min(cfg.limit, remaining);
		var label = cfg.labelTpl
			.replace('%s', nextBatch)
			.replace('{0}', nextBatch);
		btnEl.textContent = label;
	}

	/**
	 * Extract innerHTML of a selector from a remote parsed document.
	 * Returns null silently if the block is absent (it may legitimately not exist).
	 */
	function extractBlock(doc, selector) {
		var el = doc.querySelector(selector);
		return el ? el.innerHTML : null;
	}

	/**
	 * Append HTML children into a local container.
	 * Returns true if at least one node was inserted.
	 */
	function appendTo(containerEl, html) {
		if (!containerEl || !html) return false;
		var temp = document.createElement('div');
		temp.innerHTML = html;
		var nodes = Array.from(temp.children);
		if (nodes.length === 0) return false;
		nodes.forEach(function (node) {
			containerEl.appendChild(node);
		});
		return true;
	}

	function updateCounter(loadedSoFar) {
		if (!counterEl) return;
		counterEl.textContent = '1 – ' + Math.min(loadedSoFar, cfg.total) + ' / ' + cfg.total;
	}

	// ── Abort helper ─────────────────────────────────────────────────────────

	/**
	 * Cancel any in-flight fetch and return a fresh AbortController.
	 * Prevents duplicate requests from rapid clicks or fast scroll triggers.
	 */
	function getFreshController() {
		if (currentController) {
			currentController.abort();
		}
		currentController = new AbortController();
		return currentController;
	}

	// ── Load more ────────────────────────────────────────────────────────────

	function loadMore() {
		if (loading || exhausted) return;
		loading = true;

		var controller = getFreshController();

		if (btnEl) {
			btnEl.disabled = true;
			btnEl.classList.add('fc-loading');
		}
		var spinner = sentinelEl && sentinelEl.querySelector('.fc-spinner');
		if (spinner) spinner.style.display = 'inline-block';

		fetch(buildUrl(cfg.nextStart), {
			method : 'GET',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			signal : controller.signal  // tied to AbortController
		})
		.then(function (res) {
			if (!res.ok) throw new Error('HTTP ' + res.status);
			return res.text();
		})
		.then(function (html) {
			var parser    = new DOMParser();
			var remoteDoc = parser.parseFromString(html, 'text/html');

			// Inject each block if present in the response
			var hasFeatured = appendTo(featuredEl, extractBlock(remoteDoc, '.featured-block'));
			var hasStandard = appendTo(standardEl, extractBlock(remoteDoc, '.standard-block'));

			// End of list if both blocks are empty
			if (!hasFeatured && !hasStandard) {
				exhaust();
				return;
			}

			cfg.nextStart += cfg.limit;
			var remaining = cfg.total - cfg.nextStart;

			updateCounter(cfg.nextStart);

			if (remaining <= 0) {
				exhaust();
			} else {
				updateBtnLabel(remaining);
			}
		})
		.catch(function (err) {
			// Ignore aborted requests (not a real error)
			if (err.name === 'AbortError') return;
			console.error('FCInfiniteScroll: fetch error', err);
		})
		.finally(function () {
			loading = false;
			if (btnEl) {
				btnEl.disabled = false;
				btnEl.classList.remove('fc-loading');
			}
			if (spinner) spinner.style.display = 'none';
		});
	}

	function exhaust() {
		exhausted = true;
		if (wrapEl) wrapEl.style.display = 'none';
		if (observer) observer.disconnect();
	}

	// ── Modes ────────────────────────────────────────────────────────────────

	function initButtonMode() {
		if (!btnEl) return;
		btnEl.addEventListener('click', function () { loadMore(); });
	}

	function initAutoScrollMode() {
		if (!sentinelEl) return;

		// Fallback to button mode if IntersectionObserver is unavailable
		if (!('IntersectionObserver' in window)) {
			initButtonMode();
			return;
		}

		// Debounce: ignore re-entry while a fetch is already in flight.
		// The loading flag + AbortController together prevent duplicate requests
		// even if the observer fires multiple times during fast scroll.
		observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting && !loading && !exhausted) {
					loadMore();
				}
			});
		}, { rootMargin: '200px' });

		observer.observe(sentinelEl);
	}

	// ── Init ─────────────────────────────────────────────────────────────────

	function init(options) {
		cfg = Object.assign({
			mode     : 1,
			wrapEl   : '#fc-infinite-wrap',
			btnEl    : '#fc-load-more-btn',
			sentinel : '#fc-scroll-sentinel',
			counter  : '#fc-items-counter',
			baseUrl  : '',
			limit    : 10,
			nextStart: 10,
			total    : 0,
			labelTpl : 'Load %s more items'
		}, options);

		featuredEl = document.querySelector('.featured-block');
		standardEl = document.querySelector('.standard-block');
		wrapEl     = document.querySelector(cfg.wrapEl);
		btnEl      = document.querySelector(cfg.btnEl);
		sentinelEl = document.querySelector(cfg.sentinel);
		counterEl  = document.querySelector(cfg.counter);

		if (!featuredEl && !standardEl) {
			console.error('FCInfiniteScroll: no target block found (.featured-block, .standard-block)');
			return;
		}

		if (cfg.mode === 1) {
			initButtonMode();
		} else if (cfg.mode === 2) {
			initAutoScrollMode();
		}
	}

	// ── Bootstrap with PHP-injected data ─────────────────────────────────────
	document.addEventListener('DOMContentLoaded', function () {
		init({
			mode     : <?php echo $infiniteMode; ?>,
			wrapEl   : '#fc-infinite-wrap',
			btnEl    : '#fc-load-more-btn',
			sentinel : '#fc-scroll-sentinel',
			counter  : '#fc-items-counter',
			baseUrl  : <?php echo json_encode($baseUrl); ?>,
			limit    : <?php echo $itemsPerPage; ?>,
			nextStart: <?php echo $nextStart; ?>,
			total    : <?php echo $totalItems; ?>,
			labelTpl : <?php echo json_encode(JText::_('COM_FLEXICONTENT_LOAD_MORE_X')); ?>
		});
	});

}(window, document));
</script>

<?php endif; ?>
<!-- EOF pagination -->