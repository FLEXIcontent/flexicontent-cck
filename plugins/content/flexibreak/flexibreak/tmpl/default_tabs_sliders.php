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
$style = $display_method === 4 ? 'sliders' : 'tabs';

// Add introduction text
$t[] = $this->texts[0];

if ($this->pagescount)
{
	// Start TAB-set / Sliders
	$t[] = (string) JHtml::_($style . '.start', 'article' . $this->row->id . '-' . $style);
	
	// Create 1 TAB / Slider per page
	$n = !empty($this->texts[0]) ? -1 : 0;

	for ($i = 1; $i <= $this->pagescount; $i++)
	{
		$page = $this->_generateToc($this->row, $i);  // Create page data of current page, needed to create TOC navigation entries (in our case TAB / Slider handle)
		$t[] = JHtml::_($style . '.panel', $page->title, 'article' . $this->row->id . '-' . $style . $i);
		$t[] = $this->texts[$i];
	}
	
	// End TAB-set / Sliders
	$t[] = (string) JHtml::_($style . '.end');
}

// Create 'visible pages text' (visible without page reload)
$this->_text = implode("\n", $t);