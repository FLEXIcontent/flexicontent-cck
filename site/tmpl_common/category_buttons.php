<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

// Action buttons of the category page (print, mail, CSV feed, add). Included by the classic
// page header (tmpl_common/category.php) and rendered separately (top-right, outside the
// page-builder layout) by the GrapesJS page template (templates/grapesjs/category.php).
// The print behaviour parameter handles the "printable" view opened by the print button.
?>
<!-- BOF buttons -->
<?php if (\Joomla\CMS\Factory::getApplication()->input->getInt('print', 0)) : ?>

	<?php if ($this->params->get('print_behaviour', 'auto') == 'auto') : ?>
		<script>jQuery(document).ready(function(){ window.print(); });</script>
	<?php	elseif ($this->params->get('print_behaviour') == 'button') : ?>
		<input type='button' id='printBtn' name='printBtn' value='<?php echo \Joomla\CMS\Language\Text::_('Print');?>' class='btn btn-info' onclick='this.style.display="none"; window.print(); return false;'>
	<?php endif; ?>

<?php else : ?>
	<?php
	$_add_btn   = flexicontent_html::addbutton( $this->params, $this->category );
	$_print_btn = flexicontent_html::printbutton( $this->print_link, $this->params );
	$_mail_btn  = flexicontent_html::mailbutton( 'category', $this->params, $this->category->slug );
	$_csv_btn   = flexicontent_html::csvbutton( 'category', $this->params, $this->category->slug );
	$_feed_btn  = flexicontent_html::feedbutton( 'category', $this->params, $this->category->slug );
	?>

	<?php if ( $_add_btn || $_print_btn || $_mail_btn || $_csv_btn || $_feed_btn ) : ?>
	
		<?php if ($this->params->get('btn_grp_dropdown')) : ?>
		
		<div class="buttons btn-group">
		  <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">
		    <span class="<?php echo $this->params->get('btn_grp_dropdown_class', 'icon-options'); ?>"></span>
		  </button>
		  <ul class="dropdown-menu" role="menu">
		    <?php echo $_add_btn   ? '<li>'.$_add_btn.'</li>' : ''; ?>
		    <?php echo $_print_btn ? '<li>'.$_print_btn.'</li>' : ''; ?>
		    <?php echo $_mail_btn  ? '<li>'.$_mail_btn.'</li>' : ''; ?>
		    <?php echo $_csv_btn  ? '<li>'.$_csv_btn.'</li>' : ''; ?>
		    <?php echo $_feed_btn  ? '<li>'.$_feed_btn.'</li>' : ''; ?>
		  </ul>
		</div>
		
	  <?php else : ?>
	  
		<div class="buttons">
	    <?php echo $_add_btn; ?>
	    <?php echo $_print_btn; ?>
	    <?php echo $_mail_btn; ?>
	    <?php echo $_csv_btn; ?>
	    <?php echo $_feed_btn; ?>
		</div>
		
		<?php endif; ?>
		
	<?php endif; ?>
	
<?php endif; ?>
<!-- EOF buttons -->