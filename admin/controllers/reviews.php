<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

// Lazy class loading (was JLoader::register): loaded only if/when actually used.
spl_autoload_register(static function ($class) {
	$map = array(
		'FlexicontentControllerBaseAdmin' => JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php',
	);
	if (isset($map[$class]) && is_file($map[$class])) require_once $map[$class];
}, true, true);

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'review.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'reviews.php';

/**
 * FLEXIcontent Reviews Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerReviews extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl = 'flexicontent_reviews';
	var $records_jtable = 'flexicontent_reviews';

	var $record_name = 'review';
	var $record_name_pl = 'reviews';

	var $_NAME = 'REVIEW';
	var $record_alias = 'not_applicable';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

	/**
	 * Internal voting mode: ajaxvote() is called by PHP code (e.g. when a review is saved),
	 * it throws exceptions on errors and returns the result, instead of sending a response
	 *
	 * @var boolean
	 */
	protected $voteInternal = false;

	/**
	 * Method to set the internal voting mode
	 *
	 * @param   boolean  $internal  True to enable the internal mode
	 *
	 * @return  void
	 */
	public function setVoteInternal($internal = true)
	{
		$this->voteInternal = (bool) $internal;
	}

	/**
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Language of the voting field (reviews strings live in the field's language file)
		\Joomla\CMS\Factory::getApplication()->getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, 'en-GB', true);
		\Joomla\CMS\Factory::getApplication()->getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, null, true);

		// Register task aliases
		$this->registerTask('add',          'edit');
		$this->registerTask('apply',        'save');
		$this->registerTask('apply_ajax',   'save');
		$this->registerTask('save2new',     'save');
		$this->registerTask('save2copy',    'save');

		$this->registerTask('exportxml', 'export');
		$this->registerTask('exportsql', 'export');
		$this->registerTask('exportcsv', 'export');

		$this->registerTask('unapproved', 'approved');

		// Can manage ACL
		$this->canManage = FlexicontentHelperPerm::getPerm()->CanReviews;

		// Error messages
		$this->err_locked_recs_changestate = 'FLEXI_ROW_STATE_NOT_MODIFIED_DUE_ASSOCIATED_DATA';
		$this->err_locked_recs_delete      = 'FLEXI_ROWS_NOT_DELETED_DUE_ASSOCIATED_DATA';

		// Warning messages
		$this->warn_locked_recs_skipped    = 'FLEXI_SKIPPED_N_ROWS_WITH_ASSOCIATIONS';
		$this->warn_noauth_recs_skipped    = 'FLEXI_SKIPPED_N_ROWS_UNAUTHORISED';
	}


	/**
	 * Logic to save a record
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function save()
	{
		$result = parent::save();

		if ($result !== false && $this->task === 'save' && $this->input->getCmd('tmpl') === 'component')
		{
			\Joomla\CMS\Factory::getApplication()->enqueueMessage(
				"<script>
					setTimeout(function(){
						window.parent.fc_closeDialog('fc_modal_popup_container');
					}, 2000);
				</script>"
				, 'success'
			);
		}
	}


	/**
	 * Check in a record
	 *
	 * @since	3.3
	 */
	public function checkin()
	{
		parent::checkin();
	}


	/**
	 * Cancel the edit, check in the record and return to the records manager
	 *
	 * @return bool
	 *
	 * @since 3.3
	 */
	public function cancel()
	{
		if ($this->input->getCmd('tmpl') === 'component')
		{
			\Joomla\CMS\Factory::getApplication()->enqueueMessage(
				"<script>
					window.parent.fc_closeDialog('fc_modal_popup_container');
				</script>"
				, 'success'
			);
		}

		return parent::cancel();
	}


	/**
	 * Logic to publish records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function publish()
	{
		parent::publish();
	}


	/**
	 * Logic to unpublish records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function unpublish()
	{
		parent::unpublish();
	}


	/**
	 * Logic to delete records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function remove()
	{
		// Remember the ratings of the selected reviews, they are also votes of the reviewed items
		$cid = ArrayHelper::toInteger($this->input->get('cid', array(), 'array'));
		$rated = array();

		if (count($cid))
		{
			$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
			$rated = $db->setQuery(
				'SELECT id, content_id, average_rating, custom_ratings FROM #__' . $this->records_dbtbl
				. ' WHERE id IN (' . implode(',', $cid) . ') AND content_id > 0 AND average_rating > 0'
			)->loadObjectList('id');
		}

		$result = parent::remove();

		// Remove the votes of the reviews that were actually deleted (permissions / locks may skip some records)
		if (count($rated))
		{
			$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
			$existing = $db->setQuery(
				'SELECT id FROM #__' . $this->records_dbtbl . ' WHERE id IN (' . implode(',', array_keys($rated)) . ')'
			)->loadColumn();

			foreach ($rated as $id => $row)
			{
				if (!in_array($id, $existing))
				{
					$this->_applyRatingChange((int) $row->content_id, (int) $row->average_rating, 0);

					if (trim((string) $row->custom_ratings) !== '')
					{
						$this->_applyCompositeChange((int) $row->content_id, (array) json_decode((string) $row->custom_ratings, true), array());
					}
				}
			}
		}

		return $result;
	}


	/**
	 * Method to toggle the approved setting of a list of records
	 *
	 * @return  void
	 *
	 * @since   3.3
	 */
	public function approved()
	{
		// Check for request forgeries
		\Joomla\CMS\Session\Session::checkToken() or jexit(\Joomla\CMS\Language\Text::_('JINVALID_TOKEN'));

		$app   = \Joomla\CMS\Factory::getApplication();
		$user  = \Joomla\CMS\Factory::getApplication()->getIdentity();

		$cid    = $this->input->get('cid', array(), 'array');
		$cid    = ArrayHelper::toInteger($cid);
		$values = array('approved' => 1, 'unapproved' => 0);
		$value  = ArrayHelper::getValue($values, $this->task, 0, 'int');

		// Access checks, these are done against the content item that is being reviewed
		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$content_ids = count($cid)
			? $db->setQuery('SELECT id, content_id FROM #__' . $this->records_dbtbl . ' WHERE id IN (' . implode(',', $cid) . ')')->loadAssocList('id', 'content_id')
			: array();

		foreach ($cid as $i => $id)
		{
			$content_id = isset($content_ids[$id]) ? (int) $content_ids[$id] : 0;

			// Reviews of an item are checked against the item, reviews without item (orphans) need the reviews management permission
			$can_change = $content_id
				? $user->authorise('core.edit.state', 'com_content.article.' . $content_id)
				: $this->canManage;

			if (!$can_change)
			{
				// Prune items that you can't change.
				unset($cid[$i]);
				$app->enqueueMessage(\Joomla\CMS\Language\Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'warning');
			}
		}

		if (empty($cid))
		{
			$app->setHeader('status', '500', true);
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$this->setRedirect($this->returnURL);

			return;
		}

		// Get the model.
		$record_model = $this->getModel($this->record_name);

		// Update approved flag (model will also handle cache cleaning)
		if (!$record_model->approved($cid, $value))
		{
			$app->enqueueMessage($record_model->getError(), 'error');
			$app->redirect($this->returnURL);
		}

		$message = $value == 1
			? \Joomla\CMS\Language\Text::plural('FLEXI_N_REVIEWS_APPROVED', count($cid))
			: \Joomla\CMS\Language\Text::plural('FLEXI_N_REVIEWS_UNAPPROVED', count($cid));
		$this->setRedirect($this->returnURL, $message);
	}


	/**
	 * Logic to create the view for record editing
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function edit()
	{
		$app      = \Joomla\CMS\Factory::getApplication();
		$user     = \Joomla\CMS\Factory::getApplication()->getIdentity();
		$session  = \Joomla\CMS\Factory::getApplication()->getSession();
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();

		$this->input->set('view', $this->record_name);
		$this->input->set('hidemainmenu', 1);

		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', $app->isClient('administrator') ? 'default' : 'form', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));

		// Get/Create the model
		$model = $this->getModel($this->record_name);

		$content_id  = $this->input->get('content_id', 0, 'int');
		$review_type = $this->input->get('review_type', 'item', 'cmd');

		// Try to load record by attributes in HTTP Request
		if ($content_id && $review_type && $user->id)
		{
			// First try to match review of the item submitted by current user id regardless of email
			$record = $model->getRecord(array(
				'content_id' => $content_id,
				'type' => $review_type,
				'user_id' => $user->id,
			));
			if ($record->id)
			{
				$app->enqueueMessage('Loaded your review for your account', '');
			}
			
			// Second try to user email with zero user id, for the case that 
			// the review was originally submitted as guest user, but user is now registered
			if (!$record->id && $user->email)
			{
				// First try to match user id
				$record = $model->getRecord(array(
					'content_id' => $content_id,
					'type' => $review_type,
					'user_id' => 0,
					'email' => $user->email,
				));

				if ($record->id)
				{
					$app->enqueueMessage('A review was found via your email', '');
				}
			}
			//echo '<pre>'; print_r($record); echo '</pre>';
		}

		// Initialize a record if not done already
		if (empty($record))
		{
			$record = $model->getItem();
		}

		$model->setState($this->getName() . '.id', $record->id);

		if (!$record->id)
		{
			$model->setState('content_id', $content_id);
			$model->setState('review_type', $review_type);
			$model->setState('user_id', $user->id);
			$model->setState('email', $user->email);
		}


		// Sanity checks before reviewing, content item exists, and reviewing are enabled
		$item = null;
		$errors = null;

		$this->_preChecks($content_id, $item, $model, $errors);

		if ($errors)
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(reset($errors), 'warning');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}


		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;

		// Calculate access
		$is_authorised = $model->canEdit($record);

		// Check access
		if (!$is_authorised)
		{
			if (!$user->id && $app->isClient('site'))
			{
				$cparams   = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
				$uri       = \Joomla\CMS\Uri\Uri::getInstance();
				$return    = strtr(base64_encode($uri->toString()), '+/=', '-_,');           // Current URL as return URL (but we will for id / cid)
				$login_url = $cparams->get('login_page', 'index.php?option=com_users&view=login');
				$login_url .= (strstr($login_url, '?') ? '&'  : '?') . 'tmpl=component&return='.$return;

				$app->enqueueMessage(\Joomla\CMS\Language\Text::_('Please register or login before you can post a review'), 'warning');
				$this->setRedirect($login_url);
			}
			else
			{
				$app->setHeader('status', '403 Forbidden', true);
				$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');

				if ($this->input->getCmd('tmpl') !== 'component')
				{
					$this->setRedirect($this->returnURL);
				}
			}

			return;
		}

		// Check if record is checked out by other editor
		if ($model->isCheckedOut($user->get('id')))
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_EDITED_BY_ANOTHER_ADMIN'), 'warning');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}

		// Checkout the record and proceed to edit form
		if (!$model->checkout())
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}

		// Call display method of the view, instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->display();
	}


	/**
	 * Method for clearing cache of data depending on records type
	 *
	 * @return void
	 *
	 * @since 3.2.0
	 */
	protected function _cleanCache()
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		parent::_cleanCache();

		$cache_site = FLEXIUtilities::getCache($group = '', $client = 0);
		$cache_site->clean('com_flexicontent_items');

		$cache_admin = FLEXIUtilities::getCache($group = '', $client = 1);
		$cache_admin->clean('com_flexicontent_items');

		// Also clean this as it contains Joomla frontend view cache of the component)
		$cache_site->clean('com_flexicontent');
	}


	/**
	 * Method for extra form validation after \Joomla\CMS\Form\Form validation is executed
	 *
	 * @param   array     $validated_data  The already jform-validated data of the record
	 * @param   object    $model           The Model object of current controller instance
	 * @param   array     $data            The original posted data of the record
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since 3.3
	 */
	protected function _afterModelValidation(& $validated_data, & $data, $model)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		if (!parent::_afterModelValidation($validated_data, $data, $model))
		{
			return false;
		}

		/**
		 * Reviews managers: a rating (stars) is required, a new review needs an item and a user can have only 1 review per item
		 */

		if ($this->canManage && $validated_data)
		{
			$isNew = !$model->get('id');

			// Composite voting: the rating of every characteristic is required, the overall rating is calculated from them
			$vfield = $model->getVotingReviewsField((object) array('type_id' => 0), true);
			$chars  = flexicontent_html::getVotingCharacteristics($vfield);

			if ($chars)
			{
				$posted     = isset($validated_data['custom_ratings']) && is_array($validated_data['custom_ratings']) ? $validated_data['custom_ratings'] : array();
				$resolution = max(5, min(100, (int) $vfield->parameters->get('rating_resolution', 5)));
				$percents   = array();
				$votes      = array();

				foreach ($chars as $xid => $char)
				{
					$p = isset($posted[$xid]) ? (int) $posted[$xid] : 0;

					if ($p < 1 || $p > 100)
					{
						$model->setError(\Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_ERR_RATING_ALL'));
						return false;
					}

					$votes[$xid]    = max(1, (int) round($p * $resolution / 100));
					$percents[$xid] = (int) round(100 * $votes[$xid] / $resolution);
				}

				$validated_data['custom_ratings'] = json_encode($percents);
				$validated_data['average_rating'] = (int) round(100 * max(1, (int) round(array_sum($votes) / count($votes))) / $resolution);
			}

			if (empty($validated_data['average_rating']) || (int) $validated_data['average_rating'] < 1)
			{
				$model->setError(\Joomla\CMS\Language\Text::_('FLEXI_REVIEW_ERR_RATING_REQUIRED'));
				return false;
			}

			if ($isNew)
			{
				$content_id = !empty($validated_data['content_id']) ? (int) $validated_data['content_id'] : 0;

				if (!$content_id)
				{
					$model->setError(\Joomla\CMS\Language\Text::_('FLEXI_REVIEW_ERR_SELECT_ITEM'));
					return false;
				}

				$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

				/**
				 * The reviewer is either an existing user or a guest (no user selected), a reviewer can have only 1 review per item
				 */
				$reviewer_id = !empty($validated_data['user_id']) ? (int) $validated_data['user_id'] : 0;

				if ($reviewer_id)
				{
					$reviewer = \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById((int) $reviewer_id);

					if (!$reviewer->id)
					{
						$model->setError(\Joomla\CMS\Language\Text::_('FLEXI_REVIEW_ERR_USER_NOT_FOUND'));
						return false;
					}

					// The email of a user is the email of his account
					$validated_data['email'] = $reviewer->email;
					$validated_data['name']  = '';
					$where_reviewer = 'user_id = ' . $reviewer_id;
				}
				else
				{
					$validated_data['user_id'] = 0;
					$guest_email = isset($validated_data['email']) ? trim($validated_data['email']) : '';
					$validated_data['name'] = isset($validated_data['name']) ? trim($validated_data['name']) : '';

					if ($validated_data['name'] === '')
					{
						$model->setError(\Joomla\CMS\Language\Text::_('FLEXI_VOTE_REVIEW_ERR_NAME'));
						return false;
					}

					if ($guest_email === '')
					{
						$model->setError(\Joomla\CMS\Language\Text::_('FLEXI_REVIEW_ERR_GUEST_EMAIL'));
						return false;
					}

					// The email belongs to a registered user, the review must be assigned to that user
					$registered_id = (int) $db->setQuery('SELECT id FROM #__users WHERE email = ' . $db->Quote($guest_email))->loadResult();

					if ($registered_id)
					{
						$model->setError(\Joomla\CMS\Language\Text::_('FLEXI_REVIEW_ERR_EMAIL_IS_USER'));
						return false;
					}

					$where_reviewer = 'user_id = 0 AND email = ' . $db->Quote($guest_email);
				}

				$query = $db->getQuery(true)
					->select('COUNT(*)')
					->from('#__flexicontent_reviews')
					->where('content_id = ' . $content_id)
					->where('type = ' . $db->Quote(!empty($validated_data['type']) ? $validated_data['type'] : 'item'))
					->where($where_reviewer);

				if ((int) $db->setQuery($query)->loadResult())
				{
					$model->setError(\Joomla\CMS\Language\Text::_('FLEXI_REVIEW_ERR_USER_ALREADY'));
					return false;
				}
			}
		}


		/**
		 * If user can not manage reviews then do "Review validation on the posted data", like enforcing email and stripping HTML tags
		 */

		if (!$this->canManage)
		{
			$validated_data = $model->reviewerValidation($validated_data);

			if (!$validated_data)
			{
				return false;
			}
		}

		return true;
	}


	/**
	 * Method for doing some record type specific work before calling model store
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since 3.3
	 */
	protected function _beforeModelStore(& $validated_data, & $data, $model)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		$cparams = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
		$user    = \Joomla\CMS\Factory::getApplication()->getIdentity();
		$user_id = (int) $user->id;
		$isNew   = !$model->get('id');
		$item  = (object) array('type_id' => empty($record->item_type_id) ? 0 : $record->item_type_id);
		$field = $model->getVotingReviewsField($item, $setAsDefault = true);

		/**
		 * Handling of new reviews
		 */
		if ($isNew)
		{
			// Set owner ID, reviews managers choose the reviewer (a user, or a guest) in the form, otherwise the owner is the current user
			if (!$this->canManage)
			{
				$validated_data['user_id'] = $user_id;
			}
			
			// Unlogged submission, require email verification
			if (!$user_id)
			{
				$verify_email_ownership = (int) $field->parameters->get('verify_email_ownership', 1);
				$validated_data['verified'] = $this->canManage || !$verify_email_ownership ? 1 : 0;
			}
		}

		/**
		 * Consistency checks
		 */

		// Update review's email if current editor is also review owner and editor's email has changed
		if ($user_id && $model->get('user_id') ==  $user_id)
		{
			$validated_data['email'] = $user->email;
		}

		// Update review's user_id if current editor's email match user's email
		if ($user_id && $model->get('email') ==  $user->email)
		{
			$validated_data['user_id'] = $user_id;
		}

		/**
		 * New submission or modification of existing review via non-admin
		 */
		if (!$this->canManage)
		{
			// Clear "approved" status
			$do_approval = (int) $field->parameters->get('do_approval', 1);
			$validated_data['approved'] = !$do_approval ? 1 : 0;

			// Modification of existing review (via non-admin), save old data if review is currently in "approved" status
			if (!$isNew && $model->get('approved'))
			{
				$validated_data['title_old'] = $model->get('title');
				$validated_data['text_old']  = $model->get('text');
			}
		}

		/**
		 * The rating of the review is also a vote of the item, update the item's rating
		 */
		if ($this->canManage && isset($validated_data['average_rating']))
		{
			$_content_id = $isNew ? (int) $validated_data['content_id'] : (int) $model->get('content_id');

			$this->_applyRatingChange(
				$_content_id,
				$isNew ? 0 : (int) $model->get('average_rating'),
				(int) $validated_data['average_rating']
			);

			// Composite voting: the ratings of the characteristics are votes too
			if (isset($validated_data['custom_ratings']))
			{
				$this->_applyCompositeChange(
					$_content_id,
					$isNew ? array() : (array) json_decode((string) $model->get('custom_ratings'), true),
					(array) json_decode((string) $validated_data['custom_ratings'], true)
				);
			}
		}

		return true;
	}


	/**
	 * Task to change the rating (stars) of a review, e.g. from the list of reviews (ajax)
	 *
	 * @return  void
	 *
	 * @since   3.3
	 */
	public function setrating()
	{
		$fail = function ($msg)
		{
			jexit(json_encode(array('error' => 1, 'msg' => $msg)));
		};

		if (!\Joomla\CMS\Session\Session::checkToken('request'))
		{
			$fail(\Joomla\CMS\Language\Text::_('JINVALID_TOKEN'));
		}

		if (!$this->canManage)
		{
			$fail(\Joomla\CMS\Language\Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'));
		}

		$id      = $this->input->get('id', 0, 'int');
		$percent = $this->input->get('rating', 0, 'int');

		// Composite voting: the overall rating is calculated from the characteristics
		if (flexicontent_html::getVotingCharacteristics(flexicontent_html::getVotingFieldConfig()))
		{
			$fail(\Joomla\CMS\Language\Text::_('FLEXI_VOTE_AVERAGE_RATING_CALCULATED_AUTOMATICALLY'));
		}

		if (!$id || $percent < 1 || $percent > 100)
		{
			$fail(\Joomla\CMS\Language\Text::_('FLEXI_REVIEW_ERR_RATING_REQUIRED'));
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/flexicontent_reviews.php';
		$__fcTableDb = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$review = new flexicontent_reviews($__fcTableDb);

		if (!$review->load($id))
		{
			$fail($review->getError() ?: 'Review not found');
		}

		$old_percent = (int) $review->average_rating;
		$review->average_rating = $percent;

		if (!$review->store())
		{
			$fail($review->getError());
		}

		$this->_applyRatingChange((int) $review->content_id, $old_percent, $percent);

		jexit(json_encode(array('error' => 0, 'rating' => $percent)));
	}


	/**
	 * Method to apply a change of the rating of a review, to the rating (votes) of the reviewed item
	 *
	 * @param   int   $content_id   The reviewed item
	 * @param   int   $old_percent  The old rating of the review as percentage, 0 if there was no rating
	 * @param   int   $new_percent  The new rating of the review as percentage, 0 if the rating is removed
	 *
	 * @return  void
	 *
	 * @since   3.3
	 */
	protected function _applyRatingChange($content_id, $old_percent, $new_percent)
	{
		if (!$content_id || $old_percent === $new_percent)
		{
			return;
		}

		$model = $this->getModel($this->record_name);
		$field = $model->getVotingReviewsField((object) array('type_id' => 0), true);

		// Composite voting calculates the rating of the item from the voting characteristics
		if (!$field || (int) $field->parameters->get('enable_extra_votes', 0))
		{
			return;
		}

		$resolution = max(5, min(100, (int) $field->parameters->get('rating_resolution', 5)));
		$old_vote   = $old_percent > 0 ? max(1, (int) round($old_percent * $resolution / 100)) : 0;
		$new_vote   = $new_percent > 0 ? max(1, (int) round($new_percent * $resolution / 100)) : 0;

		$delta_sum   = $new_vote - $old_vote;
		$delta_count = ($new_vote > 0 ? 1 : 0) - ($old_vote > 0 ? 1 : 0);

		if (!$delta_sum && !$delta_count)
		{
			return;
		}

		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$exists = (int) $db->setQuery('SELECT COUNT(*) FROM #__content_rating WHERE content_id = ' . (int) $content_id)->loadResult();

		if ($exists)
		{
			$db->setQuery(
				'UPDATE #__content_rating'
				. ' SET rating_sum = GREATEST(CAST(rating_sum AS SIGNED) + ' . (int) $delta_sum . ', 0),'
				. '     rating_count = GREATEST(CAST(rating_count AS SIGNED) + ' . (int) $delta_count . ', 0)'
				. ' WHERE content_id = ' . (int) $content_id
			)->execute();
		}
		elseif ($new_vote > 0)
		{
			$db->setQuery(
				'INSERT #__content_rating SET content_id = ' . (int) $content_id . ', lastip = ' . $db->Quote('')
				. ', rating_sum = ' . (int) $new_vote . ', rating_count = 1'
			)->execute();
		}

		// The rating of the item changed, clean item-related caches
		FLEXIUtilities::getCache($group = '', 0)->clean('com_flexicontent');
	}


	/**
	 * Method to apply a change of the ratings of the characteristics of a review (composite voting) to the votes of the reviewed item,
	 * the overall rating is the average of the characteristics (as calculated by the voting)
	 *
	 * @param   int    $content_id    The reviewed item
	 * @param   array  $old_percents  xid => old rating (percent), empty if there was no rating
	 * @param   array  $new_percents  xid => new rating (percent), empty if the ratings are removed
	 *
	 * @return  void
	 *
	 * @since   3.3
	 */
	protected function _applyCompositeChange($content_id, $old_percents, $new_percents)
	{
		$model = $this->getModel($this->record_name);
		$field = $model->getVotingReviewsField((object) array('type_id' => 0), true);
		$chars = flexicontent_html::getVotingCharacteristics($field);

		if (!$content_id || !$chars)
		{
			return;
		}

		$resolution = max(5, min(100, (int) $field->parameters->get('rating_resolution', 5)));
		$db         = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$to_vote    = function ($percent) use ($resolution)
		{
			return $percent > 0 ? max(1, (int) round($percent * $resolution / 100)) : 0;
		};

		// Apply a change of a vote to a votes table (the record is created by the first vote)
		$apply = function ($table, $extra_where, $extra_set, $old_vote, $new_vote) use ($db, $content_id)
		{
			$delta_sum   = $new_vote - $old_vote;
			$delta_count = ($new_vote > 0 ? 1 : 0) - ($old_vote > 0 ? 1 : 0);

			if (!$delta_sum && !$delta_count)
			{
				return;
			}

			$exists = (int) $db->setQuery('SELECT COUNT(*) FROM ' . $table . ' WHERE content_id = ' . (int) $content_id . $extra_where)->loadResult();

			if ($exists)
			{
				$db->setQuery(
					'UPDATE ' . $table
					. ' SET rating_sum = GREATEST(CAST(rating_sum AS SIGNED) + ' . (int) $delta_sum . ', 0),'
					. '     rating_count = GREATEST(CAST(rating_count AS SIGNED) + ' . (int) $delta_count . ', 0)'
					. ' WHERE content_id = ' . (int) $content_id . $extra_where
				)->execute();
			}
			elseif ($new_vote > 0)
			{
				$db->setQuery(
					'INSERT ' . $table . ' SET content_id = ' . (int) $content_id . ', lastip = ' . $db->Quote('')
					. ', rating_sum = ' . (int) $new_vote . ', rating_count = 1' . $extra_set
				)->execute();
			}
		};

		$old_votes = array();
		$new_votes = array();

		foreach ($chars as $xid => $char)
		{
			$old_votes[$xid] = $to_vote(isset($old_percents[$xid]) ? (int) $old_percents[$xid] : 0);
			$new_votes[$xid] = $to_vote(isset($new_percents[$xid]) ? (int) $new_percents[$xid] : 0);

			$apply('#__flexicontent_items_extravote', ' AND field_id = ' . (int) $xid, ', field_id = ' . (int) $xid, $old_votes[$xid], $new_votes[$xid]);
		}

		// Overall rating (a vote of the item) exists only if all characteristics are rated
		$main = function ($votes)
		{
			return count($votes) && !in_array(0, $votes, true) ? max(1, (int) round(array_sum($votes) / count($votes))) : 0;
		};

		$apply('#__content_rating', '', '', $main($old_votes), $main($new_votes));

		// The ratings of the item changed, clean item-related caches
		FLEXIUtilities::getCache($group = '', 0)->clean('com_flexicontent');
	}


	/**
	 * START OF CONTROLLER SPECIFIC METHODS
	 */


	/**
	 * Method to do prechecks for loading / saving review forms, like 
	 * - if reviews are enabled
	 * - if content item exists
	 *
	 * @param   object    $content_id  The id of the content
	 * @param   object    $item        by reference variable to return the reviewed item
	 * @param   object    $model       The record model (review)
	 * @param   array     $errors      by reference the array of error messages that have occured
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	private function _preChecks($content_id, & $item, $model, & $errors)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		$app   = \Joomla\CMS\Factory::getApplication();
		$user  = \Joomla\CMS\Factory::getApplication()->getIdentity();
		$field = $model->getVotingReviewsField($item, $setAsDefault = true);

		/**
		 * Check reviews are enabled
		 */

		$allow_reviews = (int) $field->parameters->get('allow_reviews', 0);

		if (!$allow_reviews)
		{
			$errors[] = 'Reviews are disabled';
			return;
		}


		/**
		 * Load content item related to the review
		 */

		$__tblType = $type = 'flexicontent_items';
		$__tblPrefix = $prefix = '';
		$__tblDb = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		if ($__tblPrefix === 'TagsTable') {
			$item = \Joomla\CMS\Factory::getApplication()->bootComponent('com_tags')->getMVCFactory()->createTable($__tblType, 'Administrator');
		} elseif ($__tblPrefix === 'JTable') {
			$__tblClass = '\\Joomla\\CMS\\Table\\' . ucfirst($__tblType);
			$item = new $__tblClass($__tblDb);
		} else {
			$__tblClass = $__tblPrefix . ucfirst($__tblType);
			require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/' . strtolower($__tblType) . '.php';
			$item = new $__tblClass($__tblDb);
		}

		if ($content_id && !$item->load($content_id))
		{
			$errors[] = 'ID: ' . $content_id . ': ' . $item->getError();
			return;
		}
	}


	/**
	 *  Method for voting (ajax)
	 *
	 * @TODO move the query part to the item model
	 * @access public
	 * @since 1.5
	 */
	public function ajaxvote()
	{
		$app  = \Joomla\CMS\Factory::getApplication();
		$user = \Joomla\CMS\Factory::getApplication()->getIdentity();
		$db   = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$session = \Joomla\CMS\Factory::getApplication()->getSession();
		$cparams = \Joomla\CMS\Component\ComponentHelper::getParams( 'com_flexicontent' );

		$no_ajax     = $this->input->get('no_ajax', 0, 'int');
		$user_rating = $this->input->get('user_rating', 0, 'int');
		$cid = $this->input->get('cid', 0, 'int');
		$xid = $this->input->get('xid', '', 'cmd');

		// Check for request forgeries (votes of logged users are stored in the DB per user)
		if ($user->id && !\Joomla\CMS\Session\Session::checkToken('request'))
		{
			return $this->_ajaxvote_error(\Joomla\CMS\Language\Text::_('JINVALID_TOKEN'), $xid ?: 'main', $no_ajax);
		}

		// Compatibility in case the voting originates from joomla's voting plugin
		if ($no_ajax && !$cid)
		{
			$cid = $this->input->get('id', 0, 'int');  // Joomla 's content plugin uses 'id' HTTP request variable
		}


		/**
		 * Validate xid
		 */

		$xid = empty($xid) ? 'main' : $xid;
		$xid = $xid === 'main' ? 'main' : (int) $xid;


		/**
		 * Load item
		 */

		$__tblType = $type = 'flexicontent_items';
		$__tblPrefix = $prefix = '';
		$__tblDb = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		if ($__tblPrefix === 'TagsTable') {
			$item = \Joomla\CMS\Factory::getApplication()->bootComponent('com_tags')->getMVCFactory()->createTable($__tblType, 'Administrator');
		} elseif ($__tblPrefix === 'JTable') {
			$__tblClass = '\\Joomla\\CMS\\Table\\' . ucfirst($__tblType);
			$item = new $__tblClass($__tblDb);
		} else {
			$__tblClass = $__tblPrefix . ucfirst($__tblType);
			require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/' . strtolower($__tblType) . '.php';
			$item = new $__tblClass($__tblDb);
		}
		$item->load($cid);


		/**
		 * Get voting field configuration
		 */

		// Get voting field
		$query = 'SELECT * FROM #__flexicontent_fields WHERE field_type = ' . $db->Quote('voting');
		$field = $db->setQuery($query)->loadObject();

		// Load field's configuration together with type-specific field customization
		FlexicontentFields::loadFieldConfig($field, $item);

		// Load field's language files
		\Joomla\CMS\Factory::getApplication()->getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, 'en-GB', true);
		\Joomla\CMS\Factory::getApplication()->getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, null, true);

		// Get needed parameters
		$rating_resolution = (int) $field->parameters->get('rating_resolution', 5);
		$rating_resolution = $rating_resolution >= 5   ?  $rating_resolution  :  5;
		$rating_resolution = $rating_resolution <= 100  ?  $rating_resolution  :  100;

		$min_rating = 1;
		$max_rating = $rating_resolution;

		$main_counter = (int) $field->parameters->get('main_counter', 1);
		$main_counter_show_label = (int) $field->parameters->get('main_counter_show_label', 1);
		$main_counter_show_percentage = (int) $field->parameters->get('main_counter_show_percentage', 0);

		$enable_extra_votes = (int) $field->parameters->get('enable_extra_votes', '');

		$extra_counter = (int) $field->parameters->get('extra_counter', 1);
		$extra_counter_show_label = (int) $field->parameters->get('extra_counter_show_label', 1);
		$extra_counter_show_percentage = (int) $field->parameters->get('extra_counter_show_percentage', 0);


		/**
		 * Find if user has the ACCESS level required for voting
		 */

		$aid_arr = $user->getAuthorisedViewLevels();
		$acclvl = (int) $field->parameters->get('submit_acclvl', 1);
		$has_acclvl = in_array($acclvl, $aid_arr);


		/**
		 * Create no access Redirect Message
		 */

		if (!$has_acclvl)
		{
			$logged_no_acc_msg = $field->parameters->get('logged_no_acc_msg', '');
			$guest_no_acc_msg  = $field->parameters->get('guest_no_acc_msg', '');
			$no_acc_msg = $user->id ? $logged_no_acc_msg : $guest_no_acc_msg;
			$no_acc_msg = $no_acc_msg ? \Joomla\CMS\Language\Text::_($no_acc_msg) : '';

			// Message not set create a Default Message
			if (!$no_acc_msg)
			{
				// Find name of required Access Level
				$query = 'SELECT title FROM #__viewlevels as level WHERE level.id = ' . (int) $acclvl;
				$acclvl_name = $db->setQuery($query)->loadResult();

				if (!$acclvl_name)
				{
					$acclvl_name = 'Access Level: ' . $acclvl . ' not found / was deleted';
				}

				$no_acc_msg = \Joomla\CMS\Language\Text::sprintf('FLEXI_NO_ACCESS_TO_VOTE', $acclvl_name);
			}

			$error = $no_acc_msg;
			return $this->_ajaxvote_error($error, $xid, $no_ajax);
		}

		/**
		 * Check if rating is NOT within valid range
		 */

		elseif ($user_rating < $min_rating || $user_rating > $max_rating)
		{
			$error = \Joomla\CMS\Language\Text::sprintf( 'FLEXI_VOTE_OUT_OF_RANGE', $min_rating, $max_rating);
			return $this->_ajaxvote_error($error, $xid, $no_ajax);
		}


		/**
		 * Check extra vote exists and get extra votes types
		 */

		$xids_extra = array();

		if ($enable_extra_votes)
		{
			// Retrieve and split-up extra vote types, (removing last one if empty)
			$extra_votes = $field->parameters->get('extra_votes', '');
			$extra_votes = preg_split( "/[\s]*%%[\s]*/", $field->parameters->get('extra_votes', '') );

			if (empty($extra_votes[count($extra_votes)-1]))
			{
				unset( $extra_votes[count($extra_votes)-1] );
			}

			// Split extra voting ids (xid) and their titles
			foreach ($extra_votes as $extra_vote)
			{
				@ list($xid_ev, $title_ev, $desc_ev) = explode('##', $extra_vote);
				$xids_extra[$xid_ev] = 1;
			}
		}


		/**
		 * Allow XID that is either 'main' or an integer (that exists at the extra voting cases)
		 */

		if ($xid === 'main')
		{
			if (count($xids_extra))
			{
				$error = \Joomla\CMS\Language\Text::_('FLEXI_VOTE_AVERAGE_RATING_CALCULATED_AUTOMATICALLY');
				return $this->_ajaxvote_error($error, $xid, $no_ajax);
			}
		}

		elseif (!isset($xids_extra[$xid]))
		{
			$error = !$enable_extra_votes
				? \Joomla\CMS\Language\Text::_('FLEXI_VOTE_COMPOSITE_VOTING_IS_DISABLED')
				: 'Voting characteristic with id: ' . $xid . ' does not exist';
			return $this->_ajaxvote_error($error, $xid, $no_ajax);
		}


		/**
		 * Logged users: the rating of the user is also the rating of the user's review (a review record is the rating of the user in the DB),
		 * the rating of the review is authoritative: it is used as old rating, and it is updated when the user changes his vote via the stars
		 *
		 * Option 'reviews_store_rating': if enabled, a rating without review (text) also creates a review record (without text, it is not displayed),
		 * this allows a single vote per user (any browser), that can be changed. Otherwise a review record is not created by voting,
		 * votes of users without review are protected from double voting by the user's session / IP address (as in the FLEXIcontent voting field)
		 */
		$sync_user_rating = $user->id
			&& (int) $field->parameters->get('allow_reviews', 0)
			&& !$enable_extra_votes
			&& $xid === 'main';

		$store_rating_only = $sync_user_rating && (int) $field->parameters->get('reviews_store_rating', 0);

		// The rating of the user is known from the DB (review record), set below
		$db_rating_known = false;


		/**
		 * Check: item id exists in our voting logging SESSION (array) variable, to avoid double voting
		 */

		$vote_history = $session->get('vote_history', array(),'flexicontent');
		//var_dump($vote_history); exit;

		if (!isset($vote_history[$cid]) || !is_array($vote_history[$cid]))
		{
			$vote_history[$cid] = array();
		}

		/**
		 * Allow user to change his vote. For the case that the browser was not closed,
		 * we can get rating from user's session and thus allow user to change the vote
		 */

		$old_ratings  = array();
		$rating_diffs = array();


		// Using main vote only
		if ($xid === 'main')
		{
			$voteIsComplete = true;

			$user_rating_main = $user_rating;

			$old_ratings['main'] = isset($vote_history[$cid]['main'])
				? (int) round($vote_history[$cid]['main'])
				: 0;

			// Logged users: the rating of the user is stored in the DB (in his review record), so use it as old rating
			if ($sync_user_rating)
			{
				// The stored rating is authoritative (also when it is zero, e.g. the review was trashed by a moderator)
				$stored_rating = $this->_getUserRating($cid, $user, $rating_resolution, $store_rating_only);

				// No review record: authoritative only if ratings without review are stored, otherwise use the rating of the user's session
				if ($stored_rating !== null || $store_rating_only)
				{
					$old_ratings['main'] = (int) $stored_rating;
					$db_rating_known = true;
				}
			}

			$vote_history[$cid]['main'] = $user_rating_main;
		}

		// Using voting characteristics
		else
		{
			$voteIsComplete = true;

			$user_rating_main = 0;
			$user_ratings_completed = 0;
			$user_ratings_sum = 0;

			foreach($xids_extra as $xid_n => $i)
			{
				// Get old rating and calculate rating difference
				$old_ratings[$xid_n] = isset($vote_history[$cid][$xid_n])
					? (int) $vote_history[$cid][$xid_n]
					: 0;
				$rating_diffs[$xid_n] = $xid_n == $xid
					? $user_rating - $old_ratings[$xid_n]
					: 0;

				// Update voting history with current voting characteristic
				if ($xid_n == $xid)
				{
					$vote_history[$cid][$xid] = $user_rating;
				}

				// If at least 1 rating characteristic is missing then rating has not been completed
				if (!isset($vote_history[$cid][$xid_n]))
				{
					$voteIsComplete = false;
					continue;
				}

				// Sum up mainrating so far
				$user_ratings_completed++;
				$user_ratings_sum += (int) $vote_history[$cid][$xid_n];
			}

			// Update voting history of main vote only if there is a vote for all characteristics
			if ($voteIsComplete)
			{
				$old_ratings['main'] = isset($vote_history[$cid]['main'])
					? (int) $vote_history[$cid]['main']
					: 0;
				$user_rating_main = (int) round($user_ratings_sum / count($xids_extra));
				$vote_history[$cid]['main'] = $user_rating_main;
			}
		}

		// Calculate noz-zero 'main' vote rating difference only if there is a vote for all characteristics
		$rating_diffs['main'] = $voteIsComplete
			? $user_rating_main - $old_ratings['main']
			: 0;


		/**
		 * Retrieve last vote for the given item
		 */

		$currip = $_SERVER['REMOTE_ADDR'];
		$result	= new stdClass();

		foreach($vote_history[$cid] as $xid_n => $rating_n)
		{
			// Update only current characteristic
			if (!$voteIsComplete && $xid_n != $xid)
			{
				continue;
			}

			$old_rating = $old_ratings[$xid_n];
			$rating_diff = $rating_diffs[$xid_n];


			// Choose db table to store vote (normal or extra)
			$dbtbl = $xid_n === 'main'
				? '#__content_rating'
				: '#__flexicontent_items_extravote';

			// Second part is for defining the vote type in case of extra vote
			$and_extra_id = $xid_n !== 'main' ? ' AND field_id = ' . (int) $xid_n : '';

			$query = ' SELECT *'
				. ' FROM ' . $dbtbl . ' AS a '
				. ' WHERE content_id = ' . (int) $cid
				. ' ' . $and_extra_id;
			$db_itemratings = $db->setQuery($query)->loadObject();


			/**
			 * Voting access allowed and valid, but we will need to make
			 * some more checks (IF voting record exists AND double voting)
			 */

			// Voting record does not exist for this item, accept user's vote and insert new voting record in the db
			if (!$db_itemratings)
			{
				if ($voteIsComplete)
				{
					$query = ' INSERT ' . $dbtbl
						. ' SET content_id = ' . (int) $cid . ', '
						. '  lastip = ' . $db->Quote($currip) . ', '
						. '  rating_sum = ' . (int) $rating_n . ', '
						. '  rating_count = 1 '
						. ($xid_n !== 'main' ? ', field_id = ' . (int) $xid_n : '');

					$db->setQuery($query)->execute();
				}
			}

			// Voting record exists for this item, check if user has already voted
			else
			{
				/**
				 * If item is not in the user's voting history (session), then we check
				 * if this IP has voted for this item recently and refuse to accept vote
				 */

				if ($xid_n == $xid && !$old_rating && !$db_rating_known && $currip === $db_itemratings->lastip)
				{
					// Review form (internal call): the review is saved anyway, only its rating is not counted again as vote of the item
					if ($this->voteInternal)
					{
						return null;
					}

					$error = \Joomla\CMS\Language\Text::_('FLEXI_YOU_HAVE_ALREADY_VOTED');
					return $this->_ajaxvote_error($error, $xid, $no_ajax);
				}

				//echo $db_itemratings->rating_sum. ' - ' . $rating_diff . "\n";

				/**
				 * If voting is completed, Either add all sub-votes into DB when voting is completed for the very first time
				 * -OR- if user has updated an existing voting set (update in DB only the current sub-vote and the main vote)
				 */
				if ($voteIsComplete && ($xid_n === 'main' || $xid_n == $xid))
				{
					// vote accepted update DB
					$query = 'UPDATE ' . $dbtbl
					. ' SET rating_count = rating_count + ' . ($old_rating ? 0 : 1)
					. '  , rating_sum = rating_sum + ' . ($old_rating ? $rating_diff : $rating_n)
					. '  , lastip = ' . $db->Quote($currip)
					. ' WHERE content_id = ' . (int) $cid
					. ' ' . $and_extra_id;

					$db->setQuery($query)->execute();
				}
			}

			$db_rating_sum = $db_itemratings ? (int) $db_itemratings->rating_sum : 0;
			$db_rating_count = $db_itemratings ? (int) $db_itemratings->rating_count : 0;

			if ($xid_n === 'main')
			{
				//$result->rating_sum_main_diff_debug  = ($voteIsComplete ? $rating_diffs['main'] : 0);
				$result->rating_sum_main  = $db_rating_sum   + ($voteIsComplete ? $rating_diffs['main'] : 0);
				$result->ratingcount_main = $db_rating_count + ($voteIsComplete && !$old_ratings['main'] ? 1 : 0);
				$result->percentage_main  = !$result->ratingcount_main ? 0 : (($result->rating_sum_main / $result->ratingcount_main) * (100 / $rating_resolution));
				$result->htmlrating_main  = ($main_counter ?
					$result->ratingcount_main . ($main_counter_show_label ? ' ' . \Joomla\CMS\Language\Text::_($db_rating_count > 1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE') : '') . ($main_counter_show_percentage ? ' - ' : '')
					: '')
					. ($main_counter_show_percentage ? (int) $result->percentage_main . '%' : '');
			}

			// In case of composite voting being OFF only the above will be added
			elseif ($xid_n == $xid)
			{
				$result->rating_sum  = $db_rating_sum   + ($old_rating ? $rating_diffs[$xid_n] : $rating_n);
				$result->ratingcount = $db_rating_count + ($old_rating ? 0 : 1);
				$result->percentage  = !$result->ratingcount ? 0 : (($result->rating_sum / $result->ratingcount) * (100 / $rating_resolution));
				$result->htmlrating  = ($extra_counter ?
					$result->ratingcount . ($extra_counter_show_label ? ' ' . \Joomla\CMS\Language\Text::_($db_rating_count > 1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE') : '') . ($extra_counter_show_percentage ? ' - ' : '')
					: '')
					. ($extra_counter_show_percentage ? (int) $result->percentage . '%' : '');
			}
		}


		/**
		 * Prepare response
		 */

		$html = $old_ratings[$xid]
			? '' . (100 * ($old_ratings[$xid] / $max_rating)) . '% => ' . (100 * ($user_rating / $max_rating)) . '%'
			: '' . (100 * ($user_rating / $max_rating)) . '%';

		$xid === 'main'
			? $result->html_main = $html
			: $result->html = $html;

		if ($xid !== 'main')
		{
			$result->message = '
				<div class="fc-mssg fc-warning fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.\Joomla\CMS\Language\Text::_('FLEXI_VOTE_YOUR_RATING').': '.(100*($user_rating / $max_rating)).'%
				</div>';

			if (!$voteIsComplete)
			{
				$result->message_main = '
					<div class="fc-mssg fc-warning fc-nobgimage">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						'.\Joomla\CMS\Language\Text::sprintf('FLEXI_VOTE_PLEASE_COMPLETE_VOTING', $user_ratings_completed, count($xids_extra)).'
					</div>';
			}
			else
			{
				$result->html_main = \Joomla\CMS\Language\Text::_($old_ratings['main'] ? 'FLEXI_VOTE_AVERAGE_RATING_UPDATED' : 'FLEXI_VOTE_AVERAGE_RATING_SUBMITTED');
				$result->message_main = '
				<div class="fc-mssg fc-success fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
						' . \Joomla\CMS\Language\Text::_( $old_rating ? 'FLEXI_VOTE_YOUR_OLD_AVERAGE_RATING_WAS_UPDATED' : 'FLEXI_VOTE_YOUR_AVERAGE_RATING_STORED' ) . ':
						<b>' . ($old_ratings['main'] ? (100 * ($old_ratings['main'] / $max_rating)) . '% => ' : '') . (100 * ($user_rating_main / $max_rating)) . '%</b>
				</div>';
			}
		}

		else
		{
			$result->message_main ='
				<div class="fc-mssg fc-success fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.\Joomla\CMS\Language\Text::_( $old_rating ? 'FLEXI_VOTE_YOUR_OLD_RATING_WAS_CHANGED' : 'FLEXI_THANK_YOU_FOR_VOTING' ).'
				</div>';
		}

		// Store the rating of a logged user (reviews are enabled), if called via the review form this is done by the review form
		if ($sync_user_rating && $voteIsComplete && !$this->voteInternal)
		{
			$this->_storeUserRating($cid, $user, $user_rating, $rating_resolution, $store_rating_only);
		}

		// Set the voting data, into SESSION
		$session->set('vote_history', $vote_history, 'flexicontent');

		// Item average vote changed clean item-related caches
		if ($voteIsComplete)
		{
			$cache = FLEXIUtilities::getCache($group='', 0);
			$cache->clean('com_flexicontent');  // Also clean this (as it contains Joomla frontend view cache)
		}

		/**
		 * Set response and exit
		 */

		if ($this->voteInternal)
		{
			return $result;
		}
		elseif ($no_ajax)
		{
			$app->enqueueMessage($xid === 'main' ? $result->message_main.'<br/>'.$result->message : $result->message_main, 'notice');
			return;
		}
		else
		{
			$result->vote_history = print_r($vote_history[$cid], true);
			jexit(json_encode($result));
		}
	}


	/**
	 * Method to get the rating (on the voting field scale) that a logged user has given to an item
	 *
	 * @param   int      $cid         The content ID
	 * @param   object   $user        The user
	 * @param   int      $resolution  The rating resolution of the voting field
	 * @param   boolean  $trashed     True: a review in the trash is the rating record of the user (rating zero), false: it is treated as no review record
	 *
	 * @return  int|null  The rating, 0 if the review of the user has no rating, null if the user has no review record
	 */
	protected function _getUserRating($cid, $user, $resolution, $trashed = true)
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/flexicontent_reviews.php';
		$__fcTableDb = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$review = new flexicontent_reviews($__fcTableDb);

		if (!$review->load(array('content_id' => (int) $cid, 'user_id' => (int) $user->id, 'type' => 'item')))
		{
			return null;
		}

		if (!$trashed && (int) $review->state === -2)
		{
			return null;
		}

		return (int) $review->average_rating > 0
			? max(1, (int) round($review->average_rating * $resolution / 100))
			: 0;
	}


	/**
	 * Method to store the rating of a logged user, in his review record (creating a record without text if needed)
	 *
	 * @param   int      $cid         The content ID
	 * @param   object   $user        The user
	 * @param   int      $rating      The rating on the voting field scale
	 * @param   int      $resolution  The rating resolution of the voting field
	 * @param   boolean  $create      Create the review record if the user has none (rating without review), otherwise only an existing record is updated
	 *
	 * @return  boolean  True on success
	 */
	protected function _storeUserRating($cid, $user, $rating, $resolution, $create = true)
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/flexicontent_reviews.php';
		$__fcTableDb = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$review = new flexicontent_reviews($__fcTableDb);

		$exists = $review->load(array('content_id' => (int) $cid, 'user_id' => (int) $user->id, 'type' => 'item'));

		// A review that was deleted by the user (trashed): keep its old data, and start a new rating only record
		if ($exists && (int) $review->state === -2)
		{
			$review->title_old = $review->title;
			$review->text_old  = $review->text;
			$review->title     = '';
			$review->text      = '';
			$review->state     = 1;
			$review->approved  = 1;
		}

		// The user has no review record (a trashed review is also treated as none), and ratings without review are not stored
		if (!$create && (!$exists || (int) $review->state === -2))
		{
			return true;
		}

		if (!$exists)
		{
			// Rating only record: it is not displayed (no text), and it needs no approval
			$review->reset();
			$review->content_id = (int) $cid;
			$review->type       = 'item';
			$review->user_id    = (int) $user->id;
			$review->email      = $user->email;
			$review->state      = 1;
			$review->approved   = 1;
			$review->verified   = 1;
		}

		$review->average_rating = (int) round(100 * $rating / $resolution);
		$review->check();

		return $review->store();
	}


	/**
	 * Method to terminate the ajax voting tasking task on error
	 *
	 * @since 1.0
	 */
	protected function _ajaxvote_error($mssg, $xid, $no_ajax = false)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		// Internal call (PHP code), let the caller handle the error
		if ($this->voteInternal)
		{
			throw new RuntimeException(trim(strip_tags($mssg)));
		}

		// Handle non ajax call
		elseif ($no_ajax)
		{
			\Joomla\CMS\Factory::getApplication()->enqueueMessage($mssg, 'notice');
			return;
		}

		// Since voting REJECTED, avoid setting BAR percentage and HTML rating text ... someone else may have voted for the item ...
		else
		{
			$result	= new stdClass();
			$result->percentage = '';
			$result->htmlrating = '';
			$mssg = '
			<div class="fc-mssg fc-warning fc-nobgimage">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				'.$mssg.'
			</div>';

			$xid !== 'main'
				? $result->message = $mssg
				: $result->message_main = $mssg;
			jexit(json_encode($result));
		}
	}


	/**
	 * Returns the content model of the item associated with the given review
	 *
	 * @param $review_id - The ID of the review
	 *
	 * @return An item model instance
	 */
	private function _getContentModel($review_id)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		// Get review model and from it get the associated content ID and content Type
		$review_model = $this->getModel($this->record_name);
		$review_model->setId($review_id);

		$content_id   = $review_model->get('content_id');
		$content_type = $review_model->get('type');

		// Get the related content model and set the desired content ID into the content item model
		$content_model = $this->getModel($content_type);
		$content_model->setId($content_id);

		return $content_model;
	}
}
