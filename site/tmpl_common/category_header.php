<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

// Header of the category page: page title, author description. The action buttons (print,
// mail, CSV, feed, add) are rendered separately by category_buttons.php: on the classic page
// they are included just before this header, on the GrapesJS page-builder page they are
// always shown top-right, outside the page-builder layout.
?>
<!-- BOF page title -->
<?php if ($this->params->get('show_page_heading', 1)) : ?>
	<header class="">
		<h1 class="componentheading">
			<?php echo $this->params->get( 'page_heading' ) ?>
		</h1>
<?php endif; ?>
<!-- EOF page title -->

<!-- BOF author description -->
<?php if (@$this->authordescr_item_html) echo $this->authordescr_item_html; ?>
<!-- EOF author description -->

<?php if ($this->params->get('show_page_heading', 1)) echo '</header>'; ?>
