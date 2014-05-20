<?php
/**
 * @version 1.5 stable $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
// first define the template name
$tmpl = $this->tmpl;
$user = JFactory::getUser();
?>

<?php
	ob_start();
	
	// Form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form.php');
	
	$filter_form_html = trim(ob_get_contents());
	ob_end_clean();
	if ( $filter_form_html ) {
		echo '<div class="group">'."\n".$filter_form_html."\n".'</div>';
	}
?>

<div class="clear"></div>

<?php
if (!$this->items) {
	// No items exist
	if ($this->getModel()->getState('limit')) {
		// Not creating a category view without items
		echo '<div class="noitems group">' . JText::_( 'FLEXI_NO_ITEMS_FOUND' ) . '</div>';
	}
	return;
}

// routine to determine all used columns for this table
$show_title  = $this->params->get('show_title', 1);
$link_titles = $this->params->get('link_titles', 0);

$layout = $this->params->get('clayout', 'default');
$fbypos = flexicontent_tmpl::getFieldsByPositions($layout, 'category');
$columns = array();
foreach ($this->items as $item) :
	if (isset($item->positions['table'])) :
		foreach ($fbypos['table']->fields as $f) :
			if ( ! @ $columns[$f] ) :
				$columns[$f] = @ $item->fields[$f]->label;
			endif;
		endforeach;
	endif;
endforeach;

$items = & $this->items;

// Decide whether to show the edit column
$buttons_exists = false;

if ( $user->id ) :
	
	$show_editbutton = $this->params->get('show_editbutton', 1);
	foreach ($items as $item) :
	
		if ( $show_editbutton ) :
			if ($item->editbutton = flexicontent_html::editbutton( $item, $this->params )) :
				$buttons_exists = true;
				$item->editbutton = '<div class="fc_edit_link_nopad">'.$item->editbutton.'</div>';
			endif;
			if ($item->statebutton = flexicontent_html::statebutton( $item, $this->params )) :
				$buttons_exists = true;
				$item->statebutton = '<div class="fc_state_toggle_link_nopad">'.$item->statebutton.'</div>';
			endif;
		endif;
		
		if ($item->approvalbutton = flexicontent_html::approvalbutton( $item, $this->params )) :
			$buttons_exists = true;
			$item->approvalbutton = '<div class="fc_approval_request_link_nopad">'.$item->approvalbutton.'</div>';
		endif;
		
	endforeach;
	
endif;

// Decide whether to show the comments column
$comments_non_zero = false;
if ( $this->params->get('show_comments_count', 0) ) :
	if ( isset($this->comments) && count($this->comments) ) :
		$comments_non_zero = true;
	endif;
endif;

// Check to enable show title if not other columns were configured
if (!$show_title && !count($columns)) :
	echo '<span style="font-weight:bold; color:red;">'.JText::_('FLEXI_TPL_NO_COLUMNS_SELECT_FORCING_DISPLAY_ITEM_TITLE').'</span>';
	$this->params->set('show_title', 1);
endif;
?>


<table id="flexitable" class="flexitable" width="100%" border="0" cellspacing="0" cellpadding="0" summary="<?php echo @$this->category->name; ?>">
	<?php if ($this->params->get('show_field_labels_row', 1)) : ?>
	<thead>
		<tr>
			<?php if ( $buttons_exists || $comments_non_zero || $show_title || count($item->css_markups) ) : ?>
				<th id="flexi_title" scope="col">
					<?php echo $show_title ? JText::_( 'FLEXI_ITEMS' ) : ''; ?>
				</th>
			<?php endif; ?>
			
			<?php foreach ($columns as $name => $label) : ?>
				<th id="field_<?php echo $name; ?>" scope="col"><?php echo $label; ?></th>
			<?php endforeach; ?>
		</tr>
	</thead>
	<?php endif; ?>
	
	<tbody>

	<?php foreach ($items as $i => $item) : ?>
		<tr id="tablelist_item_<?php echo $i; ?>" class="<?php echo $fc_item_classes; ?>">
		
		<?php
		$fc_item_classes = 'sectiontableentry';
		
		$markup_tags = '<span class="fc_mublock">';
		foreach($item->css_markups as $grp => $css_markups) {
			if ( empty($css_markups) )  continue;
			$fc_item_classes .= ' fc'.implode(' fc', $css_markups);
			
			$ecss_markups  = $item->ecss_markups[$grp];
			$title_markups = $item->title_markups[$grp];
			foreach($css_markups as $mui => $css_markup) {
				$markup_tags .= '<span class="fc_markup mu' . $css_markups[$mui] . $ecss_markups[$mui] .'">' .$title_markups[$mui]. '</span>';
			}
		}
		$markup_tags .= '</span>';
		?>

		<?php if ( $buttons_exists || $comments_non_zero || $show_title || count($item->css_markups) ) : ?>
			<td class="fc_title_col">
			
			<?php echo @ $item->editbutton; ?>
			<?php echo @ $item->statebutton; ?>
			<?php echo @ $item->approvalbutton; ?>
			
			<?php if ($this->params->get('show_comments_count')) : ?>
				<?php if ( isset($this->comments[ $item->id ]->total) ) : ?>
				<div class="fc_comments_count_nopad hasTip" alt="<?php echo JText::_('FLEXI_NUM_OF_COMMENTS');?>" title="<?php echo JText::_('FLEXI_NUM_OF_COMMENTS');?>::<?php echo JText::_('FLEXI_NUM_OF_COMMENTS_TIP');?>">
					<?php echo $this->comments[ $item->id ]->total; ?>
				</div>
				<?php endif; ?>
			<?php endif; ?>
			
			<!-- BOF item title -->
			<?php if ($show_title) : ?>
				<?php if ($link_titles) : ?>
					<a class="fc_item_title" href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item)); ?>"><?php echo $item->title; ?></a>
				<?php else : echo $item->title; endif; ?>
			<?php endif; ?>
			<!-- EOF item title -->
			
			<?php echo $markup_tags; ?>
			
			</td>
		<?php endif; ?>
	
	
		<!-- BOF item fields -->
		<?php foreach ($columns as $name => $label) : ?>
			<td><?php echo isset($item->positions['table']->{$name}->display) ? $item->positions['table']->{$name}->display : ''; ?></td>
		<?php endforeach; ?>
		<!-- EOF item fields -->
				
		</tr>

	<?php endforeach; ?>
			
	</tbody>
</table>
