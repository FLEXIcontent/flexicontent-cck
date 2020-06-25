<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Event
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

// Check to ensure this file is within the rest of the framework
defined('JPATH_PLATFORM') or die;
jimport('joomla.event.dispatcher');


/**
 * Class to handle dispatching of events.
 *
 * This is the Observable part of the Observer design pattern
 * for the event architecture.
 *
 * @package     Joomla.Platform
 * @subpackage  Event
 * @link        http://docs.joomla.org/Tutorial:Plugins Plugin tutorials
 * @see         JPlugin
 * @since       11.1
 */
class FCDispatcher extends JEventDispatcher
{
	protected $prepContentFuncs = null;
	protected $debug = false;
	
	/**
	 * Constructor
	 *
	 * @access	protected
	 */
	function __construct( $debug=false )
	{
		if (!FLEXI_J40GE)
		{
			parent::__construct();
		}

		$this->debug = $debug;
		
		if ( !$this->prepContentFuncs ) {
			$plgs = JPluginHelper::getPlugin('content');
			$this->prepContentFuncs = array();
			
			if ($this->debug) {
				echo "<b>Finding custom method names for content events</b>:<br>";
			}
			
			foreach ($plgs as $plg) {
				$content_plgs[] = $plg->name;
				$this->findPrepContFuncs($plg);
			}
		}
	}
	
	
	/**
	 * Returns a reference to the global Event FC Dispatcher object,
	 * only creating it, if it doesn't already exist.
	 *
	 * This method must be invoked as:
	 *  $dispatcher = FCDispatcher::getInstance();
	 *
	 * @access	public
	 * @return	FCDispatcher	The EventDispatcher object.
	 * @since	1.5
	 */
	public static function &getInstance_FC($debug)
	{
		static $instance;

		if (!is_object($instance)) {
			$instance = new FCDispatcher($debug);
		}

		return $instance;
	}
	
	
	/**
	 * Find custom method names for content events
	 *
	 */
	protected function findPrepContFuncs($plugin)
	{
		$plugin->type = preg_replace('/[^A-Z0-9_\.-]/i', '', $plugin->type);
		$plugin->name  = preg_replace('/[^A-Z0-9_\.-]/i', '', $plugin->name);

		if ($this->debug) echo $plugin->name;
		
		$path	= JPATH_PLUGINS.DS.$plugin->type.DS.$plugin->name.DS.$plugin->name.'.php';
		if (!file_exists($path)) return false;
		$plugin_code = file_get_contents($path);
		$fname_pattern='[\s]*[\'"]([^\'"]+)[\'"][\s]*';
		
		if ( preg_match_all('/->registerEvent[\s]*\('.$fname_pattern.','.$fname_pattern.'\)/', $plugin_code, $matches) )
		{
			foreach($matches[1] as $i => $event) {
				if ($event=='onContentPrepare') {
					$this->prepContentFuncs[$matches[2][$i]] = $plugin->name;
					if ($this->debug) echo " ==> ".$matches[2][$i];
				}
			}
		}
		if ($this->debug) echo "<br>";
	}
	
	
	/**
	 * Triggers an event by dispatching arguments to all observers that handle
	 * the event and returning their return values. This overriden function allows selective triggering
	 *
	 * @param   string  $event  The event to trigger.
	 * @param   array   $args   An array of arguments.
	 *
	 * @return  array  An array of results from each function call.
	 *
	 * @since   11.1
	 */
	public function trigger($event, $args = array(), $plg_names=null)
	{
		if ($this->debug) {
			echo $plg_names ? "(".implode(',', $plg_names).")<br>" : "(ALL)<br>";
		}
		
		// Initialise variables.
		$result = array();

		/*
		 * If no arguments were passed, we still need to pass an empty array to
		 * the call_user_func_array function.
		 */
		$args = (array) $args;

		$event = strtolower($event);
		
		// Get static properties to use from JEventDispatcher class
		$this->_methods   = & $this->getInstance()->_methods;
		$this->_observers = & $this->getInstance()->_observers;

		// Check if any plugins are attached to the event.
		if (!isset($this->_methods[$event]) || empty($this->_methods[$event]))
		{
			// No Plugins Associated To Event!
			return $result;
		}
		// Loop through all plugins having a method matching our event
		foreach ($this->_methods[$event] as $key)
		{
			// Check if the plugin is present.
			if (!isset($this->_observers[$key]))
			{
				continue;
			}

			// Fire the event for an object based observer.
			if (is_object($this->_observers[$key]))
			{
				// Check for selective plugin triggering
				$cname = get_class($this->_observers[$key]);
				$cname = strtolower(str_ireplace('plgContent', '', $cname));
				if ( $plg_names && !in_array($cname, $plg_names) )
				{
					continue;
				}
				if ($this->debug) {
					echo $this->_observers[$key]->get('_name')."<br>";
				}
				
				$args['event'] = $event;
				$value = $this->_observers[$key]->update($args);
			}
			// Fire the event for a function based observer.
			elseif (is_array($this->_observers[$key]))
			{
				// Check for selective plugin triggering
				if ( $plg_names && !in_array(@$this->prepContentFuncs[ $this->_observers[$key]['handler'] ], $plg_names) ) {
					continue;
				}
				if ($this->debug) {
					echo @$this->prepContentFuncs[ $this->_observers[$key]['handler'] ] ?
						$this->prepContentFuncs[ $this->_observers[$key]['handler'] ] ."<br>" : $this->_observers[$key]['handler'] ."<br>";
				}
				
				$value = call_user_func_array($this->_observers[$key]['handler'], $args);
			}
			if (isset($value))
			{
				$result[] = $value;
			}
		}

		return $result;
	}
	
}
