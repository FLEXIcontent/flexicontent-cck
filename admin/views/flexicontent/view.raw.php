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
class FlexicontentViewFlexicontent extends JViewLegacy
{
	/**
	 * Creates the Entrypage
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		$app      = JFactory::getApplication();
		$config   = JFactory::getConfig();
		$params   = JComponentHelper::getParams('com_flexicontent');
		$document	= JFactory::getDocument();
		$session  = JFactory::getSession();
		$user     = JFactory::getUser();		
		$db       = JFactory::getDbo();
		$print_logging_info = $params->get('print_logging_info');
		
		// Special displaying when getting flexicontent version
		$layout = $app->input->getString('layout', '');

		if ($layout=='fversion')
		{
			$this->fversion($tpl, $params);
		}

		// Raw output
		parent::display($tpl);
	}


	/**
	 * Fetch the version from the flexicontent.org server
	 */
	static function getUpdateComponent()
	{
		// Read installation file
		$manifest_path = JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'flexicontent.xml';
		$com_xml = JInstaller::parseXMLInstallFile( $manifest_path );
		
		// Version checking URL
		$url = 'https://www.flexicontent.org/flexicontent_update.xml';
		$data = '';
		$check = array();
		$check['connect'] = 0;
		$check['current_version'] = $com_xml['version'];
		$check['current_creationDate'] = $com_xml['creationDate'];

		//try to connect via cURL
		if (function_exists('curl_init') && function_exists('curl_exec')) {
			$ch = @curl_init();
			
			@curl_setopt($ch, CURLOPT_URL, $url);
			@curl_setopt($ch, CURLOPT_HEADER, 0);
			//http code is greater than or equal to 300 ->fail
			@curl_setopt($ch, CURLOPT_FAILONERROR, 1);
			@curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			//timeout of 5s just in case
			@curl_setopt($ch, CURLOPT_TIMEOUT, 5);
						
			$data = @curl_exec($ch);
						
			@curl_close($ch);
		}
		
		//try to connect via fsockopen
		if(function_exists('fsockopen') && $data == '') {

			$errno = 0;
			$errstr = '';

			//timeout handling: 5s for the socket and 5s for the stream = 10s
			$fsock = @fsockopen("www.flexicontent.org", 80, $errno, $errstr, 5);
			//$fsock = @fsockopen("flexicontent.googlecode.com", 80, $errno, $errstr, 5);
		
			if ($fsock) {
				@fputs($fsock, "GET /flexicontent_update.xml HTTP/1.1\r\n");
				@fputs($fsock, "HOST: www.flexicontent.org\r\n");
				@fputs($fsock, "Connection: close\r\n\r\n");
				
				//force stream timeout...
				@stream_set_blocking($fsock, 1);
				@stream_set_timeout($fsock, 5);
				 
				$get_info = false;
				while (!@feof($fsock))
				{
					if ($get_info)
					{
						$data .= @fread($fsock, 1024);
					}
					else
					{
						if (@fgets($fsock, 1024) == "\r\n")
						{
							$get_info = true;
						}
					}
				}
				@fclose($fsock);
				
				//need to check data cause http error codes aren't supported here
				if(!strstr($data, '<?xml version="1.0" encoding="utf-8"?><update>')) {
					$data = '';
				}
			}
		}

		//try to connect via fopen
		if (function_exists('fopen') && ini_get('allow_url_fopen') && $data == '') {
			
			//set socket timeout
			ini_set('default_socket_timeout', 5);
			
			$handle = @fopen ($url, 'r');
			
			//set stream timeout
			@stream_set_blocking($handle, 1);
			@stream_set_timeout($handle, 5);
			
			$data	= @fread($handle, 1000);
			
			@fclose($handle);
		}
		
		if( $data && strstr($data, '<?xml') )
		{
			$xml = simplexml_load_string($data);
			$check['version']  = (string)$xml->version;
			$check['released'] = (string)$xml->released;
			$check['connect']  = 1;
			$check['enabled']  = 1;
			$check['current']  = version_compare( str_replace(' ', '', $check['current_version']), str_replace(' ', '', $check['version']) );
		}
		
		return $check;
	}


	function fversion(&$params)
	{
		// Cache update check of FLEXIcontent version
		$cache = JFactory::getCache('com_flexicontent');
		$cache->setCaching( 1 );
		$cache->setLifeTime( 3600 );  // Set expire time (hard-code this to 1 hour), to avoid server load
		$check = $cache->get(array( 'FlexicontentViewFlexicontent', 'getUpdateComponent'), array('component'));
		$this->check = $check;
	}
}
?>
