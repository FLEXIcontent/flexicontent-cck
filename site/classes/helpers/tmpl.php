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
	static function parseTemplates_checked($tmpldir='', $unchanged_tmpls = null)
	{
		// Return cached data
		static $tmpls = null;
		
		// Set 'unchanged' layouts and return (this avoid reparsing on subsequent call)
		if ( !empty($unchanged_tmpls) )
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
				$doc = @simplexml_load_file($tmplxml);
				if (!$doc)
				{
					if (JFactory::getApplication()->isAdmin()) JFactory::getApplication()->enqueueMessage('Syntax error(s) in template XML file: '. $tmplxml, 'notice');
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
			$tmpls = $tmplcache->call(array('flexicontent_tmpl', 'parseTemplates_checked'), $tmpldir);
			
			$folder_names = array_flip( flexicontent_tmpl::getThemes($tmpldir) );
			
			$tmpl_names = array();
			foreach($tmpls->category as $tmpl) $tmpl_names[$tmpl->name] = 1;
			foreach($tmpls->items as $tmpl) $tmpl_names[$tmpl->name] = 1;
			
			$new_layouts = array();
			foreach($folder_names as $folder_name => $i) if ( !isset($tmpl_names[$folder_name]) )  $new_layouts[] = $folder_name;
			//print_r($new_layouts);
			
			$deleted_layouts = array();
			foreach($tmpl_names as $tmpl_name => $i) if ( !isset($folder_names[$tmpl_name]) ) {
				unset( $tmpls->items->{$tmpl_name} );
				unset( $tmpls->category->{$tmpl_name} );
				$deleted_layouts[] = $tmpl_name;
			}
			//print_r($deleted_layouts);
			
			// Check for modified XML files, cleaning and updating cache only for modified templates
			$modified = array();
			if ( !empty($checked_layouts) || $force )
			{
				$modified = flexicontent_tmpl::checkXmlModified($tmpls, $checked_layouts);
				$modified_file_list = '';
				// Unset modified templates
				if ( !empty($modified) ) foreach($tmpls as $layout_type => $_tmpls) foreach($_tmpls as $tmpl) {
					if ( !isset($modified[$tmpl->name][$layout_type]) )  continue;
					unset( $tmpls->$layout_type->{$tmpl->name} );
					$modified_file_list .= '<br/>'.$modified[$tmpl->name][$layout_type];
				}
			}
			
			if ( !empty($modified) || !empty($new_layouts) || !empty($deleted_layouts) )
			{
				flexicontent_tmpl::parseTemplates_checked($tmpldir, $tmpls);   // This call only set unchanged templates so that they are not reparsed
				
				if ($debug && !empty($modified) )        JFactory::getApplication()->enqueueMessage("Re-parsing XMLs, XML file modified: ".$modified_file_list, 'message');
				if ($debug && !empty($new_layouts) )     JFactory::getApplication()->enqueueMessage("Parsing new templates: ".implode(', ', $new_layouts), 'message');
				if ($debug && !empty($deleted_layouts) ) JFactory::getApplication()->enqueueMessage("Cleaned cache from deleted templates: ".implode(', ', $deleted_layouts), 'message');
				
				// Clean and update caching re-parsing only new or changed XML files
				$tmplcache->clean();
				$tmplcache->gc();
				$tmpls = $tmplcache->call(array('flexicontent_tmpl', 'parseTemplates_checked'), $tmpldir);
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
			$layout_texts = $tmplcache->call(array('flexicontent_tmpl', '_getLayoutTexts'), $layout_typename);
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
		
		$db = JFactory::getDBO();
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
			$db = JFactory::getDBO();
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
	static function validateLayoutData($data, $layout=array('type'=>'item', 'name'=>'default', 'fset'=>'attribs'))
	{
		$layout = !is_object($layout) ? (object) $layout : $layout;

		$layout->path = !$layout->name ? '' : JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS. $layout->name .DS. $layout->type .'.xml');
		if ($layout->path && !file_exists($layout->path))
		{
			$layout->path = '';
		}

		$layout_data = array();

		if ( $layout->path && isset($data['layouts'][$layout->name]) )
		{
			// Attempt to parse the XML file
			$xml = simplexml_load_file($layout->path);
			if (!$xml)
			{
				JFactory::getApplication()->enqueueMessage('Error parsing layout file of "' . $layout->name . '". Layout parameters were not saved', 'warning');
			}

			else
			{
				// Create form object and load the relevant xml file
				$jform = new JForm('com_flexicontent.template.' . $layout->type, array('control' => 'jform', 'load_data' => false));
				$tmpl_params = $xml->asXML();
				$jform->load($tmpl_params);

				$layout_data[$layout->fset] = $data['layouts'][$layout->name];
				//foreach ($jform->getGroup($layout->fset) as $field) echo $field->fieldname  . ' -- filter :: '. $field->getAttribute('filter', ' ... noHTML') . "<br/>";

				// Filter and validate the resulting data
				$layout_data = $jform->filter($layout_data);  //echo "<pre>"; print_r($layout_data); echo "</pre>"; exit();
				$isValid = $jform->validate($layout_data, $layout->fset);
				if (!$isValid)
				{
					JFactory::getApplication()->enqueueMessage('Error validating layout parameters. Layout parameters were not saved', 'warning');
				}
			}
		}

		return $layout_data;
	}

	static function mergeLayoutParams(&$item, &$data, $options)
	{
		$params_fset = $options['params_fset'];
		if (!isset($data[$params_fset]))
		{
			return;
		}

		// Create Registry object from parameters string
		$item->$params_fset = new JRegistry($item->$params_fset);

		// Layout variables
		$layout_type = $options['layout_type'];
		$tmpl_type = $layout_type == 'item' ? 'items' : 'category';

		$layout_param = $layout_type == 'item' ? 'ilayout' : 'clayout';
		$new_layout = isset($data[$params_fset][$layout_param]) ? $data[$params_fset][$layout_param] : null;
		$old_layout = $item->$params_fset->get($layout_param);


		// Verify layout file exists
		$layoutpath = !$new_layout ? '' : JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$new_layout.DS.$layout_type.'.xml');
		if ($layoutpath && !file_exists($layoutpath))
		{
			$layoutpath = '';
		}


		// ***
		// *** We will only clear old layout and new layout parameters, since
		// *** clearing all layout in sites with many templates would be costly
		// ***

		$themes = flexicontent_tmpl::getTemplates();
		$clear_arr = array();

		// Clear parameters of old ilayout, to allow proper heritage from content type, parent categories / global layout parameters, when:
		//  (a) non-NULL ** but empty new ilayout (aka use 'type default'), (b) old ilayout was non empty
		// WARNING: ** NULL ilayout means layout was not present in the FORM, (user could not change it) aka do not clear parameters

		//echo "<pre>"; var_dump($layoutpath); var_dump($new_layout); var_dump($old_layout);
		if ($new_layout!==null && $new_layout=='' && !empty($old_layout) && isset($themes->$tmpl_type->$old_layout))
		{
			$clear_arr[$old_layout] = $themes->$tmpl_type->$old_layout;  //echo 'Clear old layout: ' . $old_layout . '<br/>';
		}

		// Detect that layout was changed to new value and clear new layout parameters
		// This is useful in the case they were not added in the form,
		// so that heritage from type's defaults / parent categories / global layout parameters will take effect
		// (a) New ilayout non empty and (b) it is different than old ilayout, 
		if ($layoutpath && $new_layout && $new_layout!=$old_layout && isset($themes->$tmpl_type->$new_layout))
		{
			$clear_arr[$new_layout] = $themes->$tmpl_type->$new_layout;  //echo 'Clear new layout: ' . $new_layout . '<br/>';
		}

		if ($clear_arr)
		{
			//JFactory::getApplication()->enqueueMessage('Layout changed, cleared old layout parameters', 'message');
			foreach ($clear_arr as $tmpl_name => $tmpl)
			{
				$tmpl_params = $tmpl->params;
				$jform = new JForm('com_flexicontent.template.' . $layout_type, array('control' => 'jform', 'load_data' => false));
				$jform->load($tmpl_params);
				foreach ($jform->getGroup('attribs') as $field)
				{
					// !! Do not call empty() on a variable created by magic __get function
					if ( @ $field->fieldname )
					{
						$item->$params_fset->set($field->fieldname, null);  //echo "Clearing: " . $field->fieldname . " <br/>";
					}
				}
			}
		}


		// ***
		// *** Validate layout data
		// ***
		if ($new_layout)
		{
			$layout = (object) array('type'=>$layout_type, 'name'=>$new_layout, 'fset'=>'attribs');
			$layout_data = flexicontent_tmpl::validateLayoutData($data[$params_fset], $layout);

			// Merge the parameters of the selected layout (if not empty)
			if ( !empty($layout_data[$layout->fset]) )
			{
				foreach ($layout_data[$layout->fset] as $k => $v)
				{
					$item->$params_fset->set($k, $v);  //echo "$k: $v <br/>";
				}
			}
		}
		$item->$params_fset = $item->$params_fset->toString();
	}
}