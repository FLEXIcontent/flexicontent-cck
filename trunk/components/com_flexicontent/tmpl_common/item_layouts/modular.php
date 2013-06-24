<?php
/**
 * @version 1.5 stable $Id: item.php 1538 2012-11-05 02:44:34Z ggppdk $
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
$item = $this->item;
$menu = JSite::getMenu()->getActive();

// USE HTML5 or XHTML
$html5			= $this->params->get('htmlmode', 0); // 0 = XHTML , 1 = HTML5
if ($html5) {  /* BOF html5  */
	echo $this->loadTemplate('html5');
} else {

JFactory::getDocument()->addScript( JURI::base().'components/com_flexicontent/assets/js/tabber-minimized.js');
JFactory::getDocument()->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/tabber.css');


// ***********
// DECIDE TAGS 
// ***********
$page_heading_shown =
	$this->params->get( 'show_page_heading', 1 ) &&
	$this->params->get('page_heading') != $item->title;

// Main container
$mainAreaTag = 'div';

// SEO, header level of title tag
$itemTitleHeaderLevel = '2';
	
// SEO, header level of tab title tag
$tabsHeaderLevel = $itemTitleHeaderLevel == '2'  ?  '3' : '2';  	

$page_classes  = 'flexicontent';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' fcitems fcitem'.$item->id;
$page_classes .= ' fctype'.$item->type_id;
$page_classes .= ' fcmaincat'.$item->catid;
if ($menu) $page_classes .= ' menuitem'.$menu->id; 
?>

<?php echo '<'.$mainAreaTag; ?> id="flexicontent" class="<?php echo $page_classes; ?> group" >
	
	<?php echo ( ($mainAreaTag == 'section') ? '<header>' : ''); ?>
	
  <?php if ($item->event->beforeDisplayContent) : ?>
	  <!-- BOF beforeDisplayContent -->
		<div class="fc_beforeDisplayContent group">
			<?php echo $item->event->beforeDisplayContent; ?>
		<div>
		<!-- EOF beforeDisplayContent -->
	<?php endif; ?>
	
	<?php
	$show_editbutton = $this->params->get('show_editbutton', 1);
	$pdfbutton = flexicontent_html::pdfbutton( $item, $this->params );
	$mailbutton = flexicontent_html::mailbutton( FLEXI_ITEMVIEW, $this->params, $item->categoryslug, $item->slug );
	$printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
	$editbutton = $show_editbutton ? flexicontent_html::editbutton( $item, $this->params ) : '';
	$statebutton = $show_editbutton ? flexicontent_html::statebutton( $item, $this->params ) : '';
	$approvalbutton = flexicontent_html::approvalbutton( $item, $this->params );
	?>
	
	<?php if ($pdfbutton || $mailbutton || $printbutton || $editbutton || $statebutton || $approvalbutton) : ?>
		<!-- BOF buttons -->
		<p class="buttons">
			<?php echo $pdfbutton; ?>
			<?php echo $mailbutton; ?>
			<?php echo $printbutton; ?>
			<?php echo $editbutton; ?>
			<?php echo $statebutton; ?>
			<?php echo $approvalbutton; ?>
		</p>
		<!-- EOF buttons -->
	<?php endif; ?>
	
	<?php if ( $this->params->get( 'show_page_heading', 1 ) ) : ?>
		<!-- BOF page heading -->
		<h1 class="componentheading">
			<?php echo $this->params->get('page_heading'); ?>
		</h1>
		<!-- EOF page heading -->
	<?php endif; ?>
	
	<?php echo ( ($mainAreaTag == 'section') ? '</header>' : ''); ?>
	
	<?php echo ( ($mainAreaTag == 'section') ? '<article>' : ''); ?>
	
	<?php
		$header_shown =
			$this->params->get('show_title', 1) || $item->event->afterDisplayTitle ||
			isset($item->positions['subtitle1']) || isset($item->positions['subtitle2']) || isset($item->positions['subtitle3']);
	?>
	
	
	<?php if ($this->params->get('show_title', 1)) : ?>
		<!-- BOF item title -->
		<?php echo '<h'.$itemTitleHeaderLevel; ?> class="contentheading"><span class="fc_item_title">
			<?php
				echo ( mb_strlen($item->title, 'utf-8') > $this->params->get('title_cut_text',200) ) ?
					mb_substr ($item->title, 0, $this->params->get('title_cut_text',200), 'utf-8') . ' ...'  :  $item->title;
			?>
		</span><?php echo '</h'.$itemTitleHeaderLevel; ?>>
		<!-- EOF item title -->
	<?php endif; ?>
	
	
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
			<div class="flexi element">
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
			<div class="flexi element">
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
			<div class="flexi element">
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
		$tabcount = 6; $createtabs = false;
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
					<div class="flexi element">
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
		<div class="flexi topblock group row">  <!-- NOTE: image block is inside top block ... -->
			
			<?php if (isset($item->positions['image'])) : ?>
				<!-- BOF image block -->
				<?php foreach ($item->positions['image'] as $field) : ?>
				<div class="flexi image field_<?php echo $field->name; ?>">
					<?php echo $field->display; ?>
					<div class="clear"></div>
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
						<li class="flexi">
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
			<div class="desc-title"><?php echo $field->label; ?></div>
				<?php endif; ?>
			<div class="desc-content"><?php echo $field->display; ?></div>
			<?php endforeach; ?>
		</div>
		<!-- EOF description -->
	<?php endif; ?>
	
	
	<div class="fcclear"></div>
	
	
	<?php
		// Find if at least one tabbed position is used
		$tabcount = 6; $createtabs = false;
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
					<div class="flexi element">
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
		$footer_shown =
			isset($item->positions['bottom']) || $item->event->afterDisplayContent;
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
				<li class="flexi">
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
	
	
	
	
	<?php if ($item->event->afterDisplayContent) : ?>
		<!-- BOF afterDisplayContent -->
		<div class="fc_afterDisplayContent group">
			<?php echo $item->event->afterDisplayContent; ?>
		</div>
		<!-- EOF afterDisplayContent -->
	<?php endif; ?>
	
	
	
	<?php echo $mainAreaTag == 'section' ? '</article>' : ''; ?>
	
	<?php if ($this->params->get('comments') && !JRequest::getVar('print')) : ?>
		<!-- BOF comments -->
		<div class="comments group">
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
		</div>
		<!-- BOF comments -->
	<?php endif; ?>

<?php echo '</'.$mainAreaTag.'>'; ?>

<?php } /* EOF if html5  */ ?>
