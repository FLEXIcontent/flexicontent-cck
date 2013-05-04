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
JFactory::getDocument()->addScript( JURI::base().'components/com_flexicontent/assets/js/tabber-minimized.js');
JFactory::getDocument()->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/tabber.css');
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
endif;

$items = & $this->items;
?>

<?php
	if (!$this->params->get('show_title', 1) && $this->params->get('limit', 0) && !count($columns)) :
		echo '<span style="font-weight:bold; color:red;">'.JText::_('FLEXI_TPL_NO_COLUMNS_SELECT_FORCING_DISPLAY_ITEM_TITLE').'</span>';
		$this->params->set('show_title', 1);
	endif;
?>

<?php if ($this->params->get('show_title', 1) || count($columns)) : ?>
		
	<?php foreach ($items as $item) : ?>
					
  <!-- BOF beforeDisplayContent -->
  <?php if ($item->event->beforeDisplayContent) : ?>
		<div class="fc_beforeDisplayContent group">
			<?php echo $item->event->beforeDisplayContent; ?>
		</div>
	<?php endif; ?>
  <!-- EOF beforeDisplayContent -->
	
	<!-- BOF buttons -->
	<?php
	$pdfbutton = flexicontent_html::pdfbutton( $item, $this->params );
	$mailbutton = flexicontent_html::mailbutton( FLEXI_ITEMVIEW, $this->params, $item->categoryslug, $item->slug );
	$printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
	$editbutton = flexicontent_html::editbutton( $item, $this->params );
	$statebutton = flexicontent_html::statebutton( $item, $this->params );
	$approvalbutton = flexicontent_html::approvalbutton( $item, $this->params );
	if ($pdfbutton || $mailbutton || $printbutton || $editbutton || $statebutton || $approvalbutton) {
	?>
	<p class="buttons">
		<?php echo $pdfbutton; ?>
		<?php echo $mailbutton; ?>
		<?php echo $printbutton; ?>
		<?php echo $editbutton; ?>
		<?php echo $statebutton; ?>
		<?php echo $approvalbutton; ?>
	</p>
	<?php } ?>
	<!-- EOF buttons -->

	
	<?php if ($this->params->get('show_comments_count')) : ?>
		<?php if ( isset($this->comments[ $item->id ]->total) ) : ?>
			<div style="float:left;" class="fc_comments_count hasTip" alt=="<?php echo JText::_('FLEXI_NUM_OF_COMMENTS');?>" title="<?php echo JText::_('FLEXI_NUM_OF_COMMENTS');?>::<?php echo JText::_('FLEXI_NUM_OF_COMMENTS_TIP');?>">
				<?php echo $this->comments[ $item->id ]->total; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	
	<!-- BOF item title -->
	<?php if ($this->params->get('show_title', 1)) : ?>
	<h2 class="contentheading"><span class="fc_item_title">
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
		<div class="fc_afterDisplayTitle group">
			<?php echo $item->event->afterDisplayTitle; ?>
		</div>
	<?php endif; ?>
  <!-- EOF afterDisplayTitle -->
	
	<!-- BOF subtitle1 block -->
	<?php if (isset($item->positions['subtitle1'])) : ?>
	<div class="flexi lineinfo subtitle1 group">
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
	<div class="flexi lineinfo subtitle2 group">
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
	<div class="flexi lineinfo subtitle3 group">
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


<?php
$tabcount = 6;

// Find if at least one tabbed position is used
for ($tc=1; $tc<=$tabcount; $tc++) $createtabs = @$createtabs ||  isset($item->positions['subtitle_tab'.$tc]);

if (@$createtabs) :
	echo '	<div class="fctabber group"><!-- tabber start -->'."\n";
	
	for ($tc=1; $tc<=$tabcount; $tc++) :
		$tabpos_name  = 'subtitle_tab'.$tc;
		$tabpos_label = JText::_($this->params->get('subtitle_tab'.$tc.'_label', $tabpos_name));
		if (isset($item->positions[$tabpos_name])):
