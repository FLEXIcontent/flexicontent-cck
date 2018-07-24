<?php
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

$display_method = $this->params->get('display_method', 1);
if ($display_method == 0) return;  // No previous / next links if using scroll-to-page (anchors links)

?>
<div class="tocNav">
	<?php
	if ($this->limitstart > 0 || $display_method==1) echo '
		<a href="'.$this->prev_link.'" class="tocPrev" rel="prev" onclick="'.($display_method==1 ? 'return flexibreak.previous();' : '').'">'. JText::_( 'FLEXIBREAK_PREVIOUS_PAGE' ) .'</a>';
	else if ($display_method==2) echo '
		<span class="tocNoPrevNext tocPrev" >'. JText::_( 'FLEXIBREAK_PREVIOUS_PAGE' ) .'</span>';
	
	if ($this->params->get('show_prevnext_count',1)) echo '
		<span class="tocPrevNextCnt">'.($this->limitstart+1).' / '.$this->textscount.'</span>';
	
	if ($this->limitstart < $this->textscount - 1 || $display_method==1) echo '
		<a href="'.$this->next_link.'" class="tocNext" rel="next" onclick="'.($display_method==1 ? 'return flexibreak.next();' : '').'">'. JText::_( 'FLEXIBREAK_NEXT_PAGE' ) .'</a>';
	else if ($display_method==2) echo '
		<span class="tocNoPrevNext tocNext" >'. JText::_( 'FLEXIBREAK_NEXT_PAGE' ) .'</span>';
	?>
</div>
<div class="fcclear"></div>
