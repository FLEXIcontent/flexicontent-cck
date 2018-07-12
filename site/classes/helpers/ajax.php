<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

class flexicontent_ajax
{
	static function call_extfunc()
	{
		$app     = JFactory::getApplication();
		$jinput  = $app->input;

		// Prevent the url from being indexed
		$app->setHeader('X-Robots-Tag', 'noindex');

		// Set content type if format is JSON
		$format = $jinput->get('format', '', 'cmd');
		if ($format === 'json')
		{
			$app->setHeader('Content-Type', 'application/json');
		}

		$exttype = $jinput->get('exttype', 'modules', 'cmd');
		$extname = $jinput->get('extname', '', 'cmd');
		$extfunc = $jinput->get('extfunc', '', 'cmd');
		$extfolder = $jinput->get('extfolder', '', 'cmd');

		if ($exttype!='modules' && $exttype!='plugins') { echo 'only modules and plugins are supported'; jexit(); }  // currently supporting only module and plugins
		if (!$extname || !$extfunc) { echo 'function or extension name not set'; jexit(); }  // require variable not set
		if ($exttype=='plugins' && $extfolder=='') { echo 'plugin folder is not set'; jexit(); }  // currently supporting only module and plugins		

		if ($exttype=='modules')
		{
			// Import module helper file
			$helper_path = JPath::clean(JPATH_SITE.DS.$exttype.DS.'mod_'.$extname.DS.'helper.php');
			if ( !file_exists($helper_path) )
			{
				jexit("no helper file found at expected path, filepath is ".$helper_path);
			}
			require_once ($helper_path);

			// Create object
			$className = 'mod'.ucwords($extname).'Helper';
			if ( !class_exists($className) )
			{
				jexit("no correctly named class inside helper file");
			}
			$obj = new $className();
		}

		else  // exttype is 'plugins'
		{
			// Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = DS.strtolower($extname);
			$path = JPATH_ROOT.DS.'plugins'.DS.$extfolder.$plgfolder.DS.strtolower($extname).'.php';
			if ( !file_exists($path) )
			{
				jexit("no plugin file found at expected path, filepath is ".$path);
			}
			require_once ($path);

			// Create class name of the plugin
			$className = 'plg'. ucfirst($extfolder).$extname;
			if (!class_exists($className))
			{
				jexit("no correctly named class inside plugin file");
			}

			// Create a plugin instance, also pass the parameters so that $this->params are created too
			$dispatcher = JEventDispatcher::getInstance();
			$plg_db_data = JPluginHelper::getPlugin($extfolder, $extname);
			$obj = new $className($dispatcher, array('type'=>$extfolder, 'name'=>$extname, 'params'=>$plg_db_data->params));
		}

		// Security concern, only 'confirmed' methods will be callable
		if ( empty($obj->task_callable) || !in_array($extfunc, $obj->task_callable) )
		{
			jexit("non-allowed method called");
		}

		// Method actually exists
		if ( !method_exists($obj, $extfunc) )
		{
			jexit("non-existing method called ");
		}

		// Load extension's english language file then override with current language file
		if ($exttype=='modules')
			$extension_name = 'mod_'.strtolower($extname);
		else
			$extension_name = 'plg_'.strtolower($extname);
		JFactory::getLanguage()->load($extension_name, JPATH_SITE, 'en-GB', true);
		JFactory::getLanguage()->load($extension_name, JPATH_SITE, null, true);
		
		// Call the method
		$obj->$extfunc();
	}
}