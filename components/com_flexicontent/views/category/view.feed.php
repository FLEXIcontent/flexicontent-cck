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
class FlexicontentViewCategory extends JView
{
	/**
	 * Creates the RSS for the View
	 *
	 * @since 1.0
	 */
	function display()
	{
		$mainframe =& JFactory::getApplication();
		$flexiparams 	=& JComponentHelper::getParams('com_flexicontent');
		$db =& JFactory::getDBO();
		
		$doc 		= & JFactory::getDocument();
		$doc->link 	= JRoute::_(FlexicontentHelperRoute::getCategoryRoute(JRequest::getVar('cid',null, '', 'int')));
		//$doc->link 	= JRoute::_('index.php?option=com_flexicontent&view=category&cid='.JRequest::getVar('cid',null, '', 'int'));
		
		$category 	= & $this->get('Category');
		$params 	= & $this->get('Params');
		JRequest::setVar('limit', $params->get('feed_limit'));   // Force a specific limit, this will be moved to the model
		$rows 		= & $this->get('Data');
		
		$feed_summary = $params->get('feed_summary', 0);
		$feed_summary_cut = $params->get('feed_summary_cut', 200);
		
		$feed_use_image = $params->get('feed_use_image', 1);
		$feed_image_source = $params->get('feed_image_source', '');
		$feed_link_image = $params->get('feed_link_image', 1);
		$feed_image_method = $params->get('feed_image_method', 1);
		
		$feed_img_width = $params->get('feed_img_width', 100);
		$feed_img_height = $params->get('feed_img_height', 80);

		// Retrieve default image for the image field
		if ($feed_use_image && $feed_image_source) {
			$query = 'SELECT attribs FROM #__flexicontent_fields WHERE id = '.(int) $feed_image_source;
			$db->setQuery($query);
			$midata = new stdClass();
			$midata->params = $db->loadResult();
			$midata->params = new JParameter($midata->params);
			
			$midata->default_image = $midata->params->get( 'default_image', '');
			if ( $midata->default_image !== '' ) {
				$midata->default_image_filepath = JPATH_BASE.DS.$midata->default_image;
				$midata->default_image_filename = basename($midata->default_image);
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
			
	  	$itemimage = "";
	  	if ($feed_use_image) {  // feed image is enabled
				if ($feed_image_source=="") {  // case 1 extract from item 
					if ($image = flexicontent_html::extractimagesrc($row)) {
					  $src	= $image;
		
						$h		= '&amp;h=' . $feed_img_height;
						$w		= '&amp;w=' . $feed_img_width;
						$aoe	= '&amp;aoe=1';
						$q		= '&amp;q=95';
						$zc		= $feed_image_method ? '&amp;zc=' . $feed_image_method : '';
						$ext = pathinfo($src, PATHINFO_EXTENSION);
						$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
						$conf	= $w . $h . $aoe . $q . $zc . $f;
		
						$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
						$itemimage = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
		  		}
	  		} else {   // case 2 use an image field
					$src = '';
					if (!empty($row->image)) {
						$image	= unserialize($row->image);
						$src	= JURI::base(true) . '/' . $flexiparams->get('file_path') . '/' . $image['originalname'];
					} else if (!empty($midata->default_image_filepath)) {
						$src	= $midata->default_image_filepath;
					}
					
					if ($src) {
						$h		= '&amp;h=' . $feed_img_height;
						$w		= '&amp;w=' . $feed_img_width;
						$aoe	= '&amp;aoe=1';
						$q		= '&amp;q=95';
						$zc		= $feed_image_method ? '&amp;zc=' . $feed_image_method : '';
						$ext = pathinfo($src, PATHINFO_EXTENSION);
						$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
						$conf	= $w . $h . $aoe . $q . $zc . $f;
		
						$itemimage = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
					}
	  		}
	  		
	  		if ($itemimage) {
	  			$description = "<a href='".$link."'><img src='".$itemimage."' alt='".$title."' title='".$title."' align='left'/></a><p>".$description."</p>";
	  		}
  		}
	  	
			//$author			= $row->created_by_alias ? $row->created_by_alias : $row->author;
			@$date 			= ( $row->created ? date( 'r', strtotime($row->created) ) : '' );

			// load individual item creator class
			$item = new JFeedItem();
			$item->title 		= $title;
			$item->link 		= $link;
			$item->description 	= $description;
			$item->date			= $date;
			//$item->author		= $author;
			$item->category   	= $this->escape( $category->title );

			// loads item info into rss array
			$doc->addItem( $item );
		}
	}
}
?>