<?php
/**
 * @version 1.5 stable $Id: category_items.php 919 2011-10-03 02:17:05Z ggppdk $
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
		document.getElementById("adminForm").submit( task );
	}
</script>

<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search')) || ($this->params->get('show_alpha', 1))) : ?>
<form action="<?php echo htmlentities($this->action); ?>" method="post" id="adminForm">
<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search'))) : ?>
<div id="fc_filter" class="floattext">
	<?php if ($this->params->get('use_search')) : ?>
	<div class="fc_fleft">
		<input type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" class="text_area" onchange="document.getElementById('adminForm').submit();" />
		<button onclick="document.getElementById('adminForm').submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
		<button onclick="document.getElementById('filter').value='';document.getElementById('adminForm').submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
	</div>
	<?php endif; ?>
	<?php if ($this->params->get('use_filters', 0) && $this->filters) : ?>
	<div class="fc_fright">
	<?php
	foreach ($this->filters as $filt) :
		echo '<span class="filter">';
		echo @$filt->html;
		echo '</span>';
	endforeach;
	?>
	</div>
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
<input type="hidden" name="letter" value="" id="alpha_index" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="id" value="<?php echo $this->category->id; ?>" />
</form>
<?php endif; ?>

<?php
if ($this->items) :
	$currcatid = $this->category->id;
	$cat_items[$currcatid] = array();
	$sub_cats[$currcatid] = & $this->category;
	foreach ($this->categories as $subindex => $sub) :
		$cat_items[$sub->id] = array();
		$sub_cats[$sub->id] = & $this->categories[$subindex];
	endforeach;
	
	$items = & $this->items;
	for ($i=0; $i<count($items); $i++) :
		foreach ($items[$i]->cats as $cat) :
			if (isset($cat_items[$cat->id])) :
				$cat_items[$cat->id][] = & $items[$i];
			endif;
		endforeach;
	endfor;

	// routine to determine all used columns for this table
	$layout = $this->params->get('clayout', 'default');
	$fbypos		= flexicontent_tmpl::getFieldsByPositions($layout, 'category');
	$columns['aftertitle'] = array();
	foreach ($this->items as $item) :
		if (isset($item->positions['aftertitle'])) :
			foreach ($fbypos['aftertitle']->fields as $f) :
				if (!in_array($f, $columns['aftertitle'])) :
					$columns['aftertitle'][$f] = @$item->fields[$f]->label;
				endif;
			endforeach;
		endif;
	endforeach;

$classnum = '';
if ($this->params->get('tmpl_cols', 2) == 1) :
   $classnum = 'one';
elseif ($this->params->get('tmpl_cols', 2) == 2) :
   $classnum = 'two';
elseif ($this->params->get('tmpl_cols', 2) == 3) :
   $classnum = 'three';
elseif ($this->params->get('tmpl_cols', 2) == 4) :
   $classnum = 'four';
endif;
?>

		<!-- BOF items total-->
		<?php if ($this->params->get('show_item_total', 1)) : ?>
		<div id="item_total" class="item_total">
			<?php echo JText::sprintf( 'FLEXI_ITEMS_TOTAL', count($this->items));?>
		</div>
		<?php endif; ?>
		<!-- BOF items total-->

<ul class="faqblock <?php echo $classnum; ?>">	

<?php
global $globalcats;
$count_cat = -1;
foreach ($cat_items as $catid => $items) :
	$sub = & $sub_cats[$catid];
	if (count($items)==0) continue;
	if ($catid!=$currcatid) $count_cat++;
?>

		<li class="<?php echo $catid==$currcatid ? 'full' : ($count_cat%2 ? 'even' : 'odd'); ?>">
			
		<!-- BOF subcategory title -->
		<div class="flexi-cat">
			<a href="<?php echo JRoute::_( FlexicontentHelperRoute::getCategoryRoute($sub->slug) ); ?>">
				<?php if (!empty($sub->image) && $this->params->get('show_description_image', 1)) : ?>
				<div class="catimg">
					<img src='images/stories/<?php echo $sub->image ?>' alt='<?php echo $this->escape($sub->title) ?>' height='24' />
				</div>
				<?php endif; ?>				
				<?php
					echo $sub->title;
					if ($catid!=$currcatid) {
						$subsubcount = count($sub->subcats);
						if ($this->params->get('show_itemcount', 1)) echo ' (' . ($sub->assigneditems != null ? $sub->assigneditems.'/'.$subsubcount : '0/'.$subsubcount) . ')';
					}
				?>
			</a>
		</div>
		<!-- EOF subcategory title -->

		<!-- BOF subcategory description  -->
		<?php if ($this->params->get('show_description', 1)) : ?>
		<div class="catdescription">
			<?php	echo flexicontent_html::striptagsandcut( $sub->description, $this->params->get('description_cut_text', 120) ); ?>
		</div>
		<?php endif; ?>
		<!-- EOF subcategory description -->


		<!-- BOF subcategory items -->
			
<?php
	if (!$this->params->get('show_title', 1) && $this->params->get('limit', 0) && !count($columns['aftertitle'])) :
		echo "<span style='font-weight:bold; color:red;'>No columns selected forcing the display of item title. Please:<br>\n
		1. enable display of item title in category parameters<br>\n
		2. OR add fields to the category Layout of the template assigned to this category<br>\n
		3. OR set category parameters to display 0 items per page</span>";
		$this->params->set('show_title', 1);
	endif;
?>

		<?php if ( $this->params->get('show_title', 1) || count($columns['aftertitle']) ) : ?>
		
			<ul class='flexi-itemlist'>
			<?php foreach ($items as $item) : ?>
				<li class='flexi-item'>
				
				<?php if ($this->params->get('show_editbutton', 0)) : ?>
					<?php $editbutton = flexicontent_html::editbutton( $item, $this->params ); ?>
					<?php if ($editbutton) : ?>
						<div style="float:left;"><?php echo $editbutton;?></div>
					<?php endif; ?>
				<?php endif; ?>
					
				<!-- BOF item title -->
				<ul class='flexi-fieldlist'>
				<?php if ($this->params->get('show_title', 1)) : ?>
		   		<li class='flexi-field flexi-title'>
		   			<?php if ($this->params->get('link_titles', 0)) : ?>
		   			<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug)); ?>"><?php echo $item->title; ?></a>
		   			<?php
		   			else :
		   			echo $item->title;
		   			endif;
		    			?>
					</li>
				<?php endif; ?>
				<!-- BOF item title -->
				
				<!-- BOF item fields block aftertitle -->
				<?php
				foreach ($columns['aftertitle'] as $name => $label) :
					$label_str = '';
					if ($item->fields[$name]->parameters->get('display_label', 0)) :
						$label_str = $label.': ';
					endif; ?>
					<li class='flexi-field'>
					<?php echo $label_str.( isset($item->positions['aftertitle']->{$name}->display) ? $item->positions['aftertitle']->{$name}->display : ''); ?>
					</li>
				<?php endforeach; ?>
				</ul>
				<!-- EOF item fields block aftertitle -->
					
				</li>
			<?php endforeach; ?>
			</ul>
			
		<?php endif; ?>
			
		</li>
		<!-- EOF subcategory items -->
		
<?php endforeach; ?>

</ul>

<?php else : ?>
<div class="noitems"><?php echo JText::_( 'FLEXI_NO_ITEMS_CAT' ); ?></div>
<?php endif; ?>
