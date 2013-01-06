<?php
/**
 * HTML5 Template
 * @version 1.5 stable $Id: category_category_html5.php 0001 2012-09-23 14:00:28Z Rehne $
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

$catTitleHeaderLevel = ( $this->params->get( 'show_page_heading', 1 ) && $this->params->get('show_cat_title', 1) ) ? '2' : '1'; 
// Note:in Some editors like Dreamweaver will automatically set a closing tag > after </h when opening the document. So look for h>  and replaced it with h
?>

<div class="floattext group">
	<?php if ($this->params->get('show_cat_title', 1)) : ?>
    <header>
		<?php echo "<h".$catTitleHeaderLevel; ?> class="cattitle">
		<?php echo $this->escape($this->category->title); ?>
		<?php echo "</h". $catTitleHeaderLevel; ?>>
    </header>
	<?php endif; ?>

	<?php if ($this->params->get('show_description_image', 1) && $this->category->image) : ?>
	<figure class="catimg">
		<?php echo $this->category->image; ?>
	</figure>
	<?php endif; ?>
	
	<?php if ($this->params->get('show_description', 1) && $this->category->description) : ?>
	<div class="catdescription">
		<?php echo $this->category->description; ?>
	</div>
	<?php endif; ?>
</div>