?>
	
		<!-- BOF subtitle_tabN block -->
		<div class='tabbertab'><!-- tab start -->
		
			<h3 class="tabberheading"><?php echo $tabpos_label; ?></h3><!-- tab title -->
			
			<div class="flexi lineinfo <?php echo $tabpos_label; ?>">
				<?php foreach ($item->positions[$tabpos_name] as $field) : ?>
				<div class="flexi element">
					<?php if ($field->label) : ?>
					<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
					<?php endif; ?>
					<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
				</div>
				<?php endforeach; ?>
			</div>
			
		</div><!-- tab end -->
		 
		<?php endif; ?>
		<!-- EOF subtitle_tabN block -->	
	
	<?php endfor; ?>
		
<?php
	echo '</div><!-- tabber end -->'."\n";
endif;
?>


	<?php if ((isset($item->positions['image'])) || (isset($item->positions['top']))) : ?>
	<div class="flexi topblock group row">  <!-- NOTE: image block is inside top block ... -->
	
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
			<ul class="flexi row">
				<?php foreach ($item->positions['top'] as $field) : ?>
				<li class="flexi">
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
	<div class="description group">
		<?php foreach ($item->positions['description'] as $field) : ?>
			<?php if ($field->label) : ?>
		<div class="desc-title"><?php echo $field->label; ?></div>
			<?php endif; ?>
		<div class="desc-content"><?php echo $field->display; ?></div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<!-- EOF description -->
	

<?php
$tabcount = 6;

// Find if at least one tabbed position is used
for ($tc=1; $tc<=$tabcount; $tc++) $createtabs = @$createtabs ||  isset($item->positions['bottom_tab'.$tc]);

if (@$createtabs) :
	echo '	<div class="fctabber group"><!-- tabber start -->'."\n";
	
	for ($tc=1; $tc<=$tabcount; $tc++) :
		$tabpos_name  = 'bottom_tab'.$tc;
		$tabpos_label = JText::_($this->params->get('bottom_tab'.$tc.'_label', $tabpos_name));
		if (isset($item->positions[$tabpos_name])):
?>
	
		<!-- BOF bottom_tabN block -->
		<div class="tabbertab"><!-- tab start -->
		
			<h3 class="tabberheading"><?php echo $tabpos_label; ?></h3><!-- tab title -->
			
			<div class="flexi lineinfo <?php echo $tabpos_label; ?>">
				<?php foreach ($item->positions[$tabpos_name] as $field) : ?>
				<div class="flexi element">
					<?php if ($field->label) : ?>
					<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
					<?php endif; ?>
					<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
				</div>
				<?php endforeach; ?>
			</div>
			
		</div><!-- tab end -->
		 
		<?php endif; ?>
		<!-- EOF bottom_tabN block -->	
	
	<?php endfor; ?>
		
<?php
	echo '</div><!-- tabber end -->'."\n";
endif;
?>
	<!-- BOF bottom block -->
	<?php if (isset($item->positions['bottom'])) : ?>
	<div class="flexi infoblock <?php echo $this->params->get('bottom_cols', 'two'); ?>cols group">
		<ul class="flexi row">
			<?php foreach ($item->positions['bottom'] as $field) : ?>
			<li class="flexi">
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
		<span class="readmore group">
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
			<div class="fc_afterDisplayContent group">
				<?php echo $item->event->afterDisplayContent; ?>
			</div>
		<?php endif; ?>
	  <!-- EOF afterDisplayContent -->

		<div class="fc_item_seperator"></div>
	
	<?php endforeach; ?>

<?php elseif ($this->getModel()->getState('limit')) : // Check case of creating a category view without items ?>
	<div class="noitems group"><?php echo JText::_( 'FLEXI_NO_ITEMS_CAT' ); ?></div>
<?php endif; ?>
