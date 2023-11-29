<?php
defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

class FcFormLayoutParameters
{
	/**
	 * Parse form's layout configuration (of parameters in XML file of layout)
	 *
	 * @param   object             $item               The item record
	 * @param   array of objects   $fields             The array of fields (objects)
	 * @param   object             $params             The (component + type) parameters
	 * @param   array of objects   $coreprops_fields   The coreprops fields (objects) ... that could be used to place core form elements via fields manager
	 * @param   array of strings   $via_core_field     The field names of core form elements ...  that can use core field in fields manager
	 * @param   array of strings   $via_core_prop      The field names of core form elements ...  that can use "core property" field type in fields manager
	 *
	 * @return  array    The configuration
	 *
	 * @since   4.0
	 */
	function createPlacementConf( $item, & $fields, $params, $coreprops_fields, $via_core_field, $via_core_prop)
	{
		$app    = JFactory::getApplication();
		$CFGsfx = $app->isClient('site') ? '' : '_be';

		$placeable_fields = array_merge($via_core_field, $via_core_prop);

		/**
		 * Find if any core properties are missing a form placement field (a coreprops field named: form_*)
		 */
		$coreprop_missing = array();
		foreach($via_core_prop as $fn => $i)
		{
			if (!isset($coreprops_fields[$fn]))
			{
				$coreprop_missing[$fn] = true;
			}
		}

		// An empty 'placeViaLayout' means that by default fields manager will try to place core form fields (and elements)
		$placementConf['placeViaLayout']   = array();
		$placementConf['coreprop_missing'] = $coreprop_missing;

		return $placementConf;
	}
}
