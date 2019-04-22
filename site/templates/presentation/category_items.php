<?php
/**
 * @package FLEXIcontent
 * @copyright (C) 2009-2018 Emmanuel Danan, Georgios Papadakis, Yannick Berges
 * @author Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @license GNU/GPL v2
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;

// first define the template name
$tmpl = $this->tmpl;
$user = JFactory::getUser();

JFactory::getDocument()->addScript(JUri::base(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
JFactory::getDocument()->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
JFactory::getDocument()->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

// MICRODATA 'itemtype' for ALL items in the listing (this is the fallback if the 'itemtype' in content type / item configuration are not set)
$microdata_itemtype_cat = $this->params->get( 'microdata_itemtype_cat', 'Article' );


// Form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too

ob_start();
include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form.php');
$filter_form_html = trim(ob_get_contents());
ob_end_clean();

if ( $filter_form_html )
{
	echo '
	<div class="fcclear"></div>
	<div class="group">
		' . $filter_form_html . '
	</div>';
}

// -- Check matching items found
if (!$this->items)
{
	// No items exist
	if ($this->getModel()->getState('limit'))
	{
		// Not creating a category view without items
		echo '
		<div class="fcclear"></div>
		<div class="noitems group">
			' . JText::_( 'FLEXI_NO_ITEMS_FOUND' ) . '
		</div>';
	}
	return;
}

$items = & $this->items;
$count = count($items);

$tooltip_class = FLEXI_J30GE ? 'hasTooltip' : 'hasTip';
$_comments_container_params = 'class="fc_comments_count '.$tooltip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_NUM_OF_COMMENTS', 'FLEXI_NUM_OF_COMMENTS_TIP', 1, 1).'"';


// ***********
// DECIDE TAGS 
// ***********
// Main container
$mainAreaTag = 'div';

// SEO, header level of title tag
$itemTitleHeaderLevel = '2';
	
// SEO, header level of tab title tag
$tabsHeaderLevel = $itemTitleHeaderLevel == '2'  ?  '3' : '2';  	

foreach ($items as $i => $item) :
	
	$fc_item_classes  = 'catalogitem';
	
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
	
	// MICRODATA document type (itemtype) for each item
	// -- NOTE: category's microdata itemtype is fallback if the microdata itemtype of the CONTENT TYPE / ITEM are not set
	$microdata_itemtype = $item->params->get( 'microdata_itemtype') ? $item->params->get( 'microdata_itemtype') : $microdata_itemtype_cat;
	$microdata_itemtype_code = 'itemscope itemtype="http://schema.org/'.$microdata_itemtype.'"';
?>

<div class="fc_item_separator"></div>

<?php echo '<'.$mainAreaTag; ?> id="cataloglist_item_<?php echo $i; ?>" class="<?php echo $fc_item_classes; ?> group" <?php echo $microdata_itemtype_code; ?>>
	
	
  <?php if ($item->event->beforeDisplayContent) : ?>
	  <!-- BOF beforeDisplayContent -->
		<div class="fc_beforeDisplayContent group">
			<?php echo $item->event->beforeDisplayContent; ?>
		</div>
		<!-- EOF beforeDisplayContent -->
	<?php endif; ?>
	
	<?php
	$pdfbutton = flexicontent_html::pdfbutton( $item, $this->params );
	$mailbutton = flexicontent_html::mailbutton( FLEXI_ITEMVIEW, $this->params, $item->categoryslug, $item->slug, 0, $item );
	$printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
	$editbutton = flexicontent_html::editbutton( $item, $this->params );
	$statebutton = flexicontent_html::statebutton( $item, $this->params );
	$deletebutton = flexicontent_html::deletebutton( $item, $this->params );
	$approvalbutton = flexicontent_html::approvalbutton( $item, $this->params );
	?>
	
	<?php if ($pdfbutton || $mailbutton || $printbutton || $editbutton || $statebutton || $deletebutton || $approvalbutton) : ?>
		<!-- BOF buttons -->
		<div class="buttons">
			<?php echo $pdfbutton; ?>
			<?php echo $mailbutton; ?>
			<?php echo $printbutton; ?>
			<?php echo $editbutton; ?>
			<?php echo $statebutton; ?>
			<?php echo $deletebutton; ?>
			<?php echo $approvalbutton; ?>
		</div>
		<!-- EOF buttons -->
	<?php endif; ?>
	
	
	<?php
		$header_shown =
			$this->params->get('show_comments_count', 1) ||
			$this->params->get('show_title', 1) || $item->event->afterDisplayTitle ||
			isset($item->positions['subtitle1']) || isset($item->positions['subtitle2']) || isset($item->positions['subtitle3']);
	?>
	
	
	<?php if ($this->params->get('show_comments_count')) : ?>
		<?php if ( isset($this->comments[ $item->id ]->total) ) : ?>
			<div <?php echo $_comments_container_params; ?> >
				<?php echo $this->comments[ $item->id ]->total; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	
	
	<?php if ($this->params->get('show_title', 1)) : ?>
		<!-- BOF item title -->
		<?php echo '<h'.$itemTitleHeaderLevel; ?> class="contentheading">
			<span class="fc_item_title" itemprop="name">
			<?php $_title = ( StringHelper::strlen($item->title) > (int) $this->params->get('title_cut_text',200) ) ?
				StringHelper::substr($item->title, 0, (int) $this->params->get('title_cut_text',200)) . ' ...' : $item->title; ?>
			<?php if ($this->params->get('link_titles', 0)) : ?>
   			<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item)); ?>" itemprop="url">
					<?php echo $_title; ?>
				</a>
 			<?php else : ?>
				<?php echo $_title; ?>
			<?php endif; ?>
			</span>
		<?php echo '</h'.$itemTitleHeaderLevel; ?>>
		<!-- EOF item title -->
	<?php endif; ?>
	
	<?php echo $markup_tags; ?>
	
  <?php if ($item->event->afterDisplayTitle) : ?>
	  <!-- BOF afterDisplayTitle -->
		<div class="fc_afterDisplayTitle group">
			<?php echo $item->event->afterDisplayTitle; ?>
		</div>
	  <!-- EOF afterDisplayTitle -->
	<?php endif; ?>
	
	
	<?php if (isset($item->positions['subtitle1'])) : ?>
		<!-- BOF subtitle1 block -->
		<div class="flexi lineinfo subtitle1 group">
			<?php foreach ($item->positions['subtitle1'] as $field) : ?>
			<div class="flexi element field_<?php echo $field->name; ?>">
				<?php if ($field->label) : ?>
				<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
				<?php endif; ?>
				<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
			</div>
			<?php endforeach; ?>
		</div>
		<!-- EOF subtitle1 block -->
	<?php endif; ?>
	
	
	<?php if (isset($item->positions['subtitle2'])) : ?>
		<!-- BOF subtitle2 block -->
		<div class="flexi lineinfo subtitle2 group">
			<?php foreach ($item->positions['subtitle2'] as $field) : ?>
			<div class="flexi element field_<?php echo $field->name; ?>">
				<?php if ($field->label) : ?>
				<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
				<?php endif; ?>
				<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
			</div>
			<?php endforeach; ?>
		</div>
		<!-- EOF subtitle2 block -->
	<?php endif; ?>
	
	
	<?php if (isset($item->positions['subtitle3'])) : ?>
		<!-- BOF subtitle3 block -->
		<div class="flexi lineinfo subtitle3 group">
			<?php foreach ($item->positions['subtitle3'] as $field) : ?>
			<div class="flexi element field_<?php echo $field->name; ?>">
				<?php if ($field->label) : ?>
				<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
				<?php endif; ?>
				<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
			</div>
			<?php endforeach; ?>
		</div>
		<!-- EOF subtitle3 block -->
	<?php endif; ?>
	
	
	
	<div class="fcclear"></div>
	
	<?php
		// Find if at least one tabbed position is used
		$tabcount = 12; $createtabs = false;
		for ($tc=1; $tc<=$tabcount; $tc++) {
			$createtabs = @$createtabs ||  isset($item->positions['subtitle_tab'.$tc]);
		}
	?>
	
	<?php if ($createtabs) :?>
		<!-- tabber start -->
		<div id="fc_subtitle_tabset" class="fctabber group">
		
		<?php for ($tc=1; $tc<=$tabcount; $tc++) : ?>
			<?php
			$tabpos_name  = 'subtitle_tab'.$tc;
			$tabpos_label = JText::_($this->params->get('subtitle_tab'.$tc.'_label', $tabpos_name));
			$tab_id = 'fc_'.$tabpos_name;
			?>
			
			<?php if (isset($item->positions[$tabpos_name])): ?>
			<!-- tab start -->
			<div id="<?php echo $tab_id; ?>" class="tabbertab">
				<h3 class="tabberheading"><?php echo $tabpos_label; ?></h3><!-- tab title -->
				<div class="flexi lineinfo">
					<?php foreach ($item->positions[$tabpos_name] as $field) : ?>
					<div class="flexi element field_<?php echo $field->name; ?>">
						<?php if ($field->label) : ?>
						<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
			<!-- tab end -->
		 	
			<?php endif; ?>
			
		<?php endfor; ?>
		
		</div>
		<!-- tabber end -->
	<?php endif; ?>
	
	
	<div class="fcclear"></div>
	
	
	<?php if ((isset($item->positions['image'])) || (isset($item->positions['top']))) : ?>
		<!-- BOF image/top row -->
		<div class="flexi topblock group">  <!-- NOTE: image block is inside top block ... -->
			
			<?php if (isset($item->positions['image'])) : ?>
				<!-- BOF image block -->
				<?php foreach ($item->positions['image'] as $field) : ?>
				<div class="flexi image field_<?php echo $field->name; ?>">
					<?php echo $field->display; ?>
					<div class="fcclear"></div>
				</div>
				<?php endforeach; ?>
				<!-- EOF image block -->
			<?php endif; ?>
			
			<?php if (isset($item->positions['top'])) : ?>
				<!-- BOF top block -->
				<?php
					$top_cols = $this->params->get('top_cols', 'two');
					$span_class = $top_cols == 'one' ? 'span12' : 'span6'; // bootstrap span
				?>
				<div class="flexi infoblock <?php echo $top_cols; ?>cols group">
					<ul class="flexi">
						<?php foreach ($item->positions['top'] as $field) : ?>
						<li class="flexi lvbox <?php echo 'field_' . $field->name; ?>">
							<div>
								<?php if ($field->label) : ?>
								<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
								<?php endif; ?>
								<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
							</div>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<!-- EOF top block -->
			<?php endif; ?>
			
		</div>
		<!-- EOF image/top row -->
	<?php endif; ?>
	
	
	<div class="fcclear"></div>
	
	
	<?php if (isset($item->toc)) : ?>
		<!-- BOF TOC -->
		<?php echo $item->toc; ?>
		<!-- EOF TOC -->
	<?php endif; ?>
	
	
	<?php if (isset($item->positions['description'])) : ?>
		<!-- BOF description -->
		<div class="description group">
			<?php foreach ($item->positions['description'] as $field) : ?>
				<?php if ($field->label) : ?>
			<div class="desc-title label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></div>
				<?php endif; ?>
			<div class="desc-content field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
			<?php endforeach; ?>
		</div>
		<!-- EOF description -->
	<?php endif; ?>
	
	
	<div class="fcclear"></div>
	
	
	<?php
		// Find if at least one tabbed position is used
		$tabcount = 12; $createtabs = false;
		for ($tc=1; $tc<=$tabcount; $tc++) {
			$createtabs = @$createtabs ||  isset($item->positions['bottom_tab'.$tc]);
		}
	?>
	
	<?php if ($createtabs) :?>
		<!-- tabber start -->
		<div id="fc_bottom_tabset" class="fctabber group">
	
		<?php for ($tc=1; $tc<=$tabcount; $tc++) : ?>
			<?php
			$tabpos_name  = 'bottom_tab'.$tc;
			$tabpos_label = JText::_($this->params->get('bottom_tab'.$tc.'_label', $tabpos_name));
			$tab_id = 'fc_'.$tabpos_name;
			?>
		
			<?php if (isset($item->positions[$tabpos_name])): ?>
			<!-- tab start -->
			<div id="<?php echo $tab_id; ?>" class="tabbertab">
				<h3 class="tabberheading"><?php echo $tabpos_label; ?></h3><!-- tab title -->
				<div class="flexi lineinfo">
					<?php foreach ($item->positions[$tabpos_name] as $field) : ?>
					<div class="flexi element field_<?php echo $field->name; ?>">
						<?php if ($field->label) : ?>
						<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
			<!-- tab end -->
			<?php endif; ?>
			
		<?php endfor; ?>
		
		</div>
		<!-- tabber end -->
	<?php endif; ?>
	
	
	<div class="fcclear"></div>
	
	
	<?php
		$readmore_forced = $this->params->get('show_readmore', 1) == -1;
		$readmore_shown  = $this->params->get('show_readmore', 1) && strlen(trim($item->fulltext)) >= 1;
		$readmore_shown  = $readmore_shown || $readmore_forced;
		$footer_shown = $readmore_shown || isset($item->positions['bottom']) || $item->event->afterDisplayContent;
	?>
	
	
	<?php if (isset($item->positions['bottom'])) : ?>
		<!-- BOF bottom block -->
		<?php
			$bottom_cols = $this->params->get('bottom_cols', 'two');
			$span_class = $bottom_cols == 'one' ? 'span12' : 'span6'; // bootstrap span
		?>
		<div class="flexi infoblock <?php echo $bottom_cols; ?>cols group">
			<ul class="flexi">
				<?php foreach ($item->positions['bottom'] as $field) : ?>
				<li class="flexi lvbox <?php echo 'field_' . $field->name; ?>">
					<div>
						<?php if ($field->label) : ?>
						<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<!-- EOF bottom block -->
	<?php endif; ?>
	
	
	<?php if ( $readmore_shown ) : ?>
	<span class="readmore">
		
		<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item)); ?>" class="btn" itemprop="url">
			<span class="icon-chevron-right"></span>
			<?php echo $item->params->get('readmore')  ?  $item->params->get('readmore') : JText::sprintf('FLEXI_READ_MORE', $item->title); ?>
		</a>
		
	</span>
	<?php endif; ?>
	
	
	<?php if ($item->event->afterDisplayContent) : ?>
		<!-- BOF afterDisplayContent -->
		<div class="fc_afterDisplayContent group">
			<?php echo $item->event->afterDisplayContent; ?>
		</div>
		<!-- EOF afterDisplayContent -->
	<?php endif; ?>
	
	
	<?php echo $mainAreaTag == 'section' ? '</article>' : ''; ?>
	
	<?php if ($this->params->get('show_comments_incat') && !JFactory::getApplication()->input->getInt('print', 0)) : /* PARAMETER MISSING */?>
		<!-- BOF comments -->
		<section class="comments group">
		<?php
			if ($this->params->get('comments') == 1) :
				if (file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) :
					require_once(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php');
					echo JComments::showComments($item->id, 'com_flexicontent', $this->escape($item->title));
				endif;
			endif;
	
			if ($this->params->get('comments') == 2) :
				if (file_exists(JPATH_SITE.DS.'plugins'.DS.'content'.DS.'jom_comment_bot.php')) :
		  			require_once(JPATH_SITE.DS.'plugins'.DS.'content'.DS.'jom_comment_bot.php');
		  			echo jomcomment($item->id, 'com_flexicontent');
					endif;
				endif;
		?>
		</section>
		<!-- BOF comments -->
	<?php endif; ?>

<?php echo '</'.$mainAreaTag.'>'; ?>

<?php endforeach; /* item loop */ ?>

