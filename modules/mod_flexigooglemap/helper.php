<?php

/**
 * @version 0.6 stable $Id: helper.php yannick berges
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2015 Berges Yannick - www.com3elles.com
 * @license GNU/GPL v2

 * special thanks to ggppdk and emmanuel dannan for flexicontent
 * special thanks to my master Marc Studer

 * FLEXIadmin module is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 **/


use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

defined('_JEXEC') or die('Restricted access');

class modFlexigooglemapHelper
{

	/**
	 * Check if the module is properly configured
	 *
	 * @param object    $params       The module parameters
	 * @param bool      $print_error  If true, print error messages
	 *
	 * @return false|object  Returns false if configuration is not valid, otherwise returns the address field object
	 * @since  3.0.0
	 */
	public static function _checkConfiguration($params, $print_error = false)
	{
		/**
		 * Check field ID configured in module configuration
		 */
		$fieldAddressId = $params->get('fieldaddressid');

		if (empty($fieldAddressId)) {
			if ($print_error) echo '<div class="alert alert-warning">' . Text::_('MOD_FLEXIGOOGLEMAP_ADDRESSFORGOT') . '</div>';
			return false;
		}

		/**
		 * Get Address (Maps) field
		 */
		$field = modFlexigooglemapHelper::getField($fieldAddressId);

		if (empty($field))
		{
			/**
			 * Rare error done by webmasters (so do not add language string), the field ID is not a valid field ID or it is not an address field
			 */
			if ($print_error) echo '<div class="alert alert-warning">Address (Maps) Field with id ' . $fieldAddressId . ' or it is not an address field. Please select an appropriate field in module configuration</div>';
			return false;
		}

		return true;
	}

	/**
	 * Get items having map locations (for the given field and the given categories)
	 *
	 * @param object    $params    The module parameters
	 *
	 * @return array|mixed
	 * @since  1.0.0
	 */
	public static function getItemsLocations($params)
	{
		if (!static::_checkConfiguration($params, $print_error = true)) return [];

		// Get field ID configured in module configuration
		$fieldAddressId = $params->get('fieldaddressid');

		/**
		 * First, get the categories that have the items
		 * - By default include children categories
		 */
		$treeInclude = $params->get('treeinclude', 1);
		$catIds      = $params->get('catid');
		$catIds      = is_array($catIds) ? $catIds : [$catIds];

		/**
		 * Retrieve extra categories, such children or parent categories
		 * - Check if zero allowed categories
		 */
		$catIdsArr = flexicontent_cats::getExtraCats($catIds, $treeInclude, array());
		if (empty($catIdsArr)) return [];

		$count = $params->get('count');

		/**
		 * Include : 1 or Exclude : 0 categories
		 */
		$method_category = $params->get('method_category', '1');
		$catWhere = $method_category == 0
			? ' rel.catid IN (' . implode(',', $catIdsArr) . ')'
			: ' rel.catid NOT IN (' . implode(',', $catIdsArr) . ')';

		/**
		 * Retrieve the items having the map locations (for the given field and the given categories)
		 */
		$db = \Joomla\CMS\Factory::getDbo();
		$queryLoc = 'SELECT a.*, b.field_id, b.value, b.valueorder '
			. ', CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as itemslug'
			. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
			.' GROUP_CONCAT(cc.id SEPARATOR  ",") AS catidlist, '
			.' GROUP_CONCAT(cc.alias SEPARATOR  ",") AS  cataliaslist '
			. ', ext.type_id as type_id'
			. ' FROM #__content  AS a'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = a.id '
			. ' JOIN #__categories AS c ON c.id = a.catid'
			. ' LEFT JOIN #__flexicontent_fields_item_relations AS b ON a.id = b.item_id '
			. ' LEFT JOIN #__flexicontent_items_ext AS ext ON a.id = ext.item_id '
			.' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON a.id=rel.itemid '  // to get info for item's categories
			.' LEFT JOIN #__categories AS cc ON cc.id=rel.catid '
			. ' WHERE b.field_id = ' . $fieldAddressId . ' AND ' . $catWhere . '  AND state = 1'
			. ' ORDER BY title ' . $count;
		$itemsLoc = $db->setQuery($queryLoc)->loadObjectList();

		return $itemsLoc;
	}


	/**
	 * Render the HTML popup window display of all location markers for the given items array
	 *
	 * @param object      $params     The module parameters
	 * @param ?object[]   $mapItems   The items having these markers, to be used by the module layout
	 * @param object      $module     The module object
	 *
	 * @return string[]   The rendered HTML and other Metadata of the map Locations for the $mapItems that will be matched according to configuration
	 * @throws Exception
	 *@since  3.0.0
	 */
	public static function renderMapLocations($params, &$mapItems, $module)
	{
		$layout               = $params->get('layout', 'default');
		$renderedMapLocations = [];
		file_exists(dirname(__FILE__) . '/tmpl/' . $layout . '_mapLocations.php')
			? include(dirname(__FILE__) . '/tmpl/' . $layout . '_mapLocations.php')
			: include(dirname(__FILE__) . '/tmpl/default_mapLocations.php');

		return $renderedMapLocations;
	}

