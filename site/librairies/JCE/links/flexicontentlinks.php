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
 */
defined( '_WF_EXT' ) or die( 'RESTRICTED' );

class WFLinkBrowser_Flexicontentlinks {

	var $_option = array();
	var $_adapters = array();

	/**
	* Constructor activating the default information of the class
	*
	* @access	protected
	*/
	public function __construct($options = array()) {
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');

		$path = dirname( __FILE__ ) . '/flexicontentlinks';

		// Get all files
		$files = JFolder::files($path, '\.(php)$');

		if (!empty($files)) {
			foreach ($files as $file) {
				$name = basename($file, '.php');

				// optionally skip some view e.g. 'reviews' if it doesn't exist!
				if ($name === "reviews" && !is_dir(JPATH_SITE . '/components/com_flexicontent/views/reviews/')) {
					continue;
				}

				require_once( $path . '/' . $file );

				$classname = 'Flexicontentlinks' . ucfirst(basename($file, '.php'));

				if (class_exists($classname)) {
					$this->_adapters[] = new $classname;
				}
			}
		}
	}

	public function display() {
		// Load css
		$document = WFDocument::getInstance();
		$document->addStyleSheet(array('flexicontentlinks'), 'extensions/links/flexicontentlinks/css');
	}

	public function isEnabled() {
		$wf = WFEditorPlugin::getInstance();
		return $wf->checkAccess($wf->getName() . '.links.flexicontentlinks.enable', 1);
	}

	public function getOption() {
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
				return $adapter->getLinks($args);
			}
		}
	}

}

?>