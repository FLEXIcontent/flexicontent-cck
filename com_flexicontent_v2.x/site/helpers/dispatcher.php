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
class FCDispatcher extends JDispatcher
{
	/**
	 * Stores the singleton instance of the dispatcher.
	 *
	 * @var    JDispatcher
	 * @since  11.3
	 */
	protected static $fcinstance = null;
	
	
	public static function getInstance()
	{
		if (self::$fcinstance === null)
		{
			self::$fcinstance = new FCDispatcher;
		}

		return self::$fcinstance;
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
		// Initialise variables.
		$result = array();

		/*
		 * If no arguments were passed, we still need to pass an empty array to
		 * the call_user_func_array function.
		 */
		$args = (array) $args;

		$event = strtolower($event);

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
			
			// Check for selective plugin triggering
			if ( $plg_names && !in_array($this->_observers[$key]->get('_name'), $plg_names) ) {
				//echo "<br>".get_class($this->_observers[$key]); 	echo "<br>"; print_r( $this->_observers[$key]->get('_name') );
				continue;
			}

			// Fire the event for an object based observer.
			if (is_object($this->_observers[$key]))
			{
				$args['event'] = $event;
				$value = $this->_observers[$key]->update($args);
			}
			// Fire the event for a function based observer.
			elseif (is_array($this->_observers[$key]))
			{
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
