<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

class flexicontent_favs
{
	static $fcfavs = null;
	static $types = array('item' => 0, 'category' => 1);

	/**
	 * Method to load Favourites from cookie
	 *
	 * @access public
	 *
	 * @return void
	 *
	 * @since 3.2.0
	 */
	static function loadCookieFavs()
	{
		// If not already loaded
		if (self::$fcfavs)
		{
			return;
		}
		self::$fcfavs = JFactory::getApplication()->input->cookie->get('fcfavs', '{}', 'string');

		// Parse the favourites
		try
		{
			self::$fcfavs = json_decode(self::$fcfavs);
		}
		catch (Exception $e)
		{
			$jcookie->set('fcfavs', '{}');
		}

		// Make sure it is a class
		if (!self::$fcfavs)
		{
			self::$fcfavs = new stdClass();
		}

		// Convert data to array and disgard not known types
		foreach(self::$fcfavs as $type => $id_arr)
		{
			if (isset(self::$types[$type]))
			{
				self::$fcfavs->$type = (array)$id_arr;
				continue;
			}

			unset (self::$fcfavs->$type);
		}

		// Validate data of each type as integers
		foreach(self::$types as $type => $i)
		{
			$arr = array();
			if (!isset(self::$fcfavs->$type))
			{
				self::$fcfavs->$type = array();
			}

			foreach(self::$fcfavs->$type as $id => $i)
			{
				$id = (int) $id;
				$arr[$id] = 1;
			}
			self::$fcfavs->$type = $arr;
		}
	}


	/**
	 * Method to get Favourites from cookie all or of specific type
	 *
	 * @access public
	 * @param  $type    The type of favourites
	 * @return void
	 *
	 * @since 3.2.0
	 */
	static function getCookieFavs($type)
	{
		flexicontent_favs::loadCookieFavs();

		return $type ? self::$fcfavs->$type : self::$fcfavs;
	}


	/**
	 * Method to save Favourites into cookie
	 *
	 * @access public
	 * @return void
	 *
	 * @since 3.2.0
	 */
	static function saveCookieFavs()
	{
		flexicontent_favs::loadCookieFavs();

		$app = JFactory::getApplication();
		$jcookie = $app->input->cookie;

		// Clear any cookie set to current path, and set cookie at top-level folder of current joomla installation
		$jcookie->set('fcfavs', null, 1, '', '');
		$jcookie->set('fcfavs', json_encode(self::$fcfavs), time()+60*60*24*(365*5), JURI::base(true), '');
	}


	/**
	 * Method to toggle Favourites FLAG form a given $type / $id pair
	 *
	 * @access public
	 * @param  $type    The type of favourites
	 * @param  $id      The ID of a record
	 * @return void
	 *
	 * @since 3.2.0
	 */
	static function toggleCookieFav($type, $id)
	{
		flexicontent_favs::loadCookieFavs();

		if (isset(self::$fcfavs->$type[$id]))
		{
			unset(self::$fcfavs->$type[$id]);
			return -1;
		}
		else
		{
			self::$fcfavs->$type[$id] = 1;
			return 1;
		}
	}
}

