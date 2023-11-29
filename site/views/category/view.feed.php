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

jimport('legacy.view.legacy');
use Joomla\String\StringHelper;

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
		// Initialize framework variables
		$db       = JFactory::getDbo();
		$app      = JFactory::getApplication();
		$document = JFactory::getDocument();

		// Get model
		$model  = $this->getModel();

		// Indicate to model to merge menu parameters if menu matches
		$model->mergeMenuParams = true;

		// Get the category, loading category data and doing parameters merging
		$category = $this->get('Category');

		// Get category parameters as VIEW's parameters (category parameters are merged parameters in order: layout(template-manager)/component/ancestors-cats/category/author/menu)
		$params   = $category->parameters;

		// Prepare query to match feed data (Force a specific limit, this will be moved to the model)
		JFactory::getApplication()->input->set('limit', $params->get('feed_limit'));

		// Needed by legacy non-updated plugins
		!FLEXI_J40GE ? JRequest::setVar('limit', $params->get('feed_limit')) : null;

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

		$model->setState('limit', $params->get('feed_limit', $model->getState('limit')));


		// ***********************
		// Get data from the model
		// ***********************

		$items   = $this->get('Data');

		$feed_summary = $params->get('feed_summary', 0);
		$feed_summary_cut = $params->get('feed_summary_cut', 200);

		$feed_use_image = $params->get('feed_use_image', 1);
		$feed_link_image = $params->get('feed_link_image', 1);
		$feed_image_source = $params->get('feed_image_source', '');
		$feed_image_size = $params->get('feed_image_size', 'l');
		$feed_image_method = $params->get('feed_image_method', 1);

		$feed_image_width = $params->get('feed_image_width', 100);
		$feed_image_height = $params->get('feed_image_height', 80);

		// Retrieve default image for the image field
		if ($feed_use_image && $feed_image_source) {
			$query = 'SELECT attribs, name FROM #__flexicontent_fields WHERE id = '.(int) $feed_image_source;
			$db->setQuery($query);
			$image_dbdata = $db->loadObject();

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
				FlexicontentFields::getFieldDisplay($items, $fieldname, $values=null, $method='display');
			}
		}

		$uri = clone JUri::getInstance();
		$domain = $uri->toString(array('scheme', 'host', 'port'));
		$site_base_url = JUri::base(true).'/';
		foreach ($items as $item)
		{
			// strip html from feed item title
			$title = $this->escape( $item->title );
			$title = html_entity_decode( $title );

			// url link to article
			// & used instead of &amp; as this is converted by feed creator
			$link = $domain . JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));

			// strip html from feed item description text
			$description	= $feed_summary ? $item->introtext.$item->fulltext : $item->introtext;
			$item_desc_cut = StringHelper::strlen($description) > $feed_summary_cut;
			$description = flexicontent_html::striptagsandcut( $description, $feed_summary_cut);

			if ($feed_use_image) :
				if (!empty($img_field_name)) {
					// render method 'display_NNNN_src' to avoid CSS/JS being added to the page
					/* $src = */FlexicontentFields::getFieldDisplay($item, $img_field_name, $values=null, $method='display_'.$img_field_size.'_src');
					$img_field = $item->fields[$img_field_name];
					$src = str_replace(JUri::root(), '', ($img_field->thumbs_src[$img_field_size][0] ?? '') );
				} else {
					$src = flexicontent_html::extractimagesrc($item);
				}

				$RESIZE_FLAG = !$feed_image_source || !$img_field_size;
				if ( $src && $RESIZE_FLAG ) {
					// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
					$h		= '&amp;h=' . $feed_image_height;
					$w		= '&amp;w=' . $feed_image_width;
					$aoe	= '&amp;aoe=1';
					$q		= '&amp;q=95';
					$ar 	= '&amp;ar=x';
					$zc		= $feed_image_method ? '&amp;zc=' . $feed_image_method : '';
					$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
					$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
					$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;

					$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  $site_base_url : '';
					$thumb = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.rawurlencode($base_url.$src).$conf;
				} else {
					// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
					$thumb = $src;
					if ($src) {
						// Prepend site base folder
						$thumb = (!preg_match("#^http|^https|^ftp|^/#i", $src) ?  $site_base_url : '') . $src ;
					}
				}

				if ($thumb) {
					$thumb = (!preg_match("#^http|^https|^ftp#i", $thumb) ?  $domain : '') . $thumb;  // Prepend site 's URL protocol, domain and port
					$_img = '<img src="'.$thumb.'" alt="'.$title.'" title="'.$title.'" align="left" style="margin-left: 12px;"/>';
					if ($feed_link_image) $_img = '<a class="feed-readmore" target="_blank" href="'.$link.'">'.$_img.'</a>';
					$description = '
					<div class="feed-description">
						'.$_img.'
						<p>'.$description.'</p>
					</div>';
				}
			endif;

			if ($extra_fields) {
				foreach($extra_fields as $fieldname) {
					if ( isset($item->fields[$fieldname]->display) ) {
						$description .= '<br/><b>'.$item->fields[$fieldname]->label .":</b> ". $item->fields[$fieldname]->display;
					}
				}
			}


			// Add readmore link to description if introtext is shown, show_readmore is true and fulltext exists
			$more_text_exists = (!$feed_summary && $item->fulltext) || $item_desc_cut;
			if ($params->get('feed_show_readmore', 0) && $more_text_exists)
			{
				$description .= '<p class="feed-readmore"><a target="_blank" href ="' . $link . '">' .  JText::sprintf('FLEXI_READ_MORE', $title) . '</a></p>';
			}


			$author = !empty($item->created_by_alias)  ?  $item->created_by_alias  :  (!empty($item->author) ? $item->author : '');
			$date = $item->publish_up ? $item->publish_up : $item->created;
			@ $date = ( $date ? date( 'r', strtotime($date) ) : '' );

			// load individual item creator class
			$JF_item = new JFeedItem();
			$JF_item->title 		  = $title;
			$JF_item->link 		    = $link;
			//$JF_item->image     = $thumb;  // Currently unused by Joomla, since browser support is incomplete
			$JF_item->description = $description;
			$JF_item->date			  = $date;
			//$JF_item->author    = $author;
			$JF_item->category    = $this->escape( $category->title );

			// add item data into FEEDs array
			$document->addItem( $JF_item );
		}


		// *****************
		// Set document data
		// *****************

		$non_sef_link = null;
		$document->link = flexicontent_html::createCatLink($category->slug, $non_sef_link, $model);

		if ($category->id)
		{
			$document->title = $category->title;
			//$document->description = flexicontent_html::striptagsandcut( $category->description, $feed_summary_cut);

			$category->image = ''; //$params->get('image');
			if ($category->image)
			{
				$joomla_image_path = $app->getCfg('image_path', '');
				$joomla_image_url  = str_replace (DS, '/', $joomla_image_path);
				$joomla_image_path = $joomla_image_path ? $joomla_image_path.DS : '';
				$joomla_image_url  = $joomla_image_url  ? $joomla_image_url.'/' : '';
				$document->image = new stdClass;
				$document->image->url = $site_base_url . $joomla_image_url . $category->image;
				$document->image->title = $document->title;
				$document->image->link  = $document->link;
				$document->image->width = 100;
				$document->image->height = 80;
				$document->image->description = '';
			}
		}
	}
}
?>
