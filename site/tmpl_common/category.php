<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

/** tooltip in front */
$cparams = \Joomla\CMS\Component\ComponentHelper::getParams( 'com_flexicontent' );
// Load tooltips JS
if ($cparams->get('add_tooltips', 1)) \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.popover', '.hasTooltip', array('trigger' => 'click hover'));

$page_classes  = '';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' fccategory fccat'.$this->category->id;
$menu = \Joomla\CMS\Factory::getApplication()->getMenu()->getActive();
if ($menu) $page_classes .= ' menuitem'.$menu->id; 

?>
<div id="flexicontent" class="flexicontent <?php echo $page_classes; ?>" >

<?php include(dirname(__FILE__).DS.'category_buttons.php'); ?>
<?php include(dirname(__FILE__).DS.'category_header.php'); ?>

<?php if ($this->category->id || (count($this->categories) && $this->params->get('show_subcategories'))) echo '<section class="">'; ?>

<!-- BOF category info -->
<?php if ( $this->category->id > 0) : /* Category specific data may not be not available, e.g. for -author- layout view */ ?>
		<?php
		// Only show this part if some category info is to be printed
		if (
			$this->params->get('show_cat_title', 1) ||
			($this->params->get('show_description_image', 1) && $this->category->image) ||
			($this->params->get('show_description', 1) && $this->category->description)
		) :
			echo $this->loadTemplate('category');
		endif;
		?>
<?php endif; ?>
<!-- EOF category info -->
	
<!-- BOF sub-categories info -->
<?php 
	// Only show this part if subcategories are available
	if ( count($this->categories) && $this->params->get('show_subcategories') ) :
		echo file_exists(dirname(__FILE__).DS.'category_subcategories.php') ?  $this->loadTemplate('subcategories') : 'FILE MISSING: category_subcategories.php <br/>';
	endif;
?>
<!-- EOF sub-categories info -->
	
<!-- BOF peer-categories info -->
<?php 
	// Only show this part if subcategories are available
	if ( count($this->peercats) && $this->params->get('show_peercategories') ) :
		echo file_exists(dirname(__FILE__).DS.'category_peercategories.php') ?  $this->loadTemplate('peercategories') : 'FILE MISSING: category_peercategories.php <br/>';
	endif;
?>
<!-- EOF peer-categories info -->


<?php if ($this->category->id || (count($this->categories) && $this->params->get('show_subcategories')))  echo '</section>'; ?>

<!-- BOF item list display -->
<?php
	echo $this->loadTemplate('items');
	echo empty($this->items) && $this->getModel()->getState('limit') ? '<span class="fc_return_msg">'.\Joomla\CMS\Language\Text::sprintf('FLEXI_CLICK_HERE_TO_RETURN', '"JavaScript:window.history.back();"').'</span>' : "";
?>
<!-- BOF item list display -->

<!-- BOF pagination -->
<?php
/**
 * Body of form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
 * If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
 *
 * First try current folder, otherwise load from common folder
 */
	file_exists('pagination.php')
		? include('pagination.php')
		: include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'pagination.php');
?>
<!-- EOF pagination -->

</div>

