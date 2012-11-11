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
$user =& JFactory::getUser();

JFactory::getDocument()->addScript( JURI::base().'components/com_flexicontent/assets/js/tmpl-common.js');
?>

<?php
	// Form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form.php');
?>

<div class="clear"></div>

<?php
if ($this->items) :
	// routine to determine all used columns for this table
	$layout = $this->params->get('clayout', 'default');
	$fbypos		= flexicontent_tmpl::getFieldsByPositions($layout, 'category');
	$columns = array();
	foreach ($this->items as $item) :
		if (isset($item->positions['table'])) :
			foreach ($fbypos['table']->fields as $f) :
				if (!in_array($f, $columns)) :
					$columns[$f] = @$item->fields[$f]->label;
				endif;
			endforeach;
		endif;
	endforeach;
?>

<?php
$items = & $this->items;

// Decide whether to show the edit column
$edit_button_exists = false;
if ( $user->id && $this->params->get('show_editbutton', 0) ) :
	foreach ($items as $item) :
		if ($item->editbutton = flexicontent_html::editbutton( $item, $this->params )) :
			$edit_button_exists = true;
		endif;
	endforeach;
endif;
?>

<?php
	if (!$this->params->get('show_title', 1) && $this->params->get('limit', 0) && !count($columns)) :
		echo '<span style="font-weight:bold; color:red;">'.JText::_('FLEXI_TPL_NO_COLUMNS_SELECT_FORCING_DISPLAY_ITEM_TITLE').'</span>';
		$this->params->set('show_title', 1);
	endif;
?>


	<?php if ($this->params->get('show_title', 1) || count($columns)) : ?>
	
		<table id="flexitable" class="flexitable" width="100%" border="0" cellspacing="0" cellpadding="0" summary="<?php echo @$this->category->name; ?>">
   		<?php if ($this->params->get('show_field_labels_row', 1)) : ?>
			<thead>
				<tr>
		   		<?php if ($edit_button_exists || $this->params->get('show_title', 1)) : ?>
					<th id="flexi_title" scope="col">
						<?php echo $this->params->get('show_title', 1) ? JText::_( 'FLEXI_ITEMS' ) : ''; ?>
					</th>
					<?php endif; ?>
					
					<?php foreach ($columns as $name => $label) : ?>
					<th id="field_<?php echo $name; ?>" scope="col"><?php echo $label; ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<?php endif; ?>
			
			<tbody>
			
			<?php foreach ($items as $item) : ?>
				<tr class="sectiontableentry">
				
				<!-- BOF item title -->
				<?php if ($edit_button_exists || $this->params->get('show_title', 1)) : ?>
		   		<th scope="row" class="table-titles">
						<?php if ($this->params->get('show_title', 1)) : ?>
			   			<?php if ($this->params->get('link_titles', 0)) : ?>
			   			<a class="fc_item_title" href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug)); ?>"><?php echo $item->title; ?></a>
			   			<?php else : echo $item->title; endif; ?>
		   			<?php endif; ?>
		   			<?php echo !empty($item->editbutton) ? $item->editbutton : ''; ?>
					</th>
				<?php endif; ?>
				<!-- BOF item title -->
				
				<!-- BOF item fields -->
				<?php foreach ($columns as $name => $label) : ?>
					<td><?php echo isset($item->positions['table']->{$name}->display) ? $item->positions['table']->{$name}->display : ''; ?></td>
				<?php endforeach; ?>
				<!-- EOF item fields -->
					
				</tr>
			<?php endforeach; ?>
			
			</tbody>
		</table>
		
	<?php endif; ?>

<?php elseif ($this->getModel()->getState('limit')) : // Check case of creating a category view without items ?>
	<div class="noitems group"><?php echo JText::_( 'FLEXI_NO_ITEMS_CAT' ); ?></div>
<?php endif; ?>
