<?php
/**
 * @version 1.5 stable $Id: view.feed.php 1577 2012-12-02 15:10:44Z ggppdk $
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

jimport('legacy.view.legacy');

/**
 * HTML View class for the FLEXIcontent View (RSS)
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFlexicontent extends JViewLegacy
{
	/**
	 * Creates the RSS for the View
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		$db  = JFactory::getDbo();
		$doc = JFactory::getDocument();
		$app = JFactory::getApplication();
		$params = $this->get('Params');

		$doc->link = JRoute::_('index.php?option=com_flexicontent&view=flexicontent&rootcat='. (int)$params->get('rootcat', FLEXI_J16GE ? 1:0));

		// Prepare query to match feed data (Force a specific limit, this will be moved to the model)
		JFactory::getApplication()->input->set('limit', $params->get('feed_limit'));

		// Needed by legacy non-updated plugins
		!FLEXI_J40GE ? JRequest::setVar('limit', $params->get('feed_limit')) : null;


		// ***********************
		// Get data from the model
		// ***********************

		$cats = $this->get('Feed');

		//$feed_summary = $params->get('feed_summary', 0);
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

		$uri = clone JUri::getInstance();
		$domain = $uri->toString(array('scheme', 'host', 'port'));
		$site_base_url = JUri::base(true).'/';
		foreach ( $cats as $cat )
		{
			// strip html from feed item title
			$title = $this->escape( $cat->title );
			$title = html_entity_decode( $title );

			// url link to article
			// & used instead of &amp; as this is converted by feed creator
			$link = /*$domain .*/ JRoute::_(FlexicontentHelperRoute::getCategoryRoute($cat->slug));

			// strip html from feed item description text
			$description	= $cat->description; //$feed_summary ? $cat->description : '';
			$description = flexicontent_html::striptagsandcut( $description, $feed_summary_cut);


	  	if ($feed_use_image) {  // feed image is enabled

				// Get some variables
				$joomla_image_path = $app->getCfg('image_path',  FLEXI_J16GE ? '' : 'images'.DS.'stories' );
				$joomla_image_url  = str_replace (DS, '/', $joomla_image_path);
				$joomla_image_path = $joomla_image_path ? $joomla_image_path.DS : '';
				$joomla_image_url  = $joomla_image_url  ? $joomla_image_url.'/' : '';

				// **************
				// CATEGORY IMAGE
				// **************

				// category image params
				$show_cat_image = $params->get('show_description_image', 0);  // we use different name for variable
				$cat_image_source = $params->get('cat_image_source', 2); // 0: extract, 1: use param, 2: use both
				$cat_link_image = $params->get('cat_link_image', 1);
				$cat_image_method = $params->get('cat_image_method', 1);
				$cat_image_width = $params->get('cat_image_width', 80);
				$cat_image_height = $params->get('cat_image_height', 80);

				$cat 		= & $category;
				$thumb = "";
				if ($cat->id && $show_cat_image) {
					$cat->image = FLEXI_J16GE ? $params->get('image') : $cat->image;
					$thumb = "";
					$cat->introtext = & $cat->description;
					$cat->fulltext = "";

					if ( $cat_image_source && $cat->image && JFile::exists( JPATH_SITE .DS. $joomla_image_path . $cat->image ) ) {
						$src = JUri::base(true) ."/". $joomla_image_url . $cat->image;

						$h		= '&amp;h=' . $cat_image_height;
						$w		= '&amp;w=' . $cat_image_width;
						$aoe	= '&amp;aoe=1';
						$q		= '&amp;q=95';
						$ar 	= '&amp;ar=x';
						$zc		= $cat_image_method ? '&amp;zc=' . $cat_image_method : '';
						$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
						$f = in_array( $ext, array('png', 'ico', 'gif', 'jpg', 'jpeg') ) ? '&amp;f='.$ext : '';
						$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;

						$thumb = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
					} else if ( $cat_image_source!=1 && $src = flexicontent_html::extractimagesrc($cat) ) {

						$h		= '&amp;h=' . $feed_image_height;
						$w		= '&amp;w=' . $feed_image_width;
						$aoe	= '&amp;aoe=1';
						$q		= '&amp;q=95';
						$ar 	= '&amp;ar=x';
						$zc		= $feed_image_method ? '&amp;zc=' . $feed_image_method : '';
						$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
						$f = in_array( $ext, array('png', 'ico', 'gif', 'jpg', 'jpeg') ) ? '&amp;f='.$ext : '';
						$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;

						$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
						$src = $base_url.$src;

						$thumb = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
					}
				}
	  		if ($thumb) {
	  			$description = "<a href='".$link."'><img src='".$thumb."' alt='".$title."' title='".$title."' align='left'/></a><p>".$description."</p>";
	  		}
  		}

			//$author = $cat->created_by_alias ? $cat->created_by_alias : $cat->author;
			@$date    = ( $cat->created ? date( 'r', strtotime($cat->created) ) : '' );

			// load individual item creator class
			$item = new JFeedItem();
			$item->title 		   = $title .' ('.(int)$cat->assigneditems.')';
			$item->link 		   = $link;
			$item->description = $description;
			$item->date			   = $date;
			//$item->author    = $author;
			//$item->category  = $this->escape( $category->title );

			// add item data into FEEDs array
			$doc->addItem( $item );
		}
	}
}
?>