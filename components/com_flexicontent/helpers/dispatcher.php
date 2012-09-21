<?php
/**
* @version		$Id: dispatcher.php 14401 2010-01-26 14:10:00Z louis $
* @package		Joomla.Framework
* @subpackage	Event
* @copyright	Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();

jimport('joomla.event.dispatcher');

/**
 * Class to handle dispatching of events.
 *
 * This is the Observable part of the Observer design pattern
 * for the event architecture.
 *
 * @package 	Joomla.Framework
 * @subpackage	Event
 * @since	1.5
 * @see		JPlugin
 * @link http://docs.joomla.org/Tutorial:Plugins Plugin tutorials
 */
class FCDispatcher extends JDispatcher
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
		parent::__construct();
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
	 * 		<pre>  $dispatcher = &FCDispatcher::getInstance();</pre>
	 *
	 * @access	public
	 * @return	FCDispatcher	The EventDispatcher object.
	 * @since	1.5
	 */
	function & getInstance_FC($debug)
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
	function findPrepContFuncs($plugin)
	{
		$plugin->type = preg_replace('/[^A-Z0-9_\.-]/i', '', $plugin->type);
		$plugin->name  = preg_replace('/[^A-Z0-9_\.-]/i', '', $plugin->name);

		if ($this->debug) echo $plugin->name;
		
		$path	= JPATH_PLUGINS.DS.$plugin->type.DS.$plugin->name.'.php';
		$plugin_code = file_get_contents($path);
		$fname_pattern='[\s]*[\'"]([a-zA-Z]+)[\'"][\s]*';
		
		if ( preg_match_all('/->registerEvent[\s]*\('.$fname_pattern.','.$fname_pattern.'\)/', $plugin_code, $matches) )
		{
			foreach($matches[1] as $i => $event) {
				if ($event=='onPrepareContent') {
					$this->prepContentFuncs[$matches[2][$i]] = $plugin->name;
					if ($this->debug) echo " ==> ".$matches[2][$i];
				}
			}
		}
		if ($this->debug) echo "<br>";
	}


	/**
	 * Registers an event handler to the event dispatcher
	 *
	 * @access	public
	 * @param	string	$event		Name of the event to register handler for
	 * @param	string	$handler	Name of the event handler
	 * @return	void
	 * @since	1.5
	 */
	function register($event, $handler)
	{
		// Are we dealing with a class or function type handler?
		if (function_exists($handler))
		{
			// Ok, function type event handler... lets attach it.
			$method = array ('event' => $event, 'handler' => $handler);
			$this->attach($method);
		}
		elseif (class_exists($handler))
		{
			 //Ok, class type event handler... lets instantiate and attach it.
			$this->attach(new $handler($this));
		}
		else
		{
			JError::raiseWarning('SOME_ERROR_CODE', 'JDispatcher::register: Event handler not recognized.', 'Handler: '.$handler );
		}
	}

	/**
	 * Triggers an event by dispatching arguments to all observers that handle
	 * the event and returning their return values.
	 *
	 * @access	public
	 * @param	string	$event			The event name
	 * @param	array	$args			An array of arguments
	 * @param	boolean	$doUnpublished	[DEPRECATED]
	 * @return	array	An array of results from each function call
	 * @since	1.5
	 */
	function trigger($event, $args = null, $doUnpublished = false, $plg_names=null)
	{
		if ($this->debug) {
			echo $plg_names ? "(".implode(',', $plg_names).")<br>" : "(ALL)<br>";
		}
		
		// Initialize variables
		$result = array ();

		/*
		 * If no arguments were passed, we still need to pass an empty array to
		 * the call_user_func_array function.
		 */
		if ($args === null) {
			$args = array ();
		}

		/*
		 * We need to iterate through all of the registered observers and
		 * trigger the event for each observer that handles the event.
		 */
		foreach ($this->getInstance()->_observers as $observer)
		{
			if (is_array($observer))
			{
				/*
				 * Since we have gotten here, we know a little something about
				 * the observer.  It is a function type observer... lets see if
				 * it handles our event.
				 */
				if ($observer['event'] == $event)
				{
					if (function_exists($observer['handler']))
					{
						// Check for selective plugin triggering
						if ( $plg_names && !in_array(@$this->prepContentFuncs[ $observer['handler'] ], $plg_names) ) {
							continue;
						}
						if ($this->debug) {
							echo @$this->prepContentFuncs[ $observer['handler'] ] ?
								$this->prepContentFuncs[ $observer['handler'] ]."<br>" : $observer['handler']."<br>";
						}
						
						$result[] = call_user_func_array($observer['handler'], $args);
					}
					else
					{
						/*
						 * Couldn't find the function that the observer specified..
						 * wierd, lets throw an error.
						 */
						JError::raiseWarning('SOME_ERROR_CODE', 'JDispatcher::trigger: Event Handler Method does not exist.', 'Method called: '.$observer['handler']);
					}
				}
				else
				{
					 // Handler doesn't handle this event, move on to next observer.
					continue;
				}
			}
			elseif (is_object($observer))
			{
				/*
				 * Since we have gotten here, we know a little something about
				 * the observer.  It is a class type observer... lets see if it
				 * is an object which has an update method.
				 */
				if (method_exists($observer, 'update'))
				{
					/*
					 * Ok, now we know that the observer is both not an array
					 * and IS an object.  Lets trigger its update method if it
					 * handles the event and return any results.
					 */
					if (method_exists($observer, $event))
					{
						// Check for selective plugin triggering
						$curplg = strtolower(str_ireplace('plgContent', '', get_class($observer)));
						if ( $plg_names && !in_array($curplg, $plg_names) ) {
							continue;
						}
						if ($this->debug) echo $curplg."<br>";
						
						$args['event'] = $event;
						$result[] = $observer->update($args);
					}
					else
					{
						/*
						 * Handler doesn't handle this event, move on to next
						 * observer.
						 */
						continue;
					}
				}
				else
				{
					/*
					 * At this point, we know that the registered observer is
					 * neither a function type observer nor an object type
					 * observer.  PROBLEM, lets throw an error.
					 */
					JError::raiseWarning('SOME_ERROR_CODE', 'JDispatcher::trigger: Unknown Event Handler.', $observer );
				}
			}
		}
		return $result;
	}
}
