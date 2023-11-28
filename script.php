<?php
/**
 * @version 1.5 stable $Id: install.php 1789 2013-10-15 02:25:46Z ggppdk $
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

defined( '_JEXEC' ) or die( 'Restricted access' );


// Joomla version variables
if (!defined('FLEXI_J16GE') || !defined('FLEXI_J30GE'))
{
	jimport('cms.version.version');
	$jversion = new JVersion;
}
if (!defined('FLEXI_J16GE'))   define('FLEXI_J16GE', version_compare( $jversion->getShortVersion(), '1.6.0', 'ge' ) );
if (!defined('FLEXI_J30GE'))   define('FLEXI_J30GE', version_compare( $jversion->getShortVersion(), '3.0.0', 'ge' ) );
if (!defined('FLEXI_J40GE'))   define('FLEXI_J40GE', version_compare( $jversion->getShortVersion(), '4.0.0', 'ge' ) );

class com_flexicontentInstallerScript
{
	/*
	* $parent is the class calling this method.
	* $type is the type of change (install, update or discover_install, not uninstall).
	* preflight runs before anything else and while the extracted files are in the uploaded temp folder.
	* If preflight returns false, Joomla will abort the update and undo everything already done.
	*/
	function preflight( $type, $parent )
	{
		// Display fatal errors, warnings, notices
		error_reporting(E_ERROR || E_WARNING || E_NOTICE);
		ini_set('display_errors',1);

		// Try to increment some limits
		@ set_time_limit( 150 );   // try to set execution time 2.5 minutes
		ignore_user_abort( true ); // continue execution if client disconnects

		// Try to increment memory limits
		$memory_limit	= trim( @ ini_get( 'memory_limit' ) );
		if ( $memory_limit )
		{
			switch (strtolower(substr($memory_limit, -1)))
			{
				case 'm': $memory_limit = (int)substr($memory_limit, 0, -1) * 1048576; break;
				case 'k': $memory_limit = (int)substr($memory_limit, 0, -1) * 1024; break;
				case 'g': $memory_limit = (int)substr($memory_limit, 0, -1) * 1073741824; break;
				case 'b':
				switch (strtolower(substr($memory_limit, -2, 1)))
				{
					case 'm': $memory_limit = (int)substr($memory_limit, 0, -2) * 1048576; break;
					case 'k': $memory_limit = (int)substr($memory_limit, 0, -2) * 1024; break;
					case 'g': $memory_limit = (int)substr($memory_limit, 0, -2) * 1073741824; break;
					default : break;
				} break;
				default: break;
			}
			if ( $memory_limit < 16000000 ) @ ini_set( 'memory_limit', '16M' );
			if ( $memory_limit < 32000000 ) @ ini_set( 'memory_limit', '32M' );
			if ( $memory_limit < 64000000 ) @ ini_set( 'memory_limit', '64M' );
			if ( $memory_limit < 12800000 ) @ ini_set( 'memory_limit', '128M' );
		}

		// First check PHP minimum version is running
		$PHP_VERSION_NEEDED = '5.4.0';
		if (version_compare(PHP_VERSION, $PHP_VERSION_NEEDED, '<'))
		{
			// load english language file for 'com_flexicontent' component then override with current language file
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);

			JFactory::getApplication()->enqueueMessage(JText::sprintf('FLEXI_UPGRADE_PHP_VERSION_GE', $PHP_VERSION_NEEDED), 'warning');
			return false;
		}

		// Get existing manifest data
		$existing_manifest = $this->getExistingManifest();

		// Get Joomla version
		$jversion = new JVersion();

		// File version of new manifest file
		$this->release = $parent->getManifest()->version;

		// File version of existing manifest file
		$this->release_existing = $existing_manifest[ 'version' ];

		// Manifest file minimum Joomla version
		$this->minimum_joomla_release = $parent->getManifest()->attributes()->version;

		// Only execute during install / update not during uninstallation (J4 will run post flight during uninstall too)
		if ($type=='update' || $type=='install') :
		?>

		<table style="border-collapse: collapse; background-color: transparent;">
			<tr>
				<td valign="top">
		    	<img src="<?php echo 'components/com_flexicontent/assets/images/logo.png'; ?>" style="width:300px; margin: 0px 48px 0px 0px;" alt="FLEXIcontent Logo" />
				</td>
				<td valign="top">
	     	 	<span><?php echo JText::_('COM_FLEXICONTENT_DESCRIPTION'); ?></></span><br />
	      	<font class="small">by <a href="http://www.flexicontent.org" target="_blank">Emmanuel Danan</a>,
	      	<font class="small">by <a href="http://www.flexicontent.org" target="_blank">Georgios Papadakis</a>,
	      	<font class="small">by <a href="http://www.flexicontent.org" target="_blank">Berges Yannick</a>,
	      	<font class="small">by <a href="http://www.flexicontent.org" target="_blank">Suriya Kaewmungmuang</a>,
					<br/>
	      	<font class="small">and <a href="http://www.marvelic.co.th" target="_blank">Marvelic Engine Co.,Ltd.</a><br/>
				</td>
			</tr>

		</table>

		<?php
		echo '
		<div class="alert alert-info" style="margin:32px 0px 0px 0px;">' .JText::_('Performing prior to installation tasks ... '). '
		<br/>
		<ul>
			<li>
				' . JText::_('COM_FLEXICONTENT_REQUIRED_PHPVER') . '
				' . JText::_('COM_FLEXICONTENT_MIN').': <span class="badge bg-info badge-info">' . $PHP_VERSION_NEEDED . '</span>
				' . JText::_('COM_FLEXICONTENT_CURRENT').': <span class="badge bg-success badge-success">' . PHP_VERSION . '</span>
			</li>
		';

		// Check that current Joomla release is not older than minimum required
		if ( version_compare($jversion->getShortVersion(), $this->minimum_joomla_release, 'lt') )
		{
			echo '</ul>';
			JFactory::getApplication()->enqueueMessage('Cannot install com_flexicontent in a Joomla release prior to ' . $this->minimum_joomla_release, 'warning');
			return false;
		}
		else
		{
			echo '
				<li>
					' . JText::_('COM_FLEXICONTENT_REQUIRED_JVER') . '
					' . JText::_('COM_FLEXICONTENT_MIN').': <span class="badge bg-info badge-info">' . $this->minimum_joomla_release . '</span>
					' . JText::_('COM_FLEXICONTENT_CURRENT').': <span class="badge bg-success badge-success">' . $jversion->getShortVersion() . '</span>
				</li>';
		}

		// Print message about installing / updating / downgrading FLEXIcontent
		$downgrade_allowed = true;
		if ($type=='update')
		{
			if ( !$downgrade_allowed && version_compare( $this->release, $this->release_existing, '<' ) )
			{
				$from_to = ''
					.JText::_('COM_FLEXICONTENT_FROM'). ' <span class="badge bg-info badge-info">' .$this->release_existing. '</span> '
					.JText::_('COM_FLEXICONTENT_TO'). ' <span class="badge bg-warning badge-warning">' .$this->release. '</span> ';
				// ?? Abort if the component being installed is not newer than the currently installed version
				//echo '</ul>';
				JFactory::getApplication()->enqueueMessage('Can not perform downgrade of FLEXIcontent ' . $from_to, 'warning');
				return false;
				echo '</ul>';
				return false;  // Returning false here would abort
			}
		}

		echo '
		</ul>
		</div>
		';

		endif; // type == install / update

		// Set a flag about FLEXIcontent being installed (1) or upgraded (0)
		define('FLEXI_NEW_INSTALL', $type=='install' ? 1 : 0);
	}


	/*
	* $parent is the class calling this method.
	* install runs after the database scripts are executed.
	* If the extension is new, the install method is run.
	* If install returns false, Joomla will abort the install and undo everything already done.
	*/
	function install( $parent )
	{
		echo '
		<div class="alert alert-success" style="margin:8px 0px 8px 0px;">'
			. JText::_('COM_FLEXICONTENT_INSTALLING') . ' '
			//. JText::_('COM_FLEXICONTENT_VERSION')
			. ' <span class="badge bg-success badge-success">'.$this->release.'</span>
		</div>';

		if ( ! $this->do_extra( $parent ) ) return false;  // Abort installation

		// You can have the backend jump directly to the newly installed component configuration page
		// $parent->getParent()->setRedirectURL('index.php?option=com_flexicontent');
	}


	/*
	* $parent is the class calling this method.
	* update runs after the database scripts are executed.
	* If the extension exists, then the update method is run.
	* If this returns false, Joomla will abort the update and undo everything already done.
	*/
	function update( $parent )
	{
		echo '<div class="alert alert-success" style="margin:8px 0px 8px 0px;">'
			. JText::_('COM_FLEXICONTENT_UPDATING_INSTALLATION') . ' '
			//. JText::_('COM_FLEXICONTENT_VERSION')
			;

		if ( version_compare( $this->release, $this->release_existing, 'ge' ) ) {
			echo '
				<span class="badge bg-success badge-success">' . JText::_('COM_FLEXICONTENT_UPGRADING') . '</span>
				' . JText::_('COM_FLEXICONTENT_FROM') . ' <span class="badge bg-info badge-info">' . $this->release_existing . '</span>
				' . JText::_('COM_FLEXICONTENT_TO')   . ' <span class="badge bg-success badge-success">' . $this->release . '</span>';
		}
		else
		{
			echo '
				<span class="badge bg-warning badge-warning">' . JText::_('COM_FLEXICONTENT_DOWNGRADING') . '</span>
				' . JText::_('COM_FLEXICONTENT_FROM') . ' <span class="badge bg-info badge-info">' . $this->release_existing . '</span>
				' . JText::_('COM_FLEXICONTENT_TO')   . ' <span class="badge bg-info badge-info">' . $this->release . '</span>';
		}
		echo '</div>';

		if ( ! $this->do_extra( $parent ) ) return false;  // Abort installation

		// You can have the backend jump directly to the newly updated component configuration page
		// $parent->getParent()->setRedirectURL('index.php?option=com_flexicontent');
	}


	function do_extra( $parent )
	{
		// init vars
		$error = false;
		$extensions = array();

		// clear a cache
		$cache = JFactory::getCache();
		$cache->clean( '_system' );  // This might be necessary as installing-uninstalling in same session may result in wrong extension ids, etc
		$cache->clean( 'com_flexicontent' );
		$cache->clean( 'com_flexicontent_tmpl' );
		$cache->clean( 'com_flexicontent_cats' );
		$cache->clean( 'com_flexicontent_items' );
		$cache->clean( 'com_flexicontent_filters' );

		// reseting post installation session variables
		$session  = JFactory::getSession();
		$session->set('flexicontent.postinstall', false);
		$session->set('flexicontent.allplgpublish', false);
		$session->set('flexicontent.allplgpublish', false);
		$session->set('unbounded_noext', false, 'flexicontent');
		$session->set('unbounded_badcat', false, 'flexicontent');

		$db = JFactory::getDbo();

		// Parse XML file to identify additional extensions,
		// This code part (for installing additional extensions) originates from Zoo J1.5 Component:
		// @author    YOOtheme http://www.yootheme.com
		// @copyright Copyright (C) 2007 - 2009 YOOtheme GmbH
		// @license GPLv2
		$manifest = isset($parent) ? $parent->getParent()->manifest : $this->manifest;
		$source   = isset($parent) ? $parent->getParent()->getPath('source') : $this->parent->getPath('source');
		$additional = $manifest->xpath('additional');
		$additional = count($additional) ? reset($additional) : NULL;

		if (is_object($additional) && count($additional->children()))
		{
			$exts = $additional->children();
			foreach ($exts as $ext)
			{
				$extensions[] = array(
					'name' => strip_tags( $ext->asXml() ),
					'type' => $ext->getName(),
					'folder' => $source.'/' . $ext->attributes()->folder,
					'ext_name' => ((string) $ext->attributes()->name),  // needs to be converted to string
					'ext_folder' => ((string) $ext->attributes()->instfolder),  // needs to be converted to string
					'enabled' => ((string) $ext->attributes()->enabled),
					'installer' => new JInstaller(),
					'status' => null,
				);
	    }
			//echo "<pre>"; print_r($extensions); echo "</pre>"; exit;
		}


		// Install discovered extensions
		foreach ($extensions as $i => $extension)
		{
			$jinstaller = & $extensions[$i]['installer'];    // new JInstaller();

			// J1.6+ installer requires that we explicit set override/upgrade options
			$jinstaller->setOverwrite(true);
			$jinstaller->setUpgrade(true);

			if ($jinstaller->install($extensions[$i]['folder']))
			{
				$extensions[$i]['status'] = true;
				$ext_manifest = $jinstaller->getManifest();
				$ext_manifest_name = $ext_manifest->name;
				//if ($ext_manifest_name!=$extensions[$i]['name'])  echo $ext_manifest_name." - ".$extensions[$i]['name'] . "<br/>";

				// Force existing plugins/modules to use name found in each extension's manifest file
				if (1) //if ( in_array($extensions[$i]['ext_folder'], array('flexicontent_fields', 'flexicontent', 'search', 'content', 'system')) || $extensions[$i]['type']=='module' )
				{
					$ext_tbl = '#__extensions';
					$query = 'UPDATE '.$ext_tbl
						//.' SET name = '.$db->Quote($extensions[$i]['name'])
						.' SET name = '.$db->Quote($ext_manifest_name)
						.($extensions[$i]['enabled'] ? ', enabled = ' . (int) $extensions[$i]['enabled'] : '')
						.' WHERE element = '.$db->Quote($extensions[$i]['ext_name'])
						.'  AND folder = '.$db->Quote($extensions[$i]['ext_folder'])
						.'  AND type = '.$db->Quote($extensions[$i]['type'])
						;
					$db->setQuery($query)->execute();
				}
			}
			else
			{
				$extensions[$i]['status'] = false;
				if ( !FLEXI_NEW_INSTALL )
				{
					$error = true;
					break;
				}
			}
		}

		/**
		 * Disabled bootstrap sliders, to allow automatic non-user interactive upgrade scripts to run
		 * Instead we will use buttons with basic JS to toggle the installation logs
		 */

		//echo JHtml::_('bootstrap.startAccordion', 'additional-extensions', array());
		//echo JHtml::_('bootstrap.addSlide', 'additional-extensions', JText::_('COM_FLEXICONTENT_LOG') . ' : ' . JText::_( 'COM_FLEXICONTENT_ADDITIONAL_EXTENSIONS' ), 'additional-extensions-slide0' );
		?>

		<span class="btn btn-primary" onclick="var tbl = document.getElementById('fc_additional_extensions_log'); tbl.style.display = tbl.style.display === 'none' ? '' : 'none';">
			<?php echo JText::_('COM_FLEXICONTENT_LOG') . ' : ' . JText::_( 'COM_FLEXICONTENT_ADDITIONAL_EXTENSIONS' ); ?>
		</span>

		<table class="adminlist" id="fc_additional_extensions_log" style="display: none;">
			<thead>
				<tr>
					<th style="text-align:left; width:500px;">
						<span class="label"><?php echo JText::_('COM_FLEXICONTENT_EXTENSION'); ?></span>
					</th>
					<th style="text-align:left">
						<span class="label"><?php echo JText::_('COM_FLEXICONTENT_STATUS'); ?></span>
					</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="2">&nbsp;</td>
				</tr>
			</tfoot>
			<tbody>
				<?php foreach ($extensions as $i => $ext) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="key" style="font-size:11px;">[<?php echo JText::_($ext['type']); ?>] <?php echo $ext['name']; ?></td>
						<td>
							<?php
							if ($ext['status']===null)
								$status_class = 'badge';
							else if ($ext['status'])
								$status_class = 'badge bg-success badge-success';
							else
								$status_class = 'badge badge-error';
							?>
							<span class="<?php echo $status_class; ?>">
								<?php
									if ( $ext['status'] === null ) {
										echo JText::_('COM_FLEXICONTENT_SKIPPED');
									} else if ($ext['status']) {
										echo JText::_('COM_FLEXICONTENT_INSTALLED');
									} else {
										$msg = JText::_(FLEXI_NEW_INSTALL ? 'Upgrade ERROR (extension removed)' : 'Installation -- FAILED --' ) ."<br/>";
										if (FLEXI_NEW_INSTALL) $msg .= "FLEXIcontent may not work properly, please install an older or newer FLEXIcontent package";
										echo $msg;
										JFactory::getApplication()->enqueueMessage('<br/>'.$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) . ': ' . $msg, 'warning');
									}
								?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		//echo JHtml::_('bootstrap.endSlide');
		//echo JHtml::_('bootstrap.endAccordion');

		// Rollback on installation errors, abort() will be called on every additional extension installed above
		if ($error)
		{
			for ($i = 0; $i < count($extensions); $i++)
			{
				if ( $extensions[$i]['status'] )
				{
					$extensions[$i]['installer']->abort('<span style="color:black">'.
						$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .' '. JText::_('COM_FLEXICONTENT_INSTALLED') .':</span>'.
						' <span class="badge bg-warning badge-warning">'. JText::_('rolling back').'</span>',
						$extensions[$i]['type']
					);
					//$extensions[$i]['status'] = false;
				} /*else if ( $extensions[$i]['status'] === false ) {
					$msg = ' <span style="color:red">'. JText::_('-- FAILED --').'</span>';
					JFactory::getApplication()->enqueueMessage('<span style="color:black">'.$extensions[$i]['name'] . ' ' . JText::_($extensions[$i]['type']) . ' ' . JText::_('Install') . '</span> : ' . $msg, 'warning');
				} else {
					$msg = ' <span style="color:darkgray">'. JText::_('Skipped').'</span>';
					JFactory::getApplication()->enqueueMessage('<span style="color:black">'.$extensions[$i]['name'] . ' ' . JText::_($extensions[$i]['type']) . ' ' . JText::_('Install') . '</span> : ' . $msg, 'warning');
				}*/
			}

			return false;  // returning false here will cancel (abort) component installation and rollback changes
		}
		// All OK, or acceptable errors
		return true;
	}


	/*
	* $parent is the class calling this method.
	* $type is the type of change (install, update or discover_install, not uninstall).
	* postflight is run after the extension is registered in the database.
	*/
	function postflight( $type, $parent )
	{
		// Only execute during install / update not during uninstallation (J4 will run post flight during uninstall too)
		if ($type != 'install' && $type != 'update')
		{
			return;
		}

		$app = JFactory::getApplication();
		$db = JFactory::getDbo();
		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');

		/*
		// always create or modify these parameters
		$params['my_param0'] = 'Component version ' . $this->release;
		$params['my_param1'] = 'Another value';

		// define the following parameters only if it is an original install
		if ( $type == 'install' ) {
			$params['my_param2'] = '4';
			$params['my_param3'] = 'Star';
		}

		$this->_setComponentParams( $params );*/

		/*JFactory::getApplication()->enqueueMessage('
			Please clear your frontend / backend Joomla cache once, <br/>
			- to make sure that any changes (e.g in filtering) take immediate effect<br/>
			In case of display issue, press CTRL+F5 / F5 / command+R, (Windows / Linux / Apple\'s Safari)<br/>
			- to make sure that latest FLEXIcontent JS/CSS is retrieved',
			'warning'
		);*/

		echo FLEXI_J40GE
			? '<link type="text/css" href="components/com_flexicontent/assets/css/j3x.css" rel="stylesheet">'
			: '<link type="text/css" href="components/com_flexicontent/assets/css/j4x.css" rel="stylesheet">';
		echo '<link type="text/css" href="components/com_flexicontent/assets/css/flexicontentbackend.css" rel="stylesheet">';

		//echo JHtml::_('bootstrap.startAccordion', 'upgrade-tasks', array());
		//echo JHtml::_('bootstrap.addSlide', 'upgrade-tasks', JText::_('COM_FLEXICONTENT_LOG') . ' : ' . JText::_( 'COM_FLEXICONTENT_UPGRADE_TASKS' ), 'upgrade-tasks-slide0' );
		?>

		<span class="btn btn-primary" onclick="var tbl = document.getElementById('fc_upgrade_tasks_log'); tbl.style.display = tbl.style.display === 'none' ? '' : 'none';">
			<?php echo JText::_('COM_FLEXICONTENT_LOG') . ' : ' . JText::_( 'COM_FLEXICONTENT_UPGRADE_TASKS' ); ?>
		</span>

		<table class="adminlist" id="fc_upgrade_tasks_log" style="display: none;">
			<thead>
				<tr>
					<th style="text-align:left; width:500px;">
						<span class="label"><?php echo JText::_('COM_FLEXICONTENT_TASKS'); ?></span>
					</th>
					<th style="text-align:left">
						<span class="label"><?php echo JText::_('COM_FLEXICONTENT_STATUS'); ?></span>
					</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="2">&nbsp;</td>
				</tr>
			</tfoot>
			<tbody>

		<?php
		// Fix for categories with wrong extension
		$query = 'UPDATE #__categories SET extension = "com_content", published = 0 WHERE  extension = "com_flexicontent"';
		$db->setQuery($query);
		$db->execute();

		$deprecated_fields = array(
			'hidden'=>'text',
			'relateditems'=>'relation',
			'relateditems_backlinks'=>'relation_reverse',
			'sharedaudio'=>'sharedmedia',
			'sharedvideo'=>'sharedmedia',
			'extendedweblink'=>'weblink',
			'minigallery'=>'image',
			'groupmarker'=>'custom_form_html'
		);

		// Get DB table information

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_fields_item_relations"';
		$db->setQuery($query);
		$fi_rels_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_items_versions"';
		$db->setQuery($query);
		$fi_vers_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_favourites"';
		$db->setQuery($query);
		$favs_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_files"';
		$db->setQuery($query);
		$files_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_fields"';
		$db->setQuery($query);
		$fields_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_types"';
		$db->setQuery($query);
		$types_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_tags"';
		$db->setQuery($query);
		$tags_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_items_ext"';
		$db->setQuery($query);
		$iext_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_advsearch_index"';
		$db->setQuery($query);
		$advsearch_index_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_authors_ext"';
		$db->setQuery($query);
		$authors_ext_tbl_exists = (boolean) count($db->loadObjectList());

		// BETA table
		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_reviews_dev"';
		$db->setQuery($query);
		$reviews_beta_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_reviews"';
		$db->setQuery($query);
		$reviews_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . '__flexicontent_mediadatas"';
		$db->setQuery($query);
		$mediadatas_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_templates"';
		$db->setQuery($query);
		$templates_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_layouts_conf"';
		$db->setQuery($query);
		$layouts_conf_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_items_tmp"';
		$db->setQuery($query);
		$content_cache_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_download_history"';
		$db->setQuery($query);
		$dl_history_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_download_coupons"';
		$db->setQuery($query);
		$dl_coupons_tbl_exists = (boolean) count($db->loadObjectList());

		$query = 'SHOW TABLES LIKE "' . $dbprefix . 'flexicontent_file_usage"';
		$db->setQuery($query);
		$file_usage_tbl_exists = (boolean) count($db->loadObjectList());

		// Data Types of columns
		$tbl_names_arr = array('flexicontent_files', 'flexicontent_fields', 'flexicontent_types');
		foreach ($tbl_names_arr as $tbl_name)
		{
			$full_tbl_name = $dbprefix . $tbl_name;
			$query = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '".$dbname."' AND TABLE_NAME = '".$full_tbl_name."'";// ." AND COLUMN_NAME = 'attribs'";
			$db->setQuery($query);
			$tbl_datatypes[$tbl_name] = $db->loadAssocList('COLUMN_NAME');
		}
		?>


		<?php
		// Delete orphan plugin entries
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Delete orphan plugin entries</td>
					<td>
					<?php
					$queries = array();
					$queries[] ="DELETE FROM `#__extensions` WHERE folder='flexicontent_fields' AND element IN ('flexisystem', 'flexiadvroute', 'flexisearch', 'flexiadvsearch', 'flexinotify')";

					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								echo ($count_rows = $db->getAffectedRows()) ?
									'<span class="badge bg-success badge-success">'.$count_rows.' effected rows </span>' :
									'<span class="badge bg-info badge-info">no changes</span>' ;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
					}
					else echo '<span class="badge bg-info badge-info">nothing to do</span>';
					?>
					</td>
				</tr>

		<?php
		// Enable FLEXIcontent system plugins
		?>
				<tr class="row1">
					<td class="key" style="font-size:11px;">Enable FLEXIcontent system plugins</td>
					<td>
					<?php
					$queries = array();
					$queries[] = "UPDATE #__extensions SET enabled=1 WHERE type='plugin' AND (element=".$db->Quote('flexisystem')." OR element=".$db->Quote('flexiadvroute').") AND folder=".$db->Quote('system');

					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								echo ($count_rows = $db->getAffectedRows()) ?
									'<span class="badge bg-success badge-success">'.$count_rows.' effected rows </span>' :
									'<span class="badge bg-info badge-info">no changes</span>' ;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
					}
					else echo '<span class="badge bg-info badge-info">nothing to do</span>';
					?>
					</td>
				</tr>

		<?php
		// Update DB table flexicontent_fields: Convert deprecated fields types to 'text' field type
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Converting deprecated fields types:</td>
					<td>
					<?php
					//echo '<br/><span class="label">' . implode('</span><span class="label">', array_keys($deprecated_fields)) . '</span>';
					$msg = array();
					$n = 0;
					if ($fields_tbl_exists)
					{
						foreach ($deprecated_fields as $old_type => $new_type)
						{
							$deprecate_custom = '_deprecate_field_' . $old_type;
							method_exists(get_class($this), $deprecate_custom)
								//? call_user_func_array(array($this, $deprecate_custom), array($old_type, $new_type, & $msg, & $n))
								? $this->$deprecate_custom($old_type, $new_type, $msg, $n)
								: $this->_deprecate_field($old_type, $new_type, $msg, $n);

							$n++;
						}
					}
					?>
					</td>
					<td> <?php echo implode("\n", $msg); ?> </td>
				</tr>

		<?php
		// Rename OLD parameter names to new names
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Converting old field parameter names to new names</td>
					<td>
					<?php
					$msg = array();
					$n = 0;
					$field_ids = $db->setQuery('SELECT id FROM #__flexicontent_fields WHERE field_type = ' . $db->Quote('addressint'))->loadColumn();

					foreach($field_ids as $field_id)
					{
						$_updated = $this->_renameExtensionLegacyParameters(
							$_map = array('field_prefix' => 'opentag', 'field_suffix' => 'closetag'),
							$_dbtbl_name = 'flexicontent_fields',
							$_dbcol_name = 'attribs',
							$_record_id = $field_id 
						);

						if ($_updated) $msg[] = 'Update field: ' . $field_id . '<br>';

						$n++;
					}
					?>
					</td>
					<td> <?php echo implode("\n", $msg); ?> </td>
				</tr>

		<?php
		// Upgrade DB tables: ADD new columns
		?>
				<tr class="row1">
					<td class="key" style="font-size:11px;">Upgrading DB tables (adding/dropping columns): </td>
					<td>
					<?php
					$tbls = array();
					if ($fi_rels_tbl_exists) $tbls[] = "#__flexicontent_fields_item_relations";
					if ($fi_vers_tbl_exists) $tbls[] = "#__flexicontent_items_versions";
					if ($favs_tbl_exists)    $tbls[] = "#__flexicontent_favourites";
					if ($files_tbl_exists)   $tbls[] = "#__flexicontent_files";
					if ($fields_tbl_exists)  $tbls[] = "#__flexicontent_fields";
					if ($types_tbl_exists)   $tbls[] = "#__flexicontent_types";
					if ($tags_tbl_exists)    $tbls[] = "#__flexicontent_tags";
					if ($iext_tbl_exists)    $tbls[] = "#__flexicontent_items_ext";
					if ($templates_tbl_exists)        $tbls[] = "#__flexicontent_templates";
					if ($content_cache_tbl_exists)    $tbls[] = "#__flexicontent_items_tmp";
					if ($advsearch_index_tbl_exists)  $tbls[] = "#__flexicontent_advsearch_index";
					if ($reviews_tbl_exists)          $tbls[] = "#__flexicontent_reviews";
					if ($mediadatas_tbl_exists)       $tbls[] = "#__flexicontent_mediadatas";
					foreach ($tbls as $tbl) $tbl_fields[$tbl] = $db->getTableColumns($tbl);

					$queries = array();
					if ( $iext_tbl_exists ) {
						$_query = "ALTER TABLE `#__flexicontent_items_ext`";
						$_querycols = array();
						if (array_key_exists('cnt_state', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = "  DROP `cnt_state`";
						if (array_key_exists('cnt_access', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = " DROP `cnt_access`";
						if (array_key_exists('cnt_publish_up', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = " DROP `cnt_publish_up`";
						if (array_key_exists('cnt_publish_down', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = " DROP `cnt_publish_down`";
						if (array_key_exists('cnt_created_by', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = " DROP `cnt_created_by`";
						if (!array_key_exists('lang_parent_id', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = " ADD `lang_parent_id` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `type_id`";
						if (!array_key_exists('is_uptodate', $tbl_fields['#__flexicontent_items_ext'])) $_querycols[] = " ADD `is_uptodate` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0' AFTER `type_id`";
						if (!empty($_querycols)) $queries[] = $_query . implode(",", $_querycols);
					}

					if ($fi_rels_tbl_exists && !array_key_exists('suborder', $tbl_fields['#__flexicontent_fields_item_relations'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields_item_relations` ADD `suborder` INT(11) NOT NULL DEFAULT '1' AFTER `valueorder`";
					}
					if ($fi_vers_tbl_exists && !array_key_exists('suborder', $tbl_fields['#__flexicontent_items_versions'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_items_versions` ADD `suborder` INT(11) NOT NULL DEFAULT '1' AFTER `valueorder`";
					}

					if ($fi_rels_tbl_exists && !array_key_exists('value_integer', $tbl_fields['#__flexicontent_fields_item_relations'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields_item_relations` ADD `value_integer` BIGINT(20) NULL AFTER `value`";
					}
					if ($fi_rels_tbl_exists && !array_key_exists('value_decimal', $tbl_fields['#__flexicontent_fields_item_relations'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields_item_relations` ADD `value_decimal` DECIMAL(65,15) NULL AFTER `value_integer`";
					}
					if ($fi_rels_tbl_exists && !array_key_exists('value_datetime', $tbl_fields['#__flexicontent_fields_item_relations'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields_item_relations` ADD `value_datetime` DATETIME NULL AFTER `value_decimal`";
					}

					/*if ($fi_rels_tbl_exists && !array_key_exists('qindex01', $tbl_fields['#__flexicontent_fields_item_relations'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields_item_relations` ADD `qindex01` MEDIUMTEXT NULL DEFAULT NULL AFTER `value_datetime`";
					}
					if ($fi_rels_tbl_exists && !array_key_exists('qindex02', $tbl_fields['#__flexicontent_fields_item_relations'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields_item_relations` ADD `qindex02` MEDIUMTEXT NULL DEFAULT NULL AFTER `qindex01`";
					}
					if ($fi_rels_tbl_exists && !array_key_exists('qindex03', $tbl_fields['#__flexicontent_fields_item_relations'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields_item_relations` ADD `qindex03` MEDIUMTEXT NULL DEFAULT NULL AFTER `qindex02`";
					}

					if ($fi_vers_tbl_exists && !array_key_exists('qindex01', $tbl_fields['#__flexicontent_items_versions'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_items_versions` ADD `qindex01` MEDIUMTEXT NULL DEFAULT NULL AFTER `value`";
					}
					if ($fi_vers_tbl_exists && !array_key_exists('qindex02', $tbl_fields['#__flexicontent_items_versions'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_items_versions` ADD `qindex02` MEDIUMTEXT NULL DEFAULT NULL AFTER `qindex01`";
					}
					if ($fi_vers_tbl_exists && !array_key_exists('qindex03', $tbl_fields['#__flexicontent_items_versions'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_items_versions` ADD `qindex03` MEDIUMTEXT NULL DEFAULT NULL AFTER `qindex02`";
					}*/

					// Favourites TABLE
					if ( $favs_tbl_exists && !array_key_exists('type', $tbl_fields['#__flexicontent_favourites'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_favourites` ADD `type` INT(11) NOT NULL DEFAULT '0' AFTER `notify`";
					}

					// Files TABLE
					$tbl_name = 'flexicontent_files';
					$changes = array();

					if ( $files_tbl_exists && !array_key_exists('filename_original', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `filename_original` VARCHAR(255) NOT NULL DEFAULT '' AFTER `filename`";
					}
					if ( $files_tbl_exists && !array_key_exists('description', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `description` TEXT NOT NULL AFTER `altname`";
					}
					if ( $files_tbl_exists && !array_key_exists('language', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `language` CHAR(7) NOT NULL DEFAULT '*' AFTER `published`";
					}
					if ( $files_tbl_exists && !array_key_exists('size', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `size` INT(11) unsigned NOT NULL default '0' AFTER `hits`";
					}
					if ( $files_tbl_exists && !array_key_exists('estorage_fieldid', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `estorage_fieldid` INT(11) NOT NULL default '0' AFTER `url`";
						$changes[] = "ADD KEY `estorage_fieldid` (`estorage_fieldid`)";
					}
					if ( $files_tbl_exists && !array_key_exists('assignments', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `assignments` INT(11) unsigned NOT NULL default '0' AFTER `size`";
					}
					if ( $files_tbl_exists && !array_key_exists('stamp', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `stamp` tinyint(3) unsigned NOT NULL default '1' AFTER `assignments`";
					}
					if ( isset($tbl_datatypes[$tbl_name]) && strtolower($tbl_datatypes[$tbl_name]['attribs']['DATA_TYPE']) != 'mediumtext' ) {
						$changes[] = "CHANGE `attribs` `attribs` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL";
					}

					if ($changes)
					{
						$queries[] = "ALTER TABLE `#__".$tbl_name."` "
							. ' CHANGE `uploaded` `uploaded` DATETIME NULL DEFAULT NULL, CHANGE `checked_out_time` `checked_out_time` DATETIME NULL DEFAULT NULL, '
							. implode(' , ', $changes);
					}

					// Fields TABLE
					$tbl_name = 'flexicontent_fields';
					$changes = array();

					if ( $fields_tbl_exists && !array_key_exists('untranslatable', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `untranslatable` TINYINT(1) NOT NULL DEFAULT '0' AFTER `isadvsearch`";
					}
					if ( $fields_tbl_exists && !array_key_exists('isadvfilter', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `isadvfilter` TINYINT(1) NOT NULL DEFAULT '0' AFTER `isfilter`";
					}
					if ( $fields_tbl_exists && !array_key_exists('formhidden', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `formhidden` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `untranslatable`";
					}
					if ( $fields_tbl_exists && !array_key_exists('valueseditable', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `valueseditable` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `formhidden`";
					}
					if ( $fields_tbl_exists && !array_key_exists('edithelp', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `edithelp` SMALLINT(8) NOT NULL DEFAULT '2' AFTER `formhidden`";
					}
					if ( $fields_tbl_exists && !array_key_exists('asset_id', $tbl_fields['#__'.$tbl_name]) ) {
						$changes[] = "ADD `asset_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `id`";
					}
					if ( isset($tbl_datatypes[$tbl_name]) && strtolower($tbl_datatypes[$tbl_name]['attribs']['DATA_TYPE']) != 'mediumtext' ) {
						$changes[] = "CHANGE `attribs` `attribs` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL";
					}

					if ($changes)
					{
						$queries[] = "ALTER TABLE `#__".$tbl_name."` " . implode(' , ', $changes);
					}

					// Types TABLE
					$tbl_name = 'flexicontent_types';
					$changes = array();

					if ( $types_tbl_exists && !array_key_exists('asset_id', $tbl_fields['#__'.$tbl_name]) ) {
						$changes[] = "ADD `asset_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `id`";
					}
					if ( $types_tbl_exists && !array_key_exists('itemscreatable', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `itemscreatable` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `published`";
					}
					if ( isset($tbl_datatypes[$tbl_name]) && strtolower($tbl_datatypes[$tbl_name]['attribs']['DATA_TYPE']) != 'mediumtext' ) {
						$changes[] = "CHANGE `attribs` `attribs` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL";
					}
					if ( $types_tbl_exists && !array_key_exists('description', $tbl_fields['#__'.$tbl_name])) {
						$changes[] = "ADD `description` TEXT NULL AFTER `alias`";
					}

					if ($changes)
					{
						$queries[] = "ALTER TABLE `#__".$tbl_name."` " . implode(' , ', $changes);
					}

					// Tags TABLE
					$tbl_name = 'flexicontent_tags';
					if ( $tags_tbl_exists && !array_key_exists('jtag_id', $tbl_fields['#__'.$tbl_name]) ) {
						$queries[] = "ALTER TABLE `#__".$tbl_name."` ADD `jtag_id` INT(10) UNSIGNED NULL AFTER `checked_out_time`";
					}

					// Templates TABLE
					if ( $templates_tbl_exists && !array_key_exists('cfgname', $tbl_fields['#__flexicontent_templates'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_templates` ADD `cfgname` varchar(50) NOT NULL default '' AFTER `template`";
					}
					if ( $templates_tbl_exists && !array_key_exists('id', $tbl_fields['#__flexicontent_templates'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_templates` DROP PRIMARY KEY";
						$queries[] = "ALTER TABLE `#__flexicontent_templates` ADD `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT KEY FIRST";
					}

					$upgrade_count = 0;
					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								$upgrade_count++;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
						echo '<span class="badge bg-success badge-success">table(s) upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge bg-info badge-info">nothing to do</span>';
					?>
					</td>
				</tr>

		<?php
		// Create/Upgrade ADVANCED index DB table: ADD column and indexes
		// Because Adding indexes can be heavy to the SQL server (if not done asychronously ??) we truncate table OR drop it and recreate it
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Create/Upgrade advanced search index table: </td>
					<td>
					<?php

			    $queries = array();
					if ( $advsearch_index_tbl_exists ) {
				    $db->setQuery("SHOW INDEX FROM #__flexicontent_advsearch_index");
				    $_indexes = $db->loadObjectList();
				    foreach ($_indexes as $tbl_index) $tbl_indexes['#__flexicontent_advsearch_index'][$tbl_index->Key_name] = true;
				  }

					if ( !$advsearch_index_tbl_exists || !array_key_exists('sid', $tbl_fields['#__flexicontent_advsearch_index']) ) {
						if ( $advsearch_index_tbl_exists) $queries[] = "DROP TABLE `#__flexicontent_advsearch_index`";
						$queries[] = "CREATE TABLE `#__flexicontent_advsearch_index` (
							`sid` int(11) NOT NULL auto_increment,
							`field_id` int(11) NOT NULL, `item_id` int(11) NOT NULL, `extraid` int(11) NOT NULL,
							`search_index` longtext NOT NULL, `value_id` varchar(255) NULL,
							PRIMARY KEY (`field_id`,`item_id`,`extraid`),
							KEY `sid` (`sid`),
							KEY `field_id` (`field_id`),
							KEY `item_id` (`item_id`),
							FULLTEXT `search_index` (`search_index`),
							KEY `value_id` (`value_id`)
							) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";
					}

					/*
					$_add_indexes = array();
					if ( $advsearch_index_tbl_exists && !array_key_exists('field_id', $tbl_indexes['#__flexicontent_advsearch_index'])) {
						$_add_indexes[] = " ADD KEY ( `field_id` ) ";
					}
					if ( $advsearch_index_tbl_exists && !array_key_exists('item_id', $tbl_indexes['#__flexicontent_advsearch_index'])) {
						$_add_indexes[] = " ADD KEY ( `item_id` ) ";
					}
					if ( $advsearch_index_tbl_exists && !array_key_exists('search_index', $tbl_indexes['#__flexicontent_advsearch_index'])) {
						$_add_indexes[] = " ADD FULLTEXT ( `search_index` ) ";
					}
					if ( $advsearch_index_tbl_exists && !array_key_exists('value_id', $tbl_indexes['#__flexicontent_advsearch_index'])) {
						$_add_indexes[] = " ADD KEY ( `value_id` ) ";
					}

					if (count($_add_indexes)) {
						$db->setQuery('TRUNCATE TABLE #__flexicontent_advsearch_index');
						$db->execute();   // Truncate table of search index to avoid long-delay on indexing
						$queries[] = "ALTER TABLE `#__flexicontent_advsearch_index` ". implode(",", $_add_indexes);
					}
					*/

					$upgrade_count = 0;
					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								$upgrade_count++;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
						echo '<span class="badge bg-success badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
						if ($upgrade_count) echo ' Please <span class="badge bg-warning badge-warning">re-index</span> your content';
					}
					else echo '<span class="badge bg-info badge-info">nothing to do</span>';
					?>
					</td>
				</tr>

		<?php
		// Create authors_ext table if it does not exist
		?>
				<tr class="row1">
					<td class="key" style="font-size:11px;">Create/Upgrade authors configuration DB table: </td>
					<td>
					<?php

			    $queries = array();
					if ( !$authors_ext_tbl_exists ) {
						$queries[] = "
						CREATE TABLE IF NOT EXISTS `#__flexicontent_authors_ext` (
						  `user_id` int(11) unsigned NOT NULL,
						  `author_basicparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
						  `author_catparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
						  PRIMARY KEY  (`user_id`)
						) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";
					}

					$upgrade_count = 0;
					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								$upgrade_count++;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
						echo '<span class="badge bg-success badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge bg-info badge-info">nothing to do</span>';
					?>
					</td>
				</tr>

		<?php
		// Create flexicontent_reviews table if it does not exist
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Create/Upgrade reviews DB table: </td>
					<td>
					<?php

			    $queries = array();
					if ($reviews_beta_tbl_exists)
					{
						$queries[] = "DROP TABLE `#__flexicontent_reviews_dev`";
					}

					if (!$reviews_tbl_exists)
					{
						$queries[] = "
						CREATE TABLE IF NOT EXISTS `#__flexicontent_reviews` (
							`id` int(11) NOT NULL auto_increment,
							`content_id` int(11) NOT NULL,
							`type` varchar(255) NOT NULL DEFAULT 'item',
							`average_rating` int NOT NULL,
							`custom_ratings` text NULL,
							`user_id` int(11) NOT NULL DEFAULT '0',
							`email` varchar(255) NOT NULL DEFAULT '',
							`title` varchar(255) NULL,
							`title_old` varchar(255) NULL,
							`text` mediumtext NULL,
							`text_old` mediumtext NULL,
							`state` tinyint(3) NOT NULL DEFAULT '0',
							`approved` tinyint(3) NOT NULL DEFAULT '0',
							`verified` tinyint(3) NOT NULL DEFAULT '0',
							`useful_yes` int(11) NOT NULL DEFAULT '0',
							`useful_no` int(11) NOT NULL DEFAULT '0',
							`submit_date` datetime NOT NULL,
							`update_date` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
							`checked_out` int(11) unsigned NOT NULL default '0',
							`checked_out_time` datetime NOT NULL default '1000-01-01 00:00:00',
							`attribs` mediumtext NULL,
							PRIMARY KEY  (`id`),
							KEY (`content_id`, `user_id`, `type`),
							KEY (`content_id`, `type`),
							KEY `user_id` (`user_id`)
							FULLTEXT KEY `title` (`title`),
							FULLTEXT KEY `text` (`text`),
							KEY `state` (`state`)
							KEY `approved` (`approved`)
							KEY `verified` (`verified`)
							KEY `useful_yes` (`useful_yes`)
							KEY `useful_no` (`useful_no`)
						) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`;";
					}

					if ( $reviews_tbl_exists && !array_key_exists('title_old', $tbl_fields['#__flexicontent_reviews']) ) {
						$queries[] = "ALTER TABLE `#__flexicontent_reviews` ADD `title_old` varchar(255) NULL AFTER `title`";
					}
					if ( $reviews_tbl_exists && !array_key_exists('text_old', $tbl_fields['#__flexicontent_reviews']) ) {
						$queries[] = "ALTER TABLE `#__flexicontent_reviews` ADD `text_old` mediumtext NULL AFTER `text`";
					}
					if ( $reviews_tbl_exists && !array_key_exists('verified', $tbl_fields['#__flexicontent_reviews']) ) {
						$queries[] = "ALTER TABLE `#__flexicontent_reviews` ADD `verified` tinyint(3) NOT NULL DEFAULT '0' AFTER `approved`";
					}

					$upgrade_count = 0;

					if (!empty($queries))
					{
						foreach ($queries as $query)
						{
							try {
								$db->setQuery($query)->execute();
								$upgrade_count++;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
						echo '<span class="badge bg-success badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}

					else
					{
						echo '<span class="badge bg-info badge-info">nothing to do</span>';
					}
					?>
					</td>
				</tr>

		<?php
		// Create flexicontent_mediadatas table if it does not exist
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Create/Upgrade media data DB table: </td>
					<td>
					<?php

			    $queries = array();

					if (!$mediadatas_tbl_exists)
					{
						$queries[] = "
						CREATE TABLE IF NOT EXISTS `#__flexicontent_mediadatas` (
							`id` int(11) NOT NULL auto_increment,
							`file_id` int(11) NOT NULL,
							`state` tinyint(3) NOT NULL DEFAULT '1',
							`media_type` int(11) NOT NULL default 0, /* 0: audio , 1: video */
							`media_format` varchar(255) NULL, /* e.g 'video', 'wav', 'audio' */
							`codec_type` varchar(255) NULL, /* e.g 'audio' */
							`codec_name` varchar(255) NULL, /* e.g 'mp3', 'pcm_s24le' */
							`codec_long_name` varchar(255) NULL, /* e.g 'PCM signed 24-bit little-endian' , 'MP3 (MPEG audio layer 3)' */
							`resolution` varchar(255) NULL, /* e.g. 1280x720, 1920x1080 */
							`fps` double NULL, /* e.g. 50 (frames per second) */
							`bit_rate` int(11) NULL, /* e.g. 256000 , 320000 (bps) */
							`bits_per_sample` int(11) NULL, /* e.g. 16, 24, 32 (# bits) */
							`sample_rate` int(11) NULL, /* e.g. 44100 (HZ) */
							`duration` int(11) NOT NULL, /* e.g. 410 (seconds) */
							`channels` varchar(255) NULL, /* e.g. 1, 2, 4 (number of channels) */
							`channel_layout` varchar(255) NULL, /* e.g. 'stereo', 'mono' */
							`checked_out` int(11) unsigned NOT NULL default '0',
							`checked_out_time` datetime NOT NULL default '1000-01-01 00:00:00',
							`attribs` mediumtext NULL,
							PRIMARY KEY  (`id`),
							UNIQUE `file_id` (`file_id`),
							KEY `state` (`state`),
							KEY `media_type` (`media_type`),
							KEY `media_format` (`media_format`),
							KEY `resolution` (`resolution`),
							KEY `fps` (`fps`),
							KEY `bit_rate` (`bit_rate`),
							KEY `bits_per_sample` (`bits_per_sample`),
							KEY `sample_rate` (`sample_rate`),
							KEY `duration` (`duration`),
							KEY `channels` (`channels`),
							KEY `channel_layout` (`channel_layout`)
						) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`;";
					}

					$upgrade_count = 0;

					if (!empty($queries))
					{
						foreach ($queries as $query)
						{
							try {
								$db->setQuery($query)->execute();
								$upgrade_count++;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
						echo '<span class="badge bg-success badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}

					else
					{
						echo '<span class="badge bg-info badge-info">nothing to do</span>';
					}
					?>
					</td>
				</tr>

		<?php
		// Create content_cache table if it does not exist
		?>
				<tr class="row1">
					<td class="key" style="font-size:11px;">Create/Upgrade content cache DB table: </td>
					<td>
					<?php

			    $queries = array();
					if ( !$content_cache_tbl_exists ) {
						$queries[] = "
							CREATE TABLE `#__flexicontent_items_tmp` (
							 `id` int(10) unsigned NOT NULL,
							 `title` VARCHAR(255) NOT NULL,
							 `alias` VARCHAR(400) NOT NULL,
							 `state` tinyint(3) NOT NULL DEFAULT '0',
							 `catid` int(10) unsigned NOT NULL DEFAULT '0',
							 `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
							 `created_by` int(10) unsigned NOT NULL DEFAULT '0',
							 `modified` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
							 `modified_by` int(10) unsigned NOT NULL DEFAULT '0',
							 " . (FLEXI_J40GE ? "
							    `publish_up` datetime NULL DEFAULT NULL,
							    `publish_down` datetime NULL DEFAULT NULL,
							 " : "
							    `publish_up` datetime NULL DEFAULT '1000-01-01 00:00:00',
							    `publish_down` datetime NULL DEFAULT '1000-01-01 00:00:00',
							 ") . "
							 `version` int(10) unsigned NOT NULL DEFAULT '1',
							 `ordering` int(11) NOT NULL DEFAULT '0',
							 `access` int(10) unsigned NOT NULL DEFAULT '0',
							 `hits` int(10) unsigned NOT NULL DEFAULT '0',
							 `featured` tinyint(3) unsigned NOT NULL DEFAULT '0',
							 `language` char(7) NOT NULL,
							 `type_id` int(11) NOT NULL DEFAULT '0',
							 `is_uptodate` tinyint(3) unsigned NOT NULL default '0',
							 `lang_parent_id` int(11) NOT NULL DEFAULT '0',
							 PRIMARY KEY (`id`)
							) ENGINE=MyISAM DEFAULT CHARSET=utf8;
						";
					} else {
						$_querycols = array();
						if (array_key_exists('sectionid', $tbl_fields['#__flexicontent_items_tmp'])) $_querycols[] = " DROP `sectionid`";  // Drop J1.5 sectionid
						if (!array_key_exists('alias', $tbl_fields['#__flexicontent_items_tmp'])) $_querycols[] = " ADD `alias` VARCHAR(400) NOT NULL AFTER `title`";
						if (!array_key_exists('type_id', $tbl_fields['#__flexicontent_items_tmp'])) $_querycols[] = " ADD `type_id` INT(11) NOT NULL DEFAULT '0' AFTER `language`";
						if (!array_key_exists('lang_parent_id', $tbl_fields['#__flexicontent_items_tmp'])) $_querycols[] = " ADD `lang_parent_id` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `type_id`";
			      if (FLEXI_J40GE) $_querycols[] = " CHANGE `publish_up` `publish_up` DATETIME NULL DEFAULT NULL";
			      if (FLEXI_J40GE) $_querycols[] = " CHANGE `publish_down` `publish_down` DATETIME NULL DEFAULT NULL";
						if (!empty($_querycols)) $queries[] = "ALTER TABLE `#__flexicontent_items_tmp` " . implode(",", $_querycols);
					}

					$upgrade_count = 0;
					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								$upgrade_count++;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
						echo '<span class="badge bg-success badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge bg-info badge-info">nothing to do</span>';
					?>
					</td>
				</tr>

		<?php
		// Create/Upgrade DB tables for downloads enhancements
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Create/Upgrade DB tables for downloads enhancements: </td>
					<td>
					<?php

			    $queries = array();

					if ( !$dl_history_tbl_exists ) {
						$queries[] = "
						CREATE TABLE `#__flexicontent_download_history` (
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`user_id` int(11) NOT NULL,
							`file_id` int(11) NOT NULL,
							`hits` int(11) NOT NULL,
							`last_hit_on` datetime NOT NULL,
							PRIMARY KEY (`id`),
							KEY `user_id` (`user_id`),
							KEY `file_id` (`file_id`)
						) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";
					}

					if ( !$dl_coupons_tbl_exists ) {
						$queries[] = "
						CREATE TABLE `#__flexicontent_download_coupons` (
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`user_id` int(11) NOT NULL,
							`file_id` int(11) NOT NULL,
							`token` varchar(255) NOT NULL,
							`hits` int(11) NOT NULL,
							`hits_limit` int(11) NOT NULL,
							`expire_on` datetime NOT NULL default '1000-01-01 00:00:00',
							PRIMARY KEY (`id`),
							KEY `user_id` (`user_id`),
							KEY `file_id` (`file_id`),
							KEY `token` (`token`)
						) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";
					}


					if ( !$file_usage_tbl_exists ) {
						$queries[] = "
						CREATE TABLE IF NOT EXISTS `#__flexicontent_file_usage` (
							`id` int(11) NOT NULL,
							`context` varchar(255) NOT NULL,
							`file_id` int(11) NOT NULL default '0',
							`prop` varchar(255) NOT NULL,
							KEY  `id` (`id`),
							KEY  `file_id` (`file_id`),
							KEY  `context` (`context`),
							KEY  `prop` (`prop`)
						) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";
					}

					$upgrade_count = 0;
					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								$upgrade_count++;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
						echo '<span class="badge bg-success badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge bg-info badge-info">nothing to do</span>';
					?>
					</td>
				</tr>

		<?php
		// Create layouts_conf table if it does not exist
		?>
				<tr class="row1">
					<td class="key" style="font-size:11px;">Create/Upgrade layouts configuration DB table: </td>
					<td>
					<?php

			    $queries = array();
					if ( !$layouts_conf_tbl_exists ) {
						$queries[] = "
						CREATE TABLE IF NOT EXISTS `#__flexicontent_layouts_conf` (
						  `template` varchar(50) NOT NULL default '',
						  `cfgname` varchar(50) NOT NULL default '',
						  `layout` varchar(20) NOT NULL default '',
						  `attribs` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
						  PRIMARY KEY  (`template`,`cfgname`,`layout`)
						) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`";
					}

					$upgrade_count = 0;
					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								$upgrade_count++;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
						echo '<span class="badge bg-success badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge bg-info badge-info">nothing to do</span>';
					?>
					</td>
				</tr>

		<?php
		// Create fields table if it does not exist
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Create/Upgrade fields DB table: </td>
					<td>
					<?php

			    $queries = array();
					if ( !$fields_tbl_exists ) {
						$queries[] = "ALTER TABLE #__flexicontent_fields MODIFY description TEXT NOT NULL";
					}

					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								echo ($count_rows = $db->getAffectedRows()) ?
									'<span class="badge bg-success badge-success">'.$count_rows.' effected rows </span>' :
									'<span class="badge bg-info badge-info">no changes</span>' ;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
					}
					else echo '<span class="badge bg-info badge-info">nothing to do</span>';
					?>
					</td>
				</tr>

		<?php
		// Decide if search re-indexing is needed
		?>
				<tr class="row1">
					<td class="key" style="font-size:11px;">Set re-index needed flag to fields</td>
					<td>
					<?php
			    $queries = array();

					// Check if there is need to update the search-index
			    $version_needs_search_reindex = version_compare($this->release_existing, '3.0.10', '<');

					if ($fields_tbl_exists && $version_needs_search_reindex)
					{
						$db->setQuery('SELECT COUNT(*) FROM #__flexicontent_items_ext LIMIT 1');
						$has_items = $db->loadResult();
						if ($has_items)
						{
							// Set dirty SEARCH properties of published fields to be ON
							$set_clause = ' SET'.
								' issearch = CASE issearch WHEN 1 THEN 2   ELSE issearch   END,'.
								' isfilter = CASE isfilter WHEN 1 THEN 2   ELSE isfilter   END,'.
								' isadvsearch = CASE isadvsearch WHEN 1 THEN 2   ELSE isadvsearch   END,'.
								' isadvfilter = CASE isadvfilter WHEN 1 THEN 2   ELSE isadvfilter   END';
							$queries[] = 'UPDATE #__flexicontent_fields'. $set_clause	." WHERE published=1";
						}
					}

			    $version_needs_values_fix = version_compare($this->release_existing, '3.3.6', '<');

					if ($fields_tbl_exists && $version_needs_values_fix)
					{
						$db->setQuery('SELECT COUNT(*) FROM #__flexicontent_items_ext LIMIT 1');
						$has_items = $db->loadResult();

						if ($has_items)
						{
							// Set dirty Flag to force fixing values via re-indexing
							$set_clause = ' SET'.
								' issearch = CASE issearch WHEN 1 THEN 2   ELSE issearch   END,'.
								' isfilter = CASE isfilter WHEN 1 THEN 2   ELSE isfilter   END,'.
								' isadvsearch = CASE isadvsearch WHEN 1 THEN 2   ELSE isadvsearch   END,'.
								' isadvfilter = CASE isadvfilter WHEN 1 THEN 2   ELSE isadvfilter   END';
							$queries[] = 'UPDATE #__flexicontent_fields ' . $set_clause	. ' WHERE field_type=' . $db->Quote('date');
						}
					}

					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								echo ($count_rows = $db->getAffectedRows()) ?
									'<span class="badge bg-success badge-success">'.$count_rows.' effected rows </span>' :
									'<span class="badge bg-info badge-info">no changes</span>' ;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
					}
					else echo '<span class="badge bg-info badge-info">nothing to do</span>';
					?>
					</td>
				</tr>

			</tbody>
		</table>
		<?php
		//echo JHtml::_('bootstrap.endSlide');
		//echo JHtml::_('bootstrap.endAccordion');
		?>

		<div class="alert alert-success" style="margin: 8px 0 64px 0;">
			<span class="btn btn-info" onclick="window.open('index.php?option=com_flexicontent','_self');" style="cursor: pointer;">
				<?php echo JText::_('FLEXI_DASHBOARD'); ?>
  		</span>
			<?php echo JText::_('COM_FLEXICONTENT_PLEASE_COMPLETE_POST_INSTALL_TASKS_AT_DASHBOARD'); ?>
  	</div>

		<?php
		/* This code maybe used in the future to automate post-installation tasks
			<tr>
				<td valign="top">
					<?php // echo JText::_('Choose an option to finish the install :'); ?>
				</td>
				<td valign="top" width="100%" style="font-weight: bold; color: red; font-size: 14px;">
					<a href="index.php?option=com_flexicontent&task=finishinstall&action=newinstall" style="font-weight: bold; color: red; font-size: 14px;">
		    		<?php // echo JText::_('New install'); ?>
					</a>&nbsp;&nbsp;|&nbsp;&nbsp;
					<a href="index.php?option=com_flexicontent&task=finishinstall&action=update" style="font-weight: bold; color: red; font-size: 14px;">
		    		<?php // echo JText::_('Update an existing install'); ?>
					</a>
				</td>
			</tr>
		*/
	}

	/*
	* $parent is the class calling this method
	* uninstall runs before any other action is taken (file removal or database processing).
	*/
	function uninstall( $parent )
	{
		// Display fatal errors, warnings, notices
		error_reporting(E_ERROR || E_WARNING || E_NOTICE);
		ini_set('display_errors',1);

		$app = JFactory::getApplication();

		// Extra CSS needed for J3.x+
		echo FLEXI_J40GE
			? '<link type="text/css" href="components/com_flexicontent/assets/css/j3x.css" rel="stylesheet">'
			: '<link type="text/css" href="components/com_flexicontent/assets/css/j4x.css" rel="stylesheet">';

		// Installed component manifest file version
		$this->release = $parent->getManifest()->version;
		echo '<div class="alert alert-info" style="margin:32px 0px 8px 0px;">' .'Uninstalling FLEXIcontent '.$this->release. '</div>';

		// init vars
		$error = false;
		$extensions = array();
		$db = JFactory::getDbo();
		$dbprefix = $app->getCfg('dbprefix');
		$dbname   = $app->getCfg('db');

		// Uninstall additional flexicontent modules/plugins found in Joomla DB,
		// This code part (for uninstalling additional extensions) originates from Zoo J1.5 Component:
		// Original uninstall.php file
		// @package   Zoo Component
		// @author    YOOtheme http://www.yootheme.com
		// @copyright Copyright (C) 2007 - 2009 YOOtheme GmbH
		// @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
		$manifest = isset($parent) ? $parent->getParent()->manifest : $this->manifest;
		$additional = $manifest->xpath('additional');
		$additional = count($additional) ? reset($additional) : NULL;

		if ( is_object($additional) && count( $additional->children() ) )
		{
			$exts = $additional->children();
			foreach ($exts as $ext)
			{
				// set query
				switch ( $ext->getName() )
				{
					case 'plugin':
						if( $ext->attributes()->instfolder )
						{
							$query = 'SELECT * FROM #__extensions'
								.' WHERE type='.$db->Quote($ext->getName())
								.'  AND element='.$db->Quote( $ext->attributes()->name )
								.'  AND folder='.$db->Quote( $ext->attributes()->instfolder )
								;
							// query extension id and client id
							$db->setQuery($query);
							$res = $db->loadObject();

							$res_id = (int)( @$res->extension_id );
							$extensions[] = array(
								'name' => strip_tags( $ext->asXml() ),
								'type' => $ext->getName(),
								'id' => $res_id,
								'client_id' => isset($res->client_id) ? $res->client_id : 0,
								'installer' => new JInstaller(),
								'status' => false
							);
						}
						break;
					case 'module':
						$query = 'SELECT * FROM #__extensions'
							.' WHERE type='.$db->Quote($ext->getName())
							.'  AND element='.$db->Quote($ext->attributes()->name)
							;
						// query extension id and client id
						$db->setQuery($query);
						$res = $db->loadObject();

						$res_id = (int)( @$res->extension_id );
						$extensions[] = array(
							'name' => $ext->asXml(),
							'type' => $ext->getName(),
							'id' => $res_id,
							'client_id' => isset($res->client_id) ? $res->client_id : 0,
							'installer' => new JInstaller(),
							'status' => false);
						break;
				}
			}
		}

		// uninstall additional extensions
		for ($i = 0; $i < count($extensions); $i++) {
			$extension =& $extensions[$i];

			if ($extension['id'] > 0 && $extension['installer']->uninstall($extension['type'], $extension['id'], $extension['client_id'])) {
				$extension['status'] = true;
			}
		}

		?>
		<div class="alert alert-warning" style="margin:24px 0px 2px 0px; width: 300px;"><?php echo JText::_('COM_FLEXICONTENT_ADDITIONAL_EXTENSIONS'); ?></div>
		<table class="adminlist">
			<thead>
				<tr>
					<th style="text-align:left; width:500px;">
						<span class="label"><?php echo JText::_('COM_FLEXICONTENT_EXTENSION'); ?></span>
					</th>
					<th style="text-align:left">
						<span class="label"><?php echo JText::_('COM_FLEXICONTENT_STATUS'); ?></span>
					</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="2">&nbsp;</td>
				</tr>
			</tfoot>
			<tbody>
				<?php foreach ($extensions as $i => $ext) : ?>
					<tr class="row<?php echo ($i+1) % 2; ?>">
						<td class="key" style="font-size:11px;">[<?php echo JText::_($ext['type']); ?>] <?php echo $ext['name']; ?></td>
						<td>
							<?php $status_class = $ext['status'] ? 'badge bg-success badge-success' : 'badge badge-error'; ?>
							<span class="<?php echo $status_class; ?>"><?php echo $ext['status'] ? JText::_('COM_FLEXICONTENT_UNINSTALLED') : JText::_('uninstall FAILED'); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<br/>
		<table class="adminlist">
			<thead>
				<tr>
					<th style="text-align:left; width:500px;">
						<span class="label"><?php echo JText::_('COM_FLEXICONTENT_TASKS'); ?></span>
					</th>
					<th style="text-align:left">
						<span class="label"><?php echo JText::_('COM_FLEXICONTENT_STATUS'); ?></span>
					</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="2">&nbsp;</td>
				</tr>
			</tfoot>
			<tbody>

		<?php
		// Alter com_flexicontent jcomments comments to be com_content comments
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Restore jComments comment to be of com_content Type</td>
					<td>
						<?php
						$queries = array();

						$query = 'SHOW TABLES LIKE "' . JFactory::getApplication()->getCfg('dbprefix') . 'jcomments"';
						$db->setQuery($query);
						$jcomments_tbl_exists = (boolean) count($db->loadObjectList());
						if ($jcomments_tbl_exists) {
							$queries['jcomments'] = 'UPDATE #__jcomments AS j SET j.object_group="com_content" WHERE j.object_group="com_flexicontent" ';
							$queries['jcomments_objects'] = 'UPDATE #__jcomments_objects AS j SET j.object_group="com_content" WHERE j.object_group="com_flexicontent" ';
						}

						if ( !empty($queries) ) {
							$count_rows = 0;
							foreach ($queries as $tbl => $query) {
								$db->setQuery($query);
								try {
									$db->execute();
									if ($tbl=='jcomments') $count_rows = (int)$db->getAffectedRows();
								}
								catch (Exception $e) {
									echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
									break;
								}
							}
							if ( $count_rows ) {
								echo '<span class="badge bg-success badge-success">'.JText::_("Comments restored").' ('.$count_rows.' effected rows)</span>';
							} else echo '<span class="badge bg-info badge-info">restoring not needed</span>';
						}
						else echo '<span class="badge bg-info badge-info">jComments not installed, nothing to do</span>';
						?>
					</td>
				</tr>
		<?php
		// Restore com_content component asset, as asset parent_id, for the top-level 'com_content' categories
		?>
				<tr class="row1">
					<td class="key" style="font-size:11px;">Restore com_content top-level category assets</td>
					<td>
						<?php
						$asset	= JTable::getInstance('asset');
						$asset_loaded = $asset->loadByName('com_content');  // Try to load component asset for com_content
						if (!$asset_loaded) {
							$result = 0;
						} else {
							$cc_asset	= JTable::getInstance('asset');
							$query = 'SELECT s.id FROM #__assets AS s'
								.' JOIN #__categories AS c ON s.id=c.asset_id'
								.' WHERE c.parent_id=1 AND c.extension="com_content"';
							$db->setQuery($query);
							$asset_ids = $db->loadColumn();

							$result = 2;
							foreach ($asset_ids as $asset_id)
							{
								//echo $asset_id." parent to -> " .$asset->id ."<br/>";
								$cc_asset->load($asset_id);
								$cc_asset->parent_id = $asset->id;
								$cc_asset->lft = $asset->rgt;
								$cc_asset->setLocation($asset->id, 'last-child');

								// Save the category asset (create or update it)
								if (!$cc_asset->check() || !$cc_asset->store(false)) {
									echo $cc_asset->getError();
									echo " Problem restoring asset with id: ".$cc_asset ->id;
									//echo " Problem for category with id: ".$category->id. "(".$category->title.")";
									//echo $cc_asset->getError();
									$result = 1;
								}
							}
						}

						$status_class = $result==2 ? 'badge bg-success badge-success' : 'badge badge-error';
						?>
						<span class="<?php echo $status_class; ?>"><?php
						if ($result==2) {
							echo JText::_("Assets restored");
						} else if ($result==1) {
							echo JText::_("Failed to restore some assets");
						} else {
							echo JText::_("Failed to load asset for com_content.");
						}
						?></span>
					</td>
				</tr>



		<?php
		// Drop search tables
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Remove search tables</td>
					<td>
						<?php
						$tbl_prefix = $dbprefix.'flexicontent_advsearch_index_field_';
						$query = "SELECT TABLE_NAME
							FROM INFORMATION_SCHEMA.TABLES
							WHERE TABLE_SCHEMA = '".$dbname."' AND TABLE_NAME LIKE '".$tbl_prefix."%'
							";
						$db->setQuery($query);
						$tbl_names = $db->loadColumn();

						$count_removed = 0;
						if (count($tbl_names)) {
							foreach($tbl_names as $tbl_name) {
								$db->setQuery( 'DROP TABLE '.$tbl_name );
								try {
									$db->execute();
									$count_removed++;
								}
								catch (Exception $e) {
									echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
									continue;
								}
							}
							echo '<span class="badge bg-success badge-success">table(s) removed: '.$count_removed.'</span>';
						}
						else echo '<span class="badge bg-info badge-info">nothing to do</span>';
						?>
					</td>
				</tr>


		<?php
		// Remove template overrides, TODO make this more robust ...
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Remove template overrides</td>
					<td>
						<?php
						// Get DEFAULT backend ('administrator') and frontend ('site') Joomla template names
						$admin_tmpl = $app->getTemplate();
						$site_tmpl  = $db->setQuery('SELECT template FROM #__template_styles WHERE client_id = 0 AND home = 1')->loadResult();

						$tmpl_override_files = array(
							JPATH_ADMINISTRATOR . '/templates/' . $admin_tmpl . '/html/com_media/images/default_fc.php',
							JPATH_ADMINISTRATOR . '/templates/' . $admin_tmpl . '/html/com_media/imageslist/default_fc.php',
							JPATH_SITE . '/templates/' . $site_tmpl . '/html/com_media/images/default_fc.php',
							JPATH_SITE . '/templates/' . $site_tmpl . '/html/com_media/imageslist/default_fc.php',
						);

						$count_removed = 0;

						if (!empty($tmpl_override_files))
						{
							foreach($tmpl_override_files as $file)
							{
								if (JFile::exists($file))
								{
									if (!JFile::delete($file))
									{
										echo 'Cannot delete legacy file: ' . $file . '<br />';
									}
									else $count_removed++;
								}
							}
							echo '<span class="badge bg-success badge-success">template override(s) removed: ' . $count_removed . '</span>';
						}
						else echo '<span class="badge bg-info badge-info">nothing to do</span>';
						?>
					</td>
				</tr>

			</tbody>
		</table>
			<?php
	}

	/*
	* get a variable from the manifest file (actually, from the manifest cache).
	*/
	public function getExistingManifest( $name='com_flexicontent', $type='component' )
	{
		static $paramsArr = null;
		if ($paramsArr !== null) return $paramsArr;

		$db = JFactory::getDbo();
		$db->setQuery( 'SELECT manifest_cache FROM #__extensions WHERE element = '. $db->quote($name) .' AND type= '. $db->quote($type) );
		$manifest_cache =  $db->loadResult();

		$paramsArr = json_decode($manifest_cache, true );
		return $paramsArr;
	}

	/*
	* sets parameter values in the component's row of the extension table
	*/
	private function _setComponentParams($param_array)
	{
		if ( count($param_array) > 0 )
		{
			// read the existing component value(s)
			$db = JFactory::getDbo();
			$db->setQuery('SELECT params FROM #__extensions WHERE element = "com_flexicontent"');
			$params = json_decode( $db->loadResult(), true );
			// add the new variable(s) to the existing one(s)
			foreach ( $param_array as $name => $value ) {
				$params[ (string) $name ] = (string) $value;
			}
			// store the combined new and existing values back as a JSON string
			$paramsString = json_encode( $params );
			$db->setQuery('UPDATE #__extensions SET params = ' .
			$db->quote( $paramsString ) .
			' WHERE name = "com_flexicontent"' );
			$db->execute();
		}
	}


	/*
	 * Rename Extension's Legacy Parameters to new names
	 */
	private function _renameExtensionLegacyParameters($map, $dbtbl_name, $dbcol_name, $record_id)
	{
		// Load parameters directly from DB
		$db = JFactory::getDbo();
		$query = 'SELECT ' . $dbcol_name
			. ' FROM #__' . $dbtbl_name . ' '
			. ' WHERE '
			. '  id = ' . (int) $record_id
			;
		$attribs = $db->setQuery($query)->loadResult();

		// Decode parameters
		$_attribs = json_decode($attribs);

		$update_needed = false;

		// Set old parameter values into new parameters, removing the old parameter values
		foreach($map as $old => $new)
		{
			if (isset($_attribs->$old))
			{
				// Set new parameter value and remove legacy parameter value
				$_attribs->$new = $_attribs->$old;
				unset($_attribs->$old);

				$update_needed = true;
			}
		}

		if ($update_needed)
		{
			// Re-encode parameters
			$attribs = json_encode($_attribs);

			// Store field parameter back to the DB
			$query = 'UPDATE #__' . $dbtbl_name . ''
				.' SET ' . $dbcol_name . '=' . $db->Quote($attribs)
				.' WHERE id = ' . (int) $record_id
				;
			$db->setQuery($query)->execute();
		}

		return $update_needed;
	}


	/*
	 * Set extension parameters of matching extension records to specific values
	 */
	private function _setExtensionParameters(
			$tbl = '#__flexicontent_fields',
			$match_cols = array('field_type' => 'minigallery'),
			$id_col = 'id',
			$attr_col = 'attribs',
			$attr_vals = array(),  // For an example see minigallery case
			$attr_vals_fix = array()  // For an example see groupmarker case
	)
	{
		$db = JFactory::getDbo();
		
		$where = array();
		foreach($match_cols as $col => $val)
		{
			$where[]= $db->QuoteName($col) . ' = ' . $db->Quote($val);
		}

		$query = 'SELECT ' . $id_col . ', ' . $attr_col .
			' FROM ' . $tbl .
			' WHERE ' . implode(' AND ', $where)
			;
		$records = $db->setQuery($query)->loadObjectList();

		foreach($records as $r)
		{
			$r->$attr_col = json_decode($r->$attr_col);

			foreach($attr_vals as $i => $v)
			{
				$r->$attr_col->$i = $v;
			}

			foreach($attr_vals_fix as $i => $m)
			{
				if ($r->$attr_col->$i === $m[0])
				{
					$r->$attr_col->$i = $m[1];
				}
			}

			$r->$attr_col = json_encode($r->$attr_col);

			$query = 'UPDATE ' . $tbl .
				' SET ' . $attr_col . '=' . $db->Quote($r->$attr_col) .
				' WHERE ' . $id_col . ' = ' . $r->$id_col
				;
			$result = $db->setQuery($query)->execute();
		}
	}


	/*
	 * Deprecate 'minigallery' field type as 'image' field type
	 */
	private function _deprecate_field_minigallery($old_type, $new_type, & $msg, & $n)
	{
		$msg[$n++] = $this->_setExtensionParameters(
			$tbl = '#__flexicontent_fields',
			$match_cols = array('field_type' => 'minigallery'),
			$id_col = 'id',
			$attr_col = 'attribs',
			$attr_vals = array('allow_multiple' => 1, 'image_source' => 0, 'target_dir' => 0, 'popuptype' => 7)
		);

		$this->_deprecate_field($old_type, $new_type, $msg, $n);
	}


	/*
	 * Deprecate 'groupmarker' field type as 'image' field type
	 */
	private function _deprecate_field_groupmarker($old_type, $new_type, & $msg, & $n)
	{
		$msg[$n++] = $this->_setExtensionParameters(
			$tbl = '#__flexicontent_fields',
			$match_cols = array('field_type' => 'groupmarker'),
			$id_col = 'id',
			$attr_col = 'attribs',
			$attr_vals = array(),
			$attr_vals_fix = array('marker_type' => array('html_separator', 'custom_html') /* param2 => array('old value', 'new value')...*/)
		);

		$this->_deprecate_field($old_type, $new_type, $msg, $n);
	}


	/*
	 * Deprecate '$old_type' field type as '$new_type' field type
	 */
	private function _deprecate_field($old_type, $new_type, & $msg, & $n)
	{
		$db = JFactory::getDbo();

		$query = 'UPDATE #__flexicontent_fields'
			.' SET field_type = ' .$db->Quote($new_type)
			.' WHERE field_type = ' .$db->Quote($old_type);

		try {
			$db->setQuery($query)->execute();
		}
		catch (Exception $e) {
			$msg[$n] = '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
			return;
		}

		$count_rows = $db->getAffectedRows();
		$msg[$n] = '<span class="label label-'.($count_rows ? 'warning' : 'info').'">'.$count_rows.'</span><span class="label">'.$old_type.'</span> &nbsp; ';

		$query = 'SELECT *, extension_id AS id '
			.' FROM #__extensions'
			.' WHERE type="plugin"'
			.'  AND element='.$db->Quote( $old_type )
			.'  AND folder='.$db->Quote( 'flexicontent_fields' );

		$ext = $db->setQuery($query)->loadAssoc();

		if ($ext && $ext['id'] > 0)
		{
			$installer = new JInstaller();

			$msg[$n] = $installer->uninstall($ext['type'], $ext['id'], (int)$ext['client_id'])
				? '<br/>'.$msg[$n].', uninstalling plugin: <span class="badge bg-success badge-success">success</span> <br/>'
				: '<br/>'.$msg[$n].', uninstalling plugin: <span class="badge badge-error">failed</span> <br/>';
		}
	}
}
