<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
use Joomla\String\StringHelper;

class flexicontent_tmpl
{
	/**
	 * Parse any FLEXIcontent templates files that have been modified
	 *
	 * @return 	object	object of templates
	 * @since 1.5
	 */
	static function parseTemplates_checked($tmpldir = '', $unchanged_tmpls = null)
	{
		// Return cached data
		static $tmpls = null;
		
		// Set 'unchanged' layouts and return (this avoid reparsing on subsequent call)
		if (!empty($unchanged_tmpls))
		{
			$tmpls = $unchanged_tmpls;
			return true;
		}
		
		if ( $tmpls === null )
		{
			$tmpls = new stdClass();
			$tmpls->items = new stdClass();
			$tmpls->category = new stdClass();
		}
		
		$folders = flexicontent_tmpl::getThemes($tmpldir);
		
		foreach ($folders as $tmplname)
		{
			if ( isset($tmpls->items->$tmplname) && isset($tmpls->category->$tmplname) ) continue;  // Avoid reparsing an 'unchanged' theme
			flexicontent_tmpl::parseTemplate($tmpldir, $tmplname, $tmpls);   // Parse XML files of the template
		}
		
		return $tmpls;
	}
	
	
	/**
	 * Parses a specific FLEXIcontent template
	 * - both layout files: item.xml and category.xml are parsed
	 * - the parsed data are set into the given 'tmpls' parameter
	 * NOTE: parameter $tmpls->items and $tmpls->category needs to have been initialized
	 *
	 * @return 	nothing
	 * @since 3.0.10
	 */
	static function parseTemplate($tmpldir, $tmplname, &$themes)
	{
		static $initialized;
		if ($initialized===null)
		{
			jimport('joomla.filesystem.path' );
			jimport('joomla.filesystem.folder');
			jimport('joomla.filesystem.file');
			jimport('joomla.form.form');
			$initialized = 1;
		}
		
		$tmpldir = $tmpldir ? $tmpldir : JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$layout_types = array('items'=>'item', 'category'=>'category');
		
		foreach ($layout_types as $layout_type => $view)
		{
			// Parse & Load the XML file of the current layout
			$tmplxml = JPath::clean($tmpldir.DS.$tmplname.DS.$view.'.xml');
			if ( JFile::exists($tmplxml) && empty($themes->$layout_type->$tmplname) )
			{
				// Parse the XML file
				// About load addition XML file, please see: https://github.com/FLEXIcontent/flexicontent-cck/pull/961
				$doc = @simplexml_load_file($tmplxml, null, LIBXML_NOENT);
				if (!$doc)
				{
					if (JFactory::getApplication()->isClient('administrator')) JFactory::getApplication()->enqueueMessage('Syntax error(s) in template XML file: '. $tmplxml, 'notice');
					continue;
				}
				
				// Create new class and alias for it
				$themes->$layout_type->$tmplname = new stdClass();
				$t = & $themes->$layout_type->$tmplname;
				
				$t->name     = $tmplname;
				$t->xmlpath  = $tmplxml;
				$t->xmlmtime = filemtime($tmplxml);
				$t->view     = $view;
				$t->tmplvar  = '.'.$layout_type.'.'.$tmplname;
				$t->thumb    = 'components/com_flexicontent/templates/'.$tmplname.'/'.$view.'.png';
				
				// *** This can be serialized and thus Joomla Cache will work
				$t->params = $doc->asXML();
				
				// *** This was moved into the template files of the forms, because JForm contains 'JXMLElement',
				// which extends the PHP built-in Class 'SimpleXMLElement', (built-in Classes cannot be serialized
				// but serialization is used by Joomla 's cache, causing problem with caching the output of this function
				
				//$t->params		= new JForm('com_flexicontent.template.'.$view, array('control' => 'jform', 'load_data' => true));
				//$t->params->loadFile($tmplxml);
				
				// Get Meta Information
				$t->author    = (string) @$doc->author;
				$t->website   = (string) @$doc->website;
				$t->email     = (string) @$doc->email;
				$t->license   = (string) @$doc->license;
				$t->version   = (string) @$doc->version;
				$t->release   = (string) @$doc->release;
				$t->microdata_support = (string) @$doc->microdata_support;
				
				// Get Display Information
				$t->defaulttitle = (string) @$doc->defaulttitle;
				$t->description  = (string) @$doc->description;
				
				// Get field positions
				$groups = & $doc->fieldgroups;
				$pos    = & $groups->group;
				if ($pos) {
					for ($n=0; $n<count($pos); $n++) {
						$t->attributes[$n] = array();
						foreach ($pos[$n]->attributes() as $_attr_name => $_attr_val) {
							$t->attributes[$n][(string)$_attr_name] = (string)$_attr_val;
						}
						$t->positions[$n] = (string)$pos[$n];
					}
				}
				
				$tmpl_path = 'components/com_flexicontent/templates/'.$tmplname.'/';
				
				// CSS files
				$cssfiles = & $doc->{'css'.$view}->file;
				if ($cssfiles) {
					$t->css = new stdClass();
					$t->less_files = array();
					for ($n=0; $n<count($cssfiles); $n++) {
						$t->css->$n = $tmpl_path. (string)$cssfiles[$n];
						$less_file = JPath::clean( preg_replace('/^css|css$/', 'less', (string)$cssfiles[$n]) );
						$t->less_files[] = $less_file;
					}
				}
				
				// JS files
				$js     = & $doc->{'js'.$view};
				$jsfile = & $js->file;
				if ($jsfile) {
					$t->js = new stdClass();
					for ($n=0; $n<count($jsfile); $n++) {
						$t->js->$n = $tmpl_path. (string)$jsfile[$n];
					}
				}
			}
		}
	}	
	
