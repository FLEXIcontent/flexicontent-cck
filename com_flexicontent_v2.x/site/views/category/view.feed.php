<?php
/**
 * @version 1.5 stable $Id: view.feed.php 1764 2013-09-16 08:00:21Z ggppdk $
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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the FLEXIcontent View (RSS)
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewCategory extends JViewLegacy
{
	/**
	 * Creates the RSS for the View
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		$db  = JFactory::getDBO();
		$doc = JFactory::getDocument();
		$app = JFactory::getApplication();
		$params = $this->get('Params');
		
		$doc->link = JRoute::_(FlexicontentHelperRoute::getCategoryRoute(JRequest::getVar('cid',null, '', 'int')));
		
		$category = $this->get('Category');
		
		// Prepare query to match feed data
		JRequest::setVar('limit', $params->get('feed_limit'));   // Force a specific limit, this will be moved to the model
		$params->set('orderby', $params->get('feed_orderby', 'rdate'));
		$params->set('orderbycustomfieldid' , $params->get('feed_orderbycustomfieldid' , '0'));
		$params->set('orderbycustomfielddir', $params->get('feed_orderbycustomfielddir', 'ASC'));
		$params->set('orderbycustomfieldint', $params->get('feed_orderbycustomfieldint', '0'));
		
		$cats = $this->get('Data');
		
		$feed_summary = $params->get('feed_summary', 0);
		$feed_summary_cut = $params->get('feed_summary_cut', 200);
		
		$feed_use_image = $params->get('feed_use_image', 1);
		$feed_image_source = $params->get('feed_image_source', '');
		$feed_link_image = $params->get('feed_link_image', 1);
		$feed_image_method = $params->get('feed_image_method', 1);
		
		$feed_image_width = $params->get('feed_image_width', 100);
		$feed_image_height = $params->get('feed_image_height', 80);

		// Retrieve default image for the image field
		if ($feed_use_image && $feed_image_source) {
			$query = 'SELECT attribs, name FROM #__flexicontent_fields WHERE id = '.(int) $feed_image_source;
			$db->setQuery($query);
			$image_dbdata = $db->loadObject();
			//$image_dbdata->params = FLEXI_J16GE ? new JRegistry($image_dbdata->params) : new JParameter($image_dbdata->params);
			
			$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small', '' => '');
			$img_field_size = $img_size_map[ $image_size ];
			$img_field_name = $image_dbdata->name;
		}
		
		foreach ( $cats as $row )
		{
			// strip html from feed item title
			$title = $this->escape( $row->title );
			$title = html_entity_decode( $title );

			// url link to article
			// & used instead of &amp; as this is converted by feed creator
			$link = JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $category->slug));

			// strip html from feed item description text
			$description	= $feed_summary ? $row->introtext.$row->fulltext : $row->introtext;
			$description = flexicontent_html::striptagsandcut( $description, $feed_summary_cut);
			
	  	if ($feed_use_image) {  // feed image is enabled
				$src = '';
				$thumb = '';
				if ($feed_image_source) {   // case 1 use an image field
					FlexicontentFields::getFieldDisplay($row, $img_field_name, null, 'display', 'module');
					$img_field = $row->fields[$img_field_name];
					if ( !$img_field_size ) {
						$src = str_replace(JURI::root(), '',  $img_field->thumbs_src['large'][0] );
					} else {
						$src = '';
						$thumb = $img_field->thumbs_src[ $img_field_size ][0];
					}
	  		} else {     // case 2 extract from item
					$src = flexicontent_html::extractimagesrc($row);
				}
				
				$RESIZE_FLAG = !$feed_image_source || !$img_field_size;
				if ($src && $RESIZE_FLAG) {
					// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
					$h		= '&amp;h=' . $feed_image_height;
					$w		= '&amp;w=' . $feed_image_width;
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$zc		= $feed_image_method ? '&amp;zc=' . $feed_image_method : '';
					$ext = pathinfo($src, PATHINFO_EXTENSION);
					$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $zc . $f;
					
					$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
					$thumb = JURI::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
				} else {
					// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
				}
	  		
	  		if ($thumb) {
	  			$description = "<a href='".$link."'><img src='".$thumb."' alt='".$title."' title='".$title."' align='left'/></a><p>".$description."</p>";
	  		}
  		}
	  	
			//$author = $row->created_by_alias ? $row->created_by_alias : $row->author;
			@$date    = ( $row->created ? date( 'r', strtotime($row->created) ) : '' );

			// load individual item creator class
			$item = new JFeedItem();
			$item->title 		   = $title;
			$item->link 		   = $link;
			$item->description = $description;
			$item->date			   = $date;
			//$item->author    = $author;
			$item->category    = $this->escape( $category->title );

			// loads item info into rss array
			$doc->addItem( $item );
		}
	}
}
?>