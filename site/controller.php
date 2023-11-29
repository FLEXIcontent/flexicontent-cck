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

jimport('legacy.controller.legacy');
JLoader::register('FlexicontentControllerItems', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'controllers'.DS.'items.php');  // we use JPATH_BASE since controller exists in frontend too

/**
 * FLEXIcontent Component Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentController extends JControllerLegacy
{
	var $records_dbtbl  = 'content';
	var $records_jtable = 'flexicontent_items';

	var $record_name = 'item';
	var $record_name_pl = 'items';

	var $_NAME = 'ITEM';
	var $record_alias = 'alias';

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

		/**
		 * Register task aliases
		 */
		$this->registerTask('apply_type',   'save');
		$this->registerTask('save_a_preview', 'save');

		if (JFactory::getApplication()->isClient('site'))
		{
			$this->registerTask('download_tree',  'download');
			$this->registerTask('download_file',  'download');

			$this->input  = empty($this->input) ? JFactory::getApplication()->input : $this->input;
			$this->option = $this->input->get('option', '', 'cmd');
			$this->task   = $this->input->get('task', '', 'cmd');
			$this->view   = $this->input->get('view', '', 'cmd');
			$this->format = $this->input->get('format', '', 'cmd');

			// Get referer URL from HTTP request and validate it
			$this->refererURL = !empty($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER'])
				? $_SERVER['HTTP_REFERER']
				: JUri::base();

			// Get return URL from HTTP request and validate it
			$this->returnURL = $this->_getReturnUrl();

			// For frontend default return is refererURL
			$this->returnURL = $this->returnURL ?: $this->refererURL;
		}
		else
		{
			$this->registerTask('unfeatured',   'featured');
			$this->registerTask('copy',         'batch');
			$this->registerTask('translate',    'batch');
		}
	}


	/**
	 * Logic to create category SEF urls via AJAX requests
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function getsefurl()
	{
		// Initialize variables
		$app  = JFactory::getApplication();
		$db   = JFactory::getDbo();

		$view = $this->input->get('view', '', 'cmd');
		$cid  = $this->input->get('cid', 0, 'int');

		if ($view=='category' && $cid)
		{
			$query = 'SELECT CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
				.' FROM #__categories AS c'
				.' WHERE c.id = '.$cid;
			$db->setQuery( $query );
			$categoryslug = $db->loadResult();
			echo JRoute::_(FlexicontentHelperRoute::getCategoryRoute($categoryslug), false);
		}
		jexit();
	}



	/**
	 * Display the view
	 */
	function display($cachable = null, $urlparams = false)
	{
		$CLIENT_CACHEABLE_PUBLIC = 1;
		$CLIENT_CACHEABLE_PRIVATE = 2;

		$userid = JFactory::getUser()->get('id');
		$cc     = $this->input->get('cc', null);
		$view   = $this->input->get('view', '', 'cmd');
		$layout = $this->input->get('layout', '', 'cmd');


		// Access checking for --items-- viewing, will be handled by the items model, this is because THIS display() TASK is used by other views too
		// in future it maybe moved here to the controller, e.g. create a special task item_display() for item viewing, or insert some IF bellow


		// ///////////////////////
		// Display case: ITEM FORM
		// ///////////////////////

		// Also a compatibility check: Layout is form and task is not set:  this is new item submit ...
		if ( $this->input->get('layout', false) == "form" && !$this->input->get('task', false))
		{
			$this->input->set('browser_cachable', 0);
			$this->input->set('task', 'add');
			// The 'add' task does not exist, instead it is an alias to 'edit' task
			$this->edit();
			return;
		}



		// //////////////////////////////////////////////////////////////////////////
		// Display case: FLEXIcontent frontend view (category, item, favourites, etc)
		// //////////////////////////////////////////////////////////////////////////


		// *******************
		// Handle SERVER Cache
		// *******************

		// SHOW RECENT FAVOURED ITEMS IMMEDIATELY: do not cache the view
		if ($view=='favourites' || ($view=='category' && $layout=='favs')) $cachable = false;

		// AVOID MAKING TOO LARGE (case 1): search view or other view with TEXT search active
		else if ($view=='search' || $this->input->get('filter', '', 'string')) $cachable = false;

		// AVOID MAKING TOO LARGE: (case 2) some field filters are active
		else {
			$cachable = true;
			foreach($_GET as $i => $v) {
				if (substr($i, 0, 7) === "filter_") {   $cachable = false;   break;   }
			}
		}


		// ********************
		// Handle browser Cache
		// ********************

		if ( $cc !== null ) {
			// Currently our plugin will ignore this and force 'private', because of risk to break 3rd party extensions doing cookie-based content per guest
			$browser_cachable = $userid ? $CLIENT_CACHEABLE_PRIVATE : $CLIENT_CACHEABLE_PUBLIC;
		} else {
			$browser_cachable = 0;
		}


		// CASE: urlparams were explicitely given
		if (!empty($urlparams)) $safeurlparams = & $urlparams;

		// CASE: urlparams are empty, use the FULL URL request array (_GET)
		else
		{
			$safeurlparams = array();

			// (1) Add menu URL variables
			$menu = JFactory::getApplication()->getMenu()->getActive();
			if ($menu)
			{
				// Add menu Itemid to make sure that the menu items with --different-- parameter values, will display differently
				$safeurlparams['Itemid'] = 'STRING';

				// Add menu's HTTP query variables so that we match the non-SEF URL exactly, thus we create the same cache-ID for both SEF / non-SEF urls (purpose: save some cache space)
				foreach($menu->query as $_varname => $_ignore)
				{
					$safeurlparams[$_varname] = 'STRING';
				}
			}

			// (2) Add real URL variables (GET)
			foreach($_GET as $_varname => $_ignore)
			{
				$safeurlparams[$_varname] = 'STRING';
			}

			// (3) Add other variables added during Joomla URL routing
			//  ... ?

			/* (redo 1 and 2 but also implement 3) Add any existing URL variables
			 * 1) menu URL variables
			 * 2) real URL variables (GET),
			 * 3) other variables added during Joomla URL routing
			 * NOTE: we only need variable names, (values are ignored)
			 */
			foreach($this->input->getArray() as $_varname => $_ignore)
			{
				$safeurlparams[$_varname] = 'STRING';
			}
		}


		// If component is serving different pages to logged users, this will avoid
		// having users seeing same page after login/logout when conservative caching is used
		if ( $userid = JFactory::getUser()->get('id') )
		{
			$this->input->set('__fc_user_id__', $userid);
			$safeurlparams['__fc_user_id__'] = 'STRING';
		}

		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$use_mobile_layouts  = $cparams->get('use_mobile_layouts', 0);
		$tabletSameAsDesktop = $cparams->get('force_desktop_layout', 0) == 1;

		// If component is serving different pages for mobile devices, this will avoid
		// having users seeing the same page regardless of being on desktop or mobile
		$mobileDetector = flexicontent_html::getMobileDetector();  //$client = JFactory::getApplication()->client; $isMobile = $client->mobile;
		$isMobile = $mobileDetector->isMobile();
		$isTablet = $mobileDetector->isTablet();
		if ( $use_mobile_layouts && $isMobile && (!$isTablet || !$tabletSameAsDesktop) )
		{
			$this->input->set('__fc_client__', 'Mobile' );
			$safeurlparams['__fc_client__'] = 'STRING';
		}

		// Moved code for browser's cache control to system plugin to do at the latest possible point
		// =0, NOT user brower CACHEABLE
		// >0, user browser CACHEABLE, ask browser to store and redisplay it, without revalidating
		// *** Intermediary Cache control
		// 1 means CACHEABLE, PUBLIC  content, proxies can cache: 'Cache-Control:public'
		// 2 means CACHEABLE, PRIVATE (logged user) content, proxies must not cache: 'Cache-Control:private'
		// null will let default (Joomla website) HTTP headers, e.g. re-validate
		$this->input->set('browser_cachable', $browser_cachable);

		//echo "cacheable: ".(int)$cachable." - " . print_r($safeurlparams, true) ."<br/>";
		parent::display($cachable, $safeurlparams);
	}


	/**
	 * Method of the voting without AJAX. Exists for compatibility reasons, since it can be called by Joomla's content vote plugin.
	 *
	 * @access public
	 * @since 1.0
	 */
	function vote()
	{
		// Initialize variables
		$app     = JFactory::getApplication();

		$id   = $this->input->get('id', 0, 'int');
		$cid  = $this->input->get('cid', 0, 'int');
		$url  = $this->input->get('url', '', 'string');

		// Check that the given URL variable is 'safe' (allowed), e.g. not an offsite URL
		if ( ! $url || ! flexicontent_html::is_safe_url($url) )
		{
			if ($url)
			{
				$dolog = JComponentHelper::getParams( 'com_flexicontent' )->get('print_logging_info');
				if ( $dolog ) JFactory::getApplication()->enqueueMessage( 'refused redirection to possible unsafe URL: '.$url, 'notice' );
			}
			global $globalcats;
			$Itemid = $this->input->get('Itemid', 0, 'int');  // maintain current menu item if this was given
			$url = JRoute::_(FlexicontentHelperRoute::getItemRoute($id, $globalcats[$cid]->slug, $Itemid));
		}

		// Finally store the vote
		$this->input->set('no_ajax', 1);
		$this->ajaxvote();

		$this->setRedirect($url);
	}


	/**
	 *  Ajax favourites
	 *
	 * @access public
	 * @since 1.0
	 */
	function ajaxfav()
	{
		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();
		//$db      = JFactory::getDbo();
		//$cparams = JComponentHelper::getParams( 'com_flexicontent' );

		$id   = $this->input->get('id', 0, 'int');
		$type = $this->input->get('type', 'item', 'cmd');

		if ($type !== 'item' && $type !== 'category')
		{
			jexit('Type: ' . $type . ' not supported');
		}

		// Get Favourites field configuration
		$favs_field = reset(FlexicontentFields::getFieldsByIds(array(12)));
		$favs_field->parameters = new JRegistry($favs_field->attribs);

		$usercount = (int) $favs_field->parameters->get('display_favoured_usercount', 0);
		$allow_guests_favs = $favs_field->parameters->get('allow_guests_favs', 1);

		// Guest user (and allowing favourites for guests via Cookie data is disabled)
		if (!$user->id && !$allow_guests_favs)
		{
			echo 'login';
		}

		// Guest user does not have DB data, instead use Cookie data
		else
		{
			// Get model of the give type
			$model = $this->getModel($type);

			if (!$user->id)
			{
				// Output simple response without counter
				/*echo flexicontent_favs::getInstance()->toggleIsFavoured($type, $id, true) < 1
					? 'removed'
					: 'added';*/
				$isfav = flexicontent_favs::getInstance()->toggleIsFavoured($type, $id, true) < 1;
				flexicontent_favs::getInstance()->saveState();
			}

			// Logged user, update DB, adding / removing given id as favoured
			else
			{
				// Toggle favourite
				$isfav = $model->getFavoured();
				$isfav
					? $model->removefav()
					: $model->addfav();
			}

			// Output response for counter (if this has been enabled)
			$favs = $model->getFavourites();
			echo $isfav
				? ($favs && $usercount ? '-' . $favs : 'removed')
				: ($favs && $usercount ? '+' . $favs : 'added');
		}

		// Item favouring changed clean item-related caches
		$cache = FLEXIUtilities::getCache($group='', 0);
		$cache->clean('com_flexicontent');  // Also clean this (as it contains Joomla frontend view cache)

		jexit();
	}


	/**
	 *  Ajax review form
	 *
	 * @access public
	 * @since 3.0
	 */
	function getreviewform()
	{
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();

		$html_tagid  = $this->input->get('tagid', '', 'cmd');
		$content_id  = $this->input->get('content_id', 0, 'int');
		$review_type = $this->input->get('review_type', 'item', 'cmd');

		$errors = array();


		/**
		 * Check for validation failures on posted data
		 */

		if (!$content_id)
		{
			$errors[] = 'content_id is zero';
		}

		if ($review_type !== 'item')
		{
			$errors[] = 'review_type <> "item" is not yet supported';
		}


		/**
		 * Do voting / reviewing permissions check
		 */

		if (!count($errors))
		{
			$item  = null;
			$field = null;
			$this->reviewPrepare($content_id, $item, $field, $errors, $_checkSubmit = true);
		}

		// Check if an error has been encountered
		if (count($errors))
		{
			$result = (object) array(
				'error' => 0,
				'html' => '
					<div class="fc-mssg fc-warning fc-nobgimage">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						' . implode('<br>', $errors) . '
					</div>'
			);

			jexit(json_encode($result));
		}

		// Load review of a logged user
		$review = false;

		if ($user->id)
		{
			$query = $db->getQuery(true)
				->select('*')
				->from('#__flexicontent_reviews_dev AS r')
				->where('r.content_id = ' . (int) $content_id)
				->where('r.type = ' . $db->Quote($review_type))
				->where('r.user_id = ' . (int) $user->id);
			$review = $db->setQuery($query)->loadObject();
		}

		$layouts_path = null;

		/**
		 * field: 'Voting' field
		 * item: item or category record
		 * type: 'item' or 'category'
		 * review: review record
		 * html_tagid: HTML tag id of target box
		 * user => user object (of user submiting the review)
		 */

		$review_type = 'item';

		$result = (object) array(
			'html' => '
				<form id="fcvote_review_form_' . $item->id . '" name="fcvote_review_form_' . $item->id . '" action="javascript:;">

					<input type="hidden" name="review_id"  value="'. ($review ? $review->id : '').'" form="fcvote_review_form_' . $item->id . '" />
					<input type="hidden" name="content_id"  value="' . $item->id . '" />
					<input type="hidden" name="review_type" value="' . $review_type . '" />

					<table class="fc-form-tbl fcinner">

						<tr class="fcvote_review_form_title_row">
							<td class="key">
								<label class="fc-prop-lbl" for="fcvote_review_form_' . $item->id . '_title">' . JText::_('FLEXI_VOTE_REVIEW_TITLE') . '</label>
							</td>
							<td>
								<input type="text" name="title" size="200"
									value="'.htmlspecialchars( ($review ? $review->title : ''), ENT_COMPAT, 'UTF-8' ).'"
									id="fcvote_review_form_' . $item->id . '_title"
								/>
							</td>
						</tr>

						<tr class="fcvote_review_form_email_row">
							<td class="key">
								<label class="fc-prop-lbl" for="fcvote_review_form_' . $item->id . '_email">' . JText::_('FLEXI_VOTE_REVIEW_EMAIL') . '</label>
							</td>
							<td>' . ($user->id ? '<span class=badge>' . $user->email . '</span>' : '
								<input required type="text" name="email" size="200"
									value="'.htmlspecialchars( ($review ? $review->email : ''), ENT_COMPAT, 'UTF-8' ).'"
									id="fcvote_review_form_' . $item->id . '_email"
								/>') . '
							</td>
						</tr>

						<tr class="fcvote_review_form_text_row">
							<td class="key">
								<label class="fc-prop-lbl" for="fcvote_review_form_' . $item->id . '_text">'.JText::_('FLEXI_VOTE_REVIEW_TEXT').'</label>
							</td>
							<td class="top">
								<textarea required name="text" rows="4" cols="200" id="fcvote_review_form_' . $item->id . '_text" >' . ($review ? $review->text : '') . '</textarea>
							</td>
						</tr>

						<tr class="fcvote_review_form_submit_btn_row">
							<td class="key"></td>
							<td class="top">
								<input type="button" class="btn btn-success fcvote_review_form_submit_btn"
									onclick="fcvote_submit_review_form(\'' . $html_tagid . '\', this.form); return false;"
									value="' . JText::_('FLEXI_VOTE_REVIEW_SUMBIT') . '"
								/>
							</td>
						</tr>

					</table>

				</form>
		');

		jexit(json_encode($result));
	}


	function storereviewform()
	{
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();

		$review_id   = $this->input->get('review_id', 0, 'int');
		$content_id  = $this->input->get('content_id', 0, 'int');
		$review_type = $this->input->get('review_type', 'item', 'cmd');

		$errors = array();

		// Validate title
		$title = flexicontent_html::dataFilter($this->input->get('title', '', 'string'), $maxlength=255, 'STRING', 0);  // Decode entities, and strip HTML

		// Validate email
		$email = $user->id ? $user->email : flexicontent_html::dataFilter($this->input->get('email', '', 'string'), $maxlength=255, 'EMAIL', 0);  // Validate email

		// Validate text
		$text = flexicontent_html::dataFilter($this->input->get('text', '', 'string'), $maxlength=10000, 'STRING', 0);  // Validate text only: decode entities and strip HTML


		/**
		 * Check for validation failures on posted data
		 */

		if (!$content_id)
		{
			$errors[] = 'content_id is zero';
		}

		if (!$email)
		{
			$errors[] = 'Email is invalid or empty';
		}
		elseif (!$user->id)
		{
			$query = 'SELECT id FROM #__users WHERE email = ' . $db->Quote($email);
			$reviewer = $db->setQuery($query)->loadObject();

			if ($reviewer)
			{
				$errors[] = 'Please login';
			}
		}

		if (!$text)
		{
			$errors[] = 'Text is invalid or empty';
		}

		if ($review_type !== 'item')
		{
			$errors[] = 'review_type <> item is not yet supported';
		}


		/**
		 * Do voting / reviewing permissions check
		 */

		if (!count($errors))
		{
			$item  = null;
			$field = null;
			$this->reviewPrepare($content_id, $item, $field, $errors, $_checkSubmit = true);
		}

		if (!count($errors))
		{
			// Get a 'flexicontent_reviews' JTable instance
			$review = JTable::getInstance($type = 'flexicontent_reviews', $prefix = '', $config = array());

			$review_props = array('content_id' => $content_id, 'user_id' => $user->id, 'type' => $review_type);

			if ($review_id)
			{
				$review_props['id'] = $review_id;
			}

			// Try to find existing review and delete
			if (!$review->load($review_props))
			{
				$review->reset();
			}

			$review->content_id = $content_id;
			$review->type  = $review_type;
			$review->title = $title;
			$review->email = $user->id ? '' : $email;
			$review->user_id = $user->id;
			$review->text  = $text;

			// Save review into DB
			if (!$review->store())
			{
				$errors[] = 'Error storing review : ' . $review->getError();
			}
		}

		// Create success response
		if (!count($errors))
		{
			$mssg = $review_id
				? 'Existing review updated'
				: 'New review saved';

			$result = (object) array(
				'error' => 0,
				'html' => '
					<div class="fc-mssg fc-success fc-nobgimage">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						' . $mssg . '
					</div>'
			);
		}

		// Create error response
		else
		{
			$result = (object) array(
				'error' => 1,
				'html' => '
					<div class="fc-mssg fc-warning fc-nobgimage">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						' . implode('<br>', $errors) . '
					</div>'
			);
		}

		// Send response to client
		jexit(json_encode($result));
	}



	/**
	 * Method to do prechecks for loading / saving review forms
	 *
	 * @param   object    $item       by reference variable to return the reviewed item
	 * @param   object    $field      by reference variable to return the voting (reviews) field
	 * @param   array     $errors     The array of error messages that have occured
	 *
	 * @return  void
	 *
	 * @since   3.3.0
	 */
	private function reviewPrepare($content_id, & $item = null, & $field = null, $errors = null, $checkSubmit = true)
	{
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDbo();


		/**
		 * Load content item related to the review
		 */

		$item = JTable::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());

		if (!$item->load($content_id))
		{
			$errors[] = 'ID: ' . $content_id . ': ' . $item->getError();
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

		// Check if user has the ACCESS level required for voting
		elseif ($checkSubmit)
		{
			$aid_arr = $user->getAuthorisedViewLevels();
			$acclvl = (int) $field->parameters->get('submit_acclvl', 1);
			$has_acclvl = in_array($acclvl, $aid_arr);

			// Create no access Redirect Message
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
					$acclvl_name = '';
					if ($acclvl)
					{
						$query = 'SELECT title FROM #__viewlevels as level WHERE level.id = ' . (int) $acclvl;
						$acclvl_name = $db->setQuery($query)->loadResult();
						if (!$acclvl_name)
						{
							$acclvl_name = 'Access Level: ' . $acclvl . ' not found / was deleted';
						}
					}

					$no_acc_msg = JText::sprintf( 'FLEXI_NO_ACCESS_TO_VOTE' , $acclvl_name);
				}

				$errors[] = 'You are not authorized to submit reviews';
			}
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
		JLoader::register('FlexicontentControllerReviews', JPATH_BASE.DS.'components'.DS.'com_flexicontent'.DS.'controllers'.DS.'reviews.php');

		$rman = new FlexicontentControllerReviews();
		$rman->ajaxvote();
	}


	/**
	 *  Add new Tag from item screen
	 *
	 */
	function addtag()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$name = $this->input->get('name', null, 'string');
		$cid  = $this->input->get('id', array(0), 'array');
		$cid  = ArrayHelper::toInteger($cid, array(0));
		$cid  = (int) $cid[0];

		// Check if tag exists (id exists or name exists)
		JLoader::register("FlexicontentModelTag", JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'tag.php');
		$model = new FlexicontentModelTag();
		$model->setId($cid);
		$tag = $model->getTag($name);

		if ($tag && $tag->id)
		{
			// Since tag was found just output the loaded tag
			$id   = $model->get('id');
			$name = $model->get('name');
			echo $id."|".$name;
			jexit();
		}

		if ($cid)
		{
			echo "0|Tag not found";
			jexit();
		}

		if (!FlexicontentHelperPerm::getPerm()->CanCreateTags)
		{
			echo "0|".JText::_('FLEXI_NO_AUTH_CREATE_NEW_TAGS');
			jexit();
		}

		// Add the new tag and output it so that it gets loaded by the form
		try {
			$obj = new stdClass();
			$obj->name = $name;
			$obj->published	= 1;
			$result = $model->store($obj);
			echo $result
				? $model->get('id') . '|' . $model->get('name')
				: '0|New tag was not created';
		}
		catch (Exception $e) {
			echo "0|New tag creation failed";
		}
		jexit();
	}


	/**
	 * Logic to change the state of an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function setitemstate()
	{
		// Helper method also checks access, according to user ACL
		flexicontent_html::setitemstate($this, 'json');
	}


	function call_extfunc()
	{
		// Helper method also checks access, since each plugin needs to explicitely declare URL callable methods, which methods also implement access checks
		flexicontent_ajax::call_extfunc();
	}


	/**
	 * Download logic
	 *
	 * @access public
	 * @since 1.0
	 */
	public function download()
	{
		// Import and Initialize some joomla API variables
		jimport('joomla.filesystem.file');

		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$user  = JFactory::getUser();
		$session = JFactory::getSession();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );


		$task   = $this->input->get('task', 'download', 'cmd');
		$method = $this->input->get('method', 'download', 'cmd');

		// Sanity check
		if ($method !== 'view' && $method !== 'download')
		{
			die('unknown download method:' . $method);
		}


		/**
		 * Single file download (via HTTP request) or multi-file downloaded (via a folder structure in session or in DB table)
		 */

		if ($task === 'download_tree')
		{
			// TODO: maybe move this part in module
			$cart_id = $this->input->get('cart_id', 0, 'int');
			if (!$cart_id)
			{
				// Get zTree data and parse JSON string
				$tree_var = $this->input->get('tree_var', '', 'string');
				if ($session->has($tree_var, 'flexicontent'))
				{
					$ztree_nodes_json = $session->get($tree_var, false, 'flexicontent');
				}
				$nodes = json_decode($ztree_nodes_json);
			}

			else
			{
				$cart_token = $this->input->get('cart_token', '', 'cmd');

				$query = ' SELECT * FROM #__flexicontent_downloads_cart WHERE id='. $cart_id;
				$cart = $db->setQuery($query)->loadObject();

				if (!$cart)
				{
					jexit('Cart with ID: ' . $cart_id . ', was not found');
				}

				$cart_token_matches = $cart_token==$cart->token;  // no access will be checked
				$nodes = json_decode($cart->json);
			}


			// Some validation check
			if (!is_array($nodes))
			{
				$app->enqueueMessage("Tree structure is empty or invalid", 'notice');
				$this->setRedirect('index.php', '');
				return;
			}

			$app = JFactory::getApplication();
			$tmp_ffname = 'fcmd_uid_'.$user->id.'_'.date('Y-m-d__H-i-s');
			$targetpath = JPath::clean($app->getCfg('tmp_path') .DS. $tmp_ffname);

			$tree_files = $this->_traverseFileTree($nodes, $targetpath);
			//echo "<pre>"; print_r($tree_files); jexit();

			if ( empty($tree_files) )
			{
				$app->enqueueMessage("No files selected for download", 'notice');
				$this->setRedirect('index.php', '');
				return;
			}
		}

		else//if ($task === 'download' || $task === 'download_file')
		{
			$file_node = new stdClass();
			$file_node->fieldid   = $this->input->get('fid', 0, 'int');
			$file_node->contentid = $this->input->get('cid', 0, 'int');
			$file_node->fileid    = $this->input->get('id', 0, 'int');

			$coupon_id    = $this->input->get('conid', 0, 'int');
			$coupon_token = $this->input->get('contok', '', 'string');

			if ($coupon_id)
			{
				$_nowDate = 'UTC_TIMESTAMP()';
				$_nullDate = $db->Quote($db->getNullDate());
				$query = ' SELECT *'
					.', CASE WHEN '
					.'   expire_on = '.$_nullDate.'   OR   expire_on > '.$_nowDate
					.'  THEN 0 ELSE 1 END AS has_expired'
					.', CASE WHEN '
					.'   hits_limit = -1   OR   hits < hits_limit'
					.'  THEN 0 ELSE 1 END AS has_reached_limit'
					.' FROM #__flexicontent_download_coupons'
					.' WHERE id = ' . $coupon_id . ' AND token = ' . $db->Quote($coupon_token)
					;
				$coupon = $db->setQuery($query)->loadObject();

				if ($coupon)
				{
					$slink_valid_coupon = !$coupon->has_reached_limit && !$coupon->has_expired ;
					if ( !$slink_valid_coupon )
					{
						$query = ' DELETE FROM #__flexicontent_download_coupons WHERE id='. $coupon->id;
						$db->setQuery($query)->execute();
					}
				}

				// Set to false to indicate not found, since null (not set) will mean not given
				$file_node->coupon = !empty($coupon) ? $coupon : false;
			}

			$tree_files = array($file_node);
		}


		/**
		 * Create and Execute SQL query to retrieve file info
		 */

		// Create SELECT OR JOIN / AND clauses for checking Access
		$access_clauses['select'] = '';
		$access_clauses['join']   = '';
		$access_clauses['and']    = '';
		$using_access = empty($cart_token_matches) && empty($slink_valid_coupon);

		if ( $using_access )
		{
			// note CURRENTLY multi-download feature does not use coupons
			$access_clauses = $this->_createFieldItemAccessClause(
				$get_select_access = true,
				$include_file = ($task === 'download_file' ? 'fileaccess_only' : true)
			);
		}


		/**
		 * Get file data for all files
		 */

		$fields_props = array();
		$fields_conf  = array();
		$valid_files  = array();
		$email_recipients = array();

		foreach ($tree_files as $file_node)
		{
			// Get file variable shortcuts (reforce being int)
			$field_id   = (int) $file_node->fieldid;
			$content_id = (int) $file_node->contentid;
			$file_id    = (int) $file_node->fileid;

			if (!isset($fields_conf[$field_id]))
			{
				$q = 'SELECT attribs, name, field_type FROM #__flexicontent_fields WHERE id = '.(int) $field_id;
				$db->setQuery($q);
				$fld = $db->loadObject();
				$fields_conf[$field_id] = new JRegistry($fld->attribs);
				$fields_props[$field_id] = $fld;
			}
			$field_type = $fields_props[$field_id]->field_type;

			$query  = 'SELECT DISTINCT f.id, f.filename, f.filename_original, f.altname, f.secure, f.url, f.hits, f.stamp, f.size'
					.($task !== 'download_file'
						? ', u.email as item_owner_email' .
							', i.title as item_title, i.introtext as item_introtext, i.fulltext as item_fulltext' .
							', i.language as item_language, ie.type_id as item_type_id, i.access as item_access' .
							// item and current category slugs (for URL in notifications)
							', CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as itemslug' .
							', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
						: '')
					. ', dh.id as history_id'  // download history
					. $access_clauses['select']  // has access

					.' FROM #__flexicontent_files AS f '
					.($field_type=='file' ? ' LEFT JOIN #__flexicontent_fields_item_relations AS rel ON '
						. ' rel.field_id = '. $field_id . ' AND rel.value = ' . (int) $file_id . ' AND rel.item_id = ' . (int) $content_id
						: '')  // Only check value usage for 'file' field
					.($task !== 'download_file' ? ' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = '. $field_id : '')
					.($task !== 'download_file' ? ' LEFT JOIN #__content AS i ON i.id = '. $content_id : '')
					.($task !== 'download_file' ? ' LEFT JOIN #__categories AS c ON c.id = i.catid' : '')
					.($task !== 'download_file' ? ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id' : '')
					.($task !== 'download_file' ? ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id' : '')
					.($task !== 'download_file' ? ' LEFT JOIN #__users AS u ON u.id = i.created_by' : '')
					.' LEFT JOIN #__flexicontent_download_history AS dh ON dh.file_id = f.id AND dh.user_id = '. (int)$user->id
					. $access_clauses['join']
					.' WHERE 1'
					.($task !== 'download_file' ? ' AND i.id = ' . $content_id : '')
					.($task !== 'download_file' ? ' AND fi.id = ' . $field_id : '')
					.' AND f.id = ' . $file_id
					.' AND f.published= 1'
					. $access_clauses['and']
					;
			$file = $db->setQuery($query)->loadObject();
			//echo "<pre>". print_r($file, true) ."</pre>"; exit;


			/**
			 * Check if file was found AND IF user has required Access Levels
			 */

			if ( empty($file) || ($using_access && (!$file->has_content_access || !$file->has_field_access || !$file->has_file_access)) )
			{
				if (empty($file))
				{
					$msg = JText::_('FLEXI_FDC_FAILED_TO_FIND_DATA');     // Failed to match DB data to the download URL data
				}

				else
				{
					$msg = JText::_( 'FLEXI_ALERTNOTAUTH' );

					if (!empty($file_node->coupon))
					{
						if ( $file_node->coupon->has_expired )              $msg .= JText::_('FLEXI_FDC_COUPON_HAS_EXPIRED');         // No access and given coupon has expired
						else if ( $file_node->coupon->has_reached_limit )   $msg .= JText::_('FLEXI_FDC_COUPON_REACHED_USAGE_LIMIT'); // No access and given coupon has reached download limit
						else $msg = "unreachable code in download coupon handling";
					}

					else
					{
						if (isset($file_node->coupon))
						{
							$msg .= "<br/> <small>".JText::_('FLEXI_FDC_COUPON_NO_LONGER_USABLE')."</small>";
						}

						// Redirect unlogged user to login
						if ($user->guest)
						{
							$uri    = JUri::getInstance();
							$return	= $uri->toString();
							$url    = $cparams->get('login_page', 'index.php?option=com_users&view=login');
							$return = strtr(base64_encode($return), '+/=', '-_,');
							$url   .= '&return='.$return; // '&return='.base64_encode($return);
							$url   .= '&isfcurl=1';

							$app->setHeader('status', 403, true);
							$app->enqueueMessage(JText::sprintf('FLEXI_LOGIN_TO_ACCESS', $url), 'error');
							$app->redirect($url);
						}

						// Use custom unauthorized page for already logged user
						elseif ($cparams->get('unauthorized_page', ''))
						{
							$app->setHeader('status', 403, true);
							$app->redirect($cparams->get('unauthorized_page'));
						}

						// JDEBUG is OFF, output a simple not authorized message
						elseif(!JDEBUG)
						{
							$app->setHeader('status', 403, true);
							$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_VIEW'), 'error');
							$app->redirect('index.php');
						}

						// JDEBUG is ON, output a detailed message
						$msg .= ''
							.(!$file->has_content_access ? "<br/><br/> ".JText::_('FLEXI_FDC_NO_ACCESS_TO')
								." -- ".JText::_('FLEXI_FDC_CONTENT_CONTAINS')." ".JText::_('FLEXI_FDC_WEBLINK')
								."<br/><small>(".JText::_('FLEXI_FDC_CONTENT_EXPLANATION').")</small>"
								: '')
							.(!$file->has_field_access ? "<br/><br/> ".JText::_('FLEXI_FDC_NO_ACCESS_TO')
								." -- ".JText::_('FLEXI_FDC_FIELD_CONTAINS')." ".JText::_('FLEXI_FDC_WEBLINK')
								: '')
							.(!$file->has_file_access ? "<br/><br/> ".JText::_('FLEXI_FDC_NO_ACCESS_TO') ." -- ".JText::_('FLEXI_FDC_FILE')." " : '')
						;
						$msg .= "<br/><br/> ". JText::sprintf('FLEXI_FDC_FILE_DATA', $file_id, $content_id, $field_id);
					}

					// Enqueue the final created message
					$app->enqueueMessage($msg, 'warning');
				}

				// Only abort further execution for single file download
				if ($task !== 'download_tree')
				{
					$this->setRedirect('index.php', '');
					return;
				}
			}


			/**
			 * (for non-URL) Create file path and check file exists
			 */

			if (!$file->url)
			{
				$basePath = $file->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;
				$file->abspath = str_replace(DS, '/', JPath::clean($basePath.DS.$file->filename));

				if (!JFile::exists($file->abspath))
				{
					$msg = JText::_( 'FLEXI_REQUESTED_FILE_DOES_NOT_EXIST_ANYMORE' );
					$app->enqueueMessage($msg, 'notice');

					// Only abort for single file download
					if ($task !== 'download_tree')
					{
						$this->setRedirect('index.php', '');
						return;
					}
				}
			}
			else
			{
				/**
				 * We may need absolute URL path later use JUri::root() !! for media manager Links
				 * we may use readfile(Absolute URL) to force download of a URL link !!
				 */
				$file->abspath = $file->url == 2
					? JUri::root() . $file->filename
					: $file->filename;
			}


			/**
			 * Get item and field JTable records, and then load field's configuration
			 */

			$item = JTable::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
			$field = JTable::getInstance($type = 'flexicontent_fields', $prefix = '', $config = array());
			$item->load($file_node->contentid);
			$field->load($file_node->fieldid);
			FlexicontentFields::loadFieldConfig($field, $item);


			/**
			 * Trigger pluging event 'onFieldValueAction_FC'
			 * Import all system plugins, and all FC field plugins
			 */

			ob_start();
			JPluginHelper::importPlugin('system');
			JPluginHelper::importPlugin('flexicontent_fields');
			$text = ob_get_contents();
			ob_end_clean();

			// Die on plugin output but ... ignore bogus plugin code adding white characters to output
			if (trim($text))
			{
				die('Aborting, plugin output detected: ' . $text);
			}


			$value_order = null;
			$config = array(
				'fileid' => $file_id,
				'task' => $task,  // (string) 'download', 'download_tree'
				'method' => $method,  // (string) 'view', 'download'
				'coupon_id' => $coupon_id,  // int or null
				'coupon_token' => $coupon_token // string or null
			);

			$result = FLEXI_J40GE
				? $app->triggerEvent('onFieldValueAction_FC', array(&$field, &$item, $value_order, &$config))
				: JEventDispatcher::getInstance()->trigger('onFieldValueAction_FC', array(&$field, &$item, $value_order, &$config));

			// Abort on pluging event code returning value -- false --
			if ($result === false)
			{
				// Event should have set the warning / error message ... set none here
				$this->setRedirect($this->refererURL);
				return;
			}


			/**
			 * Increment hits counter of file, and hits counter of file-user history
			 */

			$filetable = JTable::getInstance('flexicontent_files', '');
			$filetable->hit($file_id);
			if ( empty($file->history_id) )
			{
				$query = ' INSERT #__flexicontent_download_history '
					. ' SET user_id = ' . (int)$user->id
					. '  , file_id = ' . $file_id
					. '  , last_hit_on = NOW()'
					. '  , hits = 1'
					;
			}
			else
			{
				$query = ' UPDATE #__flexicontent_download_history '
					. ' SET last_hit_on = NOW()'
					. '  , hits = hits + 1'
					. ' WHERE id = '. (int)$file->history_id
					;
			}
			$db->setQuery($query)->execute();


			/**
			 * Increment hits on download coupon or delete the coupon if it has expired due to date or hits limit
			 */

			if (!empty($file_node->coupon))
			{
				if (!$file_node->coupon->has_reached_limit && !$file_node->coupon->has_expired)
				{
					$query = ' UPDATE #__flexicontent_download_coupons'
						.' SET hits = hits + 1'
						.' WHERE id='. $file_node->coupon->id
						;
					$db->setQuery($query)->execute();
				}
			}


			/**
			 * Special case file is a URL
			 */

			if ($file->url)
			{
				// Check and prefix URL Media manager links too
				$url = $file->url == 2
					? JUri::root(true) . '/' . $file->filename
					: $file->filename;
				$ext = strtolower(flexicontent_upload::getExt($url));

				// Check for empty URL
				if (empty($url))
				{
					$msg = "File URL is empty: ".$file->url;
					$app->enqueueMessage($msg, 'error');
					return false;
				}

				// skip url-based file if downloading multiple files
				if ($task == 'download_tree')
				{
					$msg = "Skipped URL based file: ".$url;
					$app->enqueueMessage($msg, 'notice');
					continue;
				}
				else
				{
					$force_url_download         = (int) $fields_conf[$field_id]->get('force_url_download', 0);
					$force_url_download_exts    = preg_split("/[\s]*,[\s]*/", strtolower($fields_conf[$field_id]->get('force_url_download_exts', 'bmp,wbmp,gif,jpg,jpeg,png,webp,ico,wav,mp3,aiff')));
					$force_url_download_exts    = array_flip($force_url_download_exts);
					$force_url_download_max_kbs = (int) $fields_conf[$field_id]->get('force_url_download_max_kbs', 100000);

					if ($force_url_download && isset($force_url_download_exts[$ext]))
					{
						$size = $this->_get_file_size_from_url($url, $retry = true);

						if ($size != $file->size)
						{
							$file->size = $size;
						}
					}

					/**
					 * Just redirect to the file URL. If force URL download is disabled, or does not match criteria.
					 * Also do not force is file size is suspiciously small, propably it was calculated correctly !!
					 */
					if (!$force_url_download || !isset($force_url_download_exts[$ext]) || $file->size < 2048 || $file->size > ($force_url_download_max_kbs * 1024))
					{
						// Redirect to the file download link
						@header("Location: ".$url."","target=blank");
						$app->close();
					}
				}
			}


			/**
			 * Set file (tree) node and assign file into valid files for downloading
			 */

			$file->node = $file_node;
			$valid_files[$file_id] = $file;

			$file->hits++;
			$per_downloads = $fields_conf[$field_id]->get('notifications_step', 20);

			// Create current date string according to configuration
			$current_date = flexicontent_html::getDateFieldDisplay($fields_conf[$field_id], $_date_ = '', 'stamp_');

			$file->header_text = $fields_conf[$field_id]->get('pdf_header_text', '');
			$file->footer_text = $fields_conf[$field_id]->get('pdf_footer_text', '');

			$result = preg_match_all("/\%\%([^%]+)\%\%/", $file->header_text, $translate_matches);
			if (!empty($translate_matches[1])) foreach ($translate_matches[1] as $translate_string)
			{
				$file->header_text = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $file->header_text);
			}
			$file->header_text = str_replace('{{current_date}}', $current_date, $file->header_text);

			$result = preg_match_all("/\%\%([^%]+)\%\%/", $file->footer_text, $translate_matches);
			if (!empty($translate_matches[1])) foreach ($translate_matches[1] as $translate_string)
			{
				$file->footer_text = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $file->footer_text);
			}
			$file->footer_text = str_replace('{{current_date}}', $current_date, $file->footer_text);

			/**
			 * Send notifications email about file download if file was download via a field that has these notifications enabled
			 */
			if ($task !== 'download_file' && $fields_conf[$field_id]->get('send_notifications') && ($file->hits % $per_downloads == 0) )
			{
				// Calculate (once per file) some text used for notifications
				$file->__file_title__ = $file->altname && $file->altname != $file->filename
					? $file->altname . ' ['.$file->filename.']'
					: $file->filename;

				$item = new stdClass();
				$item->access = $file->item_access;
				$item->type_id = $file->item_type_id;
				$item->language = $file->item_language;
				$file->__item_url__ = JRoute::_(FlexicontentHelperRoute::getItemRoute($file->itemslug, $file->catslug, 0, $item));

				// Parse and identify language strings and then make language replacements
				$notification_tmpl = $fields_conf[$field_id]->get('notification_tmpl');
				if ( empty($notification_tmpl) )
				{
					$notification_tmpl = JText::_('FLEXI_HITS') .": ".$file->hits;
					$notification_tmpl .= '%%FLEXI_FDN_FILE_NO%% __file_id__:  "__file_title__" '."\n";
					$notification_tmpl .= '%%FLEXI_FDN_FILE_IN_ITEM%% "__item_title__":' ."\n";
					$notification_tmpl .= '__item_url__';
				}

				$result = preg_match_all("/\%\%([^%]+)\%\%/", $notification_tmpl, $translate_matches);
				$translate_strings = $result ? $translate_matches[1] : array();
				foreach ($translate_strings as $translate_string)
				{
					$notification_tmpl = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $notification_tmpl);
				}
				$file->notification_tmpl = $notification_tmpl;

				// Send to hard-coded email list
				$send_all_to_email = $fields_conf[$field_id]->get('send_all_to_email');
				if ($send_all_to_email)
				{
					$emails = preg_split("/[\s]*[;,][\s]*/", $send_all_to_email);
					foreach($emails as $email)
					{
						$email_recipients[trim($email)][] = $file;
					}
				}

				// Send to item owner
				$send_to_current_item_owner = (int) $fields_conf[$field_id]->get('send_to_current_item_owner');
				if ($send_to_current_item_owner)
				{
					$email_recipients[$file->item_owner_email][] = $file;
				}

				// Send to email assigned to email field in same content item
				$send_to_email_field = (int) $fields_conf[$field_id]->get('send_to_email_field');
				if ($send_to_email_field) {

					$q  = 'SELECT value '
						.' FROM #__flexicontent_fields_item_relations '
						.' WHERE field_id = ' . $send_to_email_field .' AND item_id='.$content_id;
					$db->setQuery($q);
					$email_values = $db->loadColumn();

					foreach ($email_values as $i => $email_value) {
						if ( @unserialize($email_value)!== false || $email_value === 'b:0;' ) {
							$email_values[$i] = unserialize($email_value);
						} else {
							$email_values[$i] = array('addr' => $email_value, 'text' => '');
						}
						$addr = @ $email_values[$i]['addr'];
						if ( $addr ) {
							$email_recipients[$addr][] = $file;
						}
					}
				}
			}
		}
		//echo "<pre>". print_r($valid_files, true) ."</pre>";
		//echo "<pre>". print_r($email_recipients, true) ."</pre>";
		//jexit();


		if (!empty($email_recipients))
		{
			ob_start();
			$sendermail	= $app->getCfg('mailfrom');
			$sendermail	= JMailHelper::cleanAddress($sendermail);
			$sendername	= $app->getCfg('sitename');
			$subject    = JText::_('FLEXI_FDN_FILE_DOWNLOAD_REPORT');
			$message_header = JText::_('FLEXI_FDN_FILE_DOWNLOAD_REPORT_BY') .': '. $user->name .' ['.$user->username .']';


			/**
			 * Send email notifications about file being downloaded
			 */

			// Personalized email per subscribers
			foreach ($email_recipients as $email_addr => $files_arr)
			{
				$to = JMailHelper::cleanAddress($email_addr);
				$_message = $message_header;

				foreach($files_arr as $filedata)
				{
					$_mssg_file = $filedata->notification_tmpl;
					$_mssg_file = str_ireplace('__file_id__', $filedata->id, $_mssg_file);
					$_mssg_file = str_ireplace('__file_title__', $filedata->__file_title__, $_mssg_file);
					$_mssg_file = str_ireplace('__item_title__', $filedata->item_title, $_mssg_file);
					//$_mssg_file = str_ireplace('__item_title_linked__', $filedata->password, $_mssg_file);
					$_mssg_file = str_ireplace('__item_url__', $filedata->__item_url__, $_mssg_file);
					$count = 0;
					$_mssg_file = str_ireplace('__file_hits__', $filedata->hits, $_mssg_file, $count);
					if ($count == 0) $_mssg_file = JText::_('FLEXI_HITS') .": ".$file->hits ."\n". $_mssg_file;
					$_message .= "\n\n" . $_mssg_file;
				}
				//echo "<pre>". $_message ."</pre>";

				$from = $sendermail;
				$fromname = $sendername;
				$recipient = array($to);
				$html_mode=false; $cc=null; $bcc=null;
				$attachment=null; $replyto=null; $replytoname=null;

				$send_result = JFactory::getMailer()->sendMail( $from, $fromname, $recipient, $subject, $_message, $html_mode, $cc, $bcc, $attachment, $replyto, $replytoname );
			}
			ob_end_clean();
		}


		// * Required for IE, otherwise Content-disposition is ignored
		if (ini_get('zlib.output_compression'))
		{
			ini_set('zlib.output_compression', 'Off');
		}


		// *** Single file download
		if ($task != 'download_tree')
		{
			$dlfile = reset($valid_files);
		}

		/* Multi-file download, create a compressed archive (e.g. ZIP) to contain them,
		 * also adding a text file with name and descriptions
		 * URLs for download-tree should have been skipped above */
		else
		{
			// Create target (top level) folder
			JFolder::create($targetpath, 0755);

			// Copy Files
			foreach ($valid_files as $file) JFile::copy($file->abspath, $file->node->targetpath);

			// Create text/html file with ITEM title / descriptions
			// TODO replace this with a TEMPLATE file ...
			$desc_filename = $targetpath .DS. "_descriptions";
			$handle_txt = fopen($desc_filename.".txt", "w");
			$handle_htm = fopen($desc_filename.".htm", "w");
			fprintf($handle_htm, '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-gb" lang="en-gb" dir="ltr" >
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
</head>
<body>
'
			);
			foreach ($valid_files as $file) {
				fprintf($handle_txt, "%s", $file->item_title."\n\n");
				fprintf($handle_txt, "%s", flexicontent_html::striptagsandcut($file->item_introtext) ."\n\n" );
				if ( strlen($file->item_fulltext) ) fprintf($handle_txt, "%s", flexicontent_html::striptagsandcut($file->item_fulltext)."\n\n" );

				fprintf($handle_htm, "%s", "<h2>".$file->item_title."</h2>");
				fprintf($handle_htm, "%s", "<blockquote>".$file->item_introtext."</blockquote><br/>");
				if ( strlen($file->item_fulltext) ) fprintf($handle_htm, "%s", "<blockquote>".$file->item_fulltext."</blockquote><br/>");
				fprintf($handle_htm, "<hr/><br/>");
			}
			fclose($handle_txt);
			fclose($handle_htm);

			// Get file list recursively, and calculate archive filename
			$fileslist   = JFolder::files($targetpath, '.', $recurse=true, $fullpath=true);
			$archivename = $tmp_ffname . '.zip';
			$archivepath = JPath::clean( $app->getCfg('tmp_path').DS.$archivename );


			/**
			 * Create the archive
			 */

			$za = new flexicontent_zip();
			$zip_result = $za->open($archivepath, ZipArchive::CREATE);
			if ($zip_result !== true)
			{
				$msg = JText::_('FLEXI_OPERATION_FAILED'). ": compressed archive could not be created";
				$app->enqueueMessage($msg, 'notice');
				$this->setRedirect('index.php', '');
				return;
			}
			$za->addDir($targetpath, "");
			$za->close();


			/**
			 * Remove temporary folder structure
			 */

			if (!JFolder::delete(($targetpath)) )
			{
				$msg = "Temporary folder ". $targetpath ." could not be deleted";
				$app->enqueueMessage($msg, 'notice');
			}

			// Delete old files (they can not be deleted during download time ...)
			$tmp_path = JPath::clean($app->getCfg('tmp_path'));
			$matched_files = JFolder::files($tmp_path, 'fcmd_uid_.*', $recurse=false, $fullpath=true);

			foreach ($matched_files as $archive_file)
			{
				//echo "Seconds passed:". (time() - filemtime($tmp_folder)) ."<br>". "$filename was last modified: " . date ("F d Y H:i:s.", filemtime($tmp_folder)) . "<br>";
				if (time() - filemtime($archive_file) > 3600) JFile::delete($archive_file);
			}

			// Delete old tmp folder (in case that the some archiving procedures were interrupted thus their tmp folder were not deleted)
			$matched_folders = JFolder::folders($tmp_path, 'fcmd_uid_.*', $recurse=false, $fullpath=true);
			foreach ($matched_folders as $tmp_folder) {
				//echo "Seconds passed:". (time() - filemtime($tmp_folder)) ."<br>". "$filename was last modified: " . date ("F d Y H:i:s.", filemtime($tmp_folder)) . "<br>";
				JFolder::delete($tmp_folder);
			}

			$dlfile = new stdClass();
			$dlfile->filename = 'cart_files_'.date('m-d-Y_H-i-s'). '.zip';   // a friendly name instead of  $archivename
			$dlfile->abspath  = $archivepath;
		}

		// Get file filesize and extension
		$dlfile->size = !$dlfile->url ? filesize($dlfile->abspath) : $dlfile->size;
		$dlfile->ext  = strtolower(flexicontent_upload::getExt($dlfile->filename));

		// Set content type of file (that is an archive for multi-download)
		$ctypes = array(
			"pdf" => "application/pdf", "exe" => "application/octet-stream", "rar" => "application/zip", "zip" => "application/zip",
			"txt" => "text/plain", "doc" => "application/msword", "xls" => "application/vnd.ms-excel", "ppt" => "application/vnd.ms-powerpoint",
			"gif" => "image/gif", "png" => "image/png", "jpeg" => "image/jpg", "jpg" => "image/jpg", "mp3" => "audio/mpeg"
		);
		$dlfile->ctype = isset($ctypes[$dlfile->ext]) ? $ctypes[$dlfile->ext] : "application/force-download";

		if (!$dlfile->url)
		{
			$dlfile->download_filename = strlen($dlfile->filename_original) ? $dlfile->filename_original : $dlfile->filename;
		}
		else
		{
			$_url = strlen($dlfile->filename_original) ? $dlfile->filename_original : $dlfile->filename;
			$dlfile->download_filename = strrpos($_url, '/') !== false
				? substr($_url, strrpos($_url, '/') + 1)
				: $_url;
		}


		/**
		 * Handle PDF time-stamping
		 */

		$pdf = false;
		$dlfile->abspath_tmp = false;
		$dlfile->size_tmp = false;

		// Do not try to stamp URLs
		if (!$dlfile->url && $dlfile->ext == 'pdf' && $fields_conf[$field_id]->get('stamp_pdfs', 0) && $dlfile->stamp)
		{
			// Create new PDF document (initiate FPDI)
			$TCPDF_path = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'TCPDF'.DS.'vendor'.DS.'autoload.php';
			if (file_exists($TCPDF_path))
			{
				$pdf = new flexicontent_FPDI();
				$pdf->setAllPagesHeaderText($file->header_text);
				$pdf->setAllPagesFooterText($file->footer_text);
				$pdf->setHeaderConf(array(
					'ffamily' => $fields_conf[$field_id]->get('pdf_header_ffamily', 'Helvetica'),
					'fstyle' => $fields_conf[$field_id]->get('pdf_header_fstyle', ''),
					'fsize' => $fields_conf[$field_id]->get('pdf_header_fsize', '12'),
					'border_type' => $fields_conf[$field_id]->get('pdf_header_border_type', '0'),
					'border_width' => 2,
					'border_color' => array(0,0,0),
					'text_align' => $fields_conf[$field_id]->get('pdf_header_align', 'C')
				));
				$pdf->setFooterConf(array(
					'ffamily' => $fields_conf[$field_id]->get('pdf_footer_ffamily', 'Helvetica'),
					'fstyle' => $fields_conf[$field_id]->get('pdf_footer_fstyle', ''),
					'fsize' => $fields_conf[$field_id]->get('pdf_footer_fsize', '12'),
					'border_type' => $fields_conf[$field_id]->get('pdf_footer_border_type', '0'),
					'border_width' => 2,
					'border_color' => array(0,0,0),
					'text_align' => $fields_conf[$field_id]->get('pdf_footer_align', 'C')
				));

				// Set the source file
				try
				{
					$pageCount = $pdf->setSourceFile($dlfile->abspath);
					//echo '<span>Converting file: <span style="color: darkgreen; font-weight: bold;">'.$dlfile->abspath . '</span></span><br/>';
				}
				catch (Exception $e)
				{
					$pdf = false;
					//die('<blockquote>Cannot convert file: <span style="color: darkred; font-weight: bold;">'.$dlfile->abspath.'</span> Error: '. $e->getMessage() . '</blockquote>');
				}
			}
		}

		// IF $pdf is non-empty, then it was created above
		if ($pdf)
		{
			// Loop through all pages
			for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++)
			{
				// Read next page from source file
				$tplIdx = $pdf->importPage($pageNo);

				// Create new empty page, and add it to it the imported page, formating it according to our templage
				$pdf->AddPage();
				$pdf->useTemplate($tplIdx, null, null, 0, 0, true);
			}

			// Output formatted PDF data into a new file
			$tmpDir = ini_get("upload_tmp_dir")  ?  ini_get("upload_tmp_dir")  :  sys_get_temp_dir();
			$tmpDir .= DIRECTORY_SEPARATOR . "fc_pdf_downloads";

			if (!file_exists($tmpDir))
			{
				if (@ !mkdir($tmpDir) ) die('Can not create temporary folder for handling PDF file');
			}

			$pdf_filename = basename($dlfile->abspath);
			$dlfile->abspath_tmp = $tmpDir . DIRECTORY_SEPARATOR . date('Y_m_d_').uniqid() . '_' . $pdf_filename;
			$pdf->Output($dlfile->abspath_tmp, "F");
			$dlfile->size_tmp = filesize($dlfile->abspath_tmp);

			// Output formatted PDF data to stdout (this browser if running via web-server, we would probably want to set HTTP headers first)
			//$pdf->Output();

			//die('is PDF: ' . $dlfile->abspath);
		}
		//echo '<pre>'; print_r($dlfile); exit;


		// *****************************************
		// Output an appropriate Content-Type header
		// *****************************************
		header("Pragma: public"); // required
		header("Expires: 0");
		//header("HTTP/1.1 200 OK");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false); // required for certain browsers
		header("Content-Type: ".$dlfile->ctype);

		// Set desired filename when downloading, quoting it to allow spaces in filenames
		$method == 'view'
			? header("Content-Disposition: inline; filename=\"".$dlfile->download_filename."\";" )
			: header("Content-Disposition: attachment; filename=\"".$dlfile->download_filename."\";" );

		header("Content-Transfer-Encoding: binary");
		header("Content-Length: " . ($dlfile->size_tmp ?: $dlfile->size));


		// *******************************
		// Finally read file and output it
		// *******************************

		if ( !FLEXIUtilities::funcIsDisabled('set_time_limit') ) @set_time_limit(0);

		$chunksize = 1 * (1024 * 1024); // 1MB, highest possible for fread should be 8MB
		$filesize  = $dlfile->size_tmp ?: $dlfile->size;

		// Do not try to read too big file URL files
		if ($dlfile->url && $filesize > 100 * (1024 * 1024))
		{
			@header("Location: ".$url."","target=blank");
			$app->close();
		}
		elseif ($filesize > $chunksize)
		{
			$handle = @fopen($dlfile->abspath_tmp ?: $dlfile->abspath, 'rb');

			// Redirect to the exteral file download link if we failed to open the URL
			if (!$handle && $dlfile->url)
			{
				@header("Location: ".$url."","target=blank");
				$app->close();
			}

			while(!feof($handle))
			{
				print(@fread($handle, $chunksize));
				ob_flush();
				flush();
			}
			fclose($handle);
		}

		else
		{
			// This is good for small files, it will read an output the file into
			// memory and output it, it will cause a memory exhausted error on large files
			ob_clean();
			flush();
			readfile($dlfile->abspath_tmp ?: $dlfile->abspath);
		}


		// ****************************************************
		// In case of multi-download clear the session variable
		// ****************************************************
		//if ($task=='download_tree') $session->set($tree_var, false,'flexicontent');

		// Done ... terminate execution
		$app->close();
	}


	/**
	 * External link logic
	 *
	 * @access public
	 * @since 1.5
	 */
	function weblink()
	{
		// Import and Initialize some joomla API variables
		$app     = JFactory::getApplication();
		$db      = JFactory::getDbo();
		$user    = JFactory::getUser();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );

		// Get HTTP REQUEST variables
		$field_id    = $this->input->get('fid', 0, 'int');
		$content_id  = $this->input->get('cid', 0, 'int');
		$value_order = $this->input->get('ord', 0, 'int');


		/**
		 * Create and Execute SQL query to retrieve file info
		 */

		// Create SELECT OR JOIN / AND clauses for checking Access
		$access_clauses['select'] = '';
		$access_clauses['join']   = '';
		$access_clauses['and']    = '';
		$access_clauses = $this->_createFieldItemAccessClause( $get_select_access = true, $include_file = false );

		$query  = 'SELECT value'
				. $access_clauses['select']
				.' FROM #__flexicontent_fields_item_relations AS rel'
				.' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
				.' LEFT JOIN #__content AS i ON i.id = rel.item_id'
				.' LEFT JOIN #__categories AS c ON c.id = i.catid'
				.' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
				.' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
				. $access_clauses['join']
				.' WHERE rel.item_id = ' . $content_id
				.' AND rel.field_id = ' . $field_id
				.' AND rel.valueorder = ' . $value_order
				. $access_clauses['and']
				;
		$link_data = $db->setQuery($query)->loadObject();


		/**
		 * Check if web link value was found AND IF user has required Access Levels
		 */

		if ( empty($link_data) || (!$link_data->has_content_access || !$link_data->has_field_access) )
		{
			if (empty($link_data))
			{
				$msg = JText::_('FLEXI_FDC_FAILED_TO_FIND_DATA');     // Failed to match DB data to the download URL data
			}

			else
			{
				// Redirect unlogged user to login
				if ($user->guest)
				{
					$uri    = JUri::getInstance();
					$return	= $uri->toString();
					$url    = $cparams->get('login_page', 'index.php?option=com_users&view=login');
					$return = strtr(base64_encode($return), '+/=', '-_,');
					$url   .= '&return='.$return; // '&return='.base64_encode($return);
					$url   .= '&isfcurl=1';

					$app->setHeader('status', 403, true);
					$app->enqueueMessage(JText::sprintf('FLEXI_LOGIN_TO_ACCESS', $url), 'error');
					$app->redirect($url);
				}

				// Use custom unauthorized page for already logged user
				elseif ($cparams->get('unauthorized_page', ''))
				{
					$app->setHeader('status', 403, true);
					$app->redirect($cparams->get('unauthorized_page'));
				}

				// JDEBUG is OFF, output a simple not authorized message
				elseif(!JDEBUG)
				{
					$app->setHeader('status', 403, true);
					$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_VIEW'), 'error');
					$app->redirect('index.php');
				}

				// JDEBUG is ON, output a detailed message
				$msg  = JText::_('FLEXI_ALERTNOTAUTH');
				$msg .= ""
					.(!$link_data->has_content_access ? "<br/><br/> ".JText::_('FLEXI_FDC_NO_ACCESS_TO')
							." -- ".JText::_('FLEXI_FDC_CONTENT_CONTAINS')." ".JText::_('FLEXI_FDC_WEBLINK')
							."<br/><small>(".JText::_('FLEXI_FDC_CONTENT_EXPLANATION').")</small>"
						: '')
					.(!$link_data->has_field_access ? "<br/><br/> ".JText::_('FLEXI_FDC_NO_ACCESS_TO')
							." -- ".JText::_('FLEXI_FDC_FIELD_CONTAINS')." ".JText::_('FLEXI_FDC_WEBLINK')
						: '')
				;
				$msg .= "<br/><br/> ". JText::sprintf('FLEXI_FDC_WEBLINK_DATA', $value_order, $content_id, $field_id);
			}

			// Enqueue the final created message
			$app->enqueueMessage($msg, 'warning');

			// Abort further execution, and redirect to home
			$this->setRedirect('index.php', '');
			return;
		}


		/**
		 * Get item and field JTable records, and then load field's configuration
		 */

		$item = JTable::getInstance($type = 'flexicontent_items', $prefix = '', $config = array());
		$field = JTable::getInstance($type = 'flexicontent_fields', $prefix = '', $config = array());
		$item->load($content_id);
		$field->load($field_id);
		FlexicontentFields::loadFieldConfig($field, $item);


		/**
		 * Trigger pluging event 'onFieldValueAction_FC'
		 * Import all system plugins, and all FC field plugins
		 */

		ob_start();
		JPluginHelper::importPlugin('system');
		JPluginHelper::importPlugin('flexicontent_fields');
		$text = ob_get_contents();
		ob_end_clean();

		// Die on plugin output but ... ignore bogus plugin code adding white characters to output
		if (trim($text))
		{
			die('Aborting, plugin output detected: ' . $text);
		}

		$config = array(
			'task' => 'default'
		);

		$result = FLEXI_J40GE
			? $app->triggerEvent('onFieldValueAction_FC', array(&$field, &$item, $value_order, &$config))
			: JEventDispatcher::getInstance()->trigger('onFieldValueAction_FC', array(&$field, &$item, $value_order, &$config));

		// Abort on pluging event code returning value -- false --
		if ($result === false)
		{
			// Event should have set the warning / error message ... set none here
			$this->setRedirect($this->refererURL);
			return;
		}


		/*
		 * Increment hits counter
		 */

		// Recover the link array (url|title|hits)
		$link = unserialize($link_data->value);

		// Force an absolute URL, if relative URL prepend Joomla root uri
		$url = flexicontent_html::make_absolute_url($link['link']);

		// Update the hit count
		$link['hits'] = (int) $link['hits'] + 1;
		$value = serialize($link);

		// Update the array in the DB
		$query 	= 'UPDATE #__flexicontent_fields_item_relations'
				.' SET value = ' . $db->Quote($value)
				.' WHERE item_id = ' . $content_id
				.' AND field_id = ' . $field_id
				.' AND valueorder = ' . $value_order
				;
		$db->setQuery($query)->execute();


		/**
		 * Finally redirect to the URL
		 */

		@header("Location: ".$url."","target=blank");
		$app->close();
	}


	/**
	 * Method to fetch the tags for selecting in item form
	 *
	 * @since 1.5
	 */
	function viewtags()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$app    = JFactory::getApplication();
		$perms  = FlexicontentHelperPerm::getPerm();

		@ob_end_clean();

		//header('Content-type: application/json; charset=utf-8');
		header('Content-type: application/json');
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		$array = array();

		if (!$perms->CanUseTags)
		{
			$array[] = (object) array(
				'id' => '0',
				'name' => JText::_('FLEXI_FIELD_NO_ACCESS')
			);
		}
		else
		{
			$q = $this->input->getString('q', '');
			$q = $q !== parse_url(@$_SERVER["REQUEST_URI"], PHP_URL_PATH) ? $q : '';

			$model = $this->getModel($this->record_name);
			$tagobjs = $model->gettags($q);

			if ($tagobjs)
			{
				foreach ($tagobjs as $tag)
				{
					$array[] = (object) array(
						'id' => $tag->id,
						'name' => $tag->name
					);
				}
			}

			if (empty($array))
			{
				$array[] = (object) array(
					'id' => '0',
					'name' => JText::_($perms->CanCreateTags ? 'FLEXI_NEW_TAG_ENTER_TO_CREATE' : 'FLEXI_NO_TAGS_FOUND')
				);
			}
		}

		jexit(json_encode($array/*, JSON_UNESCAPED_UNICODE*/));
	}


	function search()
	{
		$app = JFactory::getApplication();

		// Strip characters that will cause errors
		$badchars = array('#','>','<','\\');

		$q = $this->input->getString('q', '');
		$q = $q !== parse_url(@$_SERVER["REQUEST_URI"], PHP_URL_PATH) ? $q : '';

		$searchword = $this->input->getString('searchword', $q);
		$searchword = trim( str_replace($badchars, '', $searchword) );

		// If searchword is enclosed in double quotes, then strip quotes and do exact phrase matching
		if (substr($searchword,0,1) == '"' && substr($searchword, -1) == '"')
		{
			$searchword = substr($searchword,1,-1);
			$this->input->set('p', 'exact');
			$this->input->set('searchphrase', 'exact');
			$this->input->set('q', $searchword);
			$this->input->set('searchword', $searchword);
		}

		// If no current menu itemid, then set it using the first menu item that points to the search view
		$Itemid = $this->input->get('Itemid', 0, 'int');
		if (!$Itemid)
		{
			$menus = JFactory::getApplication()->getMenu();
			$items = $menus->getItems('link', 'index.php?option=com_flexicontent&view=search');

			if(isset($items[0]))
			{
				$this->input->set('Itemid', $items[0]->id);
			}
		}

		// Go through display task of this controller instead of parent class, so that cacheable and safeurlparams can be decided properly
		$this->input->set('view', 'search');
		$this->display();
	}



	// **************
	// Helper methods
	// **************


	/**
	 * Method to create join + and-where SQL CLAUSEs, for checking access of field - item pair(s), IN FUTURE maybe moved
	 *
	 * @access private
	 * @since 1.0
	 */
	protected function _createFieldItemAccessClause($get_select_access = false, $include_file = false )
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		$user = JFactory::getUser();
		$select_access = $joinacc = $andacc = '';
		$aid_arr = $user->getAuthorisedViewLevels();
		$aid_list = implode(',', $aid_arr);

		// Access Flags for: content item and field
		if ($get_select_access)
		{
			if ($include_file)
			{
				$select_access .= ', CASE WHEN'.
				'   f.access IN (0,'.$aid_list.')  THEN 1 ELSE 0 END AS has_file_access';
			}
			if ($include_file === 'fileaccess_only')
			{
				$select_access .= ', 1 AS has_field_access, 1 AS has_content_access';
			}
			else
			{
				$select_access .= ', CASE WHEN'.
					'  fi.access IN (0,'.$aid_list.')  THEN 1 ELSE 0 END AS has_field_access';
				$select_access .= ', CASE WHEN'.
					'  ty.access IN (0,'.$aid_list.') AND '.
					'   c.access IN (0,'.$aid_list.') AND '.
					'   i.access IN (0,'.$aid_list.')'.
					' THEN 1 ELSE 0 END AS has_content_access';
			}
		}

		else
		{
			if ($include_file)
			{
				$andacc .= ' AND  f.access IN (0,'.$aid_list.')';  // AND file access
			}
			if ($include_file !== 'fileaccess_only')
			{
				$andacc   .= ' AND fi.access IN (0,'.$aid_list.')';  // AND field access
				$andacc   .= ' AND ty.access IN (0,'.$aid_list.')  AND  c.access IN (0,'.$aid_list.')  AND  i.access IN (0,'.$aid_list.')';  // AND content access
			}
		}

		$clauses['select'] = $select_access;
		$clauses['join']   = $joinacc;
		$clauses['and']    = $andacc;
		return $clauses;
	}


	/**
	 * Traverse Tree to create folder structure and get/prepare file objects
	 *
	 * @access private
	 * @since 1.0
	 */
	protected function _traverseFileTree($nodes, $targetpath)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		jimport('joomla.filesystem.file');
		$all_files = array();

		foreach ($nodes as $node)
		{
			// Folder (Parent node)
			if ($node->isParent)
			{
				$targetpath_node = JPath::clean($targetpath.DS.$node->name);
				JFolder::create($targetpath_node, 0755);

				// Folder has sub-contents
				if (!empty($node->children))
				{
					$node_files = $this->_traverseFileTree($node->children, $targetpath_node);
					foreach ($node_files as $nodeID => $file)  $all_files[$nodeID] = $file;
				}
			}

			// File (Leaf node)
			else
			{
				$file = new stdClass();
				$nodeID = $node->id;
				$file->fieldid    = (int) $node->fieldid;  // sql security ...
				$file->contentid  = (int) $node->contentid; // sql security ...
				$file->fileid     = (int) $node->fileid; // sql security ...
				$file->filename   = $node->name;
				// (of course) for each file the target path includes the filename,
				// which can be different than original filename (user may have renamed it)
				$file->targetpath = $targetpath.DS.$file->filename;
				$all_files[$nodeID] = $file;
			}
		}

		return $all_files;
	}


	/**
	 * Get return URL via a client request variable, checking if it is safe (otherwise home page will be used)
	 *
	 * @return  string  A validated URL to be used typical as redirect URL when a task completes
	 *
	 * @since 3.3
	 */
	protected function _getReturnUrl()
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		// Get HTTP request variable 'return' (base64 encoded)
		$return = $this->input->get('return', null, 'base64');

		// Base64 decode the return URL
		if ($return)
		{
			$return = base64_decode($return);
		}

		// Also try 'referer' (form posted, encode with htmlspecialchars)
		else
		{
			$referer = $this->input->getString('referer', null);
			$return = $referer ? htmlspecialchars_decode($referer) : null;
		}

		// Check return URL if empty or not safe and set a default one
		if (empty($return) || !flexicontent_html::is_safe_url($return))
		{
			$app = JFactory::getApplication();

			if ($app->isClient('administrator') && ($this->view === $this->record_name || $this->view === $this->record_name_pl))
			{
				$return = 'index.php?option=com_flexicontent&view=' . $this->record_name_pl;
			}
			else
			{
				$return = $app->isClient('administrator') ? 'index.php?option=com_flexicontent' : JUri::base();
			}
		}

		return $return;
	}


	/**
	 * Method to create a query object for getting record data (specific columns) of multiple records
	 *
	 * @param   array     $cid    an array record ids
	 * @param   array     $cid    an array columns names
	 *
	 * @return  object    return a Joomla Database Query object
	 *
	 * @since 3.3.0
	 */
	protected function _getRecordsQuery($cid, $cols)
	{
		$db = JFactory::getDbo();

		$cid = ArrayHelper::toInteger($cid);
		$cols_list = implode(',', array_filter($cols, array($db, 'quoteName')));

		$query = $db->getQuery(true)
			->select($cols_list)
			->from('#__' . $this->records_dbtbl)
			->where('id IN (' . implode(',', $cid) . ')');

		return $query;
	}


	/**
	 * START OF CONTROLLER SPECIFIC METHODS
	 */

	/**
	 * Returns the size of a file without downloading it, or -1 if the file size could not be determined.
	 *
	 * @param $url - The location of the remote file to download. Cannot be null or empty.
	 *
	 * @return The size of the file referenced by $url,
	 * or -1 if the size could not be determined
	 * or -999 if there was an error
	 */
	private function _get_file_size_from_url($url, $retry = true)
	{
		$original_url = $url;
		$retry = $retry === true ? 6 : 0;

		// clear last error
		$ignore_last_error = error_get_last();

		try {
			$headers = array('Location' => $url);

			// Follow the Location headers until the actual file URL is known
			while (isset($headers['Location']))
			{
				$url = is_array($headers['Location'])
					? end($headers['Location'])
					: $headers['Location'];

				$headers = @ get_headers($url, 1);

				// Check for get headers failing to execute
				if ($headers === false)
				{
					$error = error_get_last();

					$error_message = is_array($error) && isset($error['message'])
						? $error['message']
						: 'Error retrieving headers of URL';
					$this->setError($error_message);

					return -999;
				}

				// Check for bad response from server, e.g. not found 404 , or 403 no access
				$n = 0;
				while(isset($headers[$n]))
				{
					$code = (int) substr($headers[$n], 9, 3);
					if ($code < 200 || $code >= 400 )
					{
						$this->setError($headers[$n]);
						return -999;
					}
					$n++;
				}
			}
		}

		catch (RuntimeException $e) {
			$this->setError($e->getMessage());
			return -999;  // indicate a fatal error
		}

		// Work-around with content length missing during 1st try, just retry once more
		if (!isset($headers["Content-Length"]) && $retry)
		{
			return $this->get_file_size_from_url($original_url, --$retry);
		}

		$headers["Content-Length"] = is_array($headers["Content-Length"]) ? end($headers["Content-Length"]) : $headers["Content-Length"];

		// Get file size, -1 indicates that the size could not be determined
		return isset($headers["Content-Length"])
			? $headers["Content-Length"]
			: -1;
	}
}
