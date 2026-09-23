<?php
	// GrapesJS Builder-based item layout (Prototype)
	// Renders the Builder's HTML at runtime resolving {flexi_field:..} {flexi_link:..} {flexi_item:..} placeholders
	// Falls back to the classic modular item layout when the Builder mode is disabled or the layout is empty
	$inc_path = JPATH_SITE . DS . 'components' . DS . 'com_flexicontent' . DS;
	require_once($inc_path . 'helpers' . DS . 'route.php');

	$ilayout = $this->params->get('ilayout', 'grapesjs');
	$builder_enabled = (int) $this->params->get('builder_enabled', 1);
	$builder_layout  = trim($this->params->get('builder_layout1_html', ''));

	if ($builder_enabled && strlen($builder_layout))
	{
		$rendered = flexicontent_html::renderBuilderLayout(
			$builder_layout,
			$this->params,
			$this->item,
			array(
				'layout_name' => 'builder_layout1',
				'css_prefix'  => '#flexicontent',
				'css_id'      => $ilayout,
			)
		);

		echo '<div id="flexicontent" class="flexicontent fcitems fcitem' . (int) $this->item->id . '">' . $rendered . '</div>';
	}
	else
	{
		include($inc_path . 'tmpl_common' . DS . 'item_layouts' . DS . 'modular.php');
	}
?>