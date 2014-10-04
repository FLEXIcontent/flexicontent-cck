<?php
/**
 * @version 1.5 stable $Id: default.php 1108 2012-01-15 04:06:31Z ggppdk $
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
$page_classes .= ' fcdirectory rootcat'.$this->params->get('rootcat', FLEXI_J16GE ? 1:0);
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
		<?php echo JText::_( 'FLEXI_CATEGORIES' ) ;?>
	</h2>
<?php endif; ?>


<?php if ($this->params->get('showintrotext')) : ?>
	<div class="description no_space floattext">
		<?php echo $this->params->get('introtext'); ?>
	</div>
<?php endif; ?>

<?php echo $this->loadTemplate('categories'); ?>

<!-- BOF pagination -->
<?php
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'pagination.php');
?>
<!-- EOF pagination -->

</div>