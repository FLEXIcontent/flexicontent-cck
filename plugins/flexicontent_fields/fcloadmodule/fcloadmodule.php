<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
// Lazy class loading (was JLoader::register): loaded only if/when actually used.
spl_autoload_register(static function ($class) {
	$map = array(
		'FCField' => JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php',
	);
	if (isset($map[$class]) && is_file($map[$class])) require_once $map[$class];
}, true, true);

class plgFlexicontent_fieldsFcloadmodule extends FCField
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

		$field->label = $field->parameters->get('label_form') ? \Joomla\CMS\Language\Text::_($field->parameters->get('label_form')) : \Joomla\CMS\Language\Text::_($field->label);

		// initialise property
		if ( empty($field->value[0]) )
		{
			$field->value[0] = '';
		}

		$document	= \Joomla\CMS\Factory::getApplication()->getDocument();

		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		$mod_params	= $field->parameters->get( 'mod_params', '') ;
		$mod_params	= preg_split("/[\s]*%%[\s]*/", $mod_params);

		if ( empty($mod_params[0]) ) return;

		$field->html = array();
		$n = 0;

		// Compatibility for non-serialized values or for NULL values in a field group
		$value = reset($field->value);
		if ( !is_array($value) )
		{
			$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
			$value = $array ?: array();
		}

		foreach ($mod_params as $mod_param)
		{
			if ( !strlen($mod_param) ) continue;

			list($param_label, $param_name) = array_pad(preg_split("/[\s]*!![\s]*/", $mod_param), 2, '');
			if (!static::isItemModuleParam($param_name)) continue;
			$param_value = isset($value[$param_name]) && is_scalar($value[$param_name]) ? (string) $value[$param_name] : '';

			$field->html[] = '
				<tr>
					<td class="key"><label for="'.$elementid.'_'.$n.'" class="fc-prop-lbl">' . $param_label . '</label></td>
					<td><input id="'.$elementid.'_'.$n.'" name="'.$fieldname.'[0]['.$param_name.']" class="input-xlarge" type="text" size="40" value="'.htmlspecialchars($param_value, ENT_COMPAT, 'UTF-8').'" /></td>
				</tr>'
				;
			$n++;
		}

		$field->html = '
		<table class="fc-form-tbl fcinner"><tbody>
			' . implode('', $field->html) . '
		</tbody></table>';
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = \Joomla\CMS\Language\Text::_($field->label);

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

			$app       = \Joomla\CMS\Factory::getApplication();
			$document  = \Joomla\CMS\Factory::getApplication()->getDocument();
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

		// Get field values
		$values = $values ? $values : $field->value;

		if (empty($values))
		{
			$values = array(0 => array(
			));
		}

		$unserialize_vals = true;
		if ($unserialize_vals)
		{
			// (* BECAUSE OF THIS, the value display loop expects unserialized values)
			foreach ($values as &$value)
			{
				// Compatibility for non-serialized values or for NULL values in a field group
				if ( !is_array($value) )
				{
					$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
					$value = $array ?: array(
					);
				}
			}
			unset($value); // Unset this or you are looking for trouble !!!, because it is a reference and reusing it will overwrite the pointed variable !!!
		}

		// Parameters shortcuts
		$module_method_oldname = $field->parameters->get('module-method', 1);
		$module_method	= $field->parameters->get('module_method', $module_method_oldname );
		$mymodule		= (int) $field->parameters->get('modules', 0);
		$position		= $field->parameters->get('position', '');
		$style 			= $field->parameters->get('style', -2);

		$document		= \Joomla\CMS\Factory::getApplication()->getDocument();
		$display 		= array();
		$renderer		= $document->loadRenderer('module');
		$mparams		= array('style'=>$style);


		/**
		 * CASE: specific module
		 */
		if ($module_method == 1)
		{
			if ($mymodule == 0)
			{
				$field->{$prop} = 'Please select a module';
				return;
			}


			/**
			 * Query module data
			 */
			$object = $this->_getModuleObject($mymodule);

			if (empty($object))
			{
				$field->{$prop} = 'Selected module was not found, e.g. it was deleted';
				return;
			}
			//echo '<pre>'; print_r($object); echo '</pre>';


			/**
			 * Get module object
			 */
			$mod = \Joomla\CMS\Helper\ModuleHelper::getModuleById((string) $mymodule);
			//echo '<pre>'; print_r($mod); echo '</pre>';

			// Check if module is not assigned to current menu item (not assigned to current page), and terminate
			if (!$mod || empty($mod->id))
			{
				return;
			}


			/**
			 * Set module parameter per item
			 */
			$mod_params	= $field->parameters->get( 'mod_params', '') ;
			$mod_params	= preg_split("/[\s]*%%[\s]*/", $mod_params);
			$mod_params = !empty($mod_params[0]) ? $mod_params : array();


			/**
			 * Apply per item configuration to the module (set module parameters per item)
			 */
			$value = reset($values);

			$custom_mod_params = array();
			foreach ($mod_params as $mod_param)
			{
				if ( !strlen($mod_param) ) continue;

				list($param_label, $param_name) = array_pad(preg_split("/[\s]*!![\s]*/", $mod_param), 2, '');
				if (!static::isItemModuleParam($param_name)) continue;
				$custom_mod_params[$param_name] = isset($value[$param_name]) ? static::cleanItemModuleParamValue($value[$param_name]) : null;
			}
			$_mod_params = new \Joomla\Registry\Registry($mod->params);
			foreach ($custom_mod_params as $i => $v)
			{
				$_mod_params->set($i,$v);
			}
			$mod->params = $_mod_params->toString();


			/**
			 * Render the module's HTML
			 */
			$display[] = $renderer->render($mod, $mparams);
		}


		/**
		 * CASE: all modules in module position
		 */
		else
		{
			if (!$position)
			{
				$field->{$prop} = 'Error';
				return;
			}
			foreach (\Joomla\CMS\Helper\ModuleHelper::getModules($position) as $mod)
			{
				$display[] = $renderer->render($mod, $mparams);
			}
		}

		$display = trim( implode('', $display) );
		$field->{$prop} = strlen( $display ) ? $display : null;
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if(!is_array($post) && !strlen($post)) return;

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;

		// Keep only the module parameters that the field configuration offers, as plain text values
		$allowed = array();

		foreach (preg_split("/[\s]*%%[\s]*/", (string) $field->parameters->get('mod_params', '')) as $mod_param)
		{
			$parts = array_pad(preg_split("/[\s]*!![\s]*/", $mod_param), 2, '');

			if (static::isItemModuleParam($parts[1]))
			{
				$allowed[$parts[1]] = 1;
			}
		}

		foreach ($post as $n => $v)
		{
			$clean = array();

			foreach (is_array($v) ? array_intersect_key($v, $allowed) : array() as $param_name => $param_value)
			{
				$param_value = static::cleanItemModuleParamValue($param_value);

				if ($param_value !== null && $param_value !== '')
				{
					$clean[$param_name] = $param_value;
				}
			}

			$post[$n] = $clean;
		}
	}


	/**
	 * Module parameters that an item value never sets: they are executed as PHP, unserialized, or used to build a file path
	 */
	protected static $unsafe_mod_params = array('php_rule', 'enable_php_rule', 'dynamic_catids', 'layout', 'cache', 'cache_time', 'cachemode');


	/**
	 * Check that a parameter name of the field configuration may be set per item
	 */
	public static function isItemModuleParam($param_name)
	{
		return is_string($param_name) && preg_match('/^[A-Za-z0-9_-]+$/D', $param_name) && !in_array(strtolower($param_name), static::$unsafe_mod_params, true);
	}


	/**
	 * A module parameter value that comes from an item is plain text. Modules print some of their parameters into HTML
	 * attributes, styles and scripts as they are, so the characters that leave those contexts are removed
	 *
	 * @return  string|null  The value, null if it is not usable
	 */
	public static function cleanItemModuleParamValue($value)
	{
		if (!is_scalar($value)) return null;

		$value = strip_tags((string) $value);
		$value = str_replace(array('<', '>', '"', '`', '\\', "\0", "\r", "\n"), '', $value);
		$value = str_replace("'", "\u{2019}", $value);

		return trim(function_exists('mb_substr') ? mb_substr($value, 0, 1000, 'UTF-8') : substr($value, 0, 1000));
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

	function _getModuleObject($id)
	{
		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

		$query 	= 'SELECT * FROM #__modules'
				. ' WHERE id = ' . (int)$id
				;
		$db->setQuery($query);

		return $db->loadObject();
	}

}
