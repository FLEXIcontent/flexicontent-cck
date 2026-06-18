<?php
/**
 * @version 1.5 stable $Id: view.html.php 1900 2014-05-03 07:25:51Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('legacy.view.legacy');

/**
 * HTML View class for the FLEXIcontent View
 */
class FlexicontentViewFlexicontent extends \Joomla\CMS\MVC\View\HtmlView
{
	/**
	 * Creates the Entrypage
	 *
	 * @since 1.0
	 */
	function display($tpl = null)
	{
		$app    = \Joomla\CMS\Factory::getApplication();
		$params = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');

		// Special displaying when getting flexicontent version
		$layout = $app->input->getString('layout', '');

		if ($layout == 'fversion')
		{
			$this->fversion($params);
		}

		// Raw output
		parent::display($tpl);
	}


	/**
	 * Fetch stable and beta releases from GitHub API
	 * Returns array with keys: connect, current_version, current_creationDate,
	 *   stable_version, stable_released, stable_current,
	 *   beta_version, beta_released, beta_current,
	 *   channel
	 */
	static function getUpdateComponent()
	{
		// Read installation manifest
		$manifest_path = JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'flexicontent.xml';
		$com_xml = \Joomla\CMS\Installer\Installer::parseXMLInstallFile($manifest_path);

		$check = array();
		$check['connect']              = 0;
		$check['current_version']      = $com_xml['version'];
		$check['current_creationDate'] = $com_xml['creationDate'];
		$check['channel']              = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent')->get('update_channel', 'stable');

		// GitHub API — last 10 releases (enough to find latest stable + latest beta)
		$url  = 'https://api.github.com/repos/FLEXIcontent/flexicontent-cck/releases?per_page=10';
		$data = '';

		$ctx = stream_context_create([
			'http' => [
				'header'  => "User-Agent: FLEXIcontent-dashboard\r\n",
				'timeout' => 5,
			],
			'ssl' => [
				'verify_peer'      => true,
				'verify_peer_name' => true,
			],
		]);

		// Try cURL first
		if (function_exists('curl_init') && function_exists('curl_exec'))
		{
			$ch = @curl_init();
			@curl_setopt($ch, CURLOPT_URL, $url);
			@curl_setopt($ch, CURLOPT_HEADER, 0);
			@curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			@curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			@curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			@curl_setopt($ch, CURLOPT_USERAGENT, 'FLEXIcontent-dashboard');
			@curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			$data = @curl_exec($ch);
			@curl_close($ch);
		}

		// Fallback: fopen
		if (empty($data) && function_exists('fopen') && ini_get('allow_url_fopen'))
		{
			$data = @file_get_contents($url, false, $ctx);
		}

		if (empty($data))
		{
			return $check;
		}

		$releases = @json_decode($data, true);

		if (!is_array($releases) || empty($releases))
		{
			return $check;
		}

		$check['connect'] = 1;

		// Find latest stable and latest beta
		$stable = null;
		$beta   = null;

		foreach ($releases as $r)
		{
			if ($r['draft'])
			{
				continue;
			}

			if (!$r['prerelease'] && $stable === null)
			{
				$stable = $r;
			}

			if ($r['prerelease'] && $beta === null)
			{
				$beta = $r;
			}

			if ($stable !== null && $beta !== null)
			{
				break;
			}
		}

		$installed = str_replace(' ', '', $check['current_version']);

		// Stable info
		if ($stable)
		{
			$sv = ltrim($stable['tag_name'], 'v');
			$check['stable_version']  = $sv;
			$check['stable_released'] = substr($stable['published_at'], 0, 10);
			$check['stable_current']  = version_compare($installed, str_replace(' ', '', $sv));
		}

		// Beta info
		if ($beta)
		{
			$bv = ltrim($beta['tag_name'], 'v');
			$check['beta_version']  = $bv;
			$check['beta_released'] = substr($beta['published_at'], 0, 10);
			$check['beta_current']  = version_compare($installed, str_replace(' ', '', $bv));
		}

		return $check;
	}


	/**
	 * Prepare version check data and trigger Joomla update site reset if needed
	 */
	function fversion($params)
	{
		// Cache GitHub API call for 1 hour
		$cache = \Joomla\CMS\Factory::getCache('com_flexicontent');
		$cache->setCaching(1);
		$cache->setLifeTime(3600);
		$check = $cache->get(array('FlexicontentViewFlexicontent', 'getUpdateComponent'), array());

		$this->check = $check;
	}
}
