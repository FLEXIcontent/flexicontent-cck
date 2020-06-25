<?php

// Display parameters
$link_to_profile       = (int) $field->parameters->get('link_to_profile', 1);
$display_jprofile_data = (int) $field->parameters->get('display_jprofile_data', 0);   // TO BE REMOVED
$display_text          = $field->parameters->get('display_text', '{{user->name}} <a href="__profile_url__" class=&quot;btn&quot;>FLEXI_FIELD_JP_VIEW_PROFILE</a>');

static $ext_installed = null;

if ($ext_installed === null)
{
	$ext_installed = array();

	// Check if Communit Builder is installed and active
	$destpath = JPATH_SITE.DS.'components'.DS.'com_profiler';
	$ext_installed['com_comprofiler'] = JFolder::exists($destpath); // && JPluginHelper::isEnabled('system', 'comprofiler');

	// Check if Jomsocial is installed and active
	$destpath = JPATH_SITE.DS.'components'.DS.'com_community';
	$ext_installed['com_community'] = JFolder::exists($destpath); // && JPluginHelper::isEnabled('system', 'community');
}


// Cache contacts and try to minimize number of DB queries
static $jcontacts = null;

if ($link_to_profile === 1)
{
	$uids = array();

	foreach ($values as $value)
	{
		if (!isset($jcontacts[$value]))
		{
			$uids[] = (int) $value;
		}
	}

	if ($uids)
	{
		$query = 'SELECT * FROM #__contact_details WHERE user_id IN (' . implode(',', $uids) . ')';
		$contacts = JFactory::getDbo()->setQuery($query)->loadObjectList('user_id');

		foreach ($uids as $uid)
		{
			$jcontacts = isset($contacts[$uid]) ? $contacts[$uid] : false;
		}
	}
}


$n = 0;

