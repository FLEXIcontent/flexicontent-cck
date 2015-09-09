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
if (!defined('FLEXI_J16GE') || !defined('FLEXI_J30GE')) {
	jimport( 'joomla.version' );  $jversion = new JVersion;
}
if (!defined('FLEXI_J16GE'))   define('FLEXI_J16GE', version_compare( $jversion->getShortVersion(), '1.6.0', 'ge' ) );
if (!defined('FLEXI_J30GE'))   define('FLEXI_J30GE', version_compare( $jversion->getShortVersion(), '3.0.0', 'ge' ) );

class com_flexicontentInstallerScript
{
	/*
	* $parent is the class calling this method.
	* $type is the type of change (install, update or discover_install, not uninstall).
	* preflight runs before anything else and while the extracted files are in the uploaded temp folder.
	* If preflight returns false, Joomla will abort the update and undo everything already done.
	*/
	function preflight( $type, $parent ) {
		
		// Make sure that fatal errors are printed
		error_reporting(E_ERROR);
		ini_set('display_errors',1);
		
		// Try to increment some limits
		
		@set_time_limit( 150 );    // try to set execution time 2.5 minutes
		ignore_user_abort( true ); // continue execution if client disconnects
		
		// Try to increment memory limits
		$memory_limit	= trim( @ini_get( 'memory_limit' ) );
		if ( $memory_limit ) {
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
			if ( $memory_limit < 16000000 ) @ini_set( 'memory_limit', '16M' );
			if ( $memory_limit < 32000000 ) @ini_set( 'memory_limit', '32M' );
			if ( $memory_limit < 64000000 ) @ini_set( 'memory_limit', '64M' );
		}
		
		// first check if PHP v5.3.0 or later is running
		$PHP_VERSION_NEEDED = '5.3.0';
		if (version_compare(PHP_VERSION, $PHP_VERSION_NEEDED, '<'))
		{
			// load english language file for 'com_flexicontent' component then override with current language file
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		
			Jerror::raiseWarning(null, JText::sprintf( 'FLEXI_UPGRADE_PHP_VERSION_GE', $PHP_VERSION_NEEDED ));
			return false;
		}
		
		// Get existing manifest data
		$existing_manifest = $this->getExistingManifest();
		
		// Get Joomla version
		$jversion = new JVersion();
		
		// File version of new manifest file
		$this->release = $parent->get( "manifest" )->version;
		
		// File version of existing manifest file
		$this->release_existing = $existing_manifest[ 'version' ];
		
		// Manifest file minimum Joomla version
		$this->minimum_joomla_release = $parent->get( "manifest" )->attributes()->version;
		
		// !!! *** J2.5 no longer supported ***, For J2.5 require other minimum
		/*if( version_compare( $jversion->getShortVersion(), '3.0', 'lt' ) )
		{
			$this->minimum_joomla_release = '2.5.0';
		}*/
		?>
		
		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="adminlist">
			<tr>
				<td valign="top">
		    	<img src="<?php echo 'components/com_flexicontent/assets/images/logo.png'; ?>" style="width:300px; margin: 0px 48px 0px 0px;" alt="FLEXIcontent Logo" />
				</td>
				<td valign="top" width="100%">
	     	 	<span><?php echo JText::_('COM_FLEXICONTENT_DESCRIPTION'); ?></></span><br />
	      	<font class="small">by <a href="http://www.flexicontent.org" target="_blank">Emmanuel Danan</a>,
	      	<font class="small">by <a href="http://www.flexicontent.org" target="_blank">Georgios Papadakis</a>,
	      	<font class="small">by <a href="http://www.flexicontent.org" target="_blank">Berges Yannick</a>,
					<br/>
	      	<font class="small">and <a href="http://www.marvelic.co.th" target="_blank">Marvelic Engine Co.,Ltd.</a><br/>
				</td>
			</tr>
		<!--
			<tr>
				<td valign="top" style="font-weight: bold;">
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
		-->
		</table>
		
		<?php		
		echo '
		<div class="alert alert-info" style="margin:32px 0px 8px 0px; width:50%;">' .JText::_('Performing prior to installation tasks ... '). '</div>
		<ul>
			<li>
				'.JText::_('COM_FLEXICONTENT_REQUIRED_PHPVER').':
				'.JText::_('COM_FLEXICONTENT_MIN').' <span class="badge">' .$PHP_VERSION_NEEDED. '</span>
				'.JText::_('COM_FLEXICONTENT_CURRENT').' <span class="badge badge-success">' .PHP_VERSION. '</span>
			</li>
		';
		
		// Check that current Joomla release is not older than minimum required
		if( version_compare( $jversion->getShortVersion(), $this->minimum_joomla_release, 'lt' ) ) {
			echo '</ul>';
			Jerror::raiseWarning(null, 'Cannot install com_flexicontent in a Joomla release prior to '.$this->minimum_joomla_release);
			return false;
		} else {
			echo '
				<li>
					'.JText::_('COM_FLEXICONTENT_REQUIRED_JVER').':
					'.JText::_('COM_FLEXICONTENT_MIN').' <span class="badge">' .$this->minimum_joomla_release. '</span>
					'.JText::_('COM_FLEXICONTENT_CURRENT').' <span class="badge badge-success">' .$jversion->getShortVersion(). '</span>
				</li>';
		}
		
		// Print message about installing / updating / downgrading FLEXIcontent
		$downgrade_allowed = true;
		if ($type=='update')
		{
			if ( !$downgrade_allowed && version_compare( $this->release, $this->release_existing, 'l' ) )
			{
				$from_to = ''
					.JText::_('COM_FLEXICONTENT_FROM'). ' <span class="badge">' .$this->release_existing. '</span> '
					.JText::_('COM_FLEXICONTENT_TO'). ' <span class="badge badge-warning">' .$this->release. '</span> ';
				// ?? Abort if the component being installed is not newer than the currently installed version
				//echo '</ul>';
				Jerror::raiseWarning(null, 'Refusing to downgrade installation of com_flexicontent '.$from_to);
				return false;
				echo '</ul>';
				return false;  // Returning false here would abort
			}
		}
		
		echo '
		</ul>
		';
		
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
		<div class="alert alert-info" style="margin:32px 0px 8px 0px; width:50%;">' . JText::_('COM_FLEXICONTENT_INSTALLING')
			.' '.JText::_('COM_FLEXICONTENT_VERSION').'  <span class="badge badge-info">'.$this->release.'</span>
		</div>';
		if ( ! $this->do_extra( $parent ) ) return false;
		
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
		echo '<div class="alert alert-info" style="margin:32px 0px 8px 0px; width:50%;">' . JText::_('COM_FLEXICONTENT_UPDATING_INSTALLATION')
			.' '.JText::_('COM_FLEXICONTENT_VERSION').': ';
		if ( version_compare( $this->release, $this->release_existing, 'ge' ) ) {
			echo '
				'.JText::_('COM_FLEXICONTENT_FROM').' <span class="badge">'.$this->release_existing.'</span>
				'.JText::_('COM_FLEXICONTENT_TO').' <span class="badge badge-info">'.$this->release.'</span>';
		} else {
			echo '
				<span class="badge badge-info">'.JText::_('COM_FLEXICONTENT_DOWNGRADING').'</span>
				'.JText::_('COM_FLEXICONTENT_FROM'). ' <span class="badge">' .$this->release_existing. '</span>
				'.JText::_('COM_FLEXICONTENT_TO'). ' <span class="badge badge-info">' .$this->release. '</span>';
		}
		echo '</div>';
		
		if ( ! $this->do_extra( $parent ) ) return false;
		
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
		
		$db = JFactory::getDBO();
		
		// Parse XML file to identify additional extensions,
		// This code part (for installing additional extensions) originates from Zoo J1.5 Component:
		// Original install.php file
		// @package   Zoo Component
		// @author    YOOtheme http://www.yootheme.com
		// @copyright Copyright (C) 2007 - 2009 YOOtheme GmbH
		// @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
		$manifest = isset($parent) ? $parent->getParent()->manifest : $this->manifest;
		$source   = isset($parent) ? $parent->getParent()->getPath('source') : $this->parent->getPath('source');
		$additional = $manifest->xpath('additional');
		$additional = count($additional) ? reset($additional) : NULL;
		
		if ( is_object($additional) && count( $additional->children() ) ) {
	    $exts = $additional->children();
	    foreach ($exts as $ext) {
				$extensions[] = array(
					'name' => strip_tags( $ext->asXml() ),
					'type' => $ext->getName(),
					'folder' => $source.'/' . $ext->attributes()->folder,
					'ext_name' => ((string) $ext->attributes()->name),  // needs to be converted to string
					'ext_folder' => ((string) $ext->attributes()->instfolder),  // needs to be converted to string
					'installer' => new JInstaller(),
					'status' => null
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
			
			if ($jinstaller->install($extensions[$i]['folder'])) {
				$extensions[$i]['status'] = true;
				
				$ext_manifest = $jinstaller->getManifest();
				$ext_manifest_name = $ext_manifest->name;
				//if ($ext_manifest_name!=$extensions[$i]['name'])  echo $ext_manifest_name." - ".$extensions[$i]['name'] . "<br/>";
				
				// Force existing plugins/modules to use name found in each extension's manifest.xml file
				if (1) //if ( in_array($extensions[$i]['ext_folder'], array('flexicontent_fields', 'flexicontent', 'search', 'content', 'system')) || $extensions[$i]['type']=='module' )
				{
					$ext_tbl = '#__extensions';
					$query = 'UPDATE '.$ext_tbl
						//.' SET name = '.$db->Quote($extensions[$i]['name'])
						.' SET name = '.$db->Quote($ext_manifest_name)
						.' WHERE element = '.$db->Quote($extensions[$i]['ext_name'])
						.'  AND folder = '.$db->Quote($extensions[$i]['ext_folder'])
						.'  AND type = '.$db->Quote($extensions[$i]['type'])
						;
					$db->setQuery($query);
					$db->execute();
				}
			} else {
				$extensions[$i]['status'] = false;
				if ( !FLEXI_NEW_INSTALL ) {
					$error = true;
					break;
				}
			}
		}
		
		?>
				
		<div class="alert alert-warning" style="margin:24px 0px 12px 0px; width:240px;"><?php echo JText::_('COM_FLEXICONTENT_ADDITIONAL_EXTENSIONS'); ?></div>
		
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
					<tr class="row<?php echo $i % 2; ?>">
						<td class="key" style="font-size:11px;">[<?php echo JText::_($ext['type']); ?>] <?php echo $ext['name']; ?></td>
						<td>
							<?php
							if ($ext['status']===null)
								$status_class = 'badge';
							else if ($ext['status'])
								$status_class = 'badge badge-success';
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
										Jerror::raiseWarning(null, '<br/>'.$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .': '. $msg);
									}
								?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		// Rollback on installation errors, abort() will be called on every additional extension installed above
		if ($error) {
			for ($i = 0; $i < count($extensions); $i++) {
				if ( $extensions[$i]['status'] ) {
					$extensions[$i]['installer']->abort('<span style="color:black">'.
						$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .' '. JText::_('COM_FLEXICONTENT_INSTALLED') .':</span>'.
						' <span class="badge badge-warning">'. JText::_('rolling back').'</span>',
						$extensions[$i]['type']
					);
					//$extensions[$i]['status'] = false;
				} /*else if ( $extensions[$i]['status'] === false ) {
					$msg = ' <span style="color:red">'. JText::_('-- FAILED --').'</span>';
					Jerror::raiseWarning(null, '<span style="color:black">'.$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .' '. JText::_('Install') .':</span>'.$msg);
				} else {
					$msg = ' <span style="color:darkgray">'. JText::_('Skipped').'</span>';
					Jerror::raiseWarning(null, '<span style="color:black">'.$extensions[$i]['name'] .' '. JText::_($extensions[$i]['type']) .' '. JText::_('Install') .':</span>'.$msg);
				}*/
			}
			if (!FLEXI_J16GE) {
				$this->parent->abort("<br/>".JText::_('Component installation aborted'), 'component');
			} else {
				return false;  // In J1.6+ , returning false here will cancel (abort) component installation and rollback changes
			}
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
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		
		/*
		// always create or modify these parameters
		$params['my_param0'] = 'Component version ' . $this->release;
		$params['my_param1'] = 'Another value';

		// define the following parameters only if it is an original install
		if ( $type == 'install' ) {
			$params['my_param2'] = '4';
			$params['my_param3'] = 'Star';
		}
		
		$this->setParams( $params );*/
		JFactory::getApplication()->enqueueMessage('
			Please clear your frontend / backend Joomla cache once, <br/>
			- to make sure that any changes (e.g in filtering) take immediate effect<br/>
			In case of display issue, press CTRL+F5 / F5 / command+R, (Windows / Linux / Apple\'s Safari)<br/>
			- to make sure that latest FLEXIcontent JS/CSS is retrieved',
			'warning'
		);
		
		echo '<link type="text/css" href="components/com_flexicontent/assets/css/j3x.css" rel="stylesheet">';
		echo '
		<link type="text/css" href="components/com_flexicontent/assets/css/flexicontentbackend.css" rel="stylesheet">
		<div class="alert alert-info" style="margin:32px 0px 8px 0px; width:50%;">' .JText::_('Performing after installation tasks ... '). '</div>
		';
		?>
		
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
		$deprecated_fields = array('hidden'=>'text', 'relateditems'=>'relation', 'relateditems_backlinks'=>'relation_reverse');
		
		// Get DB table information
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_fields_item_relations"';
		$db->setQuery($query);
		$fi_rels_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_items_versions"';
		$db->setQuery($query);
		$fi_vers_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_favourites"';
		$db->setQuery($query);
		$favs_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_files"';
		$db->setQuery($query);
		$files_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_fields"';
		$db->setQuery($query);
		$fields_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_types"';
		$db->setQuery($query);
		$types_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_items_ext"';
		$db->setQuery($query);
		$iext_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_advsearch_index"';
		$db->setQuery($query);
		$advsearch_index_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_authors_ext"';
		$db->setQuery($query);
		$authors_ext_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_reviews"';
		$db->setQuery($query);
		$reviews_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_templates"';
		$db->setQuery($query);
		$templates_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_layouts_conf"';
		$db->setQuery($query);
		$layouts_conf_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_items_tmp"';
		$db->setQuery($query);
		$content_cache_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_download_history"';
		$db->setQuery($query);
		$dl_history_tbl_exists = (boolean) count($db->loadObjectList());
		
		$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_download_coupons"';
		$db->setQuery($query);
		$dl_coupons_tbl_exists = (boolean) count($db->loadObjectList());
		
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
									'<span class="badge badge-success">'.$count_rows.' effected rows </span>' :
									'<span class="badge badge-info">no changes</span>' ;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
					}
					else echo '<span class="badge badge-info">nothing to do</span>';
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
									'<span class="badge badge-success">'.$count_rows.' effected rows </span>' :
									'<span class="badge badge-info">no changes</span>' ;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
					}
					else echo '<span class="badge badge-info">nothing to do</span>';
					?>
					</td>
				</tr>
		
		<?php
		// Update DB table flexicontent_fields: Convert deprecated fields types to 'text' field type
		?>
				<tr class="row0">
					<td class="key" style="font-size:11px;">Converting deprecated fields types:
					<?php
					//echo '<br/><span class="label">' . implode('</span><span class="label">', array_keys($deprecated_fields)) . '</span>';
					$msg = array();
					$n = 0;
					if ($fields_tbl_exists) foreach ($deprecated_fields as $old_type => $new_type)
					{
						$query = 'UPDATE #__flexicontent_fields'
							.' SET field_type = ' .$db->Quote($new_type)
							.' WHERE field_type = ' .$db->Quote($old_type);
						$db->setQuery($query);
						try {
							$db->execute();
						}
						catch (Exception $e) {
							$msg[$n++] = '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
							continue;
						}
						
						$count_rows = $db->getAffectedRows();
						$msg[$n] = '<span class="label label-'.($count_rows ? 'warning' : 'info').'">'.$count_rows.'</span><span class="label">'.$old_type.'</span> &nbsp; ';
						
						$query = 'SELECT *, extension_id AS id '
							.' FROM #__extensions'
							.' WHERE type="plugin"'
							.'  AND element='.$db->Quote( $old_type )
							.'  AND folder='.$db->Quote( 'flexicontent_fields' );
						$db->setQuery($query);
						$ext = $db->loadAssoc();
						
						if ($ext && $ext['id'] > 0) {
							$installer = new JInstaller();
							if ( $installer->uninstall($ext['type'], $ext['id'], (int)$ext['client_id']) )
								$msg[$n] = '<br/>'.$msg[$n].', uninstalling plugin: <span class="badge badge-success">success</span> <br/>';
							else
								$msg[$n] = '<br/>'.$msg[$n].', uninstalling plugin: <span class="badge badge-error">failed</span> <br/>';
						}
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
					if ($iext_tbl_exists)    $tbls[] = "#__flexicontent_items_ext";
					if ($templates_tbl_exists)        $tbls[] = "#__flexicontent_templates";
					if ($content_cache_tbl_exists)    $tbls[] = "#__flexicontent_items_tmp";
					if ($advsearch_index_tbl_exists)  $tbls[] = "#__flexicontent_advsearch_index";
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
						if (!empty($_querycols)) $queries[] = $_query . implode(",", $_querycols);
					}
					
					if ($fi_rels_tbl_exists && !array_key_exists('suborder', $tbl_fields['#__flexicontent_fields_item_relations'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields_item_relations` ADD `suborder` INT(11) NOT NULL DEFAULT '1' AFTER `valueorder`";
					}
					if ($fi_vers_tbl_exists && !array_key_exists('suborder', $tbl_fields['#__flexicontent_items_versions'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_items_versions` ADD `suborder` INT(11) NOT NULL DEFAULT '1' AFTER `valueorder`";
					}
					
					/*if ($fi_rels_tbl_exists && !array_key_exists('qindex01', $tbl_fields['#__flexicontent_fields_item_relations'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields_item_relations` ADD `qindex01` MEDIUMTEXT NULL DEFAULT NULL AFTER `value`";
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
					if ( $files_tbl_exists && !array_key_exists('filename_original', $tbl_fields['#__flexicontent_files'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_files` ADD `filename_original` VARCHAR(255) NOT NULL DEFAULT '' AFTER `filename`";
					}
					if ( $files_tbl_exists && !array_key_exists('description', $tbl_fields['#__flexicontent_files'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_files` ADD `description` TEXT NOT NULL DEFAULT '' AFTER `altname`";
					}
					if ( $files_tbl_exists && !array_key_exists('language', $tbl_fields['#__flexicontent_files'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_files` ADD `language` CHAR(7) NOT NULL DEFAULT '*' AFTER `published`";
					}
					if ( $files_tbl_exists && !array_key_exists('size', $tbl_fields['#__flexicontent_files'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_files` ADD `size` INT(11) unsigned NOT NULL default '0' AFTER `hits`";
					}
					
					// Fields TABLE
					if ( $fields_tbl_exists && !array_key_exists('untranslatable', $tbl_fields['#__flexicontent_fields'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `untranslatable` TINYINT(1) NOT NULL DEFAULT '0' AFTER `isadvsearch`";
					}
					if ( $fields_tbl_exists && !array_key_exists('isadvfilter', $tbl_fields['#__flexicontent_fields'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `isadvfilter` TINYINT(1) NOT NULL DEFAULT '0' AFTER `isfilter`";
					}
					if ( $fields_tbl_exists && !array_key_exists('formhidden', $tbl_fields['#__flexicontent_fields'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `formhidden` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `untranslatable`";
					}
					if ( $fields_tbl_exists && !array_key_exists('valueseditable', $tbl_fields['#__flexicontent_fields'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `valueseditable` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `formhidden`";
					}
					if ( $fields_tbl_exists && !array_key_exists('edithelp', $tbl_fields['#__flexicontent_fields'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `edithelp` SMALLINT(8) NOT NULL DEFAULT '2' AFTER `formhidden`";
					}
					if ( $fields_tbl_exists && !array_key_exists('asset_id', $tbl_fields['#__flexicontent_fields']) ) {
						$queries[] = "ALTER TABLE `#__flexicontent_fields` ADD `asset_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `id`";
					}
					
					// Types TABLE
					if ( $types_tbl_exists && !array_key_exists('asset_id', $tbl_fields['#__flexicontent_types']) ) {
						$queries[] = "ALTER TABLE `#__flexicontent_types` ADD `asset_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `id`";
					}
					if ( $types_tbl_exists && !array_key_exists('itemscreatable', $tbl_fields['#__flexicontent_types'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_types` ADD `itemscreatable` SMALLINT(8) NOT NULL DEFAULT '0' AFTER `published`";
					}
					
					// Templates TABLE
					if ( $templates_tbl_exists && !array_key_exists('cfgname', $tbl_fields['#__flexicontent_templates'])) {
						$queries[] = "ALTER TABLE `#__flexicontent_templates` ADD `cfgname` varchar(50) NOT NULL default '' AFTER `template`";
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
						echo '<span class="badge badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge badge-info">nothing to do</span>';
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
						echo '<span class="badge badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
						if ($upgrade_count) echo ' Please <span class="badge badge-warning">re-index</span> your content';
					}
					else echo '<span class="badge badge-info">nothing to do</span>';
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
						echo '<span class="badge badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge badge-info">nothing to do</span>';
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
					if ( !$reviews_tbl_exists ) {
						$queries[] = "
						CREATE TABLE IF NOT EXISTS `#__flexicontent_reviews` (
							`content_id` int(11) NOT NULL,
							`type` int(11) NOT NULL DEFAULT '1',
							`average_rating` mediumtext NOT NULL,
							`custom_ratings` mediumtext NOT NULL DEFAULT '',
							`user_id` int(11) NOT NULL DEFAULT '0',
							`email` varchar(255) NOT NULL DEFAULT '',
							`title` varchar(255) NOT NULL,
							`text` mediumtext NOT NULL,
							`state` int(11) NOT NULL,
							`confirmed` int(11) NOT NULL,
							`submit_date` datetime NOT NULL,
							`update_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`custom_fields` mediumtext NULL,
							PRIMARY KEY (`content_id`, `type`),
							KEY `user_id` (`user_id`)
						) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`;";
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
						echo '<span class="badge badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge badge-info">nothing to do</span>';
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
							 `state` tinyint(3) NOT NULL DEFAULT '0',
							 `catid` int(10) unsigned NOT NULL DEFAULT '0',
							 `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							 `created_by` int(10) unsigned NOT NULL DEFAULT '0',
							 `modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							 `modified_by` int(10) unsigned NOT NULL DEFAULT '0',
							 `publish_up` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							 `publish_down` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							 `version` int(10) unsigned NOT NULL DEFAULT '1',
							 `ordering` int(11) NOT NULL DEFAULT '0',
							 `access` int(10) unsigned NOT NULL DEFAULT '0',
							 `hits` int(10) unsigned NOT NULL DEFAULT '0',
							 `featured` tinyint(3) unsigned NOT NULL DEFAULT '0',
							 `language` char(7) NOT NULL,
							 `type_id` int(11) NOT NULL DEFAULT '0',
							 `lang_parent_id` int(11) NOT NULL DEFAULT '0',
							 PRIMARY KEY (`id`)
							) ENGINE=MyISAM DEFAULT CHARSET=utf8;
						";
					} else {
						$_querycols = array();
						if (array_key_exists('sectionid', $tbl_fields['#__flexicontent_items_tmp'])) $_querycols[] = " DROP `sectionid`";  // Drop J1.5 sectionid
						if (!array_key_exists('type_id', $tbl_fields['#__flexicontent_items_tmp'])) $_querycols[] = " ADD `type_id` INT(11) NOT NULL DEFAULT '0' AFTER `language`";
						if (!array_key_exists('lang_parent_id', $tbl_fields['#__flexicontent_items_tmp'])) $_querycols[] = " ADD `lang_parent_id` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `type_id`";
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
						echo '<span class="badge badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge badge-info">nothing to do</span>';
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
							`expire_on` datetime NOT NULL default '0000-00-00 00:00:00',
							PRIMARY KEY (`id`),
							KEY `user_id` (`user_id`),
							KEY `file_id` (`file_id`),
							KEY `token` (`token`)
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
						echo '<span class="badge badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge badge-info">nothing to do</span>';
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
						echo '<span class="badge badge-success">table(s) created / upgraded: '.$upgrade_count.'</span>';
					}
					else echo '<span class="badge badge-info">nothing to do</span>';
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
						$queries[] = "ALTER TABLE #__flexicontent_fields MODIFY description TEXT NOT NULL default ''";
					}
					
					if ( !empty($queries) ) {
						foreach ($queries as $query) {
							$db->setQuery($query);
							try {
								$db->execute();
								echo ($count_rows = $db->getAffectedRows()) ?
									'<span class="badge badge-success">'.$count_rows.' effected rows </span>' :
									'<span class="badge badge-info">no changes</span>' ;
							}
							catch (Exception $e) {
								echo '<span class="badge badge-error">SQL Error</span> '. $e->getMessage() . '<br/>';
								continue;
							}
						}
					}
					else echo '<span class="badge badge-info">nothing to do</span>';
					?>
					</td>
				</tr>
				
			</tbody>
		</table>
		<?php
	}

	/*
	* $parent is the class calling this method
	* uninstall runs before any other action is taken (file removal or database processing).
	*/
	function uninstall( $parent ) {
		//error_reporting(E_ALL & ~E_STRICT);
		//ini_set('display_errors',1);
		$app = JFactory::getApplication();
		
		// Extra CSS needed for J3.x+
		echo '<link type="text/css" href="components/com_flexicontent/assets/css/j3x.css" rel="stylesheet">';
		
		// Installed component manifest file version
		$this->release = $parent->get( "manifest" )->version;
		echo '<div class="alert alert-info" style="margin:32px 0px 8px 0px; width:50%;">' .'Uninstalling FLEXIcontent '.$this->release. '</div>';
		
		// init vars
		$error = false;
		$extensions = array();
		$db = JFactory::getDBO();
		
		// Uninstall additional flexicontent modules/plugins found in Joomla DB,
		// This code part (for uninstalling additional extensions) originates from Zoo J1.5 Component:
		// Original uninstall.php file
		// @package   Zoo Component
		// @author    YOOtheme http://www.yootheme.com
		// @copyright Copyright (C) 2007 - 2009 YOOtheme GmbH
		// @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
		$manifest = isset($parent) ? $parent->getParent()->manifest : $this->manifest;
		$additional = & $manifest->xpath('additional');
		$additional = count($additional) ? reset($additional) : NULL;
		
		if ( is_object($additional) && count( $additional->children() ) )
		{
			$exts = & $additional->children();
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
		<div class="alert alert-warning" style="margin:24px 0px 12px 0px; width:240px;"><?php echo JText::_('COM_FLEXICONTENT_ADDITIONAL_EXTENSIONS'); ?></div>
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
							<?php $status_class = $ext['status'] ? 'badge badge-success' : 'badge badge-error'; ?>
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
								echo '<span class="badge badge-success">'.JText::_("Comments restored").' ('.$count_rows.' effected rows)</span>';
							} else echo '<span class="badge badge-info">restoring not needed</span>';
						}
						else echo '<span class="badge badge-info">jComments not installed, nothing to do</span>';
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
						
						$status_class = $result==2 ? 'badge badge-success' : 'badge badge-error';
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
						$tbl_prefix = $app->getCfg('dbprefix').'flexicontent_advsearch_index_field_';
						$query = "SELECT TABLE_NAME
							FROM INFORMATION_SCHEMA.TABLES
							WHERE TABLE_NAME LIKE '".$tbl_prefix."%'
							";
						$db->setQuery($query);
						$tbl_names = $db->loadColumn();
						
						$count_removed = 0;
						if (!count($tbl_names)) {
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
							echo '<span class="badge badge-success">table(s) removed: '.$count_removed.'</span>';
						}
						else echo '<span class="badge badge-info">nothing to do</span>';
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
	function getExistingManifest( $name='com_flexicontent', $type='component' ) {
		static $paramsArr = null;
		if ($paramsArr !== null) return $paramsArr;
		
		$db = JFactory::getDBO();
		$db->setQuery( 'SELECT manifest_cache FROM #__extensions WHERE element = '. $db->quote($name) .' AND type= '. $db->quote($type) );
		$manifest_cache =  $db->loadResult();
		
		$paramsArr = json_decode($manifest_cache, true );
		return $paramsArr;
	}

	/*
	* sets parameter values in the component's row of the extension table
	*/
	function setParams($param_array) {
		if ( count($param_array) > 0 ) {
			// read the existing component value(s)
			$db = JFactory::getDBO();
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
}