<?php
/**
 * HTML5 Template
 * @version 1.5 stable $Id: category_items_html5.php 0001 2012-09-23 14:00:28Z Rehne $
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
?>
<script type="text/javascript">
	function tableOrdering( order, dir, task )
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		
		var form = document.getElementById("adminForm");
		
		adminFormPrepare(form);
		form.submit( task );
	}
	
	function adminFormPrepare(form) {
		var extra_action = '';
		var var_sep = form.action.match(/\?/) ? '&' : '?';
		
		for(i=0; i<form.elements.length; i++) {
			
			var element = form.elements[i];
			
			// No need to add the default values for ordering, to the URL
			if (element.name=='filter_order' && element.value=='i.title') continue;
			if (element.name=='filter_order_Dir' && element.value=='ASC') continue;
			
			var matches = element.name.match(/(filter[.]*|letter|clayout)/);
			if (matches && element.value != '') {
			  extra_action += var_sep + element.name + '=' + element.value;
			  var_sep = '&';
			}
		}
		form.action += extra_action;   //alert(extra_action);
	}
	
	function adminFormClearFilters (form) {
		for(i=0; i<form.elements.length; i++) {
			var element = form.elements[i];
			
			if (element.name=='filter_order') {	element.value=='i.title'; continue; }
			if (element.name=='filter_order_Dir') { element.value=='ASC'; continue; }
			
			var matches = element.name.match(/(filter[.]*|letter)/);
			if (matches) {
				element.value = '';
			}
		}
	}
</script>

<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search')) || ($this->params->get('show_alpha', 1))) : ?>
<aside class="group">
	<form action="<?php echo htmlentities($this->action); ?>" method="POST" id="adminForm" onsubmit="" class="group well">

	<?php if ( JRequest::getVar('clayout') == $this->params->get('clayout', 'blog') ) :?>
		<input type="hidden" name="clayout" value="<?php echo JRequest::getVar('clayout'); ?>" />
	<?php endif; ?>

	<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search'))) : ?>
	<div id="fc_filter" class="floattext control-group group">
		<?php if ($this->params->get('use_search')) : ?>
		<div class="fc_fleft">
			<input type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" class="text_area input-medium search-query" />
			<?php if ( $this->params->get('show_filter_labels', 0) && $this->params->get('use_filters', 0) && $this->filters ) : ?>
				<br />
			<?php endif; ?>
			<button class="fc_button btn" onclick="var form=document.getElementById('adminForm');                               adminFormPrepare(form);"><i class="icon-search"></i><?php echo JText::_( 'FLEXI_GO' ); ?></button>
			<button class="fc_button btn" onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);"><i class="icon-refresh"></i><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
		</div>
		<?php endif; ?>
		<?php if ($this->params->get('use_filters', 0) && $this->filters) : ?>
	
		<!--div class="fc_fright"-->
		<?php
		foreach ($this->filters as $filt) :
			if (empty($filt->html)) continue;
			// Add form preparation
			if ( preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filt->html, $matches) ) {
				$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}adminFormPrepare(document.getElementById(\'adminForm\'));', $filt->html);
			} else {
				$filt->html = preg_replace('/<(select|input)/i', '<${1} onchange="adminFormPrepare(document.getElementById(\'adminForm\'));"', $filt->html);
			}
		?>
			<span class="filter" style="white-space: nowrap;">
			
				<?php if ( $this->params->get('show_filter_labels', 0) ) : ?>
					<span class="filter_label">
					<?php echo $filt->label; ?>
					</span>
				<?php endif; ?>
			
				<span class="filter_field">
				<?php echo $filt->html; ?>
				</span>
			
			</span>
		<?php endforeach; ?>
	
		<?php if (!$this->params->get('use_search')) : ?>
			<button class="btn" onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);"><i class="icon-refresh"></i><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
		<?php endif; ?>
		<!--/div-->

		<?php endif; ?>
	</div>
	<?php endif; ?>
	<?php
	if ($this->params->get('show_alpha', 1)) :
		echo $this->loadTemplate('alpha_html5');
	endif;
	?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="" />
	<input type="hidden" name="view" value="category" />
	<input type="hidden" name="letter" value="<?php echo JRequest::getVar('letter');?>" id="alpha_index" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="id" value="<?php echo $this->category->id; ?>" />
	<input type="hidden" name="cid" value="<?php echo $this->category->id; ?>" />
</form>
<?php endif; ?>

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

	<?php if ( (!$this->params->get('show_title', 1) || !count($columns)) && !$this->params->get('show_item_total', 1)) : ?>
    </aside>
    <?php endif; ?>

	<?php if ($this->params->get('show_title', 1) || count($columns)) : ?>
	
		<!-- BOF items total-->
		<?php if ($this->params->get('show_item_total', 1)) : ?>
		<div id="item_total" class="item_total group">
			<?php	//echo $this->pageNav->getResultsCounter(); // Alternative way of displaying total (via joomla pagination class) ?>
			<?php echo $this->resultsCounter; // custom Results Counter ?>
		</div>
    </aside>
		<?php endif; ?>
		<!-- BOF items total-->
    		
		<table id="flexitable" class="table table-striped flexitable" width="100%" border="0" cellspacing="0" cellpadding="0" summary="<?php echo @$this->category->name; ?>">
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