	/**
	 * Render the HTML popup window display of location marker for the given item
	 *
	 * @param  object    $params          The module parameters
	 * @param  object    $locationItem    The item for which the marker will be rendered (together with one of its location coordinates via $locationCoords)
	 * @param  string[]  $locationCoords  The coordinates of the location, indexes:
	 *                                     'lat', 'lon', 'addr1', 'city', 'province', 'state', 'zip', 'country', 'addr_display', 'url', 'custom_marker', 'marker_anchor'
	 * @param  string[]  $mapLocations    The rendered HTML and other Metadata of the map Locations for the $mapItems that will be matched according to configuration
	 *                                     (to be used by the module layout)
	 * @param  object[]  $mapItems        The items having these markers, to be used by the module layout
	 * @param  object    $module          The module object
	 * @param  int       $valueOrder      The value order of the location item, used to group-set when the address field is inside a field-group field
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function renderLocation($params, $locationItem, $locationCoords, &$mapLocations, &$mapItems, $module, $valueOrder)
	{
		//echo $valueOrder . '<br>';
		$layout               = $params->get('layout', 'default');
		file_exists(dirname(__FILE__) . '/tmpl/' . $layout . '_mapLocation.php')
			? include(dirname(__FILE__) . '/tmpl/' . $layout . '_mapLocation.php')
			: include(dirname(__FILE__) . '/tmpl/default_mapLocation.php');
	}

	/**
	 * Get a default Marker icon URL for map locations that do not specify a specific marker icon
	 *
	 * @param   object   $params         The module parameters
	 * @param   int     &$markerWidth    The marker width, (passed by reference)
	 * @param   int     &$markerHeight   The marker height, (passed by reference)
	 * @param   int     &$markerAnchorX  The marker anchor X position relative to the marker width, (passed by reference)
	 * @param   int     &$markerAnchorY  The marker anchor Y position relative to the marker height, (passed by reference)
	 *
	 * @since 3.0.0
	 */
	public static function getDefaultMarkerURL($params, &$markerWidth = 0, &$markerHeight = 0, &$markerAnchorX = 0, &$markerAnchorY = 0)
	{
		/**
		 * Get marker mode, 'lettermarkermode' was old parameter name, (in future wew may more more modes, so the old parameter name was renamed)
		 */
		$markerMode  = (int) $params->get('markermode', $params->get('lettermarkermode', 0));
		$markerImage = $params->get('markerimage', '');

		/**
		 * Fall-back to the default marker icon if custom image not set
		 */
		$markerMode = $markerMode === 1 && !$markerImage
			? -1   // Default marker icon
			: $markerMode;

		$defaultMarker_path = '';

		switch ($markerMode)
		{
			/**
			 * 'Letter' mode
			 */
			case 1:
				$color_to_file = [
					'red'   => 'spotlight-waypoint-b.png',
					'green' => 'spotlight-waypoint-a.png',
					''      => 'spotlight-waypoint-b.png' /* '' is for not set*/
				];
				$defaultMarker_url = "https://mts.googleapis.com/vt/icon/name=icons/spotlight/"
					. $color_to_file[$params->get('markercolor', '')]
					. "?text=" . $params->get('lettermarker')
					. "&psize=16&font=fonts/arialuni_t.ttf&color=ff330000&scale=1&ax=44&ay=48";
				break;
			/**
			 * 'Local image file' mode
			 */
			case 0:
				$defaultMarker_path = JPATH_SITE . '/' . $markerImage;
				$defaultMarker_url  = Uri::root(true) . '/' . $markerImage;
				break;

			/**
			 * Default marker icon: !!! AVOID changing the default marker icon URL
			 * - so that we do not have to call getimagesize() on it, which will cause a delay during PHP execution
			 */
			case -1:
			default:
				$defaultMarker_url = null;
				/*$defautmarker_url = $params->get('mapapi', 'googlemap') === 'googlemap'
					? 'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi2.png'
					: 'https://unpkg.com/leaflet@1.5.1/dist/images/marker-icon.png';*/
				break;
		}

		/**
		 * Calculate the default Marker Size and placement
		 * - Only calculate these if the default marker has been changed, because this is maybe a URL on which we need to call getimagesize()
		 * - in order to avoid delay during PHP execution due to getimagesize() call, we use caching to store the marker size and marker anchor position
		 */

		if ($defaultMarker_url && $params->get('mapapi', 'googlemap') !== 'googlemap')
		{
			if (FLEXI_CACHE) {
				$cache = \Joomla\CMS\Factory::getCache('com_flexicontent');
				$cache->setCaching(1);
				$cache->setLifeTime(FLEXI_CACHE_TIME);
				list($markerWidth, $markerHeight) = $cache->get('getimagesize', array($defaultMarker_path ?: $defaultMarker_url));
			} else {
				list($markerWidth, $markerHeight) = getimagesize($defaultMarker_path ?: $defaultMarker_url);
			}

			/**
			 * Marker anchor default marker anchor.
			 * - This is the point of the marker that touches the map location.
			 * - For default marker icons this is the bottom center.
			 */
			$markerAnchorX = $markerWidth / 2;
			$markerAnchorY = $markerHeight;
		}

		return $defaultMarker_url;
	}


	/**
	 * Load a Flexicontent field via its ID
	 *
	 * @param   int  $fieldId  The field ID
	 *
	 * @return  object|false  The field object if found, otherwise false
	 * @since   3.0.0
	 */
	public static function getField($fieldId)
	{
		/**
		 * Return already loaded field
		 */
		static $fields = [];
		if (isset($fields[$fieldId])) return $fields[$fieldId];

		/**
		 * Load the field
		 */
		$_fields = FlexicontentFields::getFieldsByIds(array($fieldId), false);
		$field   = !empty($_fields[$fieldId]) ? $_fields[$fieldId] : false;

		/**
		 * Parse field parameters
		 */
		if ($field)
		{
			$field->parameters = new Registry($field->attribs);
		}

		/**
		 * Cache and return the field
		 */
		return $fields[$fieldId] = $field;
	}

}
