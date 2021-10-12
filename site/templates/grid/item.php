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

use Joomla\String\StringHelper;

// USE HTML5 or XHTML
$html5 = $this->params->get('htmlmode', 0); // 0 = XHTML , 1 = HTML5
if ($html5) {  /* BOF html5  */
	echo $this->loadTemplate('html5');
} else {

// first define the template name
$tmpl = $this->tmpl;
$item = $this->item;
$menu = JFactory::getApplication()->getMenu()->getActive();

JFactory::getDocument()->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
JFactory::getDocument()->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
JFactory::getDocument()->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

// Prepend toc (Table of contents) before item's description (toc will usually float right)
// By prepend toc to description we make sure that it get's displayed at an appropriate place
if (isset($item->toc)) {
	$item->fields['text']->display = $item->toc . $item->fields['text']->display;
}

// ***********
// DECIDE TAGS 
// ***********
$page_heading_shown =
	$this->params->get( 'show_page_heading', 1 ) &&
	$this->params->get('page_heading') != $item->title &&
	$this->params->get('show_title', 1);

// Main container
$mainAreaTag = 'div';

// SEO, header level of title tag
$itemTitleHeaderLevel = '2';
	
// SEO, header level of tab title tag
$tabsHeaderLevel = $itemTitleHeaderLevel == '2'  ?  '3' : '2';  	

$page_classes  = 'flexicontent fc-item-block news';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' fcitems fcitem'.$item->id;
$page_classes .= ' fctype'.$item->type_id;
$page_classes .= ' fcmaincat'.$item->catid;
if ($menu) $page_classes .= ' menuitem'.$menu->id; 

// SEO
$microdata_itemtype = $this->params->get( 'microdata_itemtype', 'Article');
$microdata_itemtype_code = 'itemscope itemtype="http://schema.org/'.$microdata_itemtype.'"';
?>

<?php echo '<'.$mainAreaTag; ?> id="flexicontent" class="<?php echo $page_classes; ?> " <?php echo $microdata_itemtype_code; ?>>
	
	<?php echo ( ($mainAreaTag == 'section') ? '<header>' : ''); ?>
	
  <?php if ($item->event->beforeDisplayContent) : ?>
		<!-- BOF beforeDisplayContent -->
		<div class="fc_beforeDisplayContent ">
			<?php echo $item->event->beforeDisplayContent; ?>
		</div>
		<!-- EOF beforeDisplayContent -->
	<?php endif; ?>
	
	<?php if (JFactory::getApplication()->input->getInt('print')) : ?>
		<!-- BOF Print handling -->
		<?php if ($this->params->get('print_behaviour', 'auto') == 'auto') : ?>
			<script>jQuery(document).ready(function(){ window.print(); });</script>
		<?php	elseif ($this->params->get('print_behaviour') == 'button') : ?>
			<input type='button' id='printBtn' name='printBtn' value='<?php echo JText::_('Print');?>' class='btn btn-info' onclick='this.style.display="none"; window.print(); return false;'>
		<?php endif; ?>
		<!-- EOF Print handling -->
		
	<?php else : ?>
	
		<?php
		$pdfbutton = flexicontent_html::pdfbutton( $item, $this->params );
		$mailbutton = flexicontent_html::mailbutton( FLEXI_ITEMVIEW, $this->params, $item->categoryslug, $item->slug, 0, $item );
		$printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
		$editbutton = flexicontent_html::editbutton( $item, $this->params );
		$statebutton = flexicontent_html::statebutton( $item, $this->params );
		$deletebutton = flexicontent_html::deletebutton( $item, $this->params );
		$approvalbutton = flexicontent_html::approvalbutton( $item, $this->params );
		?>
		
		<?php if ($pdfbutton || $mailbutton || $printbutton || $editbutton || $deletebutton || $statebutton || $approvalbutton) : ?>
		
			<!-- BOF buttons -->
			<?php if ($this->params->get('btn_grp_dropdown')) : ?>
			
			<div class="buttons btn-group">
			  <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">
			    <span class="<?php echo $this->params->get('btn_grp_dropdown_class', 'icon-options'); ?>"></span>
			  </button>
			  <ul class="dropdown-menu" role="menu">
			    <?php echo $pdfbutton    ? '<li>'.$pdfbutton.'</li>' : ''; ?>
			    <?php echo $mailbutton   ? '<li>'.$mailbutton.'</li>' : ''; ?>
			    <?php echo $printbutton  ? '<li>'.$printbutton.'</li>' : ''; ?>
			    <?php echo $editbutton   ? '<li>'.$editbutton.'</li>' : ''; ?>
			    <?php echo $deletebutton   ? '<li>'.$deletebutton.'</li>' : ''; ?>
			    <?php echo $approvalbutton  ? '<li>'.$approvalbutton.'</li>' : ''; ?>
			  </ul>
		    <?php echo $statebutton; ?>
			</div>

			<?php else : ?>
			<div class="buttons">
				<?php echo $pdfbutton; ?>
				<?php echo $mailbutton; ?>
				<?php echo $printbutton; ?>
				<?php echo $editbutton; ?>
				<?php echo $deletebutton; ?>
				<?php echo $statebutton; ?>
				<?php echo $approvalbutton; ?>
			</div>
			<?php endif; ?>
			<!-- EOF buttons -->
			
		<?php endif; ?>
	<?php endif; ?>
	
	<?php if ( $page_heading_shown ) : ?>
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
		<?php echo '<h'.$itemTitleHeaderLevel; ?> class="contentheading">
			<span class="fc_item_title" itemprop="name">
			<?php
				echo ( StringHelper::strlen($item->title) > (int) $this->params->get('title_cut_text',200) ) ?
					StringHelper::substr($item->title, 0, (int) $this->params->get('title_cut_text',200)) . ' ...'  :  $item->title;
			?>
			</span>
		<?php echo '</h'.$itemTitleHeaderLevel; ?>>
		<!-- EOF item title -->
	<?php endif; ?>
	
	
  <?php if ($item->event->afterDisplayTitle) : ?>
		<!-- BOF afterDisplayTitle -->
		<div class="fc_afterDisplayTitle ">
			<?php echo $item->event->afterDisplayTitle; ?>
		</div>
		<!-- EOF afterDisplayTitle -->
	<?php endif; ?>


	<?php if (isset($item->positions['slideshow_top'])) : ?>
		<!-- BOF slideshow_top block -->
		<div class="flexi lineinfo slideshow_top ">
			<?php foreach ($item->positions['slideshow_top'] as $field) : ?>
			<div class="flexi element field_<?php echo $field->name; ?>">
				<?php if ($field->label) : ?>
				<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
				<?php endif; ?>
				<div class="field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
			</div>
			<?php endforeach; ?>
		</div>
		<!-- EOF slideshow_top block -->
	<?php endif; ?>


	<?php if (isset($item->positions['subtitle1'])) : ?>
		<!-- BOF subtitle1 block -->
		<div class="flexi lineinfo subtitle1 ">
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
		<div class="flexi lineinfo subtitle2 ">
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
		<div class="flexi lineinfo subtitle3 ">
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
		<div id="fc_subtitle_tabset" class="fctabber fc-tabber-styled ">

		<?php
		$subtitle_tab_titles = $this->params->get('subtitle_tab_titles', 'Tab1 ,, Tab2 ,, Tab3 ,, Tab4 ,, Tab5 ,, Tab6 ,, Tab7 ,, Tab8 ,, Tab9 ,, Tab10 ,, Tab11 ,, Tab12');
		$subtitle_tab_titles = preg_split('/\s*,,\s*/', $subtitle_tab_titles);
		for ($tc=1; $tc<=$tabcount; $tc++) :
			$tabpos_name  = 'subtitle_tab'.$tc;
			$tabpos_label = JText::_(isset($subtitle_tab_titles[$tc-1]) ? $subtitle_tab_titles[$tc-1] : $tabpos_name);
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
		<div class="flexi topblock ">  <!-- NOTE: image block is inside top block ... -->
			
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
					$span_class = ''; //$top_cols == 'one' ? 'span8' : 'span4'; // commented out: bootstrap spanNN is not responsive to width !
				?>
				<div class="flexi infoblock <?php echo $top_cols; ?>cols">
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
	
	
	<?php if (isset($item->positions['description'])) : ?>
		<!-- BOF description -->
		<div class="description ">
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
	
	
	<?php if (isset($item->positions['featured_row_info']) || isset($item->positions['featured_row_logo'])) : ?>
		<!-- BOF description -->
		<table class="table fc-tbl-featured">
			<tr>

				<?php $featured_row_title = JText::_($this->params->get('featured_row_title', 'JFEATURED')); ?>
				<?php if ($featured_row_title) : ?>
					<td rowspan="2" class="fc-tbl-row-title">
						<div class="fc-featured-row-title"><?php echo $featured_row_title; ?></div>
					</td>
				<?php endif; ?>
	
				<?php if (isset($item->positions['featured_row_info'])) foreach ($item->positions['featured_row_info'] as $field) : ?>
					<th>
						<?php if ($field->label) : ?>
							<div class="field_<?php echo $field->name; ?>"><?php echo $field->label; ?></div>
						<?php endif; ?>
					</th>
				<?php endforeach; ?>
	
				<?php if (isset($item->positions['featured_row_logo'])) foreach ($item->positions['featured_row_logo'] as $field) : ?>
					<td rowspan="2">
						<div class="field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
					</td>
				<?php endforeach; ?>

			</tr>

			<tr>
				<?php if (isset($item->positions['featured_row_info'])) foreach ($item->positions['featured_row_info'] as $field) : ?>
					<td>
						<div class="field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
					</td>
				<?php endforeach; ?>
			</tr>

		</table>
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
		<div id="fc_bottom_tabset" class="fctabber fc-tabber-styled ">

		<?php
		$bottom_tab_titles = $this->params->get('bottom_tab_titles', 'Tab1 ,, Tab2 ,, Tab3 ,, Tab4 ,, Tab5 ,, Tab6 ,, Tab7 ,, Tab8 ,, Tab9 ,, Tab10 ,, Tab11 ,, Tab12');
		$bottom_tab_titles = preg_split('/\s*,,\s*/', $bottom_tab_titles);
		for ($tc=1; $tc<=$tabcount; $tc++) :
			$tabpos_name  = 'bottom_tab'.$tc;
			$tabpos_label = JText::_(isset($bottom_tab_titles[$tc-1]) ? $bottom_tab_titles[$tc-1] : $tabpos_name);
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
		$footer_shown =
			isset($item->positions['bottom']) || $item->event->afterDisplayContent;
	?>
	
	
	<?php if (isset($item->positions['bottom'])) : ?>
		<!-- BOF bottom block -->
		<?php
			$bottom_cols = $this->params->get('bottom_cols', 'two');
			$span_class = $bottom_cols == 'one' ? 'span12' : 'span6'; // bootstrap span
		?>
		<div class="flexi infoblock <?php echo $bottom_cols; ?>cols ">
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
	
	
	
	
	<?php if ($item->event->afterDisplayContent) : ?>
		<!-- BOF afterDisplayContent -->
		<div class="fc_afterDisplayContent ">
			<?php echo $item->event->afterDisplayContent; ?>
		</div>
		<!-- EOF afterDisplayContent -->
	<?php endif; ?>
	
	
	
	<?php echo $mainAreaTag == 'section' ? '</article>' : ''; ?>
	
	<?php if ($this->params->get('comments') && !JFactory::getApplication()->input->getInt('print')) : ?>
		<!-- BOF comments -->
		<div class="comments ">
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

<?php } /* EOF if NOT html5  */ ?>
