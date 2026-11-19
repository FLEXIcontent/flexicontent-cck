<?php
// no direct access
defined('_JEXEC') or die('Restricted access');

// Frontend proxy for the RAW items controller (version table Ajax, permanent backup toggle).
// As with the other site controllers, include the backend file directly.
require_once(JPATH_ROOT . DS . 'administrator' . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'items.raw.php');
