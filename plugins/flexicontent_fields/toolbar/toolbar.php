<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright � 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

class plgFlexicontent_fieldsToolbar extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = JText::_($field->label);

		// Set field and item objects
		$this->setField($field);
		$this->setItem($item);


		/**
		 * One time initialization
		 */

		static $initialized = null;
		static $app, $document, $option, $format, $realview;

		if ($initialized === null)
		{
			$initialized = 1;

			$app       = JFactory::getApplication();
			$document  = JFactory::getDocument();
			$option    = $app->input->getCmd('option', '');
			$format    = $app->input->getCmd('format', 'html');
			$realview  = $app->input->getCmd('view', '');
		}

		// Current view variable
		$view = $app->input->getCmd('flexi_callview', ($realview ?: 'item'));
		$sfx = $view === 'item' ? '' : '_cat';

		// Check if field should be rendered according to configuration
		if (!$this->checkRenderConds($prop, $view))
		{
			return;
		}

		if ($app->input->get('print', '', 'cmd')) return;

		//$scheme = JUri::getInstance()->getScheme();  // we replaced http(s):// with //
		$document	= JFactory::getDocument();

		$lang = $document->getLanguage();
		$lang = $item->parameters->get('language', $lang);
		$lang = $lang ? $lang : 'en-GB';
		$lang = substr($lang, 0, 2);
		$lang = in_array($lang, array('en','es','it','th')) ? $lang : 'en';

		// parameters shortcuts
		$display_comments	= $field->parameters->get('display_comments', 1) && $item->parameters->get('comments',0);
		$display_resizer	= $field->parameters->get('display_resizer', 1);
		$display_print 		= $field->parameters->get('display_print', 1);
		$display_email 		= $field->parameters->get('display_email', 1);
		$display_voice 		= $field->parameters->get('display_voice', 1);
		$display_pdf 		= 0; //$field->parameters->get('display_pdf', 1);
		$load_css 			= $field->parameters->get('load_css', 1);

		$_sfx = ($view != FLEXI_ITEMVIEW) ? '_cat' : '';
		$display_social 	= $field->parameters->get('display_social'.$_sfx, ($view != FLEXI_ITEMVIEW ? 0 : 1));

		//$addthis_user		= $field->parameters->get('addthis_user', '');
		//$addthis_pubid	= $field->parameters->get('addthis_pubid', $addthis_user);

		$spacer_size		= $field->parameters->get('spacer_size', 21);
		$module_position	= $field->parameters->get('module_position', '');
		$default_size 		= $field->parameters->get('default_size', 12);
		$default_line 		= $field->parameters->get('default_line', 16);
		$target 			= $field->parameters->get('target', 'flexicontent');
		$voicetarget 		= $field->parameters->get('voicetarget', 'flexicontent');

		$opentag 		= $field->parameters->get('opentag');
		$closetag 		= $field->parameters->get('closetag');

		$spacer				= ' style="width:'.$spacer_size.'px;"';

		static $css_loaded = false;
		if ($load_css && !$css_loaded)
		{
			$css_loaded = true;
			$document->addStyleSheet(JUri::root(true).'/plugins/flexicontent_fields/toolbar/toolbar/toolbar.css');
		}


		// Create an absolute ITEM URL add and escaped ITEM TITLE
		if ($display_social || $display_comments || $display_email || $display_print)
		{
			$item_url = FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug);

			// NOTE: this uses current SSL setting (e.g menu item), and not URL scheme: http/https
			//$item_url_abs = JRoute::_($item_url, true, -1);

			$item_url_abs = JUri::getInstance()->toString(array('scheme', 'host', 'port')) . JRoute::_($item_url);
			$item_title_escaped = htmlspecialchars( $item->title, ENT_COMPAT, 'UTF-8' );
		}


		if ($display_social)
		{
			// ***************
			// OPEN GRAPH TAGs
			// ***************
			// OPEN GRAPH: site name
			if ($field->parameters->get('add_og_site_name') && $view == 'item')
			{
				$document->addCustomTag("<meta property=\"og:site_name\" content=\"".JFactory::getApplication()->getCfg('sitename')."\" />");
			}

			// OPEN GRAPH: title
			if ($field->parameters->get('add_og_title') && $view == 'item') {
				$title = flexicontent_html::striptagsandcut($item->title);
				$document->addCustomTag("<meta property=\"og:title\" content=\"{$title}\" />");
			}

			// OPEN GRAPH: description
			if ($field->parameters->get('add_og_descr') && $view == 'item')
			{
				if ( $item->metadesc ) {
					$document->addCustomTag('<meta property="og:description" content="'.$item->metadesc.'" />');
				} else {
					$text = flexicontent_html::striptagsandcut($item->text);
					$document->addCustomTag("<meta property=\"og:description\" content=\"{$text}\" />");
				}
			}

			// OPEN GRAPH: type
			$og_type = (int) $field->parameters->get('add_og_type');
			if ($og_type && $view == 'item') {
				if ($og_type > 2) $og_type = 1;
				$og_type_names = array(1=>'article', 2=>'website');
				$document->addCustomTag("<meta property=\"og:type\" content=\"".$og_type_names[$og_type]."\">");
			}

			// OPEN GRAPH: image (extracted from item's description text)
			$imageurl = '';
			if ($field->parameters->get('add_og_image') && $view == 'item')
			{
				$og_image_field     = $field->parameters->get('og_image_field');
				$og_image_fallback  = $field->parameters->get('og_image_fallback');
				$og_image_thumbsize = $field->parameters->get('og_image_thumbsize');
				if ($og_image_field)
				{
					$imageurl = FlexicontentFields::getFieldDisplay($item, $og_image_field, null, 'display_'.$og_image_thumbsize.'_src', 'module');
					if ( $imageurl )
					{
						$img_field = $item->fields[$og_image_field];
						if ( (!$imageurl && $og_image_fallback==1) || ($imageurl && $og_image_fallback==2 && $img_field->using_default_value) )
						{
							$imageurl = $this->_extractimageurl($item);
						}
					}
				}
				else
				{
					$imageurl = $this->_extractimageurl($item);
				}
				// Add image if fould, making sure it is converted to ABSOLUTE URL
				if ($imageurl)
				{
					$is_absolute = (boolean) parse_url($imageurl, PHP_URL_SCHEME); // preg_match("#^http|^https|^ftp#i", $imageurl);
					$imageurl = $is_absolute ? $imageurl : JUri::root().$imageurl;
					$document->addCustomTag("<meta property=\"og:image\" content=\"{$imageurl}\" />");
				}
			}

			// Add og-URL explicitely as this is required by facebook ?
			if ($item_url_abs && JFactory::getApplication()->input->get('format', 'html') === 'html')
			{
				$document->addCustomTag("<meta property=\"og:url\" content=\"".$item_url_abs."\" />");
			}


		}


		// Get layout name
		$viewlayout = $field->parameters->get('viewlayout', '');
		$viewlayout = $viewlayout ? 'value_'.$viewlayout : 'value_sharing_button';

		// Create field's viewing HTML, using layout file
		$field->{$prop} = array();
		include(self::getViewPath($field->field_type, $viewlayout));
		if (is_array($field->{$prop}))
		{
			$field->{$prop} = implode('', $field->{$prop});
		}
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item) {
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	private function _getCommentsCount($id)
	{
		$db = JFactory::getDbo();
		static $jcomment_installed = null;

		if ($jcomment_installed===null) {
			$app = JFactory::getApplication();
			$dbprefix = $app->getCfg('dbprefix');
			$db->setQuery('SHOW TABLES LIKE "'.$dbprefix.'jcomments"');
			$jcomment_installed = (boolean) count($db->loadObjectList());
		}
		if (!$jcomment_installed) return 0;

		$query 	= 'SELECT COUNT(com.object_id)'
				. ' FROM #__jcomments AS com'
				. ' WHERE com.object_id = ' . (int)$id
				. ' AND com.object_group = ' . $db->Quote('com_flexicontent')
				. ' AND com.published = 1'
				;
		$db->setQuery($query);

		return $db->loadResult() ? (int)$db->loadResult() : 0;
	}


	private function _extractimageurl(& $item)
	{
		$matches = NULL;
		preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $item->text, $matches);
		$imageurl = isset($matches[1][0]) ? $matches[1][0] : '';

		if ($imageurl)
		{
			if ($imageurl[0] == '/')
			{
				$imageurls = explode('/', $imageurl);
				$paths = array();
				$found = false;
				foreach($imageurls as $folder) {
					if(!$found) {
						if($folder!='images') continue;
						else {
							$found = true;
						}
					}
					$paths[] = $folder;
				}
				$imageurl = '/'.implode('/', $paths);
				$imageurl = JUri::root(true).$imageurl;
			}elseif(substr($imageurl, 0, 7)=='images/') {
				$imageurl = JUri::root(true).'/'.$imageurl;
			}
		}
		return $imageurl;
	}

}
