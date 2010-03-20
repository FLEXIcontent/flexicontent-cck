<?php
/**
 * @version 1.5 stable $Id$
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

<div class="floattext">
	<?php if ($this->params->get('show_cat_title', 1)) : ?>
	<h2 class="flexicontent cat<?php echo $this->category->id; ?>">
		<?php echo $this->escape($this->category->title); ?>
	</h2>
	<?php endif; ?>

	<?php if (!empty($this->category->image) && $this->params->get('show_description_image', 1)) : ?>
	<div class="catimg">
		<?php echo JHTML::_('image.site', $this->category->image, 'images/stories/', NULL, NULL, $this->escape($this->category->title)); ?>
	</div>
	<?php endif; ?>
	
	<?php if ($this->params->get('show_description', 1)) : ?>
	<div class="catdescription">
		<?php echo $this->category->description; ?>
	</div>
	<?php endif; ?>
</div>