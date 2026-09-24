<?php
	// GrapesJS page-builder "filters" block: renders the full adminForm of the category
	// (text search, field filters, items totals, selectors, submit/reset buttons and the
	// ajax filtering script), exactly like the classic page, but WITHOUT the alpha index
	// which the builder page provides as its own block.
	// Nothing is echoed: the captured HTML is assigned to $cat_elements['filters'].

	$_fc_saved_show_alpha = $this->params->get('show_alpha', 1);
	$this->params->set('show_alpha', 0);

	ob_start();
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form.php');
	$this->params->set('show_alpha', $_fc_saved_show_alpha);
	$cat_elements['filters'] = ob_get_clean();