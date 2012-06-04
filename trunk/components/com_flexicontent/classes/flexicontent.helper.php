<?php
/**
 * @version 1.5 stable $Id$
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

//include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

class flexicontent_html
{
	/**
	 * joomla version specific strings
	 * @access	protected
	 * @var		string
	 */
	
	/**
	 * Escape a string so that it can be used directly by JS source code
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	function escapeJsText($string)
	{
		$string = (string)$string;
		$string = str_replace("\r", '', $string);
		$string = addcslashes($string, "\0..\37'\\");
		$string = str_replace('"', '\"', $string);
		$string = str_replace("'", "\'", $string);
		$string = str_replace("\n", ' ', $string);
		return $string;
	}
	
	/**
	 * Trims whitespace from an array of strings
	 *
	 * @param 	string array			$arr_str
	 * @return 	string array
	 * @since 1.5
	 */
	function arrayTrim($arr_str) {
		if(!is_array($arr_str)) return false;
		foreach($arr_str as $k=>$a) {
			$arr_str[$k] = trim($a);
		}
		return $arr_str;
	}
	
	/**
	 * Strip html tags and cut after x characters
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	function striptagsandcut( $text, $chars=null )
	{
		// first strip html tags
		$text = html_entity_decode ($text, ENT_NOQUOTES, 'UTF-8'); // Convert entiies to characters so that they will not be removed ... by strip_tags
		$cleantext = strip_tags($text);

		// clean additionnal plugin tags
		$patterns = array();
		$patterns[] = '#\[(.*?)\]#';
		$patterns[] = '#{(.*?)}#';
		$patterns[] = '#&(.*?);#';
		
		foreach ($patterns as $pattern) {
			$cleantext = preg_replace( $pattern, '', $cleantext );
		}
		
		$length = JString::strlen(htmlspecialchars( $cleantext ));

		// cut the text if required
		if ($chars) {
			if ($length > $chars) {
				$cleantext = JString::substr( htmlspecialchars($cleantext, ENT_QUOTES, 'UTF-8'), 0, $chars ).'...';
			}
		}
		
		return $cleantext;
	}

	/**
	 * Make image tag from field or extract image from introtext
	 *
	 * @param 	array 		$row
	 * @return 	string
	 * @since 1.5
	 */
	function extractimagesrc( $row )
	{
		jimport('joomla.filesystem.file');

		$regex = '#<\s*img [^\>]*src\s*=\s*(["\'])(.*?)\1#im';
			
		preg_match ($regex, $row->introtext, $matches);
		
		if(!count($matches)) preg_match ($regex, $row->fulltext, $matches);
		
		$images = (count($matches)) ? $matches : array();
		
		$image = '';
		if (count($images)) $image = $images[2];
		
		if (!preg_match("#^http|^https|^ftp#i", $image)) {
			// local file check that it exists
			$image = JFile::exists( JPATH_SITE . DS . $image ) ? $image : '';
		}
		
		return $image;
	}

	/**
	 * Creates the rss feed button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	function feedbutton($view, &$params, $slug = null, $itemslug = null )
	{
		if ( $params->get('show_feed_icon', 1) && !JRequest::getCmd('print') ) {

			$uri    =& JURI::getInstance();
			$base  	= $uri->toString( array('scheme', 'host', 'port'));
			
			//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
			if($view == 'category') {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&format=feed&type=rss', false );
			} elseif($view == FLEXI_ITEMVIEW) {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug.'&format=feed&type=rss', false );
			} elseif($view == 'tags') {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&id='.$slug.'&format=feed&type=rss', false );
			} else {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&format=feed&type=rss', false );
			}
			// Fix for J1.7+ format variable removed from URL and added as URL suffix
			if (!preg_match('/format\=feed/',$link)) {
				$link .= "&amp;format=feed";
			}
			
			$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=800,height=600,directories=no,location=no';

			if ($params->get('show_icons')) 	{
				$image = JHTML::_('image.site', 'livemarks.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_FEED' ));
			} else {
				$image = '&nbsp;'.JText::_( 'FLEXI_FEED' );
			}

			$overlib = JText::_( 'FLEXI_FEED_TIP' );
			$text = JText::_( 'FLEXI_FEED' );

			$output	= '<a href="'. $link .'" class="editlinktip hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

			return $output;
		}
		return;
	}
	
	/**
	 * Creates the print button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	function printbutton( $print_link, &$params )
	{
		if ( $params->get('show_print_icon') || JRequest::getCmd('print') ) {

			$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no';

			// checks template image directory for image, if non found default are loaded
			if ( $params->get( 'show_icons' ) ) {
				$image = JHTML::_('image.site', 'printButton.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_PRINT' ));
			} else {
				$image = JText::_( 'FLEXI_ICON_SEP' ) .'&nbsp;'. JText::_( 'FLEXI_PRINT' ) .'&nbsp;'. JText::_( 'FLEXI_ICON_SEP' );
			}

			if (JRequest::getInt('pop')) {
				//button in popup
				$output = '<a href="javascript:;" onclick="window.print();return false;">'.$image.'</a>';
			} else {
				//button in view
				$overlib = JText::_( 'FLEXI_PRINT_TIP' );
				$text = JText::_( 'FLEXI_PRINT' );

				$output	= '<a href="'. JRoute::_($print_link) .'" class="editlinktip hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
			}

			return $output;
		}
		return;
	}

	/**
	 * Creates the email button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	function mailbutton($view, &$params, $slug = null, $itemslug = null )
	{
		if ( file_exists ( JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php' )
				&& $params->get('show_email_icon') && !JRequest::getCmd('print') ) {

			$uri    =& JURI::getInstance();
			$base  	= $uri->toString( array('scheme', 'host', 'port'));
			
			//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
			if($view == 'category') {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug, false );
			} elseif($view == FLEXI_ITEMVIEW) {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug, false );
			} elseif($view == 'tags') {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&id='.$slug, false );
			} else {
				$link 	= $base.JRoute::_( 'index.php?view='.$view, false );
			}
			require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
			$url 	= 'index.php?option=com_mailto&tmpl=component&link='.MailToHelper::addLink($link);
			
			$status = 'width=400,height=300,menubar=yes,resizable=yes';

			if ($params->get('show_icons')) 	{
				$image = JHTML::_('image.site', 'emailButton.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_EMAIL' ));
			} else {
				$image = '&nbsp;'.JText::_( 'FLEXI_EMAIL' );
			}

			$overlib = JText::_( 'FLEXI_EMAIL_TIP' );
			$text = JText::_( 'FLEXI_EMAIL' );

			$output	= '<a href="'. JRoute::_($url) .'" class="editlinktip hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

			return $output;
		}
		return;
	}

	/**
	 * Creates the pdf button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	function pdfbutton( $item, &$params)
	{
		if ( $params->get('show_pdf_icon') && !JRequest::getCmd('print') ) {

			if ( $params->get('show_icons') ) {
				$image = JHTML::_('image.site', 'pdf_button.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_CREATE_PDF' ));
			} else {
				$image = JText::_( 'FLEXI_ICON_SEP' ) .'&nbsp;'. JText::_( 'FLEXI_CREATE_PDF' ) .'&nbsp;'. JText::_( 'FLEXI_ICON_SEP' );
			}
			$overlib = JText::_( 'FLEXI_CREATE_PDF_TIP' );
			$text = JText::_( 'FLEXI_CREATE_PDF' );

			$link 	= 'index.php?view='.FLEXI_ITEMVIEW.'&cid='.$item->categoryslug.'&id='.$item->slug.'&format=pdf';
			$output	= '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

			return $output;
		}
		return;
	}
	
	
	/**
	 * Creates the state selector button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	function statebutton( $item, &$params)
	{
		$user = & JFactory::getUser();
		$db   = & JFactory::getDBO();
		$config   = & JFactory::getConfig();
		$document = & JFactory::getDocument();
		$nullDate = $db->getNullDate();
		
		// Determine if current user can edit state of the given item
		$has_edit_state = false;
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $item->id;
			$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $item->created_by == $user->get('id'));
			// ALTERNATIVE 1
			//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
			//$has_edit_state = in_array('edit.state', $rights) || (in_array('edit.state.own', $rights) && $item->created_by == $user->get('id')) ;
		} else if ($user->gid >= 25) {
			$has_edit_state = true;
		} else if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$has_edit_state = in_array('publish', $rights) || (in_array('publishown', $rights) && $item->created_by == $user->get('id')) ;
		} else {
			$has_edit_state = $user->authorize('com_content', 'publish', 'content', 'all');
		}
		
		static $js_and_css_added = false;
		
	 	if (!$js_and_css_added)
	 	{
			$document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/stateselector.js' );
	 		$js ='
				if(MooTools.version>="1.2.4") {
					window.addEvent("domready", function() {stateselector.init()});
				}else{
					window.onDomReady(stateselector.init.bind(stateselector));
				}
				function dostate(state, id)
				{
					var change = new processstate();
					change.dostate( state, id );
				}';
			$document->addScriptDeclaration($js);
			$js_and_css_added = true;
	 	}
	 	
		// Create the state selector button and return it
		if ($has_edit_state) {
			
			$publish_up =& JFactory::getDate($item->publish_up);
			$publish_down =& JFactory::getDate($item->publish_down);
			$publish_up->setOffset($config->getValue('config.offset'));
			$publish_down->setOffset($config->getValue('config.offset'));

			$alt = "";
			if ( $item->state == 1 ) {
				$img = 'tick.png';
				$alt = JText::_( 'FLEXI_PUBLISHED' );
				$state = 1;
			} else if ( $item->state == 0 ) {
				$img = 'publish_x.png';
				$alt = JText::_( 'FLEXI_UNPUBLISHED' );
				$state = 0;
			} else if ( $item->state == -1 ) {
				$img = 'disabled.png';
				$alt = JText::_( 'FLEXI_ARCHIVED' );
				$state = -1;
			} else if ( $item->state == -3 ) {
				$img = 'publish_r.png';
				$alt = JText::_( 'FLEXI_PENDING' );
				$state = -3;
			} else if ( $item->state == -4 ) {
				$img = 'publish_y.png';
				$alt = JText::_( 'FLEXI_TO_WRITE' );
				$state = -4;
			} else if ( $item->state == -5 ) {
				$img = 'publish_g.png';
				$alt = JText::_( 'FLEXI_IN_PROGRESS' );
				$state = -5;
			}

			$times = '';
			if (isset($item->publish_up)) {
				if ($item->publish_up == $nullDate) {
					$times .= JText::_( 'FLEXI_START_ALWAYS' );
				} else {
					$times .= JText::_( 'FLEXI_START' ) .": ". $publish_up->toFormat();
				}
			}
			if (isset($item->publish_down)) {
				if ($item->publish_down == $nullDate) {
					$times .= "<br />". JText::_( 'FLEXI_FINISH_NO_EXPIRY' );
				} else {
					$times .= "<br />". JText::_( 'FLEXI_FINISH' ) .": ". $publish_down->toFormat();
				}
			}
			
			$img_path = JURI::root()."/components/com_flexicontent/assets/images/";
			
			$state_ids = array(1, 0, -1, -3, -4 , -5);
			$state_names = array('FLEXI_PUBLISHED', 'FLEXI_UNPUBLISHED', 'FLEXI_ARCHIVED', 'FLEXI_PENDING', 'FLEXI_TO_WRITE', 'FLEXI_IN_PROGRESS');
			$state_descrs = array('FLEXI_PUBLISH_THIS_ITEM', 'FLEXI_UNPUBLISH_THIS_ITEM', 'FLEXI_ARCHIVE_THIS_ITEM', 'FLEXI_SET_ITEM_PENDING', 'FLEXI_SET_ITEM_TO_WRITE', 'FLEXI_SET_ITEM_IN_PROGRESS');
			$state_imgs = array('tick.png', 'publish_x.png', 'disabled.png', 'publish_r.png', 'publish_y.png', 'publish_g.png');			
			
			if ($has_edit_state) {
			$output ='
			<ul class="statetoggler">
				<li class="topLevel">
					<a href="javascript:void(0);" class="opener" style="outline:none;">
					<div id="row'.$item->id.'">
						<span class="editlinktip hasTip" title="'.JText::_( 'FLEXI_PUBLISH_INFORMATION' ).'::'.$times.'">
							<img src="'.$img_path.$img.'" width="16" height="16" border="0" alt="'.$alt.'" />
						</span>
					</div>
					</a>
					<div class="options" style="width:160px; position:absolute;left:-68px;">
						<ul>';
						
				foreach ($state_ids as $i => $state_id) {
					$output .='
							<li>
								<a href="javascript:void(0);" onclick="dostate(\''.$state_id.'\', \''.$item->id.'\')" class="closer hasTip" title="'.JText::_( 'FLEXI_ACTION' ).'::'.JText::_( $state_descrs[$i] ).'">
									<img src="'.$img_path.$state_imgs[$i].'" width="16" height="16" border="0" alt="'.JText::_( $state_names[$i] ).'" />
								</a>
							</li>';
				}
				$output .='
						</ul>
					</div>
				</li>
			</ul>';
			} else {
				$output = '';/* '
					<div id="row'.$item->id.'">
						<span class="editlinktip hasTip" title="'.JText::_( 'FLEXI_PUBLISH_INFORMATION' ).'::'.$times.'">
							<img src="'.$img_path.$img.'" width="16" height="16" border="0" alt="'.$alt.'" />
						</span>
					</div>';*/
			}
			
			return $output;
		}
		
		return;
	}
	
	
	/**
	 * Creates the edit button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	function editbutton( $item, &$params)
	{
		$user	= & JFactory::getUser();
		
		// Determine if current user can edit the given item
		$has_edit = false;
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $item->id;
			$has_edit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $item->created_by == $user->get('id'));
			// ALTERNATIVE 1
			//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
			//$has_edit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $item->created_by == $user->get('id')) ;
		} else if ($user->gid >= 25) {
			$has_edit = true;
		} else if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$has_edit = in_array('edit', $rights) || (in_array('editown', $rights) && $item->created_by == $user->get('id')) ;
		} else {
			$has_edit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $item->created_by == $user->get('id'));
		}
		
		// Create the edit button and return it
		if ($has_edit) {
			if ( $params->get('show_icons') ) {
				$image = JHTML::_('image.site', 'edit.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_EDIT' ));
			} else {
				$image = JText::_( 'FLEXI_ICON_SEP' ) .'&nbsp;'. JText::_( 'FLEXI_EDIT' ) .'&nbsp;'. JText::_( 'FLEXI_ICON_SEP' );
			}
			$overlib 	= JText::_( 'FLEXI_EDIT_TIP' );
			$text 		= JText::_( 'FLEXI_EDIT' );

			$link = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW.'&cid='.$item->categoryslug.'&id='.$item->slug
						.'&task=edit&typeid='.$item->type_id.'&'.JUtility::getToken().'=1';
			$output	= '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

			return $output;
		}
		
		return;
	}

	/**
	 * Creates the add button
	 *
	 * @param array $params
	 * @since 1.0
	 */
	function addbutton(&$params)
	{
		$user	= & JFactory::getUser();

		if ($user->authorize('com_flexicontent', 'add')) {

			if ( $params->get('show_icons') ) {
				$image = JHTML::_('image.site', 'add.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_ADD' ));
			} else {
				$image = JText::_( 'FLEXI_ICON_SEP' ) .'&nbsp;'. JText::_( 'FLEXI_ADD' ) .'&nbsp;'. JText::_( 'FLEXI_ICON_SEP' );
			}
			$overlib = JText::_( 'FLEXI_ADD_TIP' );
			$text = JText::_( 'FLEXI_ADD' );

			$link 	= 'index.php?view='.FLEXI_ITEMVIEW.'&task=add';
			$output	= '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

			return $output;
		}
		return;
	}

	/**
	 * Creates the stateicon
	 *
	 * @param int $state
	 * @param array $params
	 * @since 1.0
	 */
	function stateicon( $state, &$params)
	{
		$user		= & JFactory::getUser();

		if ( $state == 1 ) {
			$img = 'tick.png';
			$alt = JText::_( 'FLEXI_PUBLISHED' );
			$state = 1;
		} else if ( $state == 0 ) {
			$img = 'publish_x.png';
			$alt = JText::_( 'FLEXI_UNPUBLISHED' );
			$state = 0;
		} else if ( $state == -1 ) {
			$img = 'disabled.png';
			$alt = JText::_( 'FLEXI_ARCHIVED' );
			$state = -1;
		} else if ( $state == -3 ) {
			$img = 'publish_r.png';
			$alt = JText::_( 'FLEXI_PENDING' );
			$state = -3;
		} else if ( $state == -4 ) {
			$img = 'publish_y.png';
			$alt = JText::_( 'FLEXI_TO_WRITE' );
			$state = -4;
		} else if ( $state == -5 ) {
			$img = 'publish_g.png';
			$alt = JText::_( 'FLEXI_IN_PROGRESS' );
			$state = -5;
		}

		$text = JText::_( 'FLEXI_STATE' );
		
		if ( $params->get('show_icons', 1) ) {
			$image = JHTML::_('image.site', $img, 'components/com_flexicontent/assets/images/', NULL, NULL, $alt, 'class="editlinktip hasTip" title="'.$text.'::'.$alt.'"' );
		} else {
			$image = $alt;
		}
		return $image;
		return;
	}

	/**
	 * Creates the ratingbar
	 *
	 * @deprecated
	 * @param array $item
	 * @since 1.0
	 */
	function ratingbar($item)
	{
		//sql calculation doesn't work with negative values and thus only minus votes will not be taken into account
		if ($item->votes == 0) {
			return JText::_( 'FLEXI_NOT_YET_RATED' );
		}

		//we do the rounding here and not in the query to get better ordering results
		$rating = round($item->votes);

		$output = '<span class="qf_ratingbarcontainer editlinktip hasTip" title="'.JText::_( 'FLEXI_RATING' ).'::'.JText::_( 'FLEXI_SCORE' ).': '.$rating.'%">';
		$output .= '<span class="qf_ratingbar" style="width:'.$rating.'%;">&nbsp;</span></span>';

		return $output;
	}

	/**
	 * Creates the voteicons
	 * Deprecated to ajax votes
	 *
	 * @param array $params
	 * @since 1.0
	 */
	function voteicons($item, &$params)
	{
		if ( $params->get('show_icons') ) {
			$voteup = JHTML::_('image.site', 'thumb_up.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_GOOD' ) );
			$votedown = JHTML::_('image.site', 'thumb_down.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_BAD' ) );
		} else {
			$voteup = JText::_( 'FLEXI_GOOD' ). '&nbsp;';
			$votedown = '&nbsp;'.JText::_( 'FLEXI_BAD' );
		}

		$output = '<a href="'.JRoute::_('index.php?task=vote&vote=1&cid='.$item->categoryslug.'&id='.$item->slug.'&layout='.$params->get('ilayout')).'"class="editlinktip hasTip" title="'.JText::_( 'FLEXI_VOTE_UP' ).'::'.JText::_( 'FLEXI_VOTE_UP_TIP' ).'">'.$voteup.'</a>';
		$output .= ' - ';
		$output .= '<a href="'.JRoute::_('index.php?task=vote&vote=0&cid='.$item->categoryslug.'&id='.$item->slug.'&layout='.$params->get('ilayout')).'"class="editlinktip hasTip" title="'.JText::_( 'FLEXI_VOTE_DOWN' ).'::'.JText::_( 'FLEXI_VOTE_DOWN_TIP' ).'">'.$votedown.'</a>';

		return $output;
	}

	/**
	 * Creates the ajax voting stars system
	 *
	 * @param array $field
	 * @param int or string $xid
	 * @since 1.0
	 */
	function ItemVote( &$field, $xid, $vote )
	{
		// Check for invalid xid
		if ($xid!='main' && $xid!='extra' && $xid!='all' && !(int)$xid) {
			$html .= "ItemVote(): invalid xid '".$xid."' was given";
			return;
		}
		
		$db	=& JFactory::getDBO();
  	$id  = $field->item_id;
  	
  	$enable_extra_votes = $field->parameters->get('extra_votes', '');
		$extra_votes = !$enable_extra_votes ? '' : $field->parameters->get('extra_votes', '');
		$main_label  = !$enable_extra_votes ? '' : $field->parameters->get('main_label', '');
		// Set a Default main label if one was not given but extra votes exist
		$main_label  = (!$main_label && $extra_votes) ? JText::_('FLEXI_OVERALL') : $main_label;
		
		$html = '';
		
		if (!$vote) {
			// These are mass retrieved for multiple items, to optimize performance
			//$db->setQuery( 'SELECT * FROM #__content_rating WHERE content_id=' . $id );
			//$vote = $db->loadObject();
			$vote = new stdClass();
			$vote->rating_sum = $vote->rating_count = 0;
		} else if (!isset($vote->rating_sum) || !isset($vote->rating_sum)) {
			$vote->rating_sum = $vote->rating_count = 0;
		}
		
		if ($xid=='main' || $xid=='all') {
			$html .= flexicontent_html::ItemVoteDisplay( $field, $id, $vote->rating_sum, $vote->rating_count, 'main', $main_label );
		}
		
		if ($xid=='all' || $xid=='extra' || (int)$xid) {
			
			// Retrieve and split-up extra vote types, (removing last one if empty)
			$extra_votes = preg_split("/[\s]*%%[\s]*/", $extra_votes);
			if ( empty($extra_votes[count($extra_votes)-1]) )  unset( $extra_votes[count($extra_votes)-1] );
			
			// Split extra voting ids (xid) and their titles
			$xid_arr = array();
			foreach ($extra_votes as $extra_vote) {
				list($extra_id, $extra_title) = explode("##", $extra_vote);
				$xid_arr[$extra_id] = $extra_title;
			}
			
			// Query the database
			if ( (int)$xid )
			{
				if ( !isset($vote->extra[(int)$xid]) ) {
					$extra_vote = new stdClass();
					$extra_vote->rating_sum = $extra_vote->rating_count = 0;
					$extra_vote->extra_id = (int)$xid;
				} else {
					$extra_vote = $vote->extra[(int)$xid];
				}
				$html .= flexicontent_html::ItemVoteDisplay( $field, $id, $extra_vote->rating_sum, $extra_vote->rating_count, $extra_vote->extra_id, $xid_arr[(int)$xid] );
			}
			else
			{
				foreach ( $xid_arr as $extra_id => $extra_title) {
					if ( !isset($vote->extra[$extra_id]) ) {
						$extra_vote = new stdClass();
						$extra_vote->rating_sum = $extra_vote->rating_count = 0;
						$extra_vote->extra_id = $extra_id;
					} else {
						$extra_vote = $vote->extra[$extra_id];
					}
					$html .= flexicontent_html::ItemVoteDisplay( $field, $id, $extra_vote->rating_sum, $extra_vote->rating_count, $extra_vote->extra_id, $extra_title );
				}				
			}
		}
				
		return $html;
 	}
	
	/**
	 * Method that creates the stars
	 *
	 * @param array				$field
	 * @param int 				$id
	 * @param int			 	$rating_sum
	 * @param int 				$rating_count
	 * @param int or string 	$xid
	 * @since 1.0
	 */
 	function ItemVoteDisplay( &$field, $id, $rating_sum, $rating_count, $xid, $label='' )
	{
		$document =& JFactory::getDocument();
		
		$counter 	= $field->parameters->get( 'counter', 1 );
		$unrated 	= $field->parameters->get( 'unrated', 1 );
		$dim		= $field->parameters->get( 'dimension', 25 );    	
		$image		= $field->parameters->get( 'image', 'components/com_flexicontent/assets/images/star.gif' );    	
		$class 		= $field->name;
		$img_path	= JURI::base(true) .'/'. $image;
	
		$percent = 0;
		$stars = '';
		
		static $js_and_css_added = false;
		
	 	if (!$js_and_css_added)
	 	{
	 		JHTML::_('behavior.tooltip');
			$css 	= JURI::base(true) .'/components/com_flexicontent/assets/css/fcvote.css';
			$js		= JURI::base(true) .'/components/com_flexicontent/assets/js/fcvote.js';
			$document->addStyleSheet($css);
			$document->addScript($js);
		
			$document->addScriptDeclaration('var sfolder = "'.JURI::base(true).'";');

			$css = '
			.'.$class.' .fcvote {line-height:'.$dim.'px;}
			.'.$class.' .fcvote ul {height:'.$dim.'px;width:'.(5*$dim).'px;}
			.'.$class.' .fcvote ul, .'.$class.' .fcvote ul li a:hover, .'.$class.' .fcvote ul li.current-rating {background-image:url('.$img_path.')!important;}
			.'.$class.' .fcvote ul li a, .'.$class.' .fcvote ul li.current-rating {height:'.$dim.'px;line-height:'.$dim.'px;}
			';
			$document->addStyleDeclaration($css);

			$js_and_css_added = true;
	 	}
		
		if ($rating_count != 0) {
			$percent = number_format((intval($rating_sum) / intval( $rating_count ))*20,2);
		} elseif ($unrated == 0) {
			$counter = -1;
		}
		
		if ( (int)$xid ) { 
			if ( $counter == 2 ) $counter = 0;
		} else {
			if ( $counter == 3 ) $counter = 0;
		}
		
	 	$html='
		<div class="'.$class.'">
			<div class="fcvote">'
	  		.($label ? '<div id="fcvote_lbl'.$id.'_'.$xid.'" class="fcvote-label xid-'.$xid.'">'.$label.'</div>' : '')
				.'<ul>
    				<li id="rating_'.$id.'_'.$xid.'" class="current-rating" style="width:'.(int)$percent.'%;"></li>
    				<li><a href="javascript:;" title="'.JText::_( 'FLEXI_VERY_POOR' ).'" class="one" rel="'.$id.'_'.$xid.'">1</a></li>
    				<li><a href="javascript:;" title="'.JText::_( 'FLEXI_POOR' ).'" class="two" rel="'.$id.'_'.$xid.'">2</a></li>
    				<li><a href="javascript:;" title="'.JText::_( 'FLEXI_REGULAR' ).'" class="three" rel="'.$id.'_'.$xid.'">3</a></li>
    				<li><a href="javascript:;" title="'.JText::_( 'FLEXI_GOOD' ).'" class="four" rel="'.$id.'_'.$xid.'">4</a></li>
    				<li><a href="javascript:;" title="'.JText::_( 'FLEXI_VERY_GOOD' ).'" class="five" rel="'.$id.'_'.$xid.'">5</a></li>
				</ul>
	  		<div id="fcvote_cnt_'.$id.'_'.$xid.'" class="fcvote-count">';
		  		if ( $counter != -1 ) {
	  				if ( $counter != 0 ) {
						$html .= "(";
					 		if($rating_count!=1) {
						 		$html .= $rating_count." ".JText::_( 'FLEXI_VOTES' );
					 		} else { 
				 				$html .= $rating_count." ".JText::_( 'FLEXI_VOTE' );
	     					}
	 	 				$html .=")";
					}
				}
	 	 	$html .='
	 	 		</div>
 	 			<div class="clear"></div>
 	 		</div>
 	 	</div>';
		
	 	return $html;
 	}

	/**
	 * Creates the favourited by user list
	 *
	 * @param array $params
	 * @since 1.0
	 */
	function favoured_userlist( &$field, &$item,  $favourites)
	{
		$userlisttype = $field->parameters->get('display_favoured_userlist', 0);
		$maxusercount = $field->parameters->get('display_favoured_max', 12);
		
		$favuserlist = $favourites ? '['.$favourites.' '.JText::_('FLEXI_USERS') : '';
		
		if ( !$userlisttype ) return $favuserlist ? $favuserlist.']' : '';
		else if ($userlisttype==1) $uname="u.username";
		else /*if ($userlisttype==2)*/ $uname="u.name";
		
		$db	=& JFactory::getDBO();
		$query = "SELECT $uname FROM #__flexicontent_favourites as ff"
			." LEFT JOIN #__users AS u ON u.id=ff.userid "
			." WHERE ff.itemid=" . $item->id;
		$db->setQuery($query);
		$favusers = $db->loadResultArray();
		if (!is_array($favusers) || !count($favusers)) return $favuserlist ? $favuserlist.']' : '';
		
		$seperator = ': ';
		$count = 0;
		foreach($favusers as $favuser) {
			$favuserlist .= $seperator . $favuser;
			$seperator = ',';
			$count++;
			if ($count >= $maxusercount) break;
		}
		if (count($favusers) > $maxusercount) $favuserlist .=" ...";
		if (!empty($favuserlist)) $favuserlist .="]";
		return $favuserlist;
	}
	
 	/**
	 * Creates the favourite icons
	 *
	 * @param array $params
	 * @since 1.0
	 */
	function favicon($field, $favoured, & $item=false)
	{
		$user			= & JFactory::getUser();
		$document	= & JFactory::getDocument();
		
		static $js_and_css_added = false;
		
	 	if (!$js_and_css_added)
	 	{
			$document->addScript( JURI::base(true) .'/components/com_flexicontent/assets/js/fcfav.js' );
			JHTML::_('behavior.tooltip');
			
			$js = "
				var sfolder = '".JURI::base(true)."';
				var fcfav_text=Array(
					'".JText::_( 'FLEXI_YOUR_BROWSER_DOES_NOT_SUPPORT_AJAX' )."',
					'".JText::_( 'FLEXI_LOADING' )."',
					'".JText::_( 'FLEXI_ADDED_TO_YOUR_FAVOURITES' )."',
					'".JText::_( 'FLEXI_YOU_NEED_TO_LOGIN' )."',
					'".JText::_( 'FLEXI_REMOVED_FROM_YOUR_FAVOURITES' )."',
					'".JText::_( 'FLEXI_USERS' )."'
					);
				";
			$document->addScriptDeclaration($js);
			
			$js_and_css_added = true;
		}
		
		$output = "";
		
		if ($user->id && $favoured)
		{
			$image 		= JHTML::_('image.site', 'heart_delete.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_REMOVE_FAVOURITE' ));
			$text 		= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE' );
			$overlib 	= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE_TIP' );
			$onclick 	= 'javascript:FCFav('.$field->item_id.');';
			$link 		= 'javascript:void(null)';

			$output		.=
				 '<span class="fcfav_delete">'
				.' <a id="favlink'.$field->item_id.'" href="'.$link.'" onclick="'.$onclick.'" class="editlinktip hasTip fcfav-reponse" title="'.$text.'::'.$overlib.'">'.$image.'</a>'
				.' <span class="fav_item_id" style="display:none;">'.$item->id.'</span>'
				.' <span class="fav_item_title" style="display:none;">'.$item->title.'</span>'
				.'</span>';
		
		}
		elseif($user->id)
		{
			$image 		= JHTML::_('image.site', 'heart_add.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_FAVOURE' ));
			$text 		= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE' );
			$overlib 	= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE_TIP' );
			$onclick 	= 'javascript:FCFav('.$field->item_id.');';
			$link 		= 'javascript:void(null)';

			$output		.=
				 '<span class="fcfav_add">'
				.' <a id="favlink'.$field->item_id.'" href="'.$link.'" onclick="'.$onclick.'" class="editlinktip hasTip fcfav-reponse" title="'.$text.'::'.$overlib.'">'.$image.'</a>'
				.' <span class="fav_item_id" style="display:none;">'.$item->id.'</span>'
				.' <span class="fav_item_title" style="display:none;">'.$item->title.'</span>'
				.'</span>';
		}
		else
		{
			$overlib 	= JText::_( 'FLEXI_FAVOURE_LOGIN_TIP' );
			$text 		= JText::_( 'FLEXI_FAVOURE' );
			$image 		= JHTML::_('image.site', 'heart_login.png', 'components/com_flexicontent/assets/images/', NULL, NULL, $text, 'class="editlinktip hasTip" title="'.$text.'::'.$overlib.'"' );

			$output		= $image;
		}

		return $output;
	}

	
	/**
	 * Method to build the list for types when performing an edit action
	 * 
	 * @return array
	 * @since 1.5
	 */
	function buildtypesselect($list, $name, $selected, $top, $class = 'class="inputbox"')
	{
		$typelist 	= array();
		
		if($top) {
			$typelist[] 	= JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_TYPE' ) );
		}
		
		foreach ($list as $item) {
			$typelist[] = JHTML::_( 'select.option', $item->id, $item->name);
		}
		return JHTML::_('select.genericlist', $typelist, $name, $class, 'value', 'text', $selected );
	}


	/**
	 * Method to build the list of the autors
	 * 
	 * @return array
	 * @since 1.5
	 */
	function buildauthorsselect($list, $name, $selected, $top, $class = 'class="inputbox"')
	{
		$typelist 	= array();
		
		if($top) {
			$typelist[] 	= JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_AUTHOR' ) );
		}
		
		foreach ($list as $item) {
			$typelist[] = JHTML::_( 'select.option', $item->id, $item->name);
		}
		return JHTML::_('select.genericlist', $typelist, $name, $class, 'value', 'text', $selected );
	}

	
	/**
	 * Method to build the list for types when performing an edit action
	 * 
	 * @return array
	 * @since 1.5
	 */
	function buildfieldtypeslist($name, $class, $selected)
	{
		global $global_field_types;
		$db =& JFactory::getDBO();
		
		$query = 'SELECT element AS value, name AS text'
		. ' FROM #__plugins'
		. ' WHERE published = 1'
		. ' AND folder = ' . $db->Quote('flexicontent_fields')
		. ' AND element <> ' . $db->Quote('core')
		. ' ORDER BY ordering, name'
		;
		
		$db->setQuery($query);
		$global_field_types = $db->loadObjectList();
		foreach($global_field_types as $field_type) {
			$field_type->text = preg_replace("/FLEXIcontent[ \t]*-[ \t]*/i", "", $field_type->text);
		}

		$list = JHTML::_('select.genericlist', $global_field_types, $name, $class, 'value', 'text', $selected );
		
		return $list;
	}

	/**
	 * Method to build the file extension list
	 * 
	 * @return array
	 * @since 1.5
	 */
	function buildfilesextlist($name, $class, $selected)
	{
		$db =& JFactory::getDBO();

		$query = 'SELECT DISTINCT ext'
		. ' FROM #__flexicontent_files'
		. ' ORDER BY ext ASC'
		;		
		$db->setQuery($query);
		$exts = $db->loadResultArray();
		
		$options[] = JHTML::_( 'select.option', '', '- '.JText::_( 'FLEXI_ALL_EXT' ).' -');
		
		foreach ($exts as $ext) {
			$options[] = JHTML::_( 'select.option', $ext, $ext);
		}

		$list = JHTML::_('select.genericlist', $options, $name, $class, 'value', 'text', $selected );
		
		return $list;
	}

	/**
	 * Method to build the uploader list
	 * 
	 * @return array
	 * @since 1.5
	 */
	function builduploaderlist($name, $class, $selected)
	{
		$db =& JFactory::getDBO();

		$query = 'SELECT DISTINCT f.uploaded_by AS uid, u.name AS name'
		. ' FROM #__flexicontent_files AS f'
		. ' LEFT JOIN #__users AS u ON u.id = f.uploaded_by'
		. ' ORDER BY f.ext ASC'
		;		
		$db->setQuery($query);
		$exts = $db->loadObjectList();
		
		$options[] = JHTML::_( 'select.option', '', '- '.JText::_( 'FLEXI_ALL_UPLOADERS' ).' -');
		
		foreach ($exts as $ext) {
			$options[] = JHTML::_( 'select.option', $ext->uid, $ext->name);
		}

		$list = JHTML::_('select.genericlist', $options, $name, $class, 'value', 'text', $selected );
		
		return $list;
	}

	
	/**
	 * Method to build the Joomfish languages list
	 * 
	 * @return object
	 * @since 1.5
	 */
	function buildlanguageslist($name, $class, $selected, $type = 1)
	{
		$mainframe =& JFactory::getApplication();
		$db =& JFactory::getDBO();
		
		$languages = FLEXIUtilities::getlanguageslist();
		
		switch ($type)
		{
			case 1:
				foreach ($languages as $lang) {
					$langs[] = JHTML::_('select.option',  $lang->code, $lang->name );
				}
				$list = JHTML::_('select.genericlist', $langs, $name, $class, 'value', 'text', $selected );
				break;
			case 2:
				$langs[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_SELECT_LANGUAGE' ));
				foreach ($languages as $lang) {
					$langs[] = JHTML::_('select.option',  $lang->code, $lang->name );
				}
				$list = JHTML::_('select.genericlist', $langs, $name, $class, 'value', 'text', $selected );
				break;
			case 3:
				$checked	= '';
				$list		= '';
				
				foreach ($languages as $lang) {
					if ($lang->code == $selected) {
						$checked = ' checked="checked"';
					}
					$list 	.= '<label class="lang_box" for="lang'.$lang->id.'" title="'.$lang->name.'" >';
					$list 	.= '<input id="lang'.$lang->id.'" type="radio" name="'.$name.'" value="'.$lang->code.'"'.$checked.' />';
					if($lang->shortcode=="*") {
						$list 	.= JText::_("All");  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						echo $lang->name;
					}	
					$list 	.= '</label>';
					$checked	= '';
				}
				break;
			case 4:
				$list 	 = '<label class="lang_box" for="lang9999" title="'.JText::_( 'FLEXI_NOCHANGE_LANGUAGE_DESC' ).'" >';
				$list 	.= '<input id="lang9999" type="radio" name="'.$name.'" class="lang" value="" checked="checked" />';
				$list 	.= JText::_( 'FLEXI_NOCHANGE_LANGUAGE' );
				$list 	.= '</label><br />';

				foreach ($languages as $lang) {
					$list 	.= '<label class="lang_box" for="lang'.$lang->id.'" title="'.$lang->name.'">';
					$list 	.= '<input id="lang'.$lang->id.'" type="radio" name="'.$name.'" class="lang" value="'.$lang->code.'" />';
					if($lang->shortcode=="*") {
						$list 	.= JText::_("All");  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						echo $lang->name;
					}	
					$list 	.= '</label><br />';
				}
				break;
			case 5:
				$list		= '';
				foreach ($languages as $lang) {
					if ($lang->code==$selected) continue;
					$list 	.= '<label class="lang_box" for="lang'.$lang->id.'" title="'.$lang->name.'">';
					$list 	.= '<input id="lang'.$lang->id.'" type="radio" name="'.$name.'" class="lang" value="'.$lang->code.'" />';
					if($lang->shortcode=="*") {
						$list 	.= JText::_("All");  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						echo $lang->name;
					}	
					$list 	.= '</label><br />';
				}
				break;
		}
		return $list;
	}

	/**
	 * Method to build the Joomfish languages list
	 * 
	 * @return object
	 * @since 1.5
	 */
	function buildstateslist($name, $class, $selected)
	{
		$state[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_DO_NOT_CHANGE' ) );
		$state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
		$state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) ); 
		$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );
		$state[] = JHTML::_('select.option',   1, JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',   0, JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  -1, JText::_( 'FLEXI_ARCHIVED' ) );

		$list = JHTML::_('select.genericlist', $state, $name, $class, 'value', 'text', $selected );
	
		return $list;
	}
	
	
	/**
	 * Method to get the user's Current Language
	 * 
	 * @return string
	 * @since 1.5
	 */
	function getUserCurrentLang()
	{
		static $lang = null;
		if ($lang) return $lang;
		
		// First try language from http request
		$lang = JRequest::getWord('lang', '' );
		if ( empty($lang) ) {
			// Second get DEFAULT --USER-- language, note this is different from the default Frontend/Backend "Content" language
			$langFactory= JFactory::getLanguage();
			$lang = $langFactory->getTag();
			$lang = substr($lang,0,2);
		}
		return $lang;
	}
	
	
	/**
	 * Method to get Site (Frontend) default language
	 * NOTE: ... this is the default language of created content for J1.5, but in J1.6+ is '*' (=all) 
	 * NOTE: ... joomfish creates translations in all other languages
	 * 
	 * @return string
	 * @since 1.5
	 */
	function getSiteDefaultLang()
	{
		$languages =& JComponentHelper::getParams('com_languages');
		$lang = $languages->get('site', 'en-GB');
		return $lang;		
	}
	
	function nl2space($string) {
		if(gettype($string)!="string") return false;
		$strlen = strlen($string);
		$array = array();
		$str = "";
		for($i=0;$i<$strlen;$i++) {
			if(ord($string[$i])===ord("\n")) {
				$str .= ' ';
				continue;
			}
			$str .= $string[$i];
		}
		return $str;
	 }
	 
	 
	/**
		Diff implemented in pure php, written from scratch.
		Copyright (C) 2003  Daniel Unterberger <diff.phpnet@holomind.de>
		Copyright (C) 2005  Nils Knappmeier next version 
		
		This program is free software; you can redistribute it and/or
		modify it under the terms of the GNU General Public License
		as published by the Free Software Foundation; either version 2
		of the License, or (at your option) any later version.
		
		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.
		
		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
		
		http://www.gnu.org/licenses/gpl.html
		
		About:
		I searched a function to compare arrays and the array_diff()
		was not specific enough. It ignores the order of the array-values.
		So I reimplemented the diff-function which is found on unix-systems
		but this you can use directly in your code and adopt for your needs.
		Simply adopt the formatline-function. with the third-parameter of arr_diff()
		you can hide matching lines. Hope someone has use for this.
		
		Contact: d.u.diff@holomind.de <daniel unterberger>
	**/
	    
	## PHPDiff returns the differences between $old and $new, formatted
	## in the standard diff(1) output format.

	function PHPDiff($t1,$t2) 
	{
		# split the source text into arrays of lines
		//$t1 = explode("\n",$old);
		$x=array_pop($t1); 
		if ($x>'') $t1[]="$x\n\\ No newline at end of file";
		//$t2 = explode("\n",$new);
		$x=array_pop($t2); 
		if ($x>'') $t2[]="$x\n\\ No newline at end of file";
		
		# build a reverse-index array using the line as key and line number as value
		# don't store blank lines, so they won't be targets of the shortest distance
		# search
		foreach($t1 as $i=>$x) if ($x>'') $r1[$x][]=$i;
		foreach($t2 as $i=>$x) if ($x>'') $r2[$x][]=$i;
		
		$a1=0; $a2=0;   # start at beginning of each list
		$actions=array();
		
		# walk this loop until we reach the end of one of the lists
		while ($a1<count($t1) && $a2<count($t2))
		{
			# if we have a common element, save it and go to the next
			if ($t1[$a1]==$t2[$a2]) { $actions[]=4; $a1++; $a2++; continue; } 
			
			# otherwise, find the shortest move (Manhattan-distance) from the
			# current location
			$best1=count($t1); $best2=count($t2);
			$s1=$a1; $s2=$a2;
			while(($s1+$s2-$a1-$a2) < ($best1+$best2-$a1-$a2)) {
			$d=-1;
			foreach((array)@$r1[$t2[$s2]] as $n) 
			if ($n>=$s1) { $d=$n; break; }
			if ($d>=$s1 && ($d+$s2-$a1-$a2)<($best1+$best2-$a1-$a2))
			{ $best1=$d; $best2=$s2; }
			$d=-1;
			foreach((array)@$r2[$t1[$s1]] as $n) 
			if ($n>=$s2) { $d=$n; break; }
			if ($d>=$s2 && ($s1+$d-$a1-$a2)<($best1+$best2-$a1-$a2))
			{ $best1=$s1; $best2=$d; }
			$s1++; $s2++;
			}
			while ($a1<$best1) { $actions[]=1; $a1++; }  # deleted elements
			while ($a2<$best2) { $actions[]=2; $a2++; }  # added elements
		}

		# we've reached the end of one list, now walk to the end of the other
		while($a1<count($t1)) { $actions[]=1; $a1++; }  # deleted elements
		while($a2<count($t2)) { $actions[]=2; $a2++; }  # added elements
		
		# and this marks our ending point
		$actions[]=8;
		
		# now, let's follow the path we just took and report the added/deleted
		# elements into $out.
		$op = 0;
		$x0=$x1=0; $y0=$y1=0;
		$out1 = array();
		$out2 = array();
		foreach($actions as $act) {
			if ($act==1) { $op|=$act; $x1++; continue; }
			if ($act==2) { $op|=$act; $y1++; continue; }
			if ($op>0) {
				//$xstr = ($x1==($x0+1)) ? $x1 : ($x0+1).",$x1";
				//$ystr = ($y1==($y0+1)) ? $y1 : ($y0+1).",$y1";
				/*if ($op==1) $out[] = "{$xstr}d{$y1}";
				elseif ($op==3) $out[] = "{$xstr}c{$ystr}";*/
				while ($x0<$x1) { $out1[] = $x0; $x0++; }   # deleted elems
				/*if ($op==2) $out[] = "{$x1}a{$ystr}";
				elseif ($op==3) $out[] = '---';*/
				while ($y0<$y1) { $out2[] = $y0; $y0++; }   # added elems
			}
			$x1++; $x0=$x1;
			$y1++; $y0=$y1;
			$op=0;
		}
		//$out1[] = '';
		//$out2[] = '';
		return array($out1, $out2);
	}

	function flexiHtmlDiff($old, $new, $mode=0)
	{
		$t1 = explode(" ",$old);
		$t2 = explode(" ",$new);
		$out = flexicontent_html::PHPDiff( $t1, $t2 );
		$html1 = array();
		$html2 = array();
		foreach($t1 as $k=>$o) {
			if(in_array($k, $out[0])) $html1[] = "<s>".($mode?htmlspecialchars($o, ENT_QUOTES):$o)."</s>";
			else $html1[] = ($mode?htmlspecialchars($o, ENT_QUOTES)."<br />":$o);
		}
		foreach($t2 as $k=>$n) {
			if(in_array($k, $out[1])) $html2[] = "<u>".($mode?htmlspecialchars($n, ENT_QUOTES):$n)."</u>";
			else $html2[] = ($mode?htmlspecialchars($n, ENT_QUOTES)."<br />":$n);
		}
		$html1 = implode(" ", $html1);
		$html2 = implode(" ", $html2);
		return array($html1, $html2);
	}
	
	
	/**
	 * Method to retrieve mappings of CORE fields (Names to Types and reverse)
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getJCoreFields($ffield=NULL, $map_maintext_to_introtext=false, $reverse=false) {
		if(!$reverse)  // MAPPING core fields NAMEs => core field TYPEs
		{
			$flexifield = array(
				'title'=>'title',
				'categories'=>'categories',
				'tags'=>'tags',
				'text'=>'maintext',
				'created'=>'created',
				'created_by'=>'createdby',
				'modified'=>'modified',
				'modified_by'=>'modifiedby',
				'hits'=>'hits',
				'document_type'=>'type',
				'version'=>'version',
				'state'=>'state'
			);
			if ($map_maintext_to_introtext)
			{
				$flexifield['introtext'] = 'maintext';
			}
		}
		else    // MAPPING core field TYPEs => core fields NAMEs
		{
			$flexifield = array(
				'title'=>'title',
				'categories'=>'categories',
				'tags'=>'tags',
				'maintext'=>'text',
				'created'=>'created',
				'createdby'=>'created_by',
				'modified'=>'modified',
				'modifiedby'=>'modified_by',
				'hits'=>'hits',
				'type'=>'document_type',
				'version'=>'version',
				'state'=>'state'
			);
			if ($map_maintext_to_introtext)
			{
				$flexifield['maintext'] = 'introtext';
			}
		}
		if($ffield===NULL) return $flexifield;
		return isset($flexifield[$ffield])?$flexifield[$ffield]:NULL;
	}
	
	function getFlexiFieldId($jfield=NULL) {
		$flexifields = array(
			'introtext'=>1,
			'text'=>1,
			'created'=>2,
			'created_by'=>3,
			'modified'=>4,
			'modified_by'=>5,
			'title'=>6,
			'hits'=>7,
			'version'=>9,
			'state'=>10,
			'catid'=>13,
		);
		if($jfield===NULL) return $flexifields;
		return isset($flexifields[$jfield])?$flexifields[$jfield]:0;
	}
	
	function getFlexiField($jfield=NULL) {
		$flexifields = array(
			'introtext'=>'text',
			'fulltext'=>'text',
			'created'=>'created',
			'created_by'=>'createdby',
			'modified'=>'modified',
			'modified_by'=>'modifiedby',
			'title'=>'title',
			'hits'=>'hits',
			'version'=>'version',
			'state'=>'state'
		);
		if($jfield===NULL) return $flexifields;
		return isset($flexifields[$jfield])?$flexifields[$jfield]:0;
	}
	
	function getTypesList()
	{
		$db =& JFactory::getDBO();
		
		$query = 'SELECT id, name'
		. ' FROM #__flexicontent_types'
		. ' WHERE published = 1'
		;
		
		$db->setQuery($query);
		$types = $db->loadAssocList('id');
		
		return $types;
	}
	
	
	/**
	 * Displays a list of the available access view levels
	 *
	 * @param	string	The form field name.
	 * @param	string	The name of the selected section.
	 * @param	string	Additional attributes to add to the select field.
	 * @param	mixed	True to add "All Sections" option or and array of option
	 * @param	string	The form field id
	 *
	 * @return	string	The required HTML for the SELECT tag.
	 */
	public static function userlevel($name, $selected, $attribs = '', $params = true, $id = false, $createlist = true) {
		static $options;
		if(!$options) {
			$db		= JFactory::getDbo();
			$query	= $db->getQuery(true);
			$query->select('a.id AS value, a.title AS text');
			$query->from('#__viewlevels AS a');
			if (!$createlist) {
				$query->where('a.id="'.$selected.'"');
			}
			$query->group('a.id');
			$query->order('a.ordering ASC');
			$query->order('`title` ASC');

			// Get the options.
			$db->setQuery($query);
			$options = $db->loadObjectList();

			// Check for a database error.
			if ($db->getErrorNum()) {
				JError::raiseWarning(500, $db->getErrorMsg());
				return null;
			}
			
			if (!$createlist) {
				return $options[0]->text;  // return ACCESS LEVEL NAME
			}
			
			// If params is an array, push these options to the array
			if (is_array($params)) {
				$options = array_merge($params,$options);
			}
			// If all levels is allowed, push it into the array.
			elseif ($params) {
				//array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_ACCESS_SHOW_ALL_LEVELS')));
			}
		}

		return JHtml::_('select.genericlist', $options, $name,
			array(
				'list.attr' => $attribs,
				'list.select' => $selected,
				'id' => $id
			)
		);
	}

	/*
	 * Method to confirm if a given string is a valid MySQL date
	 * param  string			$date
	 * return boolean			true if valid date, false otherwise
	 */
	function createFieldTabber( &$field_html, &$field_tab_labels, $class )
	{
		$not_in_tabs = "";
		
		$output = "<!-- tabber start --><div class='fctabber' class='".$class."'>"."\n";
		
		foreach ($field_html as $i => $html) {
			// Hide field when it has no label, and skip creating tab
			$no_label = ! isset( $field_tab_labels[$i] );
			$not_in_tabs .= $no_label ? "<div style='display:none!important'>".$field_html[$i]."</div>" : "";
			if ( $no_label ) continue;
			
			$output .= "	<div class='tabbertab'>"."\n";
			$output .= "		<h3>".$field_tab_labels[$i]."</h3>"."\n";   // Current TAB LABEL
			$output .= "		".$not_in_tabs."\n";                        // Output hidden fields (no tab created), by placing them inside the next appearing tab
			$output .= "		".$field_html[$i]."\n";                     // Current TAB CONTENTS
			$output .= "	</div>"."\n";
			
			$not_in_tabs = "";     // Clear the hidden fields variable
		}
		$output .= "</div><!-- tabber end -->";
		$output .= $not_in_tabs;      // Output ENDING hidden fields, by placing them outside the tabbing area
		return $output;
	}
	
}

class flexicontent_upload
{
	function makeSafe($file) {//The range \xE01-\xE5B is thai language.
		$file = str_replace(" ", "", $file);
		$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\xE01-\xE5B\.\_\- ]#', '#^\.#');
		//$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\.\_\- ]#', '#^\.#');
		return preg_replace($regex, '', $file);
	}
	/**
	 * Gets the extension of a file name
	 *
	 * @param string $file The file name
	 * @return string The file extension
	 * @since 1.5
	 */
	function getExt($file) {
		$len = strlen($file);
		$params = &JComponentHelper::getParams( 'com_flexicontent' );
		$exts = $params->get('upload_extensions');
		$exts = str_replace(' ', '', $exts);
		$exts = explode(",", $exts);
		//$exts = array('pdf', 'odt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'tar.gz');
		$ext = '';
		for($i=$len-1;$i>=0;$i--) {
			$c = $file[$i];
			if($c=='.' && in_array($ext, $exts)) {
				return $ext;
			}
			$ext = $c . $ext;
		}
		$dot = strpos($file, '.') + 1;
		return substr($file, $dot);
	}
	function check(&$file, &$err, &$params)
	{
		if (!$params) {
			$params = &JComponentHelper::getParams( 'com_flexicontent' );
		}

		if(empty($file['name'])) {
			$err = 'FLEXI_PLEASE_INPUT_A_FILE';
			return false;
		}

		jimport('joomla.filesystem.file');
		$file['altname'] = $file['name'];
		if ($file['name'] !== JFile::makesafe($file['name'])) {
			//$err = JText::_('FLEXI_WARNFILENAME').','.$file['name'].'|'.JFile::makesafe($file['name'])."<br />";
			//return false;
			$file['name'] = date('Y-m-d-H-i-s').".".flexicontent_upload::getExt($file['name']);
		}

		//check if the imagefiletype is valid
		$format 	= strtolower(flexicontent_upload::getExt($file['name']));

		$allowable = explode( ',', $params->get( 'upload_extensions' ));
		$ignored = explode(',', $params->get( 'ignore_extensions' ));
		if (!in_array($format, $allowable) && !in_array($format,$ignored))
		{
			$err = 'FLEXI_WARNFILETYPE';
			return false;
		}

		//Check filesize
		$maxSize = (int) $params->get( 'upload_maxsize', 0 );
		if ($maxSize > 0 && (int) $file['size'] > $maxSize)
		{
			$err = 'FLEXI_WARNFILETOOLARGE';
			return false;
		}

		$imginfo = null;

		$images = explode( ',', $params->get( 'image_extensions' ));
		
		if($params->get('restrict_uploads', 1) ) {
			
			if(in_array($format, $images)) { // if its an image run it through getimagesize
				if(($imginfo = getimagesize($file['tmp_name'])) === FALSE) {
					$err = 'FLEXI_WARNINVALIDIMG';
					return false;
				}

			} else if(!in_array($format, $ignored)) {

				// if its not an image...and we're not ignoring it
				$allowed_mime = explode(',', $params->get('upload_mime'));
				$illegal_mime = explode(',', $params->get('upload_mime_illegal'));

				if(function_exists('finfo_open') && $params->get('check_mime',1)) {
					// We have fileinfo
					$finfo = finfo_open(FILEINFO_MIME);
					$type = finfo_file($finfo, $file['tmp_name']);
					if(strlen($type) && !in_array($type, $allowed_mime) && in_array($type, $illegal_mime)) {
						$err = 'FLEXI_WARNINVALIDMIME';
						return false;
					}
					finfo_close($finfo);

				} else if(function_exists('mime_content_type') && $params->get('check_mime',1)) {

					// we have mime magic
					$type = mime_content_type($file['tmp_name']);

					if(strlen($type) && !in_array($type, $allowed_mime) && in_array($type, $illegal_mime)) {
						$err = 'FLEXI_WARNINVALIDMIME';
						return false;
					}

				}
			}
		}
		$xss_check =  JFile::read($file['tmp_name'],false,256);
		$html_tags = array('abbr','acronym','address','applet','area','audioscope','base','basefont','bdo','bgsound','big','blackface','blink','blockquote','body','bq','br','button','caption','center','cite','code','col','colgroup','comment','custom','dd','del','dfn','dir','div','dl','dt','em','embed','fieldset','fn','font','form','frame','frameset','h1','h2','h3','h4','h5','h6','head','hr','html','iframe','ilayer','img','input','ins','isindex','keygen','kbd','label','layer','legend','li','limittext','link','listing','map','marquee','menu','meta','multicol','nobr','noembed','noframes','noscript','nosmartquotes','object','ol','optgroup','option','param','plaintext','pre','rt','ruby','s','samp','script','select','server','shadow','sidebar','small','spacer','span','strike','strong','style','sub','sup','table','tbody','td','textarea','tfoot','th','thead','title','tr','tt','ul','var','wbr','xml','xmp','!DOCTYPE', '!--');
		foreach($html_tags as $tag) {
			// A tag is '<tagname ', so we need to add < and a space or '<tagname>'
			if(stristr($xss_check, '<'.$tag.' ') || stristr($xss_check, '<'.$tag.'>')) {
				$err = 'FLEXI_WARNIEXSS';
				return false;
			}
		}

		return true;
	}

	/**
	* Sanitize the image file name and return an unique string
	*
	* @since 1.0
	*
	* @param string $base_Dir the target directory
	* @param string $filename the unsanitized imagefile name
	*
	* @return string $filename the sanitized and unique file name
	*/
	function sanitize($base_Dir, $filename)
	{
		jimport('joomla.filesystem.file');

		//check for any leading/trailing dots and remove them (trailing shouldn't be possible cause of the getEXT check)
		$filename = preg_replace( "/^[.]*/", '', $filename );
		$filename = preg_replace( "/[.]*$/", '', $filename ); //shouldn't be necessary, see above

		//we need to save the last dot position cause preg_replace will also replace dots
		$lastdotpos = strrpos( $filename, '.' );

		//replace invalid characters
		$chars = '[^0-9a-zA-Z()_-]';
		$filename 	= strtolower( preg_replace( "/$chars/", '-', $filename ) );

		//get the parts before and after the dot (assuming we have an extension...check was done before)
		$beforedot	= substr( $filename, 0, $lastdotpos );
		$afterdot 	= substr( $filename, $lastdotpos + 1 );

		//make a unique filename for the image and check it is not already taken
		//if it is already taken keep trying till success
		if (JFile::exists( $base_Dir . $beforedot . '.' . $afterdot ))
		{
			$version = 1;
			while( JFile::exists( $base_Dir . $beforedot . '-' . $version . '.' . $afterdot ) )
			{
				$version++;
			}
			//create out of the seperated parts the new filename
			$filename = $beforedot . '-' . $version . '.' . $afterdot;
		} else {
			$filename = $beforedot . '.' . $afterdot;
		}

		return $filename;
	}

	/**
	* Sanitize folders and return an unique string
	*
	* @since 1.5
	*
	* @param string $base_Dir the target directory
	* @param string $foler the unsanitized folder name
	*
	* @return string $foldername the sanitized and unique file name
	*/
	function sanitizedir($base_Dir, $folder)
	{
		jimport('joomla.filesystem.folder');

		//replace invalid characters
		$chars = '[^0-9a-zA-Z()_-]';
		$folder 	= strtolower( preg_replace( "/$chars/", '-', $folder ) );

		//make a unique folder name for the image and check it is not already taken
		if (JFolder::exists( $base_Dir . $folder ))
		{
			$version = 1;
			while( JFolder::exists( $base_Dir . $folder . '-' . $version )) {
				$version++;
			}
			//create out of the seperated parts the new folder name
			$foldername = $folder . '-' . $version;
		} else {
			$foldername = $folder;
		}

		return $foldername;
	}
}

