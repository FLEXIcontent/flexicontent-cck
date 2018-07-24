<?php
$html = $img_err_msg.'<span class="flexi fc-pagenav">';

$item_use_image = $field->parameters->get('item_use_image', 0);
$img_size = ' width: ' .$field->parameters->get('nav_width', 200). "px; height: " .$field->parameters->get('nav_height', 200). 'px;';

// CATEGORY back link
if ($use_category_link)
{
	$cat_image = $this->getCatThumb($category, $field->parameters);
	$limit = $item_count;
	$limit = $limit ? $limit : 10;
	$start = floor($location / $limit)*$limit;
	if (!empty($rows[$item->id]->categoryslug)) {
		$html .= '
		<span class="fc-pagenav-return">
			<a class="btn" href="'. JRoute::_(FlexicontentHelperRoute::getCategoryRoute($rows[$item->id]->categoryslug)) . ($start ? '?start='.$start : '') . '">' . htmlspecialchars($category_label, ENT_NOQUOTES, 'UTF-8') . '
				' . ($cat_image ? '
				<br/><img src="'.$cat_image.'" alt="Return"/>
				' : '') .'
			</a>
		</span>
		';
	}
}

// Item location and total count
$html .= $show_prevnext_count ? '<span class="fc-pagenav-items-cnt badge badge-info">'.($location+1).' / '.$item_count.'</span>' : '';

// Next item linking
if ($field->prev)
{
	$tooltip = $use_tooltip ? ' title="'. flexicontent_html::getToolTip($tooltip_title_prev, $field->prevtitle, 0) .'"' : '';
	$html .= '
	<span class="fc-pagenav-prev' . ($use_tooltip ? ' '.$tooltip_class : '') . '" ' . ($use_tooltip ? $tooltip : '') . '>
		<a class="btn" href="'. $field->prevurl .'">
			<i class="icon-previous"></i>
			' . ( $use_title ? $field->prevtitle : htmlspecialchars($prev_label, ENT_NOQUOTES, 'UTF-8') ).'
			' . ( $item_use_image ? ($field->prevThumb ? '
				<br/><img src="'.$field->prevThumb.'" alt="Previous" style="'.$img_size.'" />' : '
				<br/><span class="fc-pagenav-noimg" style="display:inline-block; '.$img_size.'"></span>
			') : '').'
		</a>
	</span>
	';
} else {
	$html .= '
	<span class="fc-pagenav-prev">
		<span class="btn disabled">
			<i class="icon-previous"></i>
			'.htmlspecialchars($prev_label, ENT_NOQUOTES, 'UTF-8').'
			'.( $item_use_image ? '
				<br/><span class="fc-pagenav-noimg" style="display:inline-block; '.$img_size.'"></span>
			' : '').'
		</span>
	</span>
	';
}

// Previous item linking
if ($field->next)
{
	$tooltip = $use_tooltip ? ' title="'. flexicontent_html::getToolTip($tooltip_title_next, $field->nexttitle, 0) .'"' : '';
	$html .= '
	<span class="fc-pagenav-next' . ($use_tooltip ? ' '.$tooltip_class : '') . '" ' . ($use_tooltip ? $tooltip : '') . '>
		<a class="btn" href="'. $field->nexturl .'">
			<i class="icon-next"></i>
			' . ( $use_title ? $field->nexttitle : htmlspecialchars($next_label, ENT_NOQUOTES, 'UTF-8') ).'
			' . ( $item_use_image ? ($field->nextThumb ? '
				<br/><img src="'.$field->nextThumb.'" alt="Next" style="'.$img_size.'" />' : '
				<br/><span class="fc-pagenav-noimg" style="display:inline-block; '.$img_size.'"></span>
			') : '').'
		</a>
	</span>
	';
} else {
	$html .= '
	<span class="fc-pagenav-next">
		<span class="btn disabled">
			<i class="icon-next"></i>
			'.htmlspecialchars($next_label, ENT_NOQUOTES, 'UTF-8').'
			'.( $item_use_image ? '
				<br/><span class="fc-pagenav-noimg" style="display:inline-block; '.$img_size.'"></span>
			' : '').'
		</span>
	</span>
	';
}

$html .= '</span>';