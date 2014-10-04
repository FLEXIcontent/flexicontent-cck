<?php
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

$display_method = $this->params->get('display_method', 1);
?>
<div class="tocNav">
	<?php
	if ($this->limitstart > 0 || $display_method==1) echo '
		<a href="'.($display_method==2 ? $this->prev_link : 'javascript:;').'" class="tocPrev" onclick="'.($display_method==1 ? 'flexibreak.previous();' : '').'">'. JText::_( 'FLEXIBREAK_PREVIOUS_PAGE' ) .'</a>';
	else if ($display_method==2) echo '
		<span class="tocNoPrevNext" >'. JText::_( 'FLEXIBREAK_NEXT_PAGE' ) .'</span>';
	
	echo $this->params->get('show_prevnext_count',1) ? '<span class="tocPrevNextCnt">['.($this->limitstart+1).'/'.$this->textscount.']</span>' : '';
	
	if ($this->limitstart < $this->textscount - 1 || $display_method==1) echo '
		<a href="'.($display_method==2 ? $this->next_link : 'javascript:;').'" class="tocNext" onclick="'.($display_method==1 ? 'flexibreak.next();' : '').'">'. JText::_( 'FLEXIBREAK_NEXT_PAGE' ) .'</a>';
	else if ($display_method==2) echo '
		<span class="tocNoPrevNext" >'. JText::_( 'FLEXIBREAK_NEXT_PAGE' ) .'</span>';
	?>
</div>