class flexicontent_tmpl
{
	/**
	 * Parse all FLEXIcontent templates files
	 *
	 * @return 	object	object of templates
	 * @since 1.5
	 */
	function parseTemplates($tmpldir='')
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.form.form');
		$themes = new stdClass();
		
		$tmpldir = $tmpldir?$tmpldir:JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$templates = JFolder::folders($tmpldir);
		
		foreach ($templates as $tmpl) {
			$tmplxml = $tmpldir.DS.$tmpl.DS.'item.xml';
			if (JFile::exists($tmplxml)) {
				$themes->items->{$tmpl}->name 		= $tmpl;
				$themes->items->{$tmpl}->view 		= FLEXI_ITEMVIEW;
				$themes->items->{$tmpl}->tmplvar 	= '.items.'.$tmpl;
				$themes->items->{$tmpl}->thumb		= 'components/com_flexicontent/templates/'.$tmpl.'/item.png';	
				if (!FLEXI_J16GE) {
					$themes->items->{$tmpl}->params	= new JParameter('', $tmplxml);
				} else {
					$themes->items->{$tmpl}->params		= new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
					$themes->items->{$tmpl}->params->loadFile($tmplxml);
				}
				foreach ($themes->items as $ilay) {
					$parser =& JFactory::getXMLParser('Simple');		
					$parser->loadFile($tmplxml);
					$document 	=& $parser->document;

					$themes->items->{$tmpl}->author 		= @$document->author[0] ? $document->author[0]->data() : '';
					$themes->items->{$tmpl}->website 		= @$document->website[0] ? $document->website[0]->data() : '';
					$themes->items->{$tmpl}->email 			= @$document->email[0] ? $document->email[0]->data() : '';
					$themes->items->{$tmpl}->license 		= @$document->license[0] ? $document->license[0]->data() : '';
					$themes->items->{$tmpl}->version 		= @$document->version[0] ? $document->version[0]->data() : '';
					$themes->items->{$tmpl}->release 		= @$document->release[0] ? $document->release[0]->data() : '';
					$themes->items->{$tmpl}->description	= @$document->description[0] ? $document->description[0]->data() : '';
					
					$groups 	=& $document->getElementByPath('fieldgroups');
					$pos	 	=& $groups->group;
					if ($pos) {
						for ($n=0; $n<count($pos); $n++) {
							$themes->items->{$tmpl}->attributes[$n] = $pos[$n]->_attributes;
							$themes->items->{$tmpl}->positions[$n] = $pos[$n]->data();
						}
					}
					$css 		=& $document->getElementByPath('cssitem');
					$cssfile	=& $css->file;
					if ($cssfile) {
						for ($n=0; $n<count($cssfile); $n++) {
							$themes->items->{$tmpl}->css->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$cssfile[$n]->data();
						}
					}
					$js 		=& $document->getElementByPath('jsitem');
					$jsfile	=& $js->file;
					if ($jsfile) {
						for ($n=0; $n<count($jsfile); $n++) {
							$themes->items->{$tmpl}->js->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$jsfile[$n]->data();
						}
					}
				}
			}
			$tmplxml = $tmpldir.DS.$tmpl.DS.'category.xml';
			if (JFile::exists($tmplxml)) {
				$themes->category->{$tmpl}->name 		= $tmpl;
				$themes->category->{$tmpl}->view 		= 'category';
				$themes->category->{$tmpl}->tmplvar 	= '.category.'.$tmpl;
				$themes->category->{$tmpl}->thumb		= 'components/com_flexicontent/templates/'.$tmpl.'/category.png';	
				if (!FLEXI_J16GE) {
					$themes->category->{$tmpl}->params		= new JParameter('', $tmplxml);
				} else {
					$themes->category->{$tmpl}->params		= new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => true));
					$themes->category->{$tmpl}->params->loadFile($tmplxml);
				}
				foreach ($themes->category as $clay) {
					$parser =& JFactory::getXMLParser('Simple');
					$parser->loadFile($tmplxml);
					$document 	=& $parser->document;

					$themes->category->{$tmpl}->author 		= @$document->author[0] ? $document->author[0]->data() : '';
					$themes->category->{$tmpl}->website 	= @$document->website[0] ? $document->website[0]->data() : '';
					$themes->category->{$tmpl}->email 		= @$document->email[0] ? $document->email[0]->data() : '';
					$themes->category->{$tmpl}->license 	= @$document->license[0] ? $document->license[0]->data() : '';
					$themes->category->{$tmpl}->version 	= @$document->version[0] ? $document->version[0]->data() : '';
					$themes->category->{$tmpl}->release 	= @$document->release[0] ? $document->release[0]->data() : '';
					$themes->category->{$tmpl}->description = @$document->description[0] ? $document->description[0]->data() : '';

					$groups 	=& $document->getElementByPath('fieldgroups');
					$pos	 	=& $groups->group;
					if ($pos) {
						for ($n=0; $n<count($pos); $n++) {
							$themes->category->{$tmpl}->attributes[$n] = $pos[$n]->_attributes;
							$themes->category->{$tmpl}->positions[$n] = $pos[$n]->data();
						}
					}
					$css 		=& $document->getElementByPath('csscategory');
					$cssfile	=& $css->file;
					if ($cssfile) {
						for ($n=0; $n<count($cssfile); $n++) {
							$themes->category->{$tmpl}->css->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$cssfile[$n]->data();
						}
					}
					$js 		=& $document->getElementByPath('jscategory');
					$jsfile	=& $js->file;
					if ($jsfile) {
						for ($n=0; $n<count($jsfile); $n++) {
							$themes->category->{$tmpl}->js->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$jsfile[$n]->data();
						}
					}
				}
			}
		}
		return $themes;
	}

	function getTemplates()
	{
		if (FLEXI_CACHE && !FLEXI_J16GE)
		{
			// add the templates to templates cache
			$tmplcache =& JFactory::getCache('com_flexicontent_tmpl');
			$tmplcache->setCaching(1); 		//force cache
			$tmplcache->setLifeTime(84600); //set expiry to one day
			$tmpls = $tmplcache->call(array('flexicontent_tmpl', 'parseTemplates'));
		}
		else 
		{
			$tmpls = flexicontent_tmpl::parseTemplates();
		}
	    
	    return $tmpls;
	}

	function getThemes($tmpldir='')
	{
		jimport('joomla.filesystem.file');

		$tmpldir = $tmpldir?$tmpldir:JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$themes = JFolder::folders($tmpldir);
		
		return $themes;
	}

	/**
	 * Method to get all available fields for a template in a view
	 *
	 * @access public
	 * @return object
	 */
	function getFieldsByPositions($folder, $type) {
		if ($type=='item') $type='items';
		
		static $templates;
		if(!isset($templates[$folder])) {
			$templates[$folder] = array();
		}
		if(!isset($templates[$folder][$type])) {
			$db = JFactory::getDBO();
			$query  = 'SELECT *'
					. ' FROM #__flexicontent_templates'
					. ' WHERE template = ' . $db->Quote($folder)
					. ' AND layout = ' . $db->Quote($type)
					;
			$db->setQuery($query);
			$positions = $db->loadObjectList('position');
			foreach ($positions as $pos) {
				$pos->fields = explode(',', $pos->fields);
			}
			$templates[$folder][$type] = & $positions;
		}
		return $templates[$folder][$type];
	}
}

class flexicontent_images
{
	/**
	 * Get file size and icons
	 *
	 * @since 1.5
	 */
	function BuildIcons($rows)
	{
		jimport('joomla.filesystem.file');
		
		for ($i=0, $n=count($rows); $i < $n; $i++) {

			$basePath = $rows[$i]->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;

			if (is_file($basePath.DS.$rows[$i]->filename)) {
				$path = str_replace(DS, '/', JPath::clean($basePath.DS.$rows[$i]->filename));

				$size = filesize($path);

				if ($size < 1024) {
					$rows[$i]->size = $size . ' bytes';
				} else {
					if ($size >= 1024 && $size < 1024 * 1024) {
						$rows[$i]->size = sprintf('%01.2f', $size / 1024.0) . ' Kb';
					} else {
						$rows[$i]->size = sprintf('%01.2f', $size / (1024.0 * 1024)) . ' Mb';
					}
				}
			} else {
				$rows[$i]->size = 'N/A';
			}
			
			if ($rows[$i]->url == 1)
			{
				$ext = $rows[$i]->ext;
			} else {
				$ext = strtolower(JFile::getExt($rows[$i]->filename));
			}
			switch ($ext)
			{
				// Image
				case 'jpg':
				case 'png':
				case 'gif':
				case 'xcf':
				case 'odg':
				case 'bmp':
				case 'jpeg':
					$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/image.png';
					break;

				// Non-image document
				default:
					$icon = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'assets'.DS.'images'.DS.'mime-icon-16'.DS.$ext.'.png';
					if (file_exists($icon)) {
						$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/'.$ext.'.png';
					} else {
						$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
					}
					break;
			}

		}

		return $rows;
	}

}


class FLEXIUtilities
{
	
	/**
	 * Load Template-Specific language file to override or add new language strings
	 * 
	 * @return object
	 * @since 1.5
	 */
	
	function loadTemplateLanguageFile( $tmplname='default', $view='' )
	{
		// Check that template name was given
		$tmplname = empty($tmplname) ? 'default' : $tmplname;
		
		// This is normally component/module/plugin name, we could use 'category', 'items', etc to have a view specific language file
		// e.g. en/en.category.ini, but this is an overkill and make result into duplication of strings ... better all in one file 
		$extension = '';  // JRequest::get('view');
		
		// Current language, we decided to use LL-CC (language-country) format mapping SEF shortcode, e.g. 'en' to 'en-GB'
		$user_lang = flexicontent_html::getUserCurrentLang();
		$languages = FLEXIUtilities::getLanguages($hash='shortcode');
		$language_tag = $languages->$user_lang->code;
		
		// We will use template folder as BASE of language files instead of joomla's language folder
		// Since FLEXIcontent templates are meant to be user-editable it makes sense to place language files inside them
		$base_dir = JPATH_COMPONENT.DS.'templates'.DS.$tmplname;
		
		// Final use joomla's API to load our template's language files -- (load english template language file then override with current language file)
		JFactory::getLanguage()->load($extension, $base_dir, 'en-GB', $reload=true);           // Fallback to english language template file
		JFactory::getLanguage()->load($extension, $base_dir, $language_tag, $reload=true);  // User's current language template file
	}
	
	/**
	 * Method to get information of site languages
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getlanguageslist()
	{
		$mainframe =& JFactory::getApplication();
		$db =& JFactory::getDBO();
		static $languages = null;
		if ($languages) return $languages;
		
		// ******************
		// Retrieve languages
		// ******************
		if (FLEXI_FISH) {   // Use joomfish languages table
			$query = 'SELECT l.* '
				. ( FLEXI_FISH_22GE ? ", lext.* " : "" )
				. ( FLEXI_FISH_22GE ? ", l.lang_id as id " : ", l.id " )
				. ( FLEXI_FISH_22GE ? ", l.lang_code as code, l.sef as shortcode" : ", l.code, l.shortcode" )
				. ( FLEXI_FISH_22GE ? ", CASE WHEN CHAR_LENGTH(l.title) THEN l.title ELSE l.title_native END as name" : ", l.name " )
				. ' FROM #__languages as l'
				. ( FLEXI_FISH_22GE ? ' LEFT JOIN #__jf_languages_ext as lext ON l.lang_id=lext.lang_id ' : '')
				. ' WHERE '.    (FLEXI_FISH_22GE ? ' l.published=1 ' : ' l.active=1 ')
				. ' ORDER BY '. (FLEXI_FISH_22GE ? ' lext.ordering ASC ' : ' l.ordering ASC ')
					;
		} else if (FLEXI_J16GE) {   // Use J1.6+ language info
			$query = 'SELECT DISTINCT le.*, le.extension_id as id, lc.image as image_prefix'
					.', CASE WHEN CHAR_LENGTH(lc.title_native) THEN lc.title_native ELSE le.name END as name'
					.' FROM #__extensions as le'
					.' LEFT JOIN #__languages as lc ON lc.lang_code=le.element AND lc.published=1'
					.' WHERE le.type="language" '
					.' GROUP BY le.element';
		} else {
			JError::raiseWarning(500, 'getlanguageslist(): ERROR no joomfish installed');
			return array();
		}
		$db->setQuery($query);
		$languages = $db->loadObjectList('id');
		//echo "<pre>"; echo $query; print_r($languages); echo "</pre>"; exit;
		if ($db->getErrorNum()) {
			JError::raiseWarning(500, $db->getErrorMsg()."<br>Query:<br>".$query);
			return array();
		}
		
		
		// *********************
		// Calculate image paths
		// *********************
		if (FLEXI_FISH) {   // Use joomfish images
			$imgpath	= $mainframe->isAdmin() ? '../images/':'images/';
			$mediapath	= $mainframe->isAdmin() ? '../components/com_joomfish/images/flags/' : 'components/com_joomfish/images/flags/';
		} else {  // FLEXI_J16GE, use J1.6+ images
			$imgpath	= $mainframe->isAdmin() ? '../images/':'images/';
			$mediapath	= $mainframe->isAdmin() ? '../media/mod_languages/images/' : 'media/mod_languages/images/';
		}
		
		
		// ************************
		// Prepare language objects
		// ************************
		if ( FLEXI_FISH && FLEXI_FISH_22GE )  // JoomFish v2.2+
		{
			require_once(JPATH_ROOT.DS.'administrator'.DS.'components'.DS.'com_joomfish'.DS.'helpers'.DS.'extensionHelper.php' );
			foreach ($languages as $lang) {
				// Get image path via helper function
				$lang->imgsrc = JURI::root().JoomfishExtensionHelper::getLanguageImageSource($lang);
			}
		}
		else if ( FLEXI_FISH )                // JoomFish until v2.1
		{
			foreach ($languages as $lang) {
				// $lang->image, holds a custom image path
				$lang->imgsrc = @$lang->image ? $imgpath . $lang->image : $mediapath . $lang->shortcode . '.gif';			
			}
		}
		else
		{                                     // FLEXI_J16GE, based on J1.6+ language data and images
			foreach ($languages as $lang) {
				// Calculate/Fix languages data
				$lang->code = $lang->element;
				$lang->shortcode = substr($lang->code, 0, strpos($lang->code,'-'));
				$lang->id = $lang->extension_id;
				$image_prefix = $lang->image_prefix ? $lang->image_prefix : $lang->shortcode;
				// $lang->image, holds a custom image path
				$lang->imgsrc = @$lang->image ? $imgpath . $lang->image : $mediapath . $image_prefix . '.gif';
			}
			
			// Also prepend '*' (ALL) language to language array
			$lang_all = new stdClass();
			$lang_all->code = '*';
			$lang_all->name = 'All';
			$lang_all->shortcode = '*';
			$lang_all->id = 0;
			array_unshift($languages, $lang_all);
			
			// Select language -ALL- if none selected
			//$selected = $selected ? $selected : '*';    // WRONG behavior commented out
		}
		
		return $languages;
	}
	
	
	/**
	 * Method to build an array of languages hashed by id or by language code
	 * 
	 * @return object
	 * @since 1.5
	 */
	function getLanguages($hash='code')
	{
		$langs = new stdClass();
		
		$languages = FLEXIUtilities::getlanguageslist();
		foreach ($languages as $language)
			$langs->{$language->$hash} = $language;
		
		return $langs;
	}
	
		
	/**
	 * Method to get the last version kept
	 * 
	 * @return int
	 * @since 1.5
	 */
	function &getLastVersions ($id=NULL, $justvalue=false, $force=false)
	{
		static $g_lastversions;  // cache ...
		
		if( $g_lastversions==NULL || $force )
		{
			$db =& JFactory::getDBO();
			$query = "SELECT item_id as id, max(version_id) as version"
									." FROM #__flexicontent_versions"
									." WHERE 1 GROUP BY item_id";
			$db->setQuery($query);
			$rows = $db->loadAssocList();
			$g_lastversions =  array();
			foreach($rows as $row) {
				$g_lastversions[$row["id"]] = $row;
			}
			unset($rows);
		}
		
		// Special case (version number of new item): return version zero
		if (!$id && $justvalue) { $v = 0; return $v; }
		
		// an item id was given return item specific data
		if ($id) {
			$return = $justvalue ? @$g_lastversions[$id]['version'] : @$g_lastversions[$id];
			return $return;
		}
		
		// no item id was given return all version data
		return $g_lastversions;
	}
	
	
	function &getCurrentVersions ($id=NULL, $justvalue=false, $force=false)
	{
		static $g_currentversions;  // cache ...
		
		if( $g_currentversions==NULL || $force )
		{
			$db =& JFactory::getDBO();
			if (!FLEXI_J16GE) {
				$query = "SELECT id, version FROM #__content WHERE sectionid='".FLEXI_SECTION."';";
			} else {
				$query = "SELECT c.id, c.version FROM #__content as c"
						. " JOIN #__categories as cat ON c.catid=cat.id"
						. " WHERE cat.extension='".FLEXI_CAT_EXTENSION."'";
			}
			$db->setQuery($query);
			$rows = $db->loadAssocList();
			$g_currentversions = array();
			foreach($rows as $row) {
				$g_currentversions[$row["id"]] = $row;
			}
			unset($rows);
		}
		
		// Special case (version number of new item): return version zero
		if (!$id && $justvalue) { $v = 0; return $v; }
		
		// an item id was given return item specific data
		if($id) {
			$return = $justvalue ? @$g_currentversions[$id]['version'] : @$g_currentversions[$id];
			return $return;
		}
		
		// no item id was given return all version data
		return $g_currentversions;
	}
	
	
	function &getLastItemVersion($id)
	{
		$db =& JFactory::getDBO();
		$query = 'SELECT max(version) as version'
				.' FROM #__flexicontent_items_versions'
				.' WHERE item_id = ' . (int)$id
				;
		$db->setQuery($query, 0, 1);
		$lastversion = $db->loadResult();
		
		return (int)$lastversion;
	}
	
	
	function &currentMissing()
	{
		static $status;
		if(!$status) {
			$db =& JFactory::getDBO();
			$query = "SELECT c.id,c.version,iv.version as iversion FROM #__content as c "
				." LEFT JOIN #__flexicontent_items_versions as iv ON c.id=iv.item_id AND c.version=iv.version"
				.(FLEXI_J16GE ? " JOIN #__categories as cat ON c.catid=cat.id" : "")
				." WHERE c.version > '1' AND iv.version IS NULL"
				.(!FLEXI_J16GE ? " AND sectionid='".FLEXI_SECTION."'" : " AND cat.extension='".FLEXI_CAT_EXTENSION."'")
				." LIMIT 0,1";
			$db->setQuery($query);
			$rows = $db->loadObjectList("id");
			if ($db->getErrorNum()) {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage($db->getErrorMsg(),'error');
			}
			$rows = is_array($rows)?$rows:array();
			$status = false;
			if(count($rows)>0) {
				$status = true;
			}
			unset($rows);
		}
		return $status;
	}
	
	
	/**
	 * Method to get the first version kept
	 * 
	 * @return int
	 * @since 1.5
	 */
	function &getFirstVersion($id, $max, $current_version)
	{
		$db =& JFactory::getDBO();
		$query = 'SELECT version_id'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$id
				.' AND version_id!=' . (int)$current_version
				.' ORDER BY version_id DESC'
				;
		$db->setQuery($query, ($max-1), 1);
		$firstversion = $db->loadResult();
		return $firstversion;
	}
	
	
	/**
	 * Method to get the versions count
	 * 
	 * @return int
	 * @since 1.5
	 */
	function &getVersionsCount($id)
	{
		$db =& JFactory::getDBO();
		$query = 'SELECT COUNT(*)'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$id
				;
		$db->setQuery($query);
		$versionscount = $db->loadResult();
		
		return $versionscount;
	}
	
	
	function doPlgAct()
	{
		$plg = JRequest::getVar('plg');
		$act = JRequest::getVar('act');
		if($plg && $act) {
			$plgfolder = !FLEXI_J16GE ? '' : DS.strtolower($plg);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.$plgfolder.DS.strtolower($plg).'.php';
			if(file_exists($path)) require_once($path);
			$class = "plgFlexicontent_fields{$plg}";
			if(class_exists($class) && in_array($act, get_class_methods($class))) {
				//call_user_func("$class::$act");
				call_user_func(array($class, $act));
			}
		}
	}
	
	
	function getCache($group='', $client=0)
	{
		$conf = JFactory::getConfig();
		//$client = 0;//0 is site, 1 is admin
		$options = array(
			'defaultgroup'	=> $group,
			'storage' 		=> $conf->get('cache_handler', ''),
			'caching'		=> true,
			'cachebase'		=> ($client == 1) ? JPATH_ADMINISTRATOR . '/cache' : $conf->get('cache_path', JPATH_SITE . '/cache')
		);

		jimport('joomla.cache.cache');
		$cache = JCache::getInstance('', $options);
		return $cache;
	}
	
	
	function call_FC_Field_Func( $fieldname, $func, $args=null )
	{
		static $fc_plgs;
		
		if ( !isset( $fc_plgs[$fieldname] ) ) {
			// 1. Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = !FLEXI_J16GE ? '' : DS.strtolower($fieldname);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.$plgfolder.DS.strtolower($fieldname).'.php';
			if(file_exists($path)) require_once($path);
			else {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(nl2br("Cannot load FC Field: $fieldname\n Please correct field name"),'error');
				return;
			}
			
			// 2. Create plugin instance
			$class = "plgFlexicontent_fields{$fieldname}";
			if( class_exists($class) ) {
				// Create class name of the plugin
				$className = 'plg'.'flexicontent_fields'.$fieldname;
				// Create a plugin instance
				$dispatcher = & JDispatcher::getInstance();
				$fc_plgs[$fieldname] =  new $className($dispatcher, array());
				// Assign plugin parameters, (most FLEXI plugins do not have plugin parameters)
				$plugin_db_data = & JPluginHelper::getPlugin('flexicontent_fields',$fieldname);
				$fc_plgs[$fieldname]->params = new JParameter($plugin_db_data->params);
			} else {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(nl2br("Could not find class: $className in file: $path\n Please correct field name"),'error');
				return;
			}
		}
		
		// 3. Execute only if it exists
		$class = "plgFlexicontent_fields{$fieldname}";
		if(in_array($func, get_class_methods($class))) {
			return call_user_func_array(array($fc_plgs[$fieldname], $func), $args);
		}
	}
	
	
	/* !!! FUNCTION NOT DONE YET */
	function call_Content_Plg_Func( $fieldname, $func, $args=null )
	{
		static $content_plgs;
		
		if ( !isset( $content_plgs[$fieldname] ) ) {
			// 1. Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = !FLEXI_J16GE ? '' : DS.strtolower($fieldname);
			$path = JPATH_ROOT.DS.'plugins'.DS.'content'.$plgfolder.DS.strtolower($fieldname).'.php';
			if(file_exists($path)) require_once($path);
			else {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(nl2br("Cannot load CONTENT Plugin: $fieldname\n Please correct field name"),'error');
				return;
			}
			
			// 2. Create plugin instance
			$class = "plgContent{$fieldname}";
			if( class_exists($class) ) {
				// Create class name of the plugin
				$className = 'plg'.'content'.$fieldname;
				// Create a plugin instance
				$dispatcher = & JDispatcher::getInstance();
				$content_plgs[$fieldname] =  new $className($dispatcher, array());
				// Assign plugin parameters, (most FLEXI plugins do not have plugin parameters)
				$plugin_db_data = & JPluginHelper::getPlugin('content',$fieldname);
				$content_plgs[$fieldname]->params = new JParameter($plugin_db_data->params);
			} else {
				$jAp=& JFactory::getApplication();
				$jAp->enqueueMessage(nl2br("Could not find class: $className in file: $path\n Please correct field name"),'error');
				return;
			}
		}
		
		// 3. Execute only if it exists
		$class = "plgContent{$fieldname}";
		if(in_array($func, get_class_methods($class))) {
			return call_user_func_array(array($content_plgs[$fieldname], $func), $args);
		}
	}
	
	
	/**
	 * Return unicode char by its code
	 * Credits: ?
	 *
	 * @param int $dec
	 * @return utf8 char
	 */
	function unichr($dec) {
	  if ($dec < 128) {
	    $utf = chr($dec);
	  } else if ($dec < 2048) {
	    $utf = chr(192 + (($dec - ($dec % 64)) / 64));
	    $utf .= chr(128 + ($dec % 64));
	  } else {
	    $utf = chr(224 + (($dec - ($dec % 4096)) / 4096));
	    $utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
	    $utf .= chr(128 + ($dec % 64));
	  }
	  return $utf;
	}
	
	
	/**
	 * Return unicode code of a utf8 char
	 * Credits: ?
	 *
	 * @param int $c
	 * @return utf8 ord
	 */
	function uniord($c) {
		$h = ord($c{0});
		if ($h <= 0x7F) {
			return $h;
		} else if ($h < 0xC2) {
			return false;
		} else if ($h <= 0xDF) {
			return ($h & 0x1F) << 6 | (ord($c{1}) & 0x3F);
		} else if ($h <= 0xEF) {
			return ($h & 0x0F) << 12 | (ord($c{1}) & 0x3F) << 6
			| (ord($c{2}) & 0x3F);
		} else if ($h <= 0xF4) {
			return ($h & 0x0F) << 18 | (ord($c{1}) & 0x3F) << 12
			| (ord($c{2}) & 0x3F) << 6
			| (ord($c{3}) & 0x3F);
		} else {
			return false;
		}
	}
	
	
	/**
	 * Return unicode string when giving an array of utf8 ords
	 * Credits: Darien Hager
	 *
	 * @param  $ords   utf8 ord arrray
	 * @return $str    utf8 string
	 */
	function ords_to_unistr($ords, $encoding = 'UTF-8'){
		// Turns an array of ordinal values into a string of unicode characters
		$str = '';
		for($i = 0; $i < sizeof($ords); $i++){
			// Pack this number into a 4-byte string
			// (Or multiple one-byte strings, depending on context.)
			$v = $ords[$i];
			$str .= pack("N",$v);
		}
		$str = mb_convert_encoding($str,$encoding,"UCS-4BE");
		return($str);
	}
	
	
	/**
	 * Return unicode string when giving an array of utf8 ords
	 * Credits: Darien Hager
	 *
	 * @param  $str    utf8 string
	 * @return $ords   utf8 ord arrray
	 */
	function unistr_to_ords($str, $encoding = 'UTF-8')
	{
		// Turns a string of unicode characters into an array of ordinal values,
		// Even if some of those characters are multibyte.
		$str = mb_convert_encoding($str,"UCS-4BE",$encoding);
		$ords = array();
	
		// Visit each unicode character
		//for($i = 0; $i < mb_strlen($str,"UCS-4BE"); $i++){
		//for($i = 0; $i < utf8_strlen($str); $i++){
		for($i = 0; $i < JString::strlen($str,"UCS-4BE"); $i++){
			// Now we have 4 bytes. Find their total
			// numeric value.
			$s2 = JString::substr($str,$i,1,"UCS-4BE");
			$val = unpack("N",$s2);
			$ords[] = $val[1];
		}
		return($ords);
	}
	
	
	function count_new_hit(&$item) // If needed to modify params then clone them !! ??
	{
		$mainframe =& JFactory::getApplication();
		$params = $mainframe->getParams('com_flexicontent');
		if (!$params->get('hits_count_unique', 0)) return 1; // Counting unique hits not enabled
		
		$db =& JFactory::getDBO();
		$visitorip = $_SERVER['REMOTE_ADDR'];  // Visitor IP
		$current_secs = time();  // Current time as seconds since Unix epoch
		if ($item->id==0) {
			$jAp=& JFactory::getApplication();
			$jAp->enqueueMessage(nl2br("Invalid item id or item id is not set in http request"),'error');
			return 1; // Invalid item id ?? (do not try to decrement hits in content table)
		}
		
		
		// CHECK RULE 1: Skip if visitor is from the specified ips
		$hits_skip_ips = $params->get('hits_skip_ips', 1);   // Skip ips enabled
		$hits_ips_list = $params->get('hits_ips_list', '127.0.0.1');  // List of ips, by default localhost
		if($hits_skip_ips)
		{
			// consider as blocked ip , if remote address is not set (is this correct behavior?)
			if( !isset($_SERVER['REMOTE_ADDR']) ) return 0;
			
			$remoteaddr = $_SERVER['REMOTE_ADDR'];
			$ips_array = explode(",", $hits_ips_list);
			foreach($ips_array as $blockedip)
			{
				if (preg_match('/'.trim($blockedip).'/i', $remoteaddr)) return 0;  // found blocked ip, do not count new hit
			}
		}
		
		
		// CHECK RULE 2: Skip if visitor is a bot
		$hits_skip_bots = $params->get('hits_skip_bots', 1);  // Skip bots enabled
		$hits_bots_list = $params->get('hits_bots_list', 'bot,spider,crawler,search,libwww,archive,slurp,teoma');   // List of bots
		if($hits_skip_bots)
		{
			// consider as bot , if user agent name is not set (is this correct behavior?)
			if( !isset($_SERVER['HTTP_USER_AGENT']) ) return 0;

			$useragent = $_SERVER['HTTP_USER_AGENT'];
			$bots_array = explode(",", $hits_bots_list);
			foreach($bots_array as $botname)
			{
				if (preg_match('/'.trim($botname).'/i', $useragent)) return 0;  // found bot, do not count new hit
			}
		}
		
		// CHECK RULE 3: item hit does not exist in current session
		$hit_method = 'use_session';  // 'use_db_table', 'use_session'
		if ($hit_method == 'use_session') {
			$session 	=& JFactory::getSession();
			$hit_accounted = false;
			$hit_arr = array();
			if ($session->has('hit', 'flexicontent')) {
				$hit_arr 	= $session->get('hit', array(), 'flexicontent');
				$hit_accounted = isset($hit_arr[$item->id]);
			}
			if (!$hit_accounted) {
				//add hit to session hit array
				$hit_arr[$item->id] = $timestamp = time();  // Current time as seconds since Unix epoc;
				$session->set('hit', $hit_arr, 'flexicontent');
				return 1;
			}
			
		} else {  // ALTERNATIVE METHOD (above is better, this will be removed?), by using db table to account hits, instead of user session
			
			// CHECK RULE 3: minimum time to consider as unique visitor aka count hit
			$secs_between_unique_hit = 60 * $params->get('hits_mins_to_unique', 10);  // Seconds between counting unique hits from an IP
			
			// Try to find matching records for visitor's IP, that is within time limit of unique hit
			$query = "SELECT COUNT(*) FROM #__flexicontent_hits_log WHERE ip=".$db->quote($visitorip)." AND (timestamp + ".$db->quote($secs_between_unique_hit).") > ".$db->quote($current_secs). " AND item_id=". $item->id;
			$db->setQuery($query);
			$result = $db->query();
			if ($db->getErrorNum()) {
				$select_error_msg = $db->getErrorMsg();
				$query_create = "CREATE TABLE #__flexicontent_hits_log (item_id INT PRIMARY KEY, timestamp INT NOT NULL, ip VARCHAR(16) NOT NULL DEFAULT '0.0.0.0')";
				$db->setQuery($query_create);
				$result = $db->query();
				if ($db->getErrorNum()) {
					$jAp=& JFactory::getApplication();
					$jAp->enqueueMessage(nl2br($query."\n".$select_error_msg."\n"),'error');
				}
				return 1; // on select error e.g. table created, count a new hit
			}
			$count = $db->loadResult();
			
			// Log the visit into the hits logging db table
			if(empty($count))
			{
				$query = "REPLACE INTO #__flexicontent_hits_log (item_id, timestamp, ip) VALUES (".$db->quote($item->id).", ".$db->quote($current_secs).", ".$db->quote($visitorip).")";
				$db->setQuery($query);
				$result = $db->query();
				if ($db->getErrorNum()) {
					$jAp=& JFactory::getApplication();
					$jAp->enqueueMessage(nl2br($query."\n".$db->getErrorMsg()."\n"),'error');
				}
				return 1;  // last visit not found or is beyond time limit, count a new hit
			}
		}
		
		// Last visit within time limit, do not count new hit
		return 0;
	}
	
	
	/*
	 * Method to confirm if a given string is a valid MySQL date
	 * param  string			$date
	 * return boolean			true if valid date, false otherwise
	 */
	function isSqlValidDate($date)
	{
		$db = & JFactory::getDBO();
		$q = "SELECT day(".$db->Quote($date).")";
		$db->setQuery($q);
		$num = $db->loadResult();
		$valid = $num > 0;
		return $valid;
	}
	
