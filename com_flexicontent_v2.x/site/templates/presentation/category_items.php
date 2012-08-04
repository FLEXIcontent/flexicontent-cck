<?php
/**
 * @version 1.5 stable $Id: category_items.php 1033 2011-12-08 08:58:02Z enjoyman@gmail.com $
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
			
			var matches = element.name.match(/(filter[.]*|letter)/);
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
<form action="<?php echo htmlentities($this->action); ?>" method="POST" id="adminForm" onsubmit="">
<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search'))) : ?>
<div id="fc_filter" class="floattext">
	<?php if ($this->params->get('use_search')) : ?>
	<div class="fc_fleft">
		<input type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" class="text_area" />
		<?php if ( $this->params->get('show_filter_labels', 0) && $this->params->get('use_filters', 0) && $this->filters ) : ?>
		  <br>
		<?php endif; ?>
		<button class='fc_button' onclick="var form=document.getElementById('adminForm');                               adminFormPrepare(form);"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
		<button class='fc_button' onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
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
		<span class="filter" style='white-space: nowrap;'>
			
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
		<button onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
	<?php endif; ?>
	<!--/div-->
	
	<?php endif; ?>
</div>
<?php endif; ?>
<?php
if ($this->params->get('show_alpha', 1)) :
	echo $this->loadTemplate('alpha');
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

<?php $items = & $this->items; ?>

<?php
	if (!$this->params->get('show_title', 1) && $this->params->get('limit', 0) && !count($columns)) :
		echo "<span style='font-weight:bold; color:red;'>No columns selected forcing the display of item title. Please:<br>\n
		1. enable display of item title in category parameters<br>\n
		2. OR add fields to the category Layout of the template assigned to this category<br>\n
		3. OR set category parameters to display 0 items per page</span>";
		$this->params->set('show_title', 1);
	endif;
?>
	
		<!-- BOF items total-->
		<?php if ($this->params->get('show_item_total', 1)) : ?>
		<div id="item_total" class="item_total">
			<?php	//echo $this->pageNav->getResultsCounter(); // Alternative way of displaying total (via joomla pagination class) ?>
			<?php echo $this->resultsCounter; // custom Results Counter ?>
		</div>
		<?php endif; ?>
		<!-- BOF items total-->
    		
		
	<?php foreach ($items as $item) : ?>
					
	  <!-- BOF beforeDisplayContent -->
	  <?php if ($item->event->beforeDisplayContent) : ?>
			<div class='fc_beforeDisplayContent' style='clear:both;'>
				<?php echo $item->event->beforeDisplayContent; ?>
			</div>
		<?php endif; ?>
	  <!-- EOF beforeDisplayContent -->

		<!-- BOF buttons -->
		<?php
		$pdfbutton = flexicontent_html::pdfbutton( $item, $this->params );
		$mailbutton = flexicontent_html::mailbutton( 'items', $this->params, null , $item->slug );
		$printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
		$editbutton = flexicontent_html::editbutton( $item, $this->params );
		if ($pdfbutton || $mailbutton || $printbutton || $editbutton) {
		?>
		<p class="buttons">
			<?php echo $pdfbutton; ?>
			<?php echo $mailbutton; ?>
				<?php echo $printbutton; ?>
				<?php echo $editbutton; ?>
			</p>
			<?php } ?>
			<!-- EOF buttons -->
		

	<!-- BOF item title -->
	<?php if ($this->params->get('show_title', 1)) : ?>
	<h2 class="contentheading"><span class='fc_item_title'>
		<?php
		if ( mb_strlen($item->title, 'utf-8') > $this->params->get('title_cut_text',200) ) :
			echo mb_substr ($item->title, 0, $this->params->get('title_cut_text',200), 'utf-8') . ' ...';
		else :
			echo $item->title;
		endif;
		?>
	</span></h2>
	<?php endif; ?>
	<!-- EOF item title -->
	
  <!-- BOF afterDisplayTitle -->
  <?php if ($item->event->afterDisplayTitle) : ?>
		<div class='fc_afterDisplayTitle' style='clear:both;'>
			<?php echo $item->event->afterDisplayTitle; ?>
		</div>
	<?php endif; ?>
  <!-- EOF afterDisplayTitle -->
	
	<!-- BOF subtitle1 block -->
	<?php if (isset($item->positions['subtitle1'])) : ?>
	<div class="flexi lineinfo subtitle1">
		<?php foreach ($item->positions['subtitle1'] as $field) : ?>
		<div class="flexi element">
			<?php if ($field->label) : ?>
			<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
			<?php endif; ?>
			<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<!-- EOF subtitle1 block -->
	
	<!-- BOF subtitle2 block -->
	<?php if (isset($item->positions['subtitle2'])) : ?>
	<div class="flexi lineinfo subtitle2">
		<?php foreach ($item->positions['subtitle2'] as $field) : ?>
		<div class="flexi element">
			<?php if ($field->label) : ?>
			<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
			<?php endif; ?>
			<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<!-- EOF subtitle2 block -->
	
	<!-- BOF subtitle3 block -->
	<?php if (isset($item->positions['subtitle3'])) : ?>
	<div class="flexi lineinfo subtitle3">
		<?php foreach ($item->positions['subtitle3'] as $field) : ?>
		<div class="flexi element">
			<?php if ($field->label) : ?>
			<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
			<?php endif; ?>
			<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<!-- EOF subtitle3 block -->
	
	
	<?php if ((isset($item->positions['image'])) || (isset($item->positions['top']))) : ?>
	<div class="flexi topblock">  <!-- NOTE: image block is inside top block ... -->
	
	<!-- BOF image block -->
		<?php if (isset($item->positions['image'])) : ?>
			<?php foreach ($item->positions['image'] as $field) : ?>
		<div class="flexi image field_<?php echo $field->name; ?>">
			<?php echo $field->display; ?>
			<div class="clear"></div>
		</div>
			<?php endforeach; ?>
		<?php endif; ?>
	<!-- EOF image block -->
	
	<!-- BOF top block -->
		<?php if (isset($item->positions['top'])) : ?>
		<div class="flexi infoblock <?php echo $this->params->get('top_cols', 'two'); ?>cols">
			<ul class='flexi'>
				<?php foreach ($item->positions['top'] as $field) : ?>
				<li class='flexi'>
					<div>
						<?php if ($field->label) : ?>
						<div class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></div>
						<?php endif; ?>
						<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>
	<!-- EOF top block -->
	
	</div>
	<?php endif; ?>
	
	<div class="clear"></div>
	
	<!-- BOF TOC -->
	<?php if (isset($item->toc)) : ?>
		<?php echo $item->toc; ?>
	<?php endif; ?>
	<!-- EOF TOC -->
	
	<!-- BOF description -->
	<?php if (isset($item->positions['description'])) : ?>
	<div class="description">
		<?php foreach ($item->positions['description'] as $field) : ?>
			<?php if ($field->label) : ?>
		<div class="desc-title"><?php echo $field->label; ?></div>
			<?php endif; ?>
		<div class="desc-content"><?php echo $field->display; ?></div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<!-- EOF description -->
	
	<div class="clear"></div>
	
	<!-- BOF bottom block -->
	<?php if (isset($item->positions['bottom'])) : ?>
	<div class="flexi infoblock <?php echo $this->params->get('bottom_cols', 'two'); ?>cols">
		<ul class='flexi'>
			<?php foreach ($item->positions['bottom'] as $field) : ?>
			<li class='flexi'>
				<div>
					<?php if ($field->label) : ?>
					<div class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></div>
					<?php endif; ?>
					<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>
	<!-- EOF bottom block -->


			<?php if (
				( $this->params->get('show_readmore', 1) && strlen(trim($item->fulltext)) >= 1 )
				||  $this->params->get('lead_strip_html', 1) == 1 /* option 2, strip-cuts and option 1 also forces read more  */
			) : ?>
			<span class="readmore">
				<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug)); ?>" class="readon">
				<?php
				if ($item->params->get('readmore')) :
					echo ' ' . $item->params->get('readmore');
				else :
					echo ' ' . JText::sprintf('FLEXI_READ_MORE', $item->title);
				endif;
				?>
				</a>
			</span>
			<?php endif; ?>
			    
	    <!-- BOF afterDisplayContent -->
	    <?php if ($item->event->afterDisplayContent) : ?>
				<div class='afterDisplayContent' style='clear:both;'>
					<?php echo $item->event->afterDisplayContent; ?>
				</div>
			<?php endif; ?>
	    <!-- EOF afterDisplayContent -->
			
			<div class="fc_item_seperator"></div>

	<?php endforeach; ?>
		
<?php elseif ($this->getModel()->getState('limit')) : // Check case of creating a category view without items ?>
	<div class="noitems"><?php echo JText::_( 'FLEXI_NO_ITEMS_CAT' ); ?></div>
<?php endif; ?>
