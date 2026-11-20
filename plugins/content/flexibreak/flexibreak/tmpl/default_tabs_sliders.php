<?php

// - RETURNED HTML is used as 'toc' 
// - Optionally set 'visible pages HTML' into $this->_text 

$this->params->set('multipage_toc', 0);  // TOC is not applicable, this file will not return any HTML as 'toc'
$this->params->set('pagination', 0);     // Prev/Next navigation is not applicable

// Handle show all
if ($this->showall)
{
	// return;  // Return will also work as all pages text will be used by default
	$this->_text = '';

	for ($i = 0; $i <= $this->pagescount; $i++)
	{
		$this->_text .= $this->texts[$i] . ($i < $this->pagescount ? '<hr class="articlePageEnd" />' : '');
	}
	return;
}

$display_method = (int) $this->params->get('display_method', 1);
if (FLEXI_J40GE)
{
	$accordion = $display_method === 4;
	$selector = 'article' . $this->row->id . ($accordion ? '-sliders' : '-tabs');
	$t = array($this->texts[0]);
	$t[] = \Joomla\CMS\HTML\HTMLHelper::_($accordion ? 'bootstrap.startAccordion' : 'uitab.startTabSet', $selector, array('active' => $selector . '-1', 'parent' => true));
	for ($i = 1; $i <= $this->pagescount; $i++)
	{
		$page = $this->_generateToc($this->row, $i);
		$t[] = $accordion
			? \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', $selector, htmlspecialchars($page->title, ENT_QUOTES, 'UTF-8'), $selector . '-' . $i)
			: \Joomla\CMS\HTML\HTMLHelper::_('uitab.addTab', $selector, $selector . '-' . $i, $page->title);
		$t[] = $this->texts[$i];
		$t[] = \Joomla\CMS\HTML\HTMLHelper::_($accordion ? 'bootstrap.endSlide' : 'uitab.endTab');
	}
	$t[] = \Joomla\CMS\HTML\HTMLHelper::_($accordion ? 'bootstrap.endAccordion' : 'uitab.endTabSet');
	$this->_text = implode("\n", $t);
	return;
}
$style = $display_method === 4 ? 'sliders' : 'tabs';

// Add introduction text
$t[] = $this->texts[0];

if ($this->pagescount)
{
	// Start TAB-set / Sliders
	$t[] = (string) \Joomla\CMS\HTML\HTMLHelper::_($style . '.start', 'article' . $this->row->id . '-' . $style);
	
	// Create 1 TAB / Slider per page
	$n = !empty($this->texts[0]) ? -1 : 0;

	for ($i = 1; $i <= $this->pagescount; $i++)
	{
		$page = $this->_generateToc($this->row, $i);  // Create page data of current page, needed to create TOC navigation entries (in our case TAB / Slider handle)
		$t[] = \Joomla\CMS\HTML\HTMLHelper::_($style . '.panel', $page->title, 'article' . $this->row->id . '-' . $style . $i);
		$t[] = $this->texts[$i];
	}
	
	// End TAB-set / Sliders
	$t[] = (string) \Joomla\CMS\HTML\HTMLHelper::_($style . '.end');
}

// Create 'visible pages text' (visible without page reload)
$this->_text = implode("\n", $t);
