<?php defined('_JEXEC') or die('Restricted access');

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;

$page_classes  = '';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' fcsearch';
$menu = Factory::getApplication()->getMenu()->getActive();
if ($menu) $page_classes .= ' menuitem'.$menu->id;

?>

<div id="flexicontent" class="flexicontent <?php echo $page_classes; ?>" >

<?php if ( $this->params->get( 'show_page_heading', 1 ) ) : ?>
	<h1 class="componentheading">
		<?php echo $this->params->get('page_heading'); ?>
	</h1>
<?php else : ?>
	<h2 class="contentheading">
		<?php echo Text::_( 'FLEXI_SEARCH' ) ;?>
	</h2>
<?php endif; ?>

<!-- BOF buttons -->
<?php
if (Factory::getApplication()->input->getInt('print', 0)) {
	if ($this->params->get('print_behaviour', 'auto') == 'auto') : ?>
		<script>jQuery(document).ready(function(){ window.print(); });</script>
	<?php	elseif ($this->params->get('print_behaviour') == 'button') : ?>
		<input type='button' id='printBtn' name='printBtn' value='<?php echo Text::_('Print');?>' class='btn btn-info' onclick='this.style.display="none"; window.print(); return false;'>
	<?php endif;
} else {
	$pdfbutton = '';
	$mailbutton = '';
	$printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
	if ($pdfbutton || $mailbutton || $printbutton) {
	?>
	<div class="buttons">
		<?php echo $pdfbutton; ?>
		<?php echo $mailbutton; ?>
		<?php echo $printbutton; ?>
	</div>
	<?php }
}
?>
<!-- EOF buttons -->

<?php

if (!Factory::getApplication()->input->getInt('print', 0))
{
	echo $this->loadTemplate('form');
}

if (!$this->error)
{
	if (Factory::getApplication()->input->getInt('direct', 0) && count($this->results) > 0)
	{
		header('Location: '.JRoute::_($this->results[0]->href));
	}

	else
	{
		echo $this->loadTemplate('results');
	}
}

else
{
	echo $this->loadTemplate('error');
}
?>

</div>