	/**
	 * Parse all FLEXIcontent templates files
	 *
	 * @return 	object	object of templates
	 * @since 1.5
	 */
	static function parseTemplates($tmpldir='', $force=false, $checked_layouts=array())
	{
		static $print_logging_info = null;
		$print_logging_info = $print_logging_info !== null  ?  $print_logging_info  :  JComponentHelper::getParams('com_flexicontent')->get('print_logging_info');
		
		$debug = JDEBUG || $print_logging_info;
		$apply_cache = 1;//FLEXI_CACHE;
		
		if ( $apply_cache )
		{
			// Get template XML data from cache
			$tmplcache = JFactory::getCache('com_flexicontent_tmpl');  // Get Joomla Cache of '...tmpl' Caching Group
			$tmplcache->setCaching(1); 		              // Force cache ON
			$tmplcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expire time (default is 1 hour)
			$tmpls = $tmplcache->get(
				array('flexicontent_tmpl', 'parseTemplates_checked'),
				array($tmpldir)
			);
			
			$folder_names = array_flip( flexicontent_tmpl::getThemes($tmpldir) );
			
			$tmpl_names = array();
			foreach($tmpls->category as $tmpl) $tmpl_names[$tmpl->name] = 1;
			foreach($tmpls->items as $tmpl) $tmpl_names[$tmpl->name] = 1;
			
			$new_layouts = array();
			foreach($folder_names as $folder_name => $i)
			{
				if (!isset($tmpl_names[$folder_name]))
				{
					$new_layouts[] = $folder_name;
				}
			}
			//print_r($new_layouts);
			
			$deleted_layouts = array();
			foreach($tmpl_names as $tmpl_name => $i)
			{
				if (!isset($folder_names[$tmpl_name]))
				{
					unset( $tmpls->items->{$tmpl_name} );
					unset( $tmpls->category->{$tmpl_name} );
					$deleted_layouts[] = $tmpl_name;
				}
			}
			//print_r($deleted_layouts);
			
			// Check for modified XML files, cleaning and updating cache only for modified templates
			$modified = array();

			if (!empty($checked_layouts) || $force)
			{
				$modified = flexicontent_tmpl::checkXmlModified($tmpls, $checked_layouts);
				$modified_file_list = '';

				// Unset modified templates
				if (!empty($modified))
				{
					foreach($tmpls as $layout_type => $_tmpls)
					{
						foreach($_tmpls as $tmpl)
						{
							if (!isset($modified[$tmpl->name][$layout_type]))
							{
								continue;
							}

							unset($tmpls->$layout_type->{$tmpl->name});
							$modified_file_list .= '<br/>' . $modified[$tmpl->name][$layout_type];
						}
					}
				}
			}
			
			if (!empty($modified) || !empty($new_layouts) || !empty($deleted_layouts))
			{
				// This call only sets non-changed templates so that they are not reparsed
				flexicontent_tmpl::parseTemplates_checked($tmpldir, $tmpls);
				
				if ($debug && !empty($modified) )        JFactory::getApplication()->enqueueMessage("Re-parsing XMLs, XML file modified: ".$modified_file_list, 'message');
				if ($debug && !empty($new_layouts) )     JFactory::getApplication()->enqueueMessage("Parsing new templates: ".implode(', ', $new_layouts), 'message');
				if ($debug && !empty($deleted_layouts) ) JFactory::getApplication()->enqueueMessage("Cleaned cache from deleted templates: ".implode(', ', $deleted_layouts), 'message');
				
				// Clean and update caching re-parsing only new or changed XML files
				$tmplcache->clean();
				$tmplcache->gc();
				$tmpls = $tmplcache->get(
					array('flexicontent_tmpl', 'parseTemplates_checked'),
					array($tmpldir)
				);
			}
		}
		else {
			$tmpls = flexicontent_tmpl::parseTemplates_checked();
		}
		
		return $tmpls;
	}
	
	
	static function checkCompileLess($tmpls, $force, $checked_layouts=array())
	{
		jimport('joomla.filesystem.path' );
		jimport('joomla.filesystem.file');
		
		$templates_path = JPath::clean(JPATH_SITE.DS.'components/com_flexicontent/templates/');
		
		foreach($checked_layouts as $tmplname)
		{
			$tmpl = @ $tmpls->items->$tmplname;
			if ( $tmpl && !empty($tmpl->less_files) ) {
				$tmpl_path = $templates_path.$tmpl->name.DS;
				flexicontent_html::checkedLessCompile($tmpl->less_files, $tmpl_path, $tmpl_path.'less/include/', $force);
			}
			$tmpl = @ $tmpls->category->$tmplname;
			if ( $tmpl && !empty($tmpl->less_files) ) {
				$tmpl_path = $templates_path.$tmpl->name.DS;
				flexicontent_html::checkedLessCompile($tmpl->less_files, $tmpl_path, $tmpl_path.'less/include/', $force);
			}
		}
	}
	
	
	static function checkXmlModified($tmpls, $checked_layouts=array())
	{
		jimport('joomla.filesystem.file');
		
		$checked_tmpls = array();
		
		// Check specific templates
		if ( !empty($checked_layouts) )
		{
			foreach ($checked_layouts as $layout_name)
			{
				$layout_types = array('items', 'category');
				foreach($layout_types as $layout_type)
				{
					$tmpl = @ $tmpls->$layout_type->$layout_name;
					if ($tmpl) $checked_tmpls[$layout_type][] = $tmpl;
				}
			}
		}
		
		// Check all given templates
		else {
			foreach($tmpls as $layout_type => $_tmpls) {
				foreach($_tmpls as $tmpl) {
					$checked_tmpls[$layout_type][] = $tmpl;
				}
			}
		}
		
		$modified_files = array();
		foreach($checked_tmpls as $layout_type => $_tmpls)
		{
			foreach($_tmpls as $tmpl)
			{
				if (!JFile::exists($tmpl->xmlpath) || filemtime($tmpl->xmlpath) > $tmpl->xmlmtime)
				{
					$modified_files[$tmpl->name][$layout_type] = $tmpl->xmlpath;
				}
			}
		}
		
		return $modified_files;
	}
	
	
	/**
	 * Method to get the layout texts (cached)
	 * 
	 * @return string
	 * @since 3.0
	 */
	static function getLayoutTexts($layout_typename)
	{
		$apply_cache = 1;//FLEXI_CACHE;
		if ( $apply_cache )
		{
			$tmplcache = JFactory::getCache('com_flexicontent_tmpl');  // Get Joomla Cache of '...tmpl' Caching Group
			$tmplcache->setCaching(1); 		              // Force cache ON
			$tmplcache->setLifeTime(FLEXI_CACHE_TIME); 	// Set expire time (default is 1 hour)
			$layout_texts = $tmplcache->get(
				array('flexicontent_tmpl', '_getLayoutTexts'),
				array($layout_typename)
			);
		}
		else {
			$layout_texts = flexicontent_tmpl::_getLayoutTexts($layout_typename);
		}
		
		return $layout_texts;
	}
	
	
	/**
	 * Method to get the layout texts (non-cached)
	 * 
	 * @return string
	 * @since 3.0
	 */
	static function _getLayoutTexts($layout_typename)
	{
		static $layout_texts;
		if ( isset($layout_texts[$layout_typename]) ) return $layout_texts[$layout_typename];
		
		$layout_texts[$layout_typename] = new stdClass();
		
		// Get all templates
		$tmpls = flexicontent_tmpl::getTemplates();
		
		// Load language files of all templates if not already loaded
		FLEXIUtilities::loadTemplateLanguageFiles();
		
		// Get layout parameters and find the layout title
		foreach($tmpls->$layout_typename as $layout_folder => $tmpl)
		{
			if ( $tmpl && empty($tmpl->parameters) ) {
				//echo "CREATING PARAMETERS FOR: {$layout_typename} - {$layout_folder}<br/>";
				$tmpl->parameters = new JRegistry( flexicontent_tmpl::getLayoutparams($layout_typename, $layout_folder, '') );
			}
			$layout_texts[$layout_typename]->$layout_folder = new stdClass();
			$layout_texts[$layout_typename]->$layout_folder->title       = $tmpl ? JText::_($tmpl->parameters->get('custom_layout_title', @ $tmpl->defaulttitle)) : '';
			$layout_texts[$layout_typename]->$layout_folder->description = $tmpl ? JText::_(@ $tmpl->description) : '';
		}
		
		return $layout_texts[$layout_typename];
	}
	
	
	/**
	 * Method to get the layout parameters of an layout configuration row
	 * 
	 * @return string
	 * @since 3.0
	 */
	static function getLayoutparams($type, $folder, $cfgname, $force = false)
	{
		static $layout_params = array();
		if ( !$force && isset($layout_params[$type][$folder][$cfgname]) ) return $layout_params[$type][$folder][$cfgname];
		
		$db = JFactory::getDbo();
		$query = 'SELECT template as folder, cfgname, attribs, layout as type'
			. ' FROM #__flexicontent_layouts_conf';
		$db->setQuery($query);
		$layout_confs = $db->loadObjectList();
		
		foreach ($layout_confs as $L) {
			$layout_params[$L->type][$L->folder][$L->cfgname] = !empty($L->attribs) ? $L->attribs : '';
		}
		
		if ( !isset($layout_params[$type][$folder][$cfgname]) ) $layout_params[$type][$folder][$cfgname] = '';
		return $layout_params[$type][$folder][$cfgname];
	}
	
	
	static function getTemplates($layout_name = null, $skip_less_compile = false)
	{
		static $tmpls = null;
		
		static $print_logging_info = null;
		$print_logging_info = $print_logging_info !== null  ?  $print_logging_info  :  JComponentHelper::getParams('com_flexicontent')->get('print_logging_info');
		if ($print_logging_info) { global $fc_run_times; $start_microtime = microtime(true); }
		$debug = JDEBUG || $print_logging_info;
		
		
		// *****************************************************
		// Parse view.xml (item|category).xml files of templates
		// *****************************************************
		
		$checked_layouts = is_array($layout_name)  ?  $layout_name  :  ( $layout_name ? array($layout_name) : array() );
		//printr_r($checked_layouts);
		//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
		
		if ($tmpls === null)
		{
			// Get templates reparsing XML, checking if -specific layout(s)- files have been modified
			if ($print_logging_info) $start_microtime = microtime(true);
			$tmpls = flexicontent_tmpl::parseTemplates('', false, $checked_layouts);
			if ($print_logging_info) $fc_run_times['templates_parsing_xml'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		
		
		// *******************************
		// Check/Compile LESS files to CSS
		// *******************************
		
		if ( count($checked_layouts) && !$skip_less_compile )
		{
			// Compile LESS to CSS, checking if -specific layout(s)- files have been modified
			if ($print_logging_info) $start_microtime = microtime(true);
			flexicontent_tmpl::checkCompileLess($tmpls, false, $checked_layouts);
			if ($print_logging_info) $fc_run_times['templates_parsing_less'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		}
		// Uncomment to update ALL
		//$all = array(); foreach ($tmpls->items as $tmplname => $i) $t[] = $tmplname;  flexicontent_tmpl::checkCompileLess($tmpls, false, $all);
		
		
		// *******************************************************************************
		// Load Template-Specific language file(s) to override or add new language strings
		// *******************************************************************************
		
		// Load language files of -specific layout(s)-
		//echo "SPECIFIC files: ".print_r($layout_name, true);
		foreach ($checked_layouts as $foldername)
		{
			FLEXIUtilities::loadTemplateLanguageFile( $foldername );
		}
		
		return $tmpls;
	}
	
	
	static function getThemes($tmpldir='')
	{
		jimport('joomla.filesystem.folder');
		$tmpldir = $tmpldir ? $tmpldir : JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$themes = JFolder::folders($tmpldir);  // Get specific template folder

		return $themes;
	}

	/**
	 * Method to get all available fields for a template in a view
	 *
	 * @access public
	 * @return object
	 */
	static function getFieldsByPositions($folder, $type) {
		if ($type=='item') $type='items';

		static $templates;
		if(!isset($templates[$folder])) {
			$templates[$folder] = array();
		}
		if(!isset($templates[$folder][$type])) {
			$db = JFactory::getDbo();
			$query  = 'SELECT *'
					. ' FROM #__flexicontent_templates'
					. ' WHERE template = ' . $db->Quote($folder)
					. ' AND layout = ' . $db->Quote($type)
					;
			$db->setQuery($query);
			$positions = $db->loadObjectList('position');
			foreach ($positions as $pos) {
				$pos->fields = explode(',', $pos->fields);
			}
			$templates[$folder][$type] = & $positions;
		}
		return $templates[$folder][$type];
	}


	/**
	 * Load XML file of the layout type and nameand filter / validate layout parameters
	 * by creating a JForm object and loading in it the layout XML file
	 *
	 * @param array $data      This is an array of the form data that contains an array 'layouts'
	 * @param object $layout   An object with layout options
	 *   string $layout->type  Layout type : 'item' OR 'category'
	 *   string $layout->name  Layout (template) name
	 *   string $layout->fset  Layout 's fieldset name inside layout's XML file
	 *
	 * @access public
	 * @return object
	 */
	static function validateLayoutData($raw_data, $params, $layout = array('type'=>'item', 'name'=>'default', 'fset'=>'attribs', 'cssprep_save' => true))
	{
		$layout_data = array( $layout->fset => array() );
		$layout = !is_object($layout) ? (object) $layout : $layout;

		// Check layout file exists
		$layout->path = JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS. $layout->name .DS. $layout->type .'.xml');
		if ( !$layout->path || !file_exists($layout->path) )
		{
			return $layout_data;
		}

		// Attempt to parse the XML file
		$xml = simplexml_load_file($layout->path);
		if (!$xml)
		{
			JFactory::getApplication()->enqueueMessage('Error parsing layout file of "' . $layout->name . '". Layout parameters were not saved', 'warning');
			return $layout_data;
		}

		// Create form object and load the relevant xml file
		$jform = new JForm('com_flexicontent.template.' . $layout->type, array('control' => 'jform', 'load_data' => false));
		$tmpl_params = $xml->asXML();
		$jform->load($tmpl_params);

		// For not-set layout fields not set, use layout data from DB
		// this will allow displaying only some of the layout fields into the form
		$layout_data[$layout->fset] = $raw_data;
		foreach ($jform->getGroup($layout->fset) as $field)
		{
			if (!$layout->cssprep_save && $field->getAttribute('cssprep'))
			{
				$jform->setFieldAttribute($field->fieldname, 'filter', 'unset', $layout->fset);
				$jform->setFieldAttribute($field->fieldname, 'required', 'false', $layout->fset);
			}

			//echo $field->fieldname  . ' -- filter :: '. $field->getAttribute('filter', ' ... noHTML') . "<br/>";
			$layout_data[$layout->fset][$field->fieldname] = isset($layout_data[$layout->fset][$field->fieldname])
				? $layout_data[$layout->fset][$field->fieldname]
				: $params->get($field->fieldname);
		}

		// Filter and validate the resulting data
		$layout_data = $jform->filter($layout_data);
		$isValid = $jform->validate($layout_data, $layout->fset);

		if (!$isValid)
		{
			JFactory::getApplication()->enqueueMessage('Skipped saving of layout parameters. <br/> Error during their validation (invalid field value or required field value missing).', 'warning');
		}

		return $layout_data[$layout->fset];
	}

	static function mergeLayoutParams(&$record, &$data, $options)
	{
		$params_fset = $options['params_fset'];
		if (!isset($data[$params_fset]))
		{
			return;
		}

		// Layout variables
		$layout_type  = $options['layout_type'];
		$layout_param = $layout_type == 'item' ? 'ilayout' : 'clayout';
		$layout_name  = isset($data[$params_fset][$layout_param]) ? $data[$params_fset][$layout_param] : null;
		$cssprep_save = isset($options['cssprep_save']) ? $options['cssprep_save'] : true;

		// If no layout name , it means use layout data from "parent" e.g. item type or parent categories, just return empty array to clear all layout data
		if (!$layout_name)
		{
		 return array();
		}

		// Get a registry out of record parameters
		$record_params = is_object($record->$params_fset)
			? clone($record->$params_fset)
			: new JRegistry($record->$params_fset);
		$layout_data = isset($data[$params_fset]['layouts'][$layout_name])
			? $data[$params_fset]['layouts'][$layout_name]
			: array();

		// Validate and return the layout field data. NOTE: new layout data are merged into existing layout data, and then all together are validated)
		$layout = (object) array(
			'type' => $layout_type,
			'name' => $layout_name,
			'fset' => 'attribs',
			'cssprep_save' => $cssprep_save,
		);
		return flexicontent_tmpl::validateLayoutData($layout_data, $record_params, $layout);
	}
}