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

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

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
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

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
		parent::save();
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
		parent::remove();
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
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();

		$cid    = $this->input->get('cid', array(), 'array');
		$values = array('approved' => 1, 'unapproved' => 0);
		$value  = ArrayHelper::getValue($values, $this->task, 0, 'int');

		// Access checks.
		foreach ($cid as $i => $id)
		{
			if (!$user->authorise('core.edit.state', 'com_content.article.' . (int) $id))
			{
				// Prune items that you can't change.
				unset($cid[$i]);
				JError::raiseNotice(403, JText::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'));
			}
		}

		if (empty($cid))
		{
			$app->setHeader('status', '500', true);
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
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
			? JText::plural('FLEXI_N_REVIEWS_APPROVED', count($cid))
			: JText::plural('FLEXI_N_REVIEWS_UNAPPROVED', count($cid));
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
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();

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

		// Sanity checks before reviewing, content item exists, and reviewing are enabled
		$item = null;
		$field = null;
		$errors = null;

		$this->_preReviewingChecks($content_id, $item, $field, $errors);

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

		// Try to load review by attributes in HTTP Request
		if ($content_id && $review_type)
		{
			$record = $model->getRecord(array(
				'content_id' => $content_id,
				'type' => $review_type,
				'user_id' => $user->id,
			));
		}

		// Try to load by unique ID or NAME
		else
		{
			$record = $model->getItem();
		}

		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;

		// Calculate access
		$is_authorised = $model->canEdit($record);

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			return;
		}

		// Check if record is checked out by other editor
		if ($model->isCheckedOut($user->get('id')))
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(JText::_('FLEXI_EDITED_BY_ANOTHER_ADMIN'), 'warning');

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
			$app->enqueueMessage(JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');

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
	 * Method for extra form validation after JForm validation is executed
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
		 * If user can not manage reviews then do "Review validation on the posted data"
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

		return true;
	}


	/**
	 * START OF CONTROLLER SPECIFIC METHODS
	 */


	/**
	 * Method to do prechecks for loading / saving review forms
	 *
	 * @param   object    $content_id  The id of the content
	 * @param   object    $item        by reference variable to return the reviewed item
	 * @param   object    $field       by reference variable to return the voting (reviews) field
	 * @param   array     $errors      The array of error messages that have occured
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	private function _preReviewingChecks($content_id, & $item = null, & $field = null, $errors = null)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();


		/**
		 * Load content item related to the review
		 */

		$item = JTable::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());

		if ($content_id && !$item->load($content_id))
		{
			$errors[] = 'ID: ' . $pk . ': ' . $item->getError();
			return;
		}


		/**
		 * Do voting / reviewing permissions check
		 */

		// Get voting field
		$query = 'SELECT * FROM #__flexicontent_fields WHERE field_type = ' . $db->Quote('voting');
		$field = $db->setQuery($query)->loadObject();

		// Load field's configuration together with type-specific field customization
		FlexicontentFields::loadFieldConfig($field, $item);

		// Load field's language files
		JFactory::getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, null, true);

		// Get needed parameters
		$allow_reviews = (int) $field->parameters->get('allow_reviews', 0);

		// Check reviews are allowed
		if (!$allow_reviews)
		{
			$errors[] = 'Reviews are disabled';
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
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();
		$session = JFactory::getSession();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );

		$no_ajax     = $this->input->get('no_ajax', 0, 'int');
		$user_rating = $this->input->get('user_rating', 0, 'int');
		$cid = $this->input->get('cid', 0, 'int');
		$xid = $this->input->get('xid', '', 'cmd');

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

		$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
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
		JFactory::getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('plg_flexicontent_fields_core', JPATH_ADMINISTRATOR, null, true);

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
			$no_acc_msg = $no_acc_msg ? JText::_($no_acc_msg) : '';

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

				$no_acc_msg = JText::sprintf('FLEXI_NO_ACCESS_TO_VOTE', $acclvl_name);
			}

			$error = $no_acc_msg;
			return $this->_ajaxvote_error($error, $xid, $no_ajax);
		}

		/**
		 * Check if rating is NOT within valid range
		 */

		elseif ($user_rating < $min_rating || $user_rating > $max_rating)
		{
			$error = JText::sprintf( 'FLEXI_VOTE_OUT_OF_RANGE', $min_rating, $max_rating);
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
				$error = JText::_('FLEXI_VOTE_AVERAGE_RATING_CALCULATED_AUTOMATICALLY');
				return $this->_ajaxvote_error($error, $xid, $no_ajax);
			}
		}

		elseif (!isset($xids_extra[$xid]))
		{
			$error = !$enable_extra_votes
				? JText::_('FLEXI_VOTE_COMPOSITE_VOTING_IS_DISABLED')
				: 'Voting characteristic with id: ' . $xid . ' does not exist';
			return $this->_ajaxvote_error($error, $xid, $no_ajax);
		}


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

				if ($xid_n == $xid && !$old_rating && $currip === $db_itemratings->lastip)
				{
					$error = JText::_('FLEXI_YOU_HAVE_ALREADY_VOTED');
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
					$result->ratingcount_main . ($main_counter_show_label ? ' ' . JText::_($db_rating_count > 1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE') : '') . ($main_counter_show_percentage ? ' - ' : '')
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
					$result->ratingcount . ($extra_counter_show_label ? ' ' . JText::_($db_rating_count > 1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE') : '') . ($extra_counter_show_percentage ? ' - ' : '')
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
					'.JText::_('FLEXI_VOTE_YOUR_RATING').': '.(100*($user_rating / $max_rating)).'%
				</div>';

			if (!$voteIsComplete)
			{
				$result->message_main = '
					<div class="fc-mssg fc-warning fc-nobgimage">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						'.JText::sprintf('FLEXI_VOTE_PLEASE_COMPLETE_VOTING', $user_ratings_completed, count($xids_extra)).'
					</div>';
			}
			else
			{
				$result->html_main = JText::_($old_ratings['main'] ? 'FLEXI_VOTE_AVERAGE_RATING_UPDATED' : 'FLEXI_VOTE_AVERAGE_RATING_SUBMITTED');
				$result->message_main = '
				<div class="fc-mssg fc-success fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
						' . JText::_( $old_rating ? 'FLEXI_VOTE_YOUR_OLD_AVERAGE_RATING_WAS_UPDATED' : 'FLEXI_VOTE_YOUR_AVERAGE_RATING_STORED' ) . ':
						<b>' . ($old_ratings['main'] ? (100 * ($old_ratings['main'] / $max_rating)) . '% => ' : '') . (100 * ($user_rating_main / $max_rating)) . '%</b>
				</div>';
			}
		}

		else
		{
			$result->message_main ='
				<div class="fc-mssg fc-success fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.JText::_( $old_rating ? 'FLEXI_VOTE_YOUR_OLD_RATING_WAS_CHANGED' : 'FLEXI_THANK_YOU_FOR_VOTING' ).'
				</div>';
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

		if ($no_ajax)
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
	 * Method to terminate the ajax voting tasking task on error
	 *
	 * @since 1.0
	 */
	protected function _ajaxvote_error($mssg, $xid, $no_ajax = false)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		// Handle non ajax call
		if ($no_ajax)
		{
			JFactory::getApplication()->enqueueMessage($mssg, 'notice');
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