foreach ($values as $value)
{
	// Skip empty value, adding an empty placeholder if field inside in field group
	if ( !strlen($value) )
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= '';
		}
		continue;
	}

	$user_id = $value;

	// Create a new user object if needed
	if (!isset(static::$users[$user_id]))
	{
		$user = new JUser($user_id);
		$user->profile = JUserHelper::getProfile($user_id);

		static::$users[$user_id] = $user;
	}
	else
	{
		$user = static::$users[$user_id];
	}

	$html = '';

	/*
	// TO BE REMOVED
	if ($display_jprofile_data === 1)
	{
		$html .= '
		<span class="alert alert-info fc-iblock" style="min-width:50%; margin-bottom:0px;">'.JText::_('COM_USERS_PROFILE_CORE_LEGEND').'</span><br/>
		<dl class="dl-horizontal">
			<dt><span class="label">
				'.JText::_('COM_USERS_PROFILE_NAME_LABEL').'
			</span><dt>
			<dd>
				'.$user->name.'
			</dd>
			<dt><span class="label">
				'.JText::_('COM_USERS_PROFILE_USERNAME_LABEL').'
			</span><dt>
			<dd>
				'.htmlspecialchars($user->username).'
			</dd>
			<dt><span class="label">
				'.JText::_('COM_USERS_PROFILE_REGISTERED_DATE_LABEL').'
			</span><dt>
			<dd>
				'.JHtml::_('date', $user->registerDate).'
			</dd>
			<dt><span class="label">
				'.JText::_('COM_USERS_PROFILE_LAST_VISITED_DATE_LABEL').'
			</span><dt>
			'.
			($user->lastvisitDate != '0000-00-00 00:00:00' ? '
				<dd>
					'.JHtml::_('date', $user->lastvisitDate).'
				</dd>
			' : '
				<dd>
					'.JText::_('COM_USERS_PROFILE_NEVER_VISITED').'
				</dd>
			').'
		</dl>
		';
	}*/
	
	if ($link_to_profile)
	{
		$matches = null;
		$link_text = $display_text;

		$result = preg_match_all("/\%\%([^%]+)\%\%/", $link_text, $matches);
		if (!empty($matches[1]))
		{
			foreach ($matches[1] as $translate_string)
			{
				$link_text = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $link_text);
			}
		}

		$result = preg_match_all("/\{\{(item->)([a-zA-Z_0-9]+)\}\}/", $link_text, $matches);
		if ($result)
		{
			$fulltexts = $matches[0];
			$propnames = $matches[2];

			foreach($fulltexts as $i => $f)
			{
				$propvalue = isset($item->{$propnames[$i]}) ? $item->{$propnames[$i]} : '';
				$link_text = str_replace($f, $propvalue, $link_text);
			}
		}

		$result = preg_match_all("/\{\{(user->)([a-zA-Z_0-9]+)\}\}/", $link_text, $matches);
		if ($result)
		{
			$fulltexts = $matches[0];
			$propnames = $matches[2];

			foreach($fulltexts as $i => $f)
			{
				$propvalue = isset($user->{$propnames[$i]}) ? $user->{$propnames[$i]} : '';
				$link_text = str_replace($f, $propvalue, $link_text);
			}
		}
	}


	/**
	 * Joomla Contact page
	 */
	//Self: JRoute::_('index.php?option=com_users&view=profile');
	//User: --

	if ($link_to_profile === 1)
	{
		if (!empty($contacts[$user->id]))
		{
			$link = JRoute::_('index.php?option=com_contact&view=contact&id=' . $contacts[$user->id]->id);
			$html .= str_replace('__profile_url__', $link, $link_text);
		}
		else
		{
			$html .= '
			<div class="fc-iblock alert alert-info">' .
				' (' . $user->name . ') : ' .
				JText::_('FLEXI_FIELD_JP_NO_CONTACT_PAGE_THIS_USER') . '
			</div>';
		}
	}


	/**
	 * Community Builder Profile
	 */
	//Self: JRoute::_('index.php?option=com_comprofiler&task=userprofile');
	//User: JRoute::_('index.php?option=com_comprofiler&task=userprofile&user=' . user_id);

	if ($link_to_profile === 2)
	{
		if (1 || $ext_installed['com_comprofiler'])
		{
			$link = JRoute::_('index.php?option=com_comprofiler&task=userprofile&user=' . $user_id);
			$html .= str_replace('__profile_url__', $link, $link_text);
		}
		else
		{
			$html .= '<div class="fc-iblock alert alert-warning">Community Builder extension not installed / not enabled</div>';
		}
	}


	/**
	 * Jomsocial Profile
	 */
	//include_once JPATH_ROOT.'/components/com_community/libraries/core.php';
	//Self: CRoute::_('index.php?option=com_community&view=profile);
	//User: CRoute::_('index.php?option=com_community&view=profile&userid=' . $user_id);

	if ($link_to_profile === 3)
	{
		if ($ext_installed['com_community'])
		{
			$link = CRoute::_('index.php?option=com_community&view=profile&userid=' . $user_id);
			$html .= str_replace('__profile_url__', $link, $link_text);
		}
		else
		{
			$html .= '<div class="fc-iblock alert alert-warning">Jomsocial extension not installed / not enabled</div>';
		}
	}

	// Get CUser object
	//$js_user = CFactory::getUser($userid);
	//$avatarImgUrl = $js_user->getThumbAvatar();
	//echo '<img src="'.$avatarUrl.'">';

	/*$profile_info = array();

	if (!empty($user->profile->profile))
	{
		foreach($user->profile->profile as $pname => $pval)
		{
			$profile_info[] = '
				<dt><span class="label">' . $pname . '</span></dt>
				<dd>' . $pval . '</dd>';
		}
	}

	if (count($profile_info))
	{
		$html .= '
		<br/>
		<span class="alert alert-info fc-iblock" style="min-width:50%; margin-bottom:0px;">Profile details</span>
		<dl class="dl-horizontal">
			' . implode('', $profile_info) . '
		</dl>';
	}*/

	//$userProfile = JUserHelper::getProfile( $user_id );
	//echo "Main Address :" . $userProfile->profile['address1'];


	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $html . $posttext;

	// Add microdata to every value if field -- is -- in a field group
	if ($is_ingroup && $itemprop) $field->{$prop}[$n] = '<div style="display:inline" itemprop="'.$itemprop.'" >' .$field->{$prop}[$n]. '</div>';

	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}