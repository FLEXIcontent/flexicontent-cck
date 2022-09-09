<?php
/**
 * @package FLEXIcontent
 * @copyright (C) 2009-2018 Emmanuel Danan, Georgios Papadakis, Yannick Berges
 * @author Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @license GNU/GPL v2
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;

// USE 1: HTML5 or 0: XHTML
$html5 = $this->params->get('htmlmode', 0);

if ($html5)
{
	// Load html5 layout
	echo $this->loadTemplate('html5');
}

// BOF XHTML
else
{

// first define the template name
$tmpl = $this->tmpl;
$item = $this->item;
$menu = JFactory::getApplication()->getMenu()->getActive();

JFactory::getDocument()->addScript(JUri::base(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
JFactory::getDocument()->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
JFactory::getDocument()->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

// Prepend toc (Table of contents) before item's description (toc will usually float right)
// By prepend toc to description we make sure that it get's displayed at an appropriate place
if (isset($item->toc))
{
	$item->fields['text']->display = $item->toc . $item->fields['text']->display;
}


/**
 * Custom Classes for containers
 */

$box_class_subtitle1 = $this->params->get('box_class_subtitle1', 'flexi lineinfo subtitle1');
$box_class_subtitle2 = $this->params->get('box_class_subtitle2', 'flexi lineinfo subtitle2');
$box_class_subtitle3 = $this->params->get('box_class_subtitle3', 'flexi lineinfo subtitle3');

$box_class_image  = $this->params->get('box_class_image', 'flexi image');
$box_class_top    = $this->params->get('box_class_top', 'flexi infoblock');
$box_class_descr  = $this->params->get('box_class_descr', 'flexi description');
$box_class_bottom = $this->params->get('box_class_bottom', 'flexi bottomblock infoblock');


/**
 * Decide Tags for containers
 */

$page_heading_shown =
	$this->params->get( 'show_page_heading', 1 ) &&
	$this->params->get('page_heading') != $item->title &&
	$this->params->get('show_title', 1);

// Main container
$mainAreaTag = 'div';

// SEO, header level of title tag
$itemTitleHeaderLevel = $page_heading_shown ? '2' : '1';

// SEO, header level of tab title tag
$tabsHeaderLevel = $itemTitleHeaderLevel == '2'  ?  '3' : '2';

$page_classes  = 'flexicontent  ';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' fcitems fcitem'.$item->id;
$page_classes .= ' fctype'.$item->type_id;
$page_classes .= ' fcmaincat'.$item->catid;
if ($menu) $page_classes .= ' menuitem'.$menu->id;

// SEO
$microdata_itemtype = $this->params->get( 'microdata_itemtype', 'Article');
$microdata_itemtype_code = 'itemscope itemtype="http://schema.org/'.$microdata_itemtype.'"';
?>

<?php echo '<'.$mainAreaTag; ?> id="flexicontent" class="<?php echo $page_classes; ?>" <?php echo $microdata_itemtype_code; ?>>

	<?php echo ( ($mainAreaTag == 'section') ? '<header>' : ''); ?>

  <?php if ($item->event->beforeDisplayContent) : ?>
		<!-- BOF beforeDisplayContent -->
		<div class="fc_beforeDisplayContent  ">
			<?php echo $item->event->beforeDisplayContent; ?>
		</div>
		<!-- EOF beforeDisplayContent -->
	<?php endif; ?>

	<?php if (JFactory::getApplication()->input->getInt('print', 0)) : ?>
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

			<div class="buttons btn- ">
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
		<div class="fc_afterDisplayTitle  ">
			<?php echo $item->event->afterDisplayTitle; ?>
		</div>
		<!-- EOF afterDisplayTitle -->
	<?php endif; ?>


	<?php if (isset($item->positions['subtitle1'])) : ?>
		<!-- BOF subtitle1 block -->
		<div class="<?php echo $box_class_subtitle1; ?>">
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
		<div class="<?php echo $box_class_subtitle2; ?>">
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
		<div class="<?php echo $box_class_subtitle3; ?>">
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
		<div id="fc_subtitle_tabset" class="fctabber  ">

		<?php for ($tc=1; $tc<=$tabcount; $tc++) : ?>
			<?php
			$tabpos_name  = 'subtitle_tab'.$tc;
			$tabpos_label = JText::_($this->params->get('subtitle_tab'.$tc.'_label', $tabpos_name));
			$box_class    = $this->params->get('box_class_subtitle_tab'.$tc, 'flexi lineinfo');
			$tab_id = 'fc_'.$tabpos_name;
			?>

			<?php if (isset($item->positions[$tabpos_name])): ?>
			<!-- tab start -->
			<div id="<?php echo $tab_id; ?>" class="tabbertab">
				<h3 class="tabberheading"><?php echo $tabpos_label; ?></h3><!-- tab title -->
				<div class="<?php echo $box_class; ?>">
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
		<div class="flexi topblock  ">  <!-- NOTE: image block is inside same outer block as position 'top' -->

			<?php
				$has_output_image = false;
				$has_output_top   = false;

				if (isset($item->positions['image']))
				{
					foreach ($item->positions['image'] as $field)
					{
						$has_output_image = $has_output_image || strlen($field->display);
					}
				}

				if (isset($item->positions['top']))
				{
					foreach ($item->positions['top'] as $field)
					{
						$has_output_top = $has_output_top || strlen($field->display);
					}
				}

				$top_span_cols_params = (int) $this->params->get('top_span_cols_params', '8');
				$top_span_cols = $top_span_cols_params < 1 || $top_span_cols_params > 12 ? 8 : $top_span_cols_params;

				$img_span_cols     = 12 - $top_span_cols > 0 ? 12 - $top_span_cols : 12;

				$imgPos_widthClass = 'span' . $img_span_cols;
				$topPos_widthClass = 'span' . $top_span_cols;

				$box_class_image .= (!$has_output_top ? ' span12' : ' ' . $imgPos_widthClass);
				$box_class_top   .= (!$has_output_image ? ' span12' : ' '. $topPos_widthClass);
			?>

			<?php if (isset($item->positions['image'])) : ?>
				<div class="<?php echo $box_class_image; ?>">
					<!-- BOF image block -->
					<?php foreach ($item->positions['image'] as $field) : ?>
					<div class=" field_<?php echo $field->name; ?>">
						<?php echo $field->display; ?>
						<div class="fcclear"></div>
					</div>
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
				<div class="<?php echo $box_class_top; ?> <?php echo $top_cols; ?>cols">
					<ul class="flexi">
						<?php foreach ($item->positions['top'] as $field) : ?>
						<li class="flexi lvbox <?php echo 'field_' . $field->name . ' ' . $span_class; ?>">
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
		<div class="<?php echo $box_class_descr; ?>">
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
		<div id="fc_bottom_tabset" class="fctabber  ">

		<?php for ($tc=1; $tc<=$tabcount; $tc++) : ?>
			<?php
			$tabpos_name  = 'bottom_tab'.$tc;
			$tabpos_label = JText::_($this->params->get('bottom_tab'.$tc.'_label', $tabpos_name));
			$box_class    = $this->params->get('box_class_bottom_tab'.$tc, 'flexi lineinfo');
			$tab_id = 'fc_'.$tabpos_name;
			?>

			<?php if (isset($item->positions[$tabpos_name])): ?>
			<!-- tab start -->
			<div id="<?php echo $tab_id; ?>" class="tabbertab">
				<h3 class="tabberheading"><?php echo $tabpos_label; ?></h3><!-- tab title -->
				<div class="<?php echo $box_class; ?>">
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
		<div class="<?php echo $box_class_bottom; ?> <?php echo $bottom_cols; ?>cols  ">
			<ul class="flexi">
				<?php foreach ($item->positions['bottom'] as $field) : ?>
				<li class="flexi lvbox <?php echo 'field_' . $field->name . ' ' . $span_class; ?>">
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
		<div class="fc_afterDisplayContent  ">
			<?php echo $item->event->afterDisplayContent; ?>
		</div>
		<!-- EOF afterDisplayContent -->
	<?php endif; ?>



	<?php echo $mainAreaTag == 'section' ? '</article>' : ''; ?>

	<?php if ($this->params->get('comments') && !JFactory::getApplication()->input->getInt('print', 0)) : ?>
		<!-- BOF comments -->
		<div class="comments  ">
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
		<!-- EOF comments -->
	<?php endif; ?>

<?php echo '</'.$mainAreaTag.'>'; ?>

<?php
} // EOF XHTML
