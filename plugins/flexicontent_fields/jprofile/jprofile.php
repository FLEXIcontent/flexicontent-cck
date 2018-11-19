<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsJProfile extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		return false;
	}



	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		static $users = null;
		if ($users === null)
		{
			$users = array();
			jimport('joomla.user.helper');
			JFactory::getLanguage()->load('com_users', JPATH_SITE, 'en-GB', $force_reload = false);
			JFactory::getLanguage()->load('com_users', JPATH_SITE, null, $force_reload = false);
		}

		$displayed_user = $field->parameters->get('displayed_user', 1);
		switch($displayed_user)
		{
			// Current user
			case 3:
				if ( !isset($users[-1]) ) {
					$users[-1] = $users[$user_id] = new JUser();
				} else {
					$user = $users[-1];
				}
				$user_id = $users[-1]->id;
				$user = $users[-1];
				break;

			// User selected in item form
			case 2:
				$user_id = (int) reset($field->value);
				if ( !isset($users[$user_id]) ) {
					$user = new JUser($user_id);
				} else {
					$user = $users[$user_id];
				}
				break;

			// Item's author
			default:
			case 1:
				$user_id = $item->created_by;
				if ( !isset($users[$user_id]) ) {
					$user = new JUser($item->created_by);
				} else {
					$user = $users[$user_id];
				}
				break;
		}

		$user->params = new JRegistry($user->params);
		$user->params = $user->params->toArray();

		$user->profile = JUserHelper::getProfile( $user_id );
		//echo "<pre>"; echo print_r($user); echo "</pre>";

		$field->{$prop} = '
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

		$profile_info = array();
		if (!empty($user->profile->profile)) foreach($user->profile->profile as $pname => $pval) {
			$profile_info[] = '
				<dt><span class="label">'.$pname.'</span></dt>
				<dd>'.$pval.'</dd>';
		}
		if (count($profile_info))
			$field->{$prop} .= '
			<br/>
			<span class="alert alert-info fc-iblock" style="min-width:50%; margin-bottom:0px;">Profile details</span>
			<dl class="dl-horizontal">
				'. implode('', $profile_info).'
			</dl>';

		//$userProfile = JUserHelper::getProfile( $user_id );
		//echo "Main Address :" . $userProfile->profile['address1'];
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	/*function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}*/


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	/*function onAfterSaveField( &$field, &$post, &$file, &$item ) {
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}*/


	// Method called just before the item is deleted to remove custom item data related to the field
	/*function onBeforeDeleteField(&$field, &$item) {
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}*/



	// ***
	// *** CATEGORY/SEARCH FILTERING METHODS
	// ***

	// Method to display a search filter for the advanced search view
	/*function onAdvSearchDisplayFilter(&$filter, $value='', $formName='searchForm')
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;
	}*/


 	// Method to get the active filter result (an array of item ids matching field filter, or subquery returning item ids)
	// This is for search view
	/*function getFilteredSearch(&$filter, $value, $return_sql=true)
	{
		if ( !in_array($filter->field_type, static::$field_types) ) return;
	}*/



	// ***
	// *** SEARCH / INDEXING METHODS
	// ***

	// Method to create (insert) advanced search index DB records for the field values
	/*function onIndexAdvSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->isadvsearch && !$field->isadvfilter ) return;
		return true;
	}*/


	// Method to create basic search index (added as the property field->search)
	/*function onIndexSearch(&$field, &$post, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
		if ( !$field->issearch ) return;
		return true;
	}*/



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	function getUserProfile_FC()
	{
		$authordescr_item_html = false;

		// Retrieve author configuration
		$authorparams = flexicontent_db::getUserConfig($item->created_by);

		// Render author profile
		if ( $authordescr_itemid = $authorparams->get('authordescr_itemid') )
		{
			$app = JFactory::getApplication();
			$saved_view = $app->input->get('view', '', 'cmd');

			$app->input->set('view', 'module');
			$flexi_html_helper = new flexicontent_html();
			$authordescr_item_html = $flexi_html_helper->renderItem($authordescr_itemid);
			$app->input->set('view', $saved_view);
		}

		return $authordescr_item_html;
	}


	function getUserProfile_Joomla()
	{
		return 'getUserProfile_Joomla() is empty';
	}


	function getUserProfile_CB()
	{
		return 'getUserProfile_CB() is empty';
	}

}
