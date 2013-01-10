<?php
/**
 * @version 1.5 stable $Id: category.php 1505 2012-10-01 17:16:26Z ggppdk $
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

// USE HTML5 or XHTML
$html5			= $this->params->get('htmlmode', 0); // 0 = XHTML , 1 = HTML5
if ($html5) {  /* BOF html5  */
	echo $this->loadTemplate('html5');
} else {

$page_classes  = '';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' fccategory fccat'.$this->category->id;
?>
<div id="flexicontent" class="flexicontent <?php echo $page_classes; ?>" >

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
		<h1 class="componentheading">
			<?php echo $this->params->get( 'page_heading' ) ?>
		</h1>
<?php endif; ?>
<!-- EOF page title -->

<!-- BOF author description -->
<?php if (@$this->authordescr_item_html) echo $this->authordescr_item_html; ?>
<!-- EOF author description -->


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
	
<?php if ( count($this->categories) ) : /* Category specific data may not be not available, e.g. for -author- layout view */ ?>

<!-- BOF sub-categories info -->
<?php 
	// Only show this part if subcategories are available
	if ( count($this->categories) && $this->params->get('show_subcategories') ) :
		echo $this->loadTemplate('subcategories');
	endif;
?>
	<!-- EOF sub-categories info -->
	
<?php endif; ?>


<!-- BOF item list display -->
	<?php echo $this->loadTemplate('items'); ?>
<!-- BOF item list display -->

<!-- BOF pagination -->
<?php
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'pagination.php');
?>
<!-- EOF pagination -->

</div>

<?php } /* EOF if html5  */ ?>
