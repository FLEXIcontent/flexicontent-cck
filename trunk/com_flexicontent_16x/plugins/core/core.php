<?php
/**
 * @version 1.0 $Id: core.php 341 2010-06-27 09:14:47Z emmanuel.danan $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.textarea
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsCore extends JPlugin{
	function plgFlexicontent_fieldsCore( &$subject, $params ) {
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR);
	}

	function onDisplayCoreFieldValue( &$field, $item, &$params, $tags=null, $categories=null, $favourites=null, $favoured=null, $vote=null, $values=null, $prop='display' ) {
		// this function is a mess and need complete refactoring
		// execute the code only if the field type match the plugin type
		$view = JRequest::setVar('view', JRequest::getVar('view', 'items'));
		
		$values = $values ? $values : $field->value;

		if($field->iscore != 1) return;
		
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$separatorf			= $field->parameters->get( 'separatorf', 1 ) ;
		$dateformat			= $field->parameters->get( 'date_format', '' ) ;
		$customdate			= $field->parameters->get( 'custom_date', '' ) ;		
						
		if($pretext) $pretext = $pretext . ' ';

		if($posttext) $posttext = ' ' . $posttext . ' ';

		switch($separatorf)
		{
			case 0:
			$separatorf = ' ';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = ' | ';
			break;

			case 3:
			$separatorf = ', ';
			break;

			default:
			$separatorf = ' ';
			break;
		}
			
		switch ($field->field_type)
		{
			case 'created': // created
/*
			$config =& JFactory::getConfig();
			$tzoffset = $config->getValue('config.offset');
			if ($item->created && strlen(trim( $item->created )) <= 10) {
				$item->created 	.= ' 00:00:00';
			}
			$date =& JFactory::getDate($item->created, $tzoffset);
			$item->created = $date->toMySQL();
*/

			$field->value[] = $item->created;
			$dateformat = $dateformat ? $dateformat : $customdate;
			$field->display = $pretext.JHTML::_( 'date', $item->created, JText::_($dateformat) ).$posttext;
			break;
			
			case 'createdby': // created by
			$field->value[] = $item->created_by;
			$field->display = $pretext.(($field->parameters->get('name_username', 1) == 2) ? $item->cuname : $item->creator).$posttext;
			break;

			case 'modified': // modified
			$field->value[] = $item->modified;
			$dateformat = $dateformat ? $dateformat : $customdate;
			$field->display = $pretext.JHTML::_( 'date', $item->modified, JText::_($dateformat) ).$posttext;
			break;
			
			case 'modifiedby': // modified by
			$field->value[] = $item->modified_by;
			$field->display = $pretext.(($field->parameters->get('name_username', 1) == 2) ? $item->muname : $item->modifier).$posttext;
			break;

			case 'title': // hits
			$field->value[] = $item->title;
			$field->display = $pretext.JText::_($item->title).$posttext;
			break;

			case 'hits': // hits
			$field->value[] = $item->hits;
			$field->display = $pretext.$item->hits.$posttext;
			break;

			case 'type': // document type
			$field->value[] = $item->type_id;
			$field->display = $pretext.JText::_($item->typename).$posttext;
			break;

			case 'version': // version
			$field->value[] = $item->version;
			$field->display = $pretext.$item->version.$posttext;
			break;

			case 'state': // state
			$field->value[] = $item->state;
			$field->display = flexicontent_html::stateicon( $item->state, $field->parameters );
			break;

			case 'voting': // voting button
// remove dummy value in next version for legacy purposes
			$field->value[] = 'button'; // dummy value to force display
			$field->display = flexicontent_html::ItemVote( $field, 'main', $vote );
			break;

			case 'favourites': // favourites button
// remove dummy value in next version for legacy purposes
			$field->value[] = 'button'; // dummy value to force display
			$favs = $favourites ? '('.$favourites.' '.JText::_('FLEXI_USERS').')' : '';
			$field->display = '
			<span class="fav-block">
				'.flexicontent_html::favicon( $field, $favoured ).'
				<span id="fcfav-reponse_'.$field->item_id.'" class="fcfav-reponse">
					<small>'.$favs.'</small>
				</span>
			</span>
				';
			break;


			case 'score': // voting score
			if ($view == 'category') break;
// remove dummy value in next version for legacy purposes
			$field->value[] = 'button'; // dummy value to force display
			$field->display = '<span id="fcfav">'.flexicontent_html::favicon( $field, $favoured ).'</span><span id="fcfav-reponse"><small>('.JText::_('Favoured').' '.$favourites.')</small></span>';
			break;

			
			case 'categories': // assigned categories
			$display = '';
			if ($categories) :
			foreach ($categories as $category) {
				$field->display[]  = '<a class="fc_categories link_' . $field->name . '" href="' . JRoute::_(FlexicontentHelperRoute::getCategoryRoute($category->slug)) . '">' . $category->title . '</a>';
				$field->value[] = $category->title; 
			}
			$field->display = implode($separatorf, $field->display);
			else :
			$field->value[] = '';
			$field->display = '';
			endif;
			break;

			case 'tags': // assigned tags
			$display = '';
			if ($tags) {
				foreach ($tags as $tag) {
					$field->value[] = $tag->name; 
					$field->display[]  = '<a class="fc_tags link_' . $field->name . '" href="' . JRoute::_(FlexicontentHelperRoute::getTagRoute($tag->slug)) . '">' . $tag->name . '</a>';
					}
				$field->display = implode($separatorf, $field->display);
			} else {
				//$field->value[] = '';
				$field->display = '';
			}
			break;
			
			case 'maintext': // main text
			if ($view == 'category') {
				$field->{$prop} = $item->introtext;
				break;
			}

			// manage the don't show introtext parameter
			if (!$item->parameters->get('show_intro', 1) && $item->fulltext && ($prop == 'display')) {
				$field->{$prop} = $item->fulltext;
				break;
			}

			$text = $item->text ? $item->text : '';

			// Search for the {readmore} tag and split the text up accordingly.
			$text = str_replace('<br>', '<br />', $text);

			$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$tagPos	= preg_match($pattern, $text);

			if ($tagPos != 0)	{
				$text = preg_replace($pattern, '',$text);
			}
			
			$field->{$prop} = $text;
			break;
		}
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		if($field->iscore != 1) return;

		switch ($field->field_type)
		{
			case 'title': // title
			$field->search = $post . ' | ';
			break;

			case 'maintext': // maintext
			$field->search = flexicontent_html::striptagsandcut($post) . ' | ';
			break;
		}

	}
}
