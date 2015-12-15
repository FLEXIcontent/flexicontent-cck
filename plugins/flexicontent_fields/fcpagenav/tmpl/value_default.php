<?php
$html = '<span class="fcpagenav btn-group">';
$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		
// CATEGORY back link
if ($use_category_link)
{
	$cat_image = $this->getCatThumb($category, $field->parameters);
	$limit = $item_count;
	$limit = $limit ? $limit : 10;
	$start = floor($location / $limit)*$limit;
	if (!empty($rows[$item->id]->categoryslug)) {
		$tooltip = $use_tooltip ? ' title="'. flexicontent_html::getToolTip($category_label, $category->title, 0) .'"' : '';
		$html .= '
			<a class="fcpagenav-return btn' . ($use_tooltip ? $tooltip_class : '') . '" ' . ($use_tooltip ? $tooltip : '') . ' href="'. JRoute::_(FlexicontentHelperRoute::getCategoryRoute($rows[$item->id]->categoryslug)).'?start='.$start .'">
				<i class="icon-undo"></i>
				' . htmlspecialchars($category->title, ENT_NOQUOTES, 'UTF-8') .'
				' . ( $cat_image ? '
					<br/><img src="'.$cat_image.'" alt="Return"/>
				' : '') .'
			</a>';
	}
}
		
// Next item linking
if ($field->prev)
{
	$tooltip = $use_tooltip ? ' title="'. flexicontent_html::getToolTip($tooltip_title_prev, $field->prevtitle, 0) .'"' : '';
	$html .= '
		<a class="fcpagenav-prev btn' . ($use_tooltip ? $tooltip_class : '') . '" ' . ($use_tooltip ? $tooltip : '') . ' href="'. $field->prevurl .'">
			<i class="icon-backward-circle"></i>
			' . ( $use_title ? $field->prevtitle : htmlspecialchars($prev_label, ENT_NOQUOTES, 'UTF-8') ).'
			' . ($field->prevThumb ? '
				<br/><img src="'.$field->prevThumb.'" alt="Previous"/>
			' : '').'
		</a>';
} else {
	$html .= '
		<span class="fcpagenav-prev btn disabled">
			<i class="icon-previous"></i>
			'.htmlspecialchars($prev_label, ENT_NOQUOTES, 'UTF-8').'
		</span>';
}
		
// Item location and total count
$html .= $show_prevnext_count ? '<span class="fcpagenav-items-cnt btn disabled">'.($location+1).' / '.$item_count.'</span>' : '';
		
// Previous item linking
if ($field->next)
{
	$tooltip = $use_tooltip ? ' title="'. flexicontent_html::getToolTip($tooltip_title_next, $field->nexttitle, 0) .'"' : '';
	$html .= '
		<a class="fcpagenav-next btn' . ($use_tooltip ? $tooltip_class : '') . '" ' . ($use_tooltip ? $tooltip : '') . ' href="'. $field->nexturl .'">
			<i class="icon-forward-circle"></i>
			' . ( $use_title ? $field->nexttitle : htmlspecialchars($next_label, ENT_NOQUOTES, 'UTF-8') ).'
			' . ($field->nextThumb ? '
				<br/><img src="'.$field->nextThumb.'" alt="Next"/>
			' : '').'
		</a>';
} else {
	$html .= '
		<span class="fcpagenav-next btn disabled">
			<i class="icon-next"></i>
			'.htmlspecialchars($next_label, ENT_NOQUOTES, 'UTF-8').'
		</span>';
}
		
$html .= '</span>';