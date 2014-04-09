<?php
/**
 * @version 1.5 stable $Id: view.feed.php 1848 2014-02-16 12:03:55Z ggppdk $
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
		$params->set('orderbycustomfield'   , $params->get('feed_orderbycustomfield' , 1));
		$params->set('orderbycustomfieldid' , $params->get('feed_orderbycustomfieldid' , 0));
		$params->set('orderbycustomfielddir', $params->get('feed_orderbycustomfielddir', 'ASC'));
		$params->set('orderbycustomfieldint', $params->get('feed_orderbycustomfieldint', 0));
		
		$params->set('orderby_2nd', $params->get('feed_orderby', 'alpha'));
		$params->set('orderbycustomfield_2nd'   , $params->get('feed_orderbycustomfield_2nd' , 1));
		$params->set('orderbycustomfieldid_2nd' , $params->get('feed_orderbycustomfieldid_2nd' , 0));
		$params->set('orderbycustomfielddir_2nd', $params->get('feed_orderbycustomfielddir_2nd', 'ASC'));
		$params->set('orderbycustomfieldint_2nd', $params->get('feed_orderbycustomfieldint_2nd', 0));
		
		$model = $this->getModel();
		$model->setState('limit', $params->get('feed_limit', $model->getState('limit')));
		$rows = $this->get('Data');
		
		$feed_summary = $params->get('feed_summary', 0);
		$feed_summary_cut = $params->get('feed_summary_cut', 200);
		
		$feed_use_image = $params->get('feed_use_image', 1);
		$feed_link_image = $params->get('feed_link_image', 1);
		$feed_image_source = $params->get('feed_image_source', '');
		$feed_image_size = $params->get('feed_image_size', '');
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
			$img_field_size = $img_size_map[ $feed_image_size ];
			$img_field_name = $image_dbdata->name;
		}
		
		// TODO render and add extra fields here ... maybe via special display function for feeds view
		$extra_fields = $params->get('feed_extra_fields', '');
		$extra_fields = array_unique(preg_split("/\s*,\s*/u", $extra_fields));
		if ($extra_fields) {
			foreach($extra_fields as $fieldname) {
				// Render given field for ALL ITEMS
				FlexicontentFields::getFieldDisplay($rows, $fieldname, $values=null, $method='display');
			}
		}
		
		foreach ( $rows as $row )
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
	  			$description = "
	  			<a href='".$link."'>
	  				<img src='".$thumb."' alt='".$title."' title='".$title."' align='left'/>
	  			</a>
	  			<p>".$description."</p>";
	  		}
				if ($extra_fields) {
					foreach($extra_fields as $fieldname) {
						if ( $row->fields[$fieldname]->display ) {
			  			$description .= '<br/><b>'.$row->fields[$fieldname]->label .":</b> ". $row->fields[$fieldname]->display;
						}
					}
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