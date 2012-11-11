<?php
/**
 * HTML5 Template
 * @version 1.5 stable $Id: category_html5.php 0001 2012-09-23 14:00:28Z Rehne $
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

$page_classes  = '';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' category cat'.$this->category->id;
?>

<?php echo ( ($this->params->get( 'show_page_heading', 1 ) || $this->params->get('show_cat_title', 1) ) ? '<section' : '<div'); ?> id="flexicontent" class="flexicontent <?php echo $page_classes; ?> group" >

<!-- BOF buttons -->
	<?php
	if ( $this->params->get('show_print_icon')
		|| $this->params->get('show_email_icon')
		|| JRequest::getCmd('print')
		|| $this->params->get('show_feed_icon', 1)
		) {
	?>
	<p class="buttons">
		<?php //echo flexicontent_html::addbutton( $this->params ); ?>
		<?php echo flexicontent_html::printbutton( $this->print_link, $this->params ); ?>
		<?php echo flexicontent_html::mailbutton( 'category', $this->params, $this->category->slug ); ?>
		<?php echo flexicontent_html::feedbutton( 'category', $this->params, $this->category->slug ); ?>
	</p>
	<?php } ?>
<!-- EOF buttons -->

<!-- BOF page title -->
	<?php if ($this->params->get('show_page_heading', 1)) : ?>
    <header class="group">
		<h1 class="componentheading">
			<?php echo $this->params->get( 'page_heading' ) ?>
		</h1>
	<?php endif; ?>
<!-- EOF page title -->

<!-- BOF author description -->
	<?php
	if (@$this->authordescr_item_html) :
		echo $this->authordescr_item_html;
	endif;
	?>
<!-- EOF author description -->

	<?php if ($this->params->get('show_page_heading', 1)) echo '</header>'; ?>


<?php if ( $this->category->id > 0) : /* Category specific data e.g. not available for -author- category view */ ?>

	<!-- BOF category info -->
		<?php
		// Only show this part if some category info is to be printed
		if (
			$this->params->get('show_cat_title', 1) ||
			($this->params->get('show_description_image', 1) && $this->category->image) ||
			($this->params->get('show_description', 1) && $this->category->description)
		) :
			if ($this->params->get('show_cat_title', 1)) echo '<section class="group">';
			echo $this->loadTemplate('category_html5');
		endif;
		?>
	<!-- EOF category info -->
	
	<!-- BOF sub-categories info -->
		<?php 
		// Only show this part if subcategories are available
		if ( count($this->categories) && $this->params->get('show_subcategories') ) :
			echo $this->loadTemplate('subcategories_html5');
		endif;
		?>
	<!-- EOF sub-categories info --
	
<?php endif; ?>

	<?php if ($this->params->get('show_cat_title', 1)) echo '</section>'; ?>

<!-- BOF item list display -->
	<?php echo $this->loadTemplate('items_html5'); ?>
<!-- BOF item list display -->

<!-- BOF pagination -->
	<?php if ($this->params->get('show_pagination', 2) != 0) : ?>
		<footer class="group">
		<div class="pageslinks">
			<?php echo $this->pageNav->getPagesLinks(); ?>
		</div>

		<?php if ($this->params->get('show_pagination_results', 1)) : ?>
		<p class="pagescounter">
			<?php echo $this->pageNav->getPagesCounter(); ?>
		</p>
		<?php endif; ?>
		</footer>
	<?php endif; ?>
<!-- EOF pagination -->


<?php echo ( ($this->params->get( 'show_page_heading', 1 ) || $this->params->get('show_cat_title', 1) ) ? '</section>' : '</div>'); ?>