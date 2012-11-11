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
	
	//added to intercept more columns (see also css changes)
	$tmpl_cols = $this->params->get('tmpl_cols', 2);
	$tmpl_cols_classes = array(1=>'one',2=>'two',3=>'three',4=>'four');
	$classnum = $tmpl_cols_classes[$tmpl_cols];
	
?>

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
			
		<div class="flexi-cat">
			
			<!-- BOF subcategory image -->
			<?php if (!empty($sub->image) && $this->params->get(($catid!=$currcatid? 'show_description_image_subcat' : 'show_description_image'), 1)) : ?>
			<div class="catimg">
				<?php echo $sub->image; ?>
			</div>
			<?php endif; ?>
			<!-- EOF subcategory image -->
			
			<!-- BOF subcategory title -->
			<?php if ($catid!=$currcatid) { ?> <a class='fc_cat_title' href="<?php echo JRoute::_( FlexicontentHelperRoute::getCategoryRoute($sub->slug) ); ?>"> <?php } else { echo "<span class='fc_cat_title'>"; } ?>
				<?php echo $sub->title; ?>
			<?php if ($catid!=$currcatid) { ?> </a> <?php } else { echo "</span>"; } ?>
			<!-- EOF subcategory title -->
			
			<!-- BOF subcategory assigned/subcats_count  -->
			<?php
				if ($catid!=$currcatid) {
					$subsubcount = count($sub->subcats);
					if ($this->params->get('show_itemcount', 1)) echo ' (' . ($sub->assigneditems != null ? $sub->assigneditems.'/'.$subsubcount : '0/'.$subsubcount) . ')';
				}
			?>
			<!-- EOF subcategory assigned/subcats_count -->

			<!-- BOF subcategory description  -->
			<?php if ($this->params->get(($catid!=$currcatid? 'show_description_subcat' : 'show_description'), 1)) : ?>
			<div class="catdescription">
				<?php	echo flexicontent_html::striptagsandcut( $sub->description, $this->params->get(($catid!=$currcatid? 'description_cut_text_subcat' : 'description_cut_text'), 120) ); ?>
			</div>
			<?php endif; ?>
			<!-- EOF subcategory description -->
			
		</div>
		
		
<!-- BOF subcategory items -->
			
<?php
	if (!$this->params->get('show_title', 1) && $this->params->get('limit', 0) && !count($columns['aftertitle'])) :
		echo '<span style="font-weight:bold; color:red;">'.JText::_('FLEXI_TPL_NO_COLUMNS_SELECT_FORCING_DISPLAY_ITEM_TITLE').'</span>';
		$this->params->set('show_title', 1);
	endif;
?>

		<?php if ( $this->params->get('show_title', 1) || count($columns['aftertitle']) ) : ?>
		
			<ul class='flexi-itemlist'>
			<?php foreach ($items as $item) : ?>
				<li class='flexi-item'>
					
				  <!-- BOF beforeDisplayContent -->
				  <?php if ($item->event->beforeDisplayContent) : ?>
						<div class='fc_beforeDisplayContent' style='clear:both;'>
							<?php echo $item->event->beforeDisplayContent; ?>
						</div>
					<?php endif; ?>
				  <!-- EOF beforeDisplayContent -->

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
		   			<a class='fc_item_title' href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug)); ?>"><?php echo $item->title; ?></a>
		   			<?php
		   			else :
		   			echo $item->title;
		   			endif;
		    			?>
					</li>
				<?php endif; ?>
				<!-- BOF item title -->
	    				
			  <!-- BOF afterDisplayTitle -->
			  <?php if ($item->event->afterDisplayTitle) : ?>
					<div class='fc_afterDisplayTitle' style='clear:both;'>
						<?php echo $item->event->afterDisplayTitle; ?>
					</div>
				<?php endif; ?>
			  <!-- EOF afterDisplayTitle -->
						  
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
			    
		    <!-- BOF afterDisplayContent -->
		    <?php if ($item->event->afterDisplayContent) : ?>
					<div class='afterDisplayContent' style='clear:both;'>
						<?php echo $item->event->afterDisplayContent; ?>
					</div>
				<?php endif; ?>
		    <!-- EOF afterDisplayContent -->
				
				</li>
			<?php endforeach; ?>
			</ul>
			
		<?php endif; ?>
			
		</li>
<!-- EOF subcategory items -->
		
<?php endforeach; ?>

</ul>

<?php elseif ($this->getModel()->getState('limit')) : // Check case of creating a category view without items ?>
	<div class="noitems"><?php echo JText::_( 'FLEXI_NO_ITEMS_CAT' ); ?></div>
<?php endif; ?>
