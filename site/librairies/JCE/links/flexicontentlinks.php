<?php

/**
 * @package   	JCE
 * @copyright 	Copyright (C) 2014 FLEXIcontent project. All rights reserved.
 * @license   	GNU/GPL 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @author     	Emmanuel Dannan, Ryan Demmer, ggppdk
 *
 * Flexicontentlinks is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 *
 * Based on "joomlalinks" found in JCE's core distribution, by Emmanuel Dannan, Ryan Demmer
 *
 * The link browser of FLEXIcontent for the link dialog of JCE 2.9 and of JCE 3. Both load it through the
 * plugin "links_flexicontentlinks" of the "jce" plugin group (see ../links_flexicontentlinks), and both call
 * the same methods: display(), getOption(), getList() and getLinks(). JCE 2.9 also asks isEnabled().
 * This file stays in the component, so the browser and its adapters are updated together with FLEXIcontent.
 */
defined( '_JEXEC' ) or die( 'RESTRICTED' );

class WFLinkBrowser_Flexicontentlinks {

	// Folder of the adapters and of their stylesheet, below the site root
	const FOLDER = 'components/com_flexicontent/librairies/JCE/links/flexicontentlinks';

	var $_option = array();
	var $_adapters = array();

	// The link dialog, given by JCE 3 only
	protected $_container = null;

	/**
	* Constructor activating the default information of the class
	*
	* JCE 2.9 creates the link browser without arguments, JCE 3 passes its configuration and the link dialog
	*
	* @access	protected
	*/
	public function __construct($options = array(), $container = null) {
		$this->_container = $container;

		// JCE 3 does not ask isEnabled(), JCE 2.9 does it after the adapters have been loaded
		if (!$this->isEnabled()) {
			return;
		}

		// The titles of the lists are in the language file of the FLEXIcontent system plugin. JCE gives its
		// dialogs a new language object, which does not have what that plugin has loaded
		\Joomla\CMS\Factory::getApplication()->getLanguage()->load('plg_system_flexisystem', JPATH_ADMINISTRATOR);

		$path = __DIR__ . '/flexicontentlinks';

		// Get all files
		$files = glob($path . '/*.php');

		if (!empty($files)) {
			foreach ($files as $file) {
				$name = basename($file, '.php');

				// optionally skip some view e.g. 'reviews' if it doesn't exist!
				if ($name === "reviews" && !is_dir(JPATH_SITE . '/components/com_flexicontent/views/reviews/')) {
					continue;
				}

				require_once( $file );

				$classname = 'Flexicontentlinks' . ucfirst($name);

				if (class_exists($classname)) {
					$this->_adapters[] = new $classname;
				}
			}
		}
	}

	public function display() {
		// Load css
		if ($this->_container && method_exists($this->_container, 'getDocument')) {
			// JCE 3: the document of the link dialog
			$document = $this->_container->getDocument();
		} elseif (class_exists('WFDocument')) {
			// JCE 2.9
			$document = WFDocument::getInstance();
		}

		if (!empty($document)) {
			// "joomla" is the site root in both versions
			$document->addStyleSheet(array(self::FOLDER . '/css/flexicontentlinks'), 'joomla');
		}
	}

	public function isEnabled() {
		$wf = WFEditorPlugin::getInstance();
		return (bool) $wf->checkAccess($wf->getName() . '.links.flexicontentlinks.enable', 1);
	}

	public function getOption() {
		$this->_option = array();

		foreach ($this->_adapters as $adapter) {
			$this->_option[] = $adapter->getOption();
		}
		return $this->_option;
	}

	public function getList() {
		$list = '';

		foreach ($this->_adapters as $adapter) {
			$list .= $adapter->getList();
		}
		return $list;
	}

	public function getLinks($args) {
		foreach ($this->_adapters as $adapter) {
			if ($adapter->getOption() == $args->option) {
				return (array) $adapter->getLinks($args);
			}
		}

		// JCE 3 merges the result into its own list
		return array();
	}


	/**
	 * Content links can be turned off in the editor profile, the lists of items follow that option
	 *
	 * JCE 2.9 has a yes / no option, JCE 3 a list of the Joomla link providers. A profile that was made with
	 * JCE 2.9 keeps its option until it is saved again, JCE 3 reads it in the same order.
	 *
	 * @return  bool
	 */
	public static function contentAllowed() {
		$wf = WFEditorPlugin::getInstance();

		$providers = $wf->getParam('links.joomla.list.providers');

		if (!empty($providers)) {
			$providers = is_string($providers) ? explode(',', $providers) : (array) $providers;

			return in_array('content', array_map('trim', $providers));
		}

		return (bool) $wf->checkAccess('links.joomlalinks.content', 1);
	}


	/**
	 * Make the URL of a link search engine friendly, when the editor profile asks for it
	 *
	 * @param   string  $url
	 *
	 * @return  string
	 */
	public static function route($url) {
		$wf = WFEditorPlugin::getInstance();

		// The option of JCE 3, then the one of JCE 2.9
		if (!$wf->getParam('links.joomla.sef_url', $wf->getParam('links.joomlalinks.sef_url', 0))) {
			return $url;
		}

		if (class_exists('\\Wfe\\Helper\\LinkHelper')) {
			// JCE 3
			return \Wfe\Helper\LinkHelper::route($url);
		}

		if (class_exists('WFLinkHelper')) {
			// JCE 2.9
			return WFLinkHelper::route($url);
		}

		return $url;
	}

}
