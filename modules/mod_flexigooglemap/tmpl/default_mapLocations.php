<?php
defined('_JEXEC') or die('Restricted access');

/**
 * TYPICALLY YOU DO NOT NEED TO EDIT THIS FILE !!
 * (unless you want to manually filter / select the items to be displayed on the map)
 * - Instead you want to edit file: LAYOUT_mapLocation.php (rendering a single map location)
 */

/**
 * Render the map locations for the items that will be matched according to configuration
 *
 * Use an anonymous function to encapsulate the logic for rendering map locations
 * - this allows us to use the variables $params, $mapItems, and $module without polluting the global namespace.
 * - and also allows to have custom logic for rendering map locations via atemplate file
 *
 * @var  object   $params    see below
 * @var  object[] $mapItems  see below
 * @var  object   $module    see below
 */
$renderedMapLocations = (function () use ($params, &$mapItems, $module)
{
	if (!modFlexigooglemapHelper::_checkConfiguration($params, $print_error = false)) return [];

	/*
	 * Get the field ID configured in module configuration
	 * - This is the field that contains the address values for the map locations
	 */
	$fieldAddressId = $params->get('fieldaddressid');

	// The rendered HTML and other Metadata of the map Locations for the $mapItems that will be matched according to configuration
	$mapLocations = [];

	// Fixed category mode
	if ($params->get('catidmode') == 0)
	{
		$itemsLocations = modFlexigooglemapHelper::getItemsLocations($params);
		$itemsLocations = $itemsLocations ?: array();

		// Items having these markers, to be used by the module layout
		$mapItems = [];

		foreach ($itemsLocations as $itemLocation)
		{
			// Skip empty value
			if (empty($itemLocation->value)) continue;
			$coordinates = $itemLocation->value;

			/**
			 * Render the Location HTML according to configuration, possibly using custom HTML with replacements
			 */
			modFlexigooglemapHelper::renderLocation($params, $itemLocation, $coordinates, $mapLocations, $mapItems, $module, $itemLocation->valueorder);
		}
	}

	/**
	 * Current category mode or current item mode, these are pre-created (global variables)
	 */
	else
	{
		/**
		 * Current category mode
		 * - Get items of current (category) view via a global variable
		 */
		if ($params->get('catidmode') == 1)
		{
			global $fc_list_items;
			if (empty($fc_list_items))
			{
				$fc_list_items = array();
			}
		}

		/**
		 * Get current item mode
		 * - Get item of current (item) view via a global variable
		 */
		else
		{
			global $fc_view_item;
			$fc_list_items = !empty($fc_view_item) ? [$fc_view_item] : [];
		}

		/**
		 * Render the map locations for the content items
		 * - We will create one to one array below for locations and items (items are repeated if having multiple locations)
		 * - Skip any item that has no address values
		 * - The location HTML is rendered according to configuration, possibly using custom HTML with replacements
		 */
		$mapItems = [];
		foreach ($fc_list_items as $itemLocation) if (!empty($itemLocation->fieldvalues[$fieldAddressId])) foreach ($itemLocation->fieldvalues[$fieldAddressId] as $valueOrder => $coordinates)
		{
			modFlexigooglemapHelper::renderLocation($params, $itemLocation, $coordinates, $mapLocations, $mapItems, $module, $valueOrder);
		}
	}

	return $mapLocations;
}
) ();
