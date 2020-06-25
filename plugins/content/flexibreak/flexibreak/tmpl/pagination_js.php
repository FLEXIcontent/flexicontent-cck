<?php
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

$display_method = (int) $this->params->get('display_method', 1);

// No previous / next links if using scroll-to-page (anchors links)
if ($display_method === 0)
{
	return;
}
?>

<div class="tocNav">
	<?php

	/**
	 * PREVIOUS PAGE
	 */

	if ($this->limitstart > 0 || $display_method === 1)
	{
		echo '
		<a href="' . $this->prev_link . '" class="tocPrev" rel="prev" onclick="' . ($display_method === 1 ? 'return flexibreak.previous();' : '') . '">'
			. JText::_( 'FLEXIBREAK_PREVIOUS_PAGE' ) .
		'</a>';
	}
	elseif ($display_method === 2)
	{
		echo '
		<span class="tocNoPrevNext tocPrev" >
			' . JText::_( 'FLEXIBREAK_PREVIOUS_PAGE' ) . '
		</span>';
	}


	/**
	 * COUNTERS (Previous / Next)
	 */

	if ($this->params->get('show_prevnext_count',1))
	{
		echo '
		<span class="tocPrevNextCnt">
			' . ($this->limitstart+1) . ' / ' . $this->textscount . '
		</span>';
	}


	/**
	 * NEXT PAGE
	 */

	if ($this->limitstart < $this->textscount - 1 || $display_method === 1)
	{
		echo '
		<a href="' . $this->next_link . '" class="tocNext" rel="next" onclick="' . ($display_method === 1 ? 'return flexibreak.next();' : '') . '">'
			. JText::_( 'FLEXIBREAK_NEXT_PAGE' ) .
		'</a>';
	}
	elseif ($display_method === 2)
	{
		echo '
		<span class="tocNoPrevNext tocNext">
			' . JText::_('FLEXIBREAK_NEXT_PAGE') . '
		</span>';
	}
	?>
</div>
<div class="fcclear"></div>
