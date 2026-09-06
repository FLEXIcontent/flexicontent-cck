<?php
/**
 * @version 1.5 stable $Id: view.html.php 1959 2014-09-18 00:15:15Z ggppdk $
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
jimport('joomla.filesystem.file');

/**
 * HTML View class for the Category View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewCategory extends \Joomla\CMS\MVC\View\HtmlView
{
	/**
	 * Creates the page's display
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		// Initialize framework variables
		$app      = \Joomla\CMS\Factory::getApplication();
		$jinput   = $app->input;
		$user     = \Joomla\CMS\Factory::getUser();
		$aid      = \Joomla\CMS\Access\Access::getAuthorisedViewLevels($user->id);

		// Get model
		$model  = $this->getModel();

		// Get the category, loading category data and doing parameters merging
		$category = $this->get('Category');

		// Get category parameters as VIEW's parameters (category parameters are merged parameters in order: layout(template-manager)/component/ancestors-cats/category/author/menu)
		$params   = $category->parameters;



		// ***********************
		// Get data from the model
		// ***********************

		$items = $this->get('Data');

		// Get field values
		$_vars = null;
		FlexicontentFields::getItemFields($items, $_vars, $_view='category', $aid);

		// Zero unneeded search index text
		foreach ($items as $item) $item->search_index = '';

		// Keep private account data, internal configuration and inaccessible fields out of public JSON.
		foreach ($items as $item)
		{
			foreach (array(
				'asset_id', 'created_by', 'modified_by', 'checked_out',
				'creatoremail', 'modifieremail', 'cmail', 'cuname', 'mmail', 'muname',
				'attribs', 'parameters', 'params'
			) as $property)
			{
				unset($item->$property);
			}

			$allowed_field_ids = array();
			if (isset($item->fields) && is_array($item->fields))
			{
				foreach ($item->fields as $field_name => $field)
				{
					if (!is_object($field) || empty($field->has_access) || empty($field->id))
					{
						unset($item->fields[$field_name]);
						continue;
					}

					$allowed_field_ids[(string) (int) $field->id] = true;
					$item->fields[$field_name] = (object) array(
						'id' => (int) $field->id,
						'field_type' => $field->field_type,
						'name' => $field->name,
						'label' => $field->label,
						'description' => $field->description,
						'has_access' => 1,
					);
				}
			}

			if (isset($item->fieldvalues) && is_array($item->fieldvalues))
			{
				foreach (array_keys($item->fieldvalues) as $field_id)
				{
					if (!isset($allowed_field_ids[(string) (int) $field_id]))
					{
						unset($item->fieldvalues[$field_id]);
					}
				}
			}
		}

		// Use &test=1 to test / preview item data of first item
		if ($jinput->getInt('test', 0) === 1)
		{
			$item = reset($items);
			die('<pre>' . print_r($item, true) . '');
		}

		// Output items in JSON FORMAT
		echo @json_encode( $items );
	}
}
?>