	/*
	 * Converts a string (containing a csv file) into a array of records ( [row][col] )and returns it
	 * @author: Klemen Nagode (in http://stackoverflow.com/)
	 */
	function csvstring_to_array($string, $separatorChar = ',', $enclosureChar = '"', $newlineChar = "\n") {
	
		$array = array();   // [row][cols]
		$size = strlen($string);
		$columnIndex = 0;
		$rowIndex = 0;
		$fieldValue="";
		$isEnclosured = false;
	
		for($i=0; $i<$size;$i++)
		{
			$char = $string{$i};
			$addChar = "";
	
			if($isEnclosured) {
				if($char==$enclosureChar) {
					if($i+1<$size && $string{$i+1}==$enclosureChar) {
						// escaped char
						$addChar=$char;
						$i++; // dont check next char
					} else {
						$isEnclosured = false;
					}
				} else {
					$addChar=$char;
				}
			}
			else
			{
				if($char==$enclosureChar) {
					$isEnclosured = true;
				} else {
					
					if($char==$separatorChar) {
		
						$array[$rowIndex][$columnIndex] = $fieldValue;
						$fieldValue="";
		
						$columnIndex++;
					} elseif($char==$newlineChar) {
						echo $char;
						$array[$rowIndex][$columnIndex] = $fieldValue;
						$fieldValue="";
						$columnIndex=0;
						$rowIndex++;
					} else {
						$addChar=$char;
					}
				}
			}
			if($addChar!="") {
				$fieldValue.=$addChar;
			}
		}
		
		if($fieldValue) { // save last field
			$array[$rowIndex][$columnIndex] = $fieldValue;
		}
		return $array;
	}
	
}


if(!function_exists('diff_version')) {
	function diff_version(&$array1, &$array2) {
		$difference = $array1;
		foreach($array1 as $key1 => $value1) {
			foreach($array2 as $key2=> $value2) {
				if( ($value1["id"]==$value2["id"]) && ($value1["version"]==$value2["version"]) ) {
					unset($difference[$key1]);
				}
			}
		}
		return $difference;
	}
}

?>
