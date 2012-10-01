<?php
/**
 * HTML5 Template
 * @version 1.5 stable $Id: category_subcategories_html5.php 0001 2012-09-23 14:00:28Z Rehne $
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
?>

<?php
$n = count($this->categories);
$i = 0;
?>
<div class="subcategorieslist group">
	<?php
	echo JText::_( 'FLEXI_SUBCATEGORIES' ) . ' : ';
	foreach ($this->categories as $sub) :
		$subsubcount = count($sub->subcats);	
	?>
		<a href="<?php echo JRoute::_( FlexicontentHelperRoute::getCategoryRoute($sub->slug) ); ?>"><?php echo $this->escape($sub->title); ?></a>
		<?php
		if ($this->params->get('show_itemcount', 1)) echo ' (' . ($sub->assigneditems != null ? $sub->assigneditems.'/'.$subsubcount : '0/'.$subsubcount) . ')';
		$i++;
		if ($i != $n) :
			echo ', ';
		endif;
	endforeach; ?>
</div>