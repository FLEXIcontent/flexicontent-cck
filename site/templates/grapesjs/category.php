<?php
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too

	$ilayout = $this->params->get('clayout', 'grapesjs');

	// GrapesJS page layout: the whole category page is composed in the Layout Builder.
	// Each {flexi_cat:..} block embeds a pre-rendered element of the classic page.
	$page_builder_enabled = (int) $this->params->get('builder_page_enabled', 1);
	$page_builder_layout  = trim($this->params->get('builder_page1_html', ''));

	if ($page_builder_enabled && $page_builder_layout !== '')
	{
		$tmpl_common = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS;
		$cat_elements = array();

		// Action buttons / page title / author description
		ob_start();
		include($tmpl_common.'category_header.php');
		$cat_elements['title'] = ob_get_clean();

		// Category description
		$cat_elements['category'] = $this->category->id > 0 ? $this->loadTemplate('category') : '';

		// Sub-categories / peer categories
		$cat_elements['subcategories']  = (count($this->categories) && $this->params->get('show_subcategories')) ? $this->loadTemplate('subcategories') : '';
		$cat_elements['peercategories'] = (count($this->peercats) && $this->params->get('show_peercategories')) ? $this->loadTemplate('peercategories') : '';

		// Alpha index
		$cat_elements['alpha'] = $this->params->get('show_alpha', 1) ? $this->loadTemplate('alpha') : '';

		// Filters form: full adminForm (search, field filters, totals, selectors, ajax), without the alpha index
		include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'category_filters_block.php');

		// Pagination
		ob_start();
		include($tmpl_common.'pagination.php');
		$cat_elements['pagination'] = ob_get_clean();

		// Item cards grid
		ob_start();
		include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'category_items.php');
		$cat_elements['items'] = ob_get_clean();

		// The composed page still needs the classic #flexicontent wrapper: the item-card and
		// page CSS are compiled scoped by css_prefix '#flexicontent' (see renderBuilderLayout),
		// and the AJAX filtering JS replaces the innerHTML of this container after a filter apply.
		$page_classes  = '';
		$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
		$page_classes .= ' fccategory fccat'.(isset($this->category->id) ? $this->category->id : 0);
		$_menu = \Joomla\CMS\Factory::getApplication()->getMenu()->getActive();
		if ($_menu) $page_classes .= ' menuitem'.$_menu->id;

		// The action buttons are NOT part of the page-builder layout: always shown top-right
		// (unless disabled via the 'show_buttons' parameter) so they stay available whatever
		// blocks are composed, and they survive the AJAX filtering innerHTML replacement.
		if ($this->params->get('show_buttons', 1))
		{
			echo '<style>.fc-builder-page-buttons{text-align:right;clear:both;margin:0 0 10px}.fc-builder-page-buttons .buttons{float:none}</style>';
			echo '<div class="fc-builder-page-buttons">';
			include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'category_buttons.php');
			echo '</div>';
		}

		echo '<div id="flexicontent" class="flexicontent '.$page_classes.'" >';

		echo flexicontent_html::renderBuilderLayout(
			$page_builder_layout,
			$this->params,
			null,
			array(
				'layout_name'   => 'builder_page1',
				'css_prefix'    => '#flexicontent',
				'css_id'        => $ilayout . 'catpage',
				'template_name' => $ilayout . 'cat',
				'view'          => 'category',
				'cat_elements'  => $cat_elements,
			)
		);

		echo '</div>';
	}
	else
	{
		include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'category.php');
	}