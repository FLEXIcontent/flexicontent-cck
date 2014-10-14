<?php defined('_JEXEC') or die('Restricted access'); ?>

<?php
$page_classes  = '';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' fcsearch';
$menu = JFactory::getApplication()->getMenu()->getActive();
if ($menu) $page_classes .= ' menuitem'.$menu->id; 
?>

<div id="flexicontent" class="flexicontent <?php echo $page_classes; ?>" >
	
<?php if ( $this->params->get( 'show_page_heading', 1 ) ) : ?>
	<h1 class="componentheading">
		<?php echo $this->params->get('page_heading'); ?>
	</h1>
<?php else : ?>
	<h2 class="contentheading">
		<?php echo JText::_( 'FLEXI_SEARCH' ) ;?>
	</h2>
<?php endif; ?>

<!-- BOF buttons -->
<?php
if (JRequest::getCmd('print')) {
	if ($this->params->get('print_behaviour', 'auto') == 'auto') : ?>
		<script type="text/javascript">window.addEvent('domready', function() { window.print(); });</script>
	<?php	elseif ($this->params->get('print_behaviour') == 'button') : ?>
		<input type='button' id='printBtn' name='printBtn' value='<?php echo JText::_('Print');?>' class='btn btn-info' onclick='this.style.display="none"; window.print(); return false;'>
	<?php endif;
} else {
	$pdfbutton = '';
	$mailbutton = '';
	$printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
	if ($pdfbutton || $mailbutton || $printbutton) {
	?>
	<p class="buttons">
		<?php echo $pdfbutton; ?>
		<?php echo $mailbutton; ?>
		<?php echo $printbutton; ?>
	</p>
	<?php }
}
?>
<!-- EOF buttons -->

<?php if (!JRequest::getVar('print',0)) echo $this->loadTemplate('form'); ?>
<?php
if(!$this->error && count($this->results) > 0) :
	echo $this->loadTemplate('results');
else :
	echo $this->loadTemplate('error');
endif;
?>

</div>