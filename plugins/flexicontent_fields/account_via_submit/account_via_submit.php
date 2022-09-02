<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright � 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsAccount_via_submit extends FCField
{
	//static $prior_to_version = "3.2";  // Display message for non free plugin
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}



	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$field->label = $field->parameters->get('label_form') ? JText::_($field->parameters->get('label_form')) : JText::_($field->label);

		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );

		$tooltip_class = 'hasTooltip';
		$add_on_class    = $cparams->get('bootstrap_ver', 2)==2  ?  'add-on' : 'input-group-addon';
		$input_grp_class = $cparams->get('bootstrap_ver', 2)==2  ?  'input-append input-prepend' : 'input-group';
		$form_font_icons = $cparams->get('form_font_icons', 1);
		$font_icon_class = $form_font_icons ? ' fcfont-icon' : '';
		$font_icon_class .= FLEXI_J40GE ? ' icon icon- ' : '';


		// ****************
		// Number of values
		// ****************
		$required   = (int) $field->parameters->get('required', 0);
		$required   = $required ? ' required' : '';


		// *************
		// Email address
		// *************

		// Input field display size & max characters
		$size       = (int) $field->parameters->get( 'size', 30 ) ;
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;   // client/server side enforced
		$inputmask	= $field->parameters->get( 'inputmask', 'email' ) ;

		// create extra HTML TAG parameters for the form field
		$attribs = $field->parameters->get( 'extra_attributes', '' ) ;
		if ($maxlength) $attribs .= ' maxlength="'.$maxlength.'" ';
		$attribs .= ' size="'.$size.'" ';
		$classes = $required;

		static $inputmask_added = false;
	  if ($inputmask && !$inputmask_added) {
			$inputmask_added = true;
			flexicontent_html::loadFramework('inputmask');
		}
		if ($inputmask) {
			$attribs .= " data-inputmask=\" 'alias': 'email' \" ";
			$classes .= ' has_inputmask';
		}
		$classes .= ' validate-email';


		// **********************************
		// Full, first, last names (optional)
		// **********************************

		// Optional properties
		$usefull   = $field->parameters->get( 'use_full', 0 ) ;
		$usefirst  = $field->parameters->get( 'use_first', 0 ) ;
		$uselast   = $field->parameters->get( 'use_last', 0 ) ;

		if ( !$field->parameters->get('initialized',0) ) $this->initialize($field);

		// Initialise property with default value
		if ( !$item->id ) {
			$value = array();
			$value['addr'] = '';
			$value['full'] = '';
			$value['first'] = '';
			$value['last'] = '';
		}

		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;

		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.']';
		$elementid = 'custom_'.$field->name;

		$js = '';
		$css = '
		div[class^="fcfield_avs_box-"] {
			clear: both;
			float: left !important;
		}
		';
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);


		/**
		 * Create field's HTML display for item form
		 */

		$field->html = array();

		$user = JFactory::getUser();

		if ($item->id)
		{
			if ($field->parameters->get('display_item_owner', 0))
			{
				$owner = JFactory::getUser($item->created_by);
			  $field->html[] = '
			  <div class="'.$input_grp_class.' fc-xpended">
				  <span class="' . $add_on_class . ' fc_acc_via_mail_item_owner">'.JText::_( 'FLEXI_ACCOUNT_V_SUBMIT_ITEM_OWNER' ).'</span>
				  <span class="' . $add_on_class . '" style="font-weight: bold; background: #f7f7f7; padding-left: 32px; padding-right: 32px;">
				  	<span class="fc_acc_via_mail_owner_name">'.$owner->name.'</span>
				  	<span class="fc_acc_via_mail_owner_uname">['.$owner->username.']</span>
				  </span>
				 </div>
				 ';
				//<span class="fc_acc_via_mail_owner_uid">: '.$owner->id.'</span>';
			}
		}
		elseif ($user->id)
		{
			if ($field->parameters->get('display_when_logged', 0))
			{
			  $field->html[] = '
			  <div class="'.$input_grp_class.' fc-xpended">
				  <span class="' . $add_on_class . ' fc_acc_via_mail_logged_as">'.JText::_( 'FLEXI_ACCOUNT_V_SUBMIT_LOGGED_AS' ).'</span>
				  <span class="' . $add_on_class . '" style="font-weight: bold; background: #f7f7f7; padding-left: 32px; padding-right: 32px;">
			  		<span class="fc_acc_via_mail_owner_name">'.$user->name.'</span>
			  		<span class="fc_acc_via_mail_owner_uname">['.$user->username.']</span>
				  </span>
				 </div>
				';
				//<span class="fc_acc_via_mail_owner_uid">: '.$user->id.'</span>';
			}
		}
		else
		{
			$n = 0;
			$fieldname_n = $fieldname.'['.$n.']';
			$elementid_n = $elementid.'_'.$n;

			$value['addr'] = !empty($value['addr']) ? $value['addr'] : '';
			$addr = '
				<div class="fcfield_avs_box-addr nowrap_box">
					<label class="label">'.JText::_( 'FLEXI_ACCOUNT_V_SUBMIT_ADDR' ).'</label>
					<input class="avs_addr fcfield_textval'.$classes.'" name="'.$fieldname_n.'[addr]" id="'.$elementid_n.'" type="text" value="'.htmlspecialchars(JStringPunycode::emailToUTF8($value['addr']), ENT_COMPAT, 'UTF-8').'" '.$attribs.' />
				</div>';

			$full = '';

			if ($usefull)
			{
				$value['full'] = !empty($value['full']) ? $value['full'] : '';
				$full = '
				<div class="fcfield_avs_box-full nowrap_box">
					<label class="label">'.JText::_( 'FLEXI_ACCOUNT_V_SUBMIT_FULLNAME' ).'</label>
					<input class="avs_full fcfield_textval" name="'.$fieldname_n.'[full]" type="text" size="'.$size.'" value="'.htmlspecialchars($value['full'], ENT_COMPAT, 'UTF-8').'" />
				</div>';
			}

			$first = '';

			if ($usefirst)
			{
				$value['first'] = !empty($value['first']) ? $value['first'] : '';
				$first = '
				<div class="fcfield_avs_box-first nowrap_box">
					<label class="label">'.JText::_( 'FLEXI_ACCOUNT_V_SUBMIT_FIRSTNAME' ).'</label>
					<input class="avs_first fcfield_textval" name="'.$fieldname_n.'[first]" type="text" size="'.$size.'" value="'.htmlspecialchars($value['first'], ENT_COMPAT, 'UTF-8').'" />
				</div>';
			}

			$last = '';

			if ($uselast)
			{
				$value['last'] = !empty($value['last']) ? $value['last'] : '';
				$last = '
				<div class="fcfield_avs_box-last nowrap_box">
					<label class="label">'.JText::_( 'FLEXI_ACCOUNT_V_SUBMIT_LASTNAME' ).'</label>
					<input class="avs_last fcfield_textval" name="'.$fieldname_n.'[last]" type="text" size="'.$size.'" value="'.htmlspecialchars($value['last'], ENT_COMPAT, 'UTF-8').'" />
				</div>';
			}

			$field->html[] = '
				'.$addr.'
				'.$full.'
				'.$first.'
				'.$last.'
				';
		}

		if (!count($field->html) && $field->formhidden != 4)
		{
			$field->html[] = '';
		}

		$field->html = !count($field->html)
			? null
			: '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}



	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	public function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// Check if item is an existing item, being modified, if so then nothing to do
		$isnew = $item->_isnew;
		if (!$isnew) return;

		// Check if user is logged, if so then nothing to do
		$user = JFactory::getUser();
		if ($user->id)
		{
			$post = array();
			return;
		}

		// Check if not inside form
		$app = JFactory::getApplication();
		$layout = $app->input->get('layout', '', 'cmd');
		$task   = $app->input->get('task', '', 'cmd');
		if ( $layout != "form" && $task != 'add' && $task != 'edit' ) return;


		// Server side validation
		$maxlength  = (int) $field->parameters->get( 'maxlength', 0 ) ;

		// Make sure posted data is an array
		$post = !is_array($post) ? array($post) : $post;
		if ( !isset($post[0]) )
		{
			JFactory::getApplication()->enqueueMessage('empty FORM data for field: Account via submit', 'error');
			return false;
		}


		// **************************************************************
		// Validate data, skipping values that are empty after validation
		// **************************************************************

		$email = flexicontent_html::dataFilter($post[0]['addr'], $maxlength, 'EMAIL', 0);  // Clean bad text/html

		// Cancel item creation, if email is invalid
		if ( !$email || !JMailHelper::isEmailAddress($email) )
		{
			$error	=
				JText::sprintf('FLEXI_ACCOUNT_V_SUBMIT_INVALID_EMAIL', $post[0]['addr']).' '.
				JText::_('FLEXI_ACCOUNT_V_SUBMIT_PROVIDE_VALID_EMAIL');
			JFactory::getApplication()->enqueueMessage($error, 'error');
			return false;
		}

		$full  = flexicontent_html::dataFilter(@$post[0]['full'], 0, 'STRING', 0);
		$first = flexicontent_html::dataFilter(@$post[0]['first'], 0, 'STRING', 0);
		$last  = flexicontent_html::dataFilter(@$post[0]['last'], 0, 'STRING', 0);
		$password = JUserHelper::genRandomPassword(8);
		$gender = flexicontent_html::dataFilter(@$post[0]['gender'], 0, 'STRING', 0);
		if(!$gender || !in_array($gender, array('M', 'F'))) {
			$gender = 'M';
		}
		$name = trim($full ? $full : $first.' '.$last);
		$name = $name ? $name : $email;

		// Make sure field is initialized
		$this->initialize($field);

		// Check email already used
		$db = JFactory::getDbo();
		$db->setQuery("SELECT id FROM #__users WHERE email='$email'");
		$existingUserID = $db->loadResult();

		// HANDLE existing user
		if ( $existingUserID )
		{
			// Fail if auto-using existing email not enabled
			if ( $field->parameters->get('handle_existing_email', 0)==0 )
			{
				$error = JText::sprintf('FLEXI_ACCOUNT_V_SUBMIT_EMAIL_EXISTS', $email);
				JFactory::getApplication()->enqueueMessage($error, 'error');
				return false;
			}
			// Account with given email exists, set as item's author
			$item->created_by = $existingUserID;
		}

		// CREATE new user
		else if ( $field->parameters->get('create_accounts', 0) )
		{
			$username = $email; // EMAIL used as username
			$newUserID = $this->registerUser($name, $username, $email, $password, $gender, $field);

			// Cancel item creation, if email creation returns false
			if ($newUserID === false)
			{
				$error = JText::_('FLEXI_ACCOUNT_V_SUBMIT_ACCOUNT_CREATION_FAILED');
				JFactory::getApplication()->enqueueMessage($error, 'error');
				return false;
			}
			// Account with given email created, set as item's author
			$item->created_by = $newUserID;
		} else {
			// item will have the 'default' owner ...
		}


		// CREATE EDIT COUPON
		$create_coupons = $field->parameters->get( 'create_coupons', 0 ) ;
		if ($create_coupons)
		{
			$token = uniqid();
			$query = 'INSERT #__flexicontent_edit_coupons '
				. 'SET timestamp = '.time()
				. ', email = '.$db->Quote($email)
				. ', token = ' . $db->Quote($token)
				. ', id = ' . $item->id
				;
			$db->setQuery( $query );
			$db->execute();
			$res = $this->sendEditCoupon($item, $field, $email, $token);
			if (!$res) {
				// Delete edit coupon and cancel item creation if email coupon sending failed ??
				$query = 'DELETE FROM #__flexicontent_edit_coupons WHERE id = '.$item->id;
				$db->setQuery( $query );
				$db->execute();
				return false;
			}
		}
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	public function onAfterSaveField( &$field, &$post, &$file, &$item )
	{
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	public function onBeforeDeleteField(&$field, &$item)
	{
	}



	// ***
	// *** VARIOUS HELPER METHODS
	// ***

	function initialize(&$field)
	{
		if ( $field->parameters->get('initialized',0) ) return;  // initialization already done

		// Execute SQL installation file
		$sql_file = dirname(__FILE__) .DS. 'installation' .DS. 'install.mysql.utf8.sql';
		flexicontent_db::execute_sql_file($sql_file);

		$attribs = json_decode($field->attribs);
		$attribs->initialized = 1;
		$attribs = json_encode($attribs);

		$db = JFactory::getDbo();
		$query = "UPDATE #__flexicontent_fields SET attribs=".$db->Quote($attribs) ." WHERE id = ".$field->id;
		$result = $db->setQuery($query)->execute();
	}


	public static function registerUser($name, $username, $email, $password, $gender, &$field)
	{
		// Initialize new usertype setting
		jimport('joomla.user.user');
		jimport('joomla.user.helper');
		jimport('cms.component.helper');
		JFactory::getLanguage()->load('com_users', JPATH_SITE, 'en-GB', false);
		JFactory::getLanguage()->load('com_users', JPATH_SITE, null, true);

		$app = JFactory::getApplication();
		$db = JFactory::getDbo();
		$usersConf = JComponentHelper::getParams( 'com_users' );

		$useractivation = $field->parameters->get('useractivation', $usersConf->get('useractivation', 2)); // Default: use Joomla com_users setting (2=user self-activation)
		$new_usertype   = $field->parameters->get('new_usertype',  $usersConf->get('useractivation', 2));  // Default: use Joomla com_users setting (2=registered)
		$mail_to_admin  = $field->parameters->get('mail_to_admin', $usersConf->get('mail_to_admin', 1));  // Default: use Joomla com_users setting (1=enabled)
		$adm_email_bcc  = $field->parameters->get('adm_email_bcc', 1);  // Default: send single email to admins as BCC

		// Add 'salt' to password
		$password_clear = $password;
		$salt     = JUserHelper::genRandomPassword(32);
		$crypted  = JUserHelper::getCryptedPassword($password_clear, $salt);
		$password = $crypted.':'.$salt;

		$instance = JUser::getInstance();
		$instance->set('id',       0);
		$instance->set('name',     $name);
		$instance->set('username', $username);
		$instance->set('password', $password);
		$instance->set('password_clear' , $password_clear);
		$instance->set('email',    $email);
		$instance->set('usertype', 'deprecated');
		$instance->set('groups',   array($new_usertype));

		// Here is possible set user profile details
		$instance->set('profile',  array('gender' =>  $gender));

		// Email with activation link
		if ($useractivation == 2 || $useractivation == 1)
		{
			$instance->set('activation', JApplication::getHash(JUserHelper::genRandomPassword()));
			$instance->set('block', 1);
		}

		// Load the users plugin group, and create the user
		JPluginHelper::importPlugin('user');
		if (!$instance->save())
		{
			// Email already used!!!
			$db->setQuery("SELECT id FROM #__users WHERE email='$email'");
			$existingUserID = $db->loadResult();
			if ($existingUserID) return -$existingUserID;
			else return false;
		}

		// Make sure user was created
		$db->setQuery("SELECT id FROM #__users WHERE email='$email'");
		$newUserID = $db->loadResult();
		$user = JFactory::getUser($newUserID);

		// User creation failed ?? !!
		if ( !$user->id )  return false;

		$data = $user->getProperties();
		$data['fromname'] = $app->getCfg('fromname');
		$data['mailfrom'] = $app->getCfg('mailfrom');
		$data['sitename'] = $app->getCfg('sitename');
		$data['siteurl'] = JUri::root();
		$data['password_clear'] = $password_clear;


		switch ($useractivation) {
		case 2:  // self-activate via link in email
		case 1:  // verify via link in email, then admin is notified and activates the account
			// Set the link to confirm the user email (activation URL)
			$uri = JUri::getInstance();
			$base = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port'));
			$data['activate'] = $base . JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

			$emailSubject = JText::sprintf( 'COM_USERS_EMAIL_ACCOUNT_DETAILS', $data['name'], $data['sitename'] );
			$emailBody = JText::sprintf(
				($useractivation == 2 ? 'COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY' : 'COM_USERS_EMAIL_REGISTERED_WITH_ACTIVATION_BODY'),
				$data['name'], $data['sitename'],
				$data['activate'], $data['siteurl'],
				$data['username'], $data['password_clear']
			);
			break;

		case 0:   // Instant account activation without verification, just notify the user of his/her account
		default:
			$emailSubject = JText::sprintf( 'COM_USERS_EMAIL_ACCOUNT_DETAILS', $data['name'], $data['sitename'] );
			$emailBody = JText::sprintf(
				'COM_USERS_EMAIL_REGISTERED_BODY',
				$data['name'], $data['sitename'],
				$data['siteurl'],
				$data['username'], $data['password_clear']
			);
			break;
		}

		// Clean the email data
		//$emailSubject = JMailHelper::cleanSubject($emailSubject);
		//$emailBody    = JMailHelper::cleanBody($emailBody);
		//$fromname     = JMailHelper::cleanAddress($data['fromname']);
		$recipient = array($email);

		$html_mode=true; $cc=null; $bcc=null;
		$attachment=null; $replyto=null; $replytoname=null;

		// Send the email
		try {
			$send_result = JFactory::getMailer()->sendMail(
				$data['mailfrom'], $data['fromname'], $recipient, $emailSubject, $emailBody,
				$html_mode, $cc, $bcc, $attachment, $replyto, $replytoname
			);
		} catch(\Exception $e) {
			$send_result = false;
		}

		if ( !$send_result )
		{
			JFactory::getApplication()->enqueueMessage(JText:: _ ('COM_USERS_REGISTRATION_SEND_MAIL_FAILED'), 'warning');
			return false;
		}

		// Send Notification mail to administrators
		if ($useractivation < 2 && $mail_to_admin == 1)
		{
			$emailSubject = JText::sprintf( 'COM_USERS_EMAIL_ACCOUNT_DETAILS', $data['name'], $data['sitename'] );
			$emailBody    = JText::sprintf(
				'COM_USERS_EMAIL_REGISTERED_NOTIFICATION_TO_ADMIN_BODY',
				$data['name'], $data['username'],	$data['siteurl']
			);

			// Get all admin users
			$rows = $db->setQuery(
				$db->getQuery(true)
					->select($db->quoteName(array('name', 'email', 'sendEmail')))
					->from($db->quoteName('#__users'))
					->where($db->quoteName('sendEmail') . ' = ' . 1)
			)->loadObjectList();

			// Send single mail to all superadministrators id
			$adm_emails = array();
			foreach ($rows as $row)
			{
				if ($adm_email_bcc && $row->email == $data['mailfrom']) continue;
				$adm_emails[] = $row->email;
			}
			$recipient = $adm_email_bcc ? array($data['mailfrom']) : $adm_emails;
			$bcc       = $adm_email_bcc ? $adm_emails : null;

			// Remove main recepient from BCC, to avoid email failing
			if ($bcc)
			{
				$_bcc_ = array_flip($bcc);
				if ( isset($_bcc_[$data['mailfrom']]) ) unset($bcc[$_bcc_[$data['mailfrom']]]);
			}

			// Send the email
			try {
				$send_result = JFactory::getMailer()->sendMail(
					$data['mailfrom'], $data['fromname'], $recipient, $emailSubject, $emailBody,
					$html_mode, $cc, $bcc, $attachment, $replyto, $replytoname
				);
			} catch(\Exception $e) {
				$send_result = false;
			}

			if ( !$send_result )
			{
				JFactory::getApplication()->enqueueMessage(JText:: _('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'), 'warning');
				return false;
			}
		}

		return $newUserID;
	}


	function sendEditCoupon(&$item, &$field, $email, $token)
	{
		$db  = JFactory::getDbo();
		$app = JFactory::getApplication();

		$SiteName	= $app->getCfg('sitename');
		$mailfrom = $app->getCfg('mailfrom');
		$fromname = $app->getCfg('fromname');

		// Check for a valid from address
		if (! $mailfrom || ! JMailHelper::isEmailAddress($mailfrom))
		{
			$warning = JText::sprintf('FLEXI_ACCOUNT_V_SUBMIT_INVALID_EMAIL', $mailfrom);
			JFactory::getApplication()->enqueueMessage($warning, 'warning');
		}

		$subject = JText::sprintf('FLEXI_ACCOUNT_V_SUBMIT_YOUR_NEW_ITEM_AT', $SiteName);
		$desc    = JText::_( $field->parameters->get('coupon_desc'), '...' );
		$link = JRoute::_(
			JUri::root(false).
			//'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW.'&cid='.$item->catid.'&id='.$item->id.
			FlexicontentHelperRoute::getItemRoute($item->id, $item->catid).
			'&task=edit&edittok='.$token
		);

		// Build the message to send
		$body  = JText::sprintf('FLEXI_ACCOUNT_V_SUBMIT_EDIT_LINK_SEND_INFO', $SiteName, $fromname, $mailfrom, $link);
		$body	.= "\n\n".$desc; // Extra text

		// Clean the email data
		$emailSubject = JMailHelper::cleanSubject($subject);
		$emailBody    = JMailHelper::cleanBody($body);
		$fromname     = JMailHelper::cleanAddress($fromname);
		$recipient = array($email);

		$html_mode=true; $cc=null; $bcc=null;
		$attachment=null; $replyto=null; $replytoname=null;

		// Send the email
		try {
			$send_result = JFactory::getMailer()->sendMail(
				$mailfrom, $fromname, $recipient, $emailSubject, $emailBody,
				$html_mode, $cc, $bcc, $attachment, $replyto, $replytoname
			);
		} catch(\Exception $e) {
			$send_result = false;
		}

		if ( !$send_result )
		{
			JFactory::getApplication()->enqueueMessage(JText:: _ ('FLEXI_ACCOUNT_V_SUBMIT_EDIT_LINK_NOT_SENT'), 'warning');
			return false;
		}
		return true;
	}

}
