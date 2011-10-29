<?php
/**
 * @version 1.5 stable $Id: item.php 920 2011-10-05 02:17:09Z ggppdk $
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
// first define the template name
$tmpl = $this->tmpl; // for backwards compatiblity
?>

<div id="flexicontent" class="flexicontent item<?php echo $this->item->id; ?> type<?php echo $this->item->type_id; ?>">

	<!-- BOF buttons -->
	<p class="buttons">
		<?php echo flexicontent_html::pdfbutton( $this->item, $this->params ); ?>
		<?php echo flexicontent_html::mailbutton( 'item', $this->params, null , $this->item->slug ); ?>
		<?php echo flexicontent_html::printbutton( $this->print_link, $this->params ); ?>
		<?php echo flexicontent_html::editbutton( $this->item, $this->params ); ?>
	</p>
	<!-- EOF buttons -->

	<!-- BOF page title -->
	<?php if ($this->params->get( 'show_page_title', 1 ) && $this->params->get('page_title') != $this->item->title) : ?>
	<h1 class="componentheading">
		<?php echo $this->params->get('page_title'); ?>
	</h1>
	<?php endif; ?>
	<!-- EOF page title -->

	<!-- BOF item title -->
	<?php if ($this->params->get('show_title', 1)) : ?>
	<h2 class="contentheading<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>"><a href='javascript:;'>
		<?php
		if ( mb_strlen($this->item->title, 'utf-8') > $this->params->get('title_cut_text',200) ) :
			echo mb_substr ($this->item->title, 0, $this->params->get('title_cut_text',200), 'utf-8') . ' ...';
		else :
			echo $this->item->title;
		endif;
		?>
	</a></h2>
	<?php endif; ?>
	<!-- EOF item title -->

	<!-- BOF item informations -->
	<?php if ((intval($this->item->modified) !=0 && $this->params->get('show_modify_date')) || ($this->params->get('show_author') && ($this->item->creator != "")) || ($this->params->get('show_create_date')) || (($this->params->get('show_modifier')) && (intval($this->item->modified) !=0))) : ?>
	<p class="iteminfo">
		
		<?php if (($this->params->get('show_author')) && ($this->item->creator != "")) : ?>
		<span class="createdline">
			<span class="createdby">
				<?php echo JText::sprintf('FLEXI_WRITTEN_BY', $this->fields['created_by']->display); ?>
			</span>
			<?php endif; ?>
			
			<?php if (($this->params->get('show_author')) && ($this->item->creator != "") && ($this->params->get('show_create_date'))) : ?>
			::
			<?php endif; ?>
	
			<?php if ($this->params->get('show_create_date')) : ?>
			<span class="created">
				<?php echo '['.JHTML::_('date', $this->fields['created']->value[0], JText::_('DATE_FORMAT_LC2')).']'; ?>		
			</span>
			<?php endif; ?>
		</span>
		
		<span class="modifiedline">
			<?php if (($this->params->get('show_modifier')) && ($this->item->modifier != "")) : ?>
			<span class="modifiedby">
				<?php echo JText::_('FLEXI_LAST_UPDATED').' '.JText::sprintf('FLEXI_BY', $this->fields['modified_by']->display); ?>
			</span>
			<?php endif; ?>
	
			<?php if (($this->params->get('show_modifier')) && ($this->item->modifier != "") && ($this->params->get('show_modify_date'))) : ?>
			::
			<?php endif; ?>
			
			<?php if (intval($this->item->modified) !=0 && $this->params->get('show_modify_date')) : ?>
				<span class="modified">
				<?php echo '['.JHTML::_('date', $this->fields['modified']->value[0], JText::_('DATE_FORMAT_LC2')).']'; ?>
				</span>
			<?php endif; ?>
		</span>
	</p>
	<?php endif; ?>
	<!-- EOF item informations -->

	<!-- BOF item rating, favourites -->
	<?php if (($this->params->get('show_vote', 1)) || ($this->params->get('show_favs', 1)))  : ?>
	<div class="itemactions">
		
		<?php if ($this->params->get('show_vote', 1)) : ?>
		<span class="voting">
		<?php echo $this->fields['voting']->display; ?>
		</span>
		<?php endif; ?>

		<?php if ($this->params->get('show_favs', 1)) : ?>
		<span class="favourites">
			<?php echo $this->fields['favourites']->display; ?>
		</span>
		<?php endif; ?>
	</div>
	<?php endif; ?>
	<!-- EOF item rating, favourites -->

	<!-- BOF event afterDisplayTitle -->
	<?php if (!$this->params->get('show_intro')) :
		echo $this->item->event->afterDisplayTitle;
	endif; ?>
	<!-- EOF event afterDisplayTitle -->

	<!-- BOF event beforeDisplayContent -->
	<?php echo $this->item->event->beforeDisplayContent; ?>
	<!-- EOF event beforeDisplayContent -->

	<!-- BOF TOC -->
	<?php if (isset($this->item->toc)) : ?>
		<?php echo $this->item->toc; ?>
	<?php endif; ?>
	<!-- EOF TOC -->

	<!-- BOF beforedescription block -->
	<?php if (isset($this->item->positions['beforedescription'])) : ?>
	<div class="customblock beforedescription">
		<?php foreach ($this->item->positions['beforedescription'] as $field) : ?>
		<span class="element">
			<?php if ($field->label) : ?>
			<span class="fclabel field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
			<?php endif; ?>
			<span class="fcvalue field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
		</span>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<!-- EOF beforedescription block -->

	<!-- BOF description block -->
	<?php if ($this->params->get('show_intro', 1)) : ?>
	<div class="description">
	<?php echo JFilterOutput::ampReplace($this->fields['text']->display); ?>
	</div>
	<?php endif; ?>
	<!-- EOF description block -->

	<!-- BOF afterdescription block -->
	<?php if (isset($this->item->positions['afterdescription'])) : ?>
	<div class="customblock afterdescription">
		<?php foreach ($this->item->positions['afterdescription'] as $field) : ?>
		<span class="element">
			<?php if ($field->label) : ?>
			<span class="fclabel field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
			<?php endif; ?>
			<span class="fcvalue field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
		</span>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<!-- EOF afterdescription block -->
	
	<!-- BOF event afterDisplayContent -->
	<?php echo $this->item->event->afterDisplayContent; ?>
	<!-- EOF event afterDisplayContent -->

	<!-- BOF item categories, tags -->
	<?php if (($this->params->get('show_tags', 1)) || ($this->params->get('show_category', 1)))  : ?>
	<div class="itemadditionnal">
		<?php if ($this->params->get('show_category', 1)) : ?>
		<span class="categories">
			<span class="fclabel"><?php echo $this->fields['categories']->label; ?></span>
			<span class="fcvalue"><?php echo $this->fields['categories']->display; ?></span>
		</span>
		<?php endif; ?>

		<?php if ($this->params->get('show_tags', 1) && $this->fields['tags']->display) : ?>
		<span class="tags">
			<span class="fclabel"><?php echo $this->fields['tags']->label; ?></span>
			<span class="fcvalue"><?php echo $this->fields['tags']->display; ?></span>
		</span>
		<?php endif; ?>
	</div>
	<?php endif; ?>
	<!-- EOF item categories, tags  -->

	<!-- BOF comments -->
	<?php if ($this->params->get('comments') && !JRequest::getVar('print')) : ?>
	<div class="comments">
	<?php
		if ($this->params->get('comments') == 1) :
			if (file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) :
				require_once(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php');
				echo JComments::showComments($this->item->id, 'com_flexicontent', $this->escape($this->item->title));
			endif;
		endif;
	
		if ($this->params->get('comments') == 2) :
			if (file_exists(JPATH_SITE.DS.'plugins'.DS.'content'.DS.'jom_comment_bot.php')) :
    			require_once(JPATH_SITE.DS.'plugins'.DS.'content'.DS.'jom_comment_bot.php');
    			echo jomcomment($this->item->id, 'com_flexicontent');
  			endif;
  		endif;
	?>
	</div>
	<?php endif; ?>
	<!-- EOF comments -->

</div>