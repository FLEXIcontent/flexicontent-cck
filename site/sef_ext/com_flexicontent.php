<?php
/**
 * FLEXIcontent plugin for sh404SEF 
 *
 */
defined('_JEXEC') or die('Direct Access to this location is not allowed.');

// ------------------ standard plugin initialize function - don't change ---------------------------
global $sh_LANG;
$sefConfig = Sh404sefFactory::getConfig();
$shLangName = '';
$shLangIso = '';
$title = array();
$shItemidString = '';
$dosef = shInitializePlugin($lang, $shLangName, $shLangIso, $option);
if ($dosef == false)
{
	return;
}
// ------------------ standard plugin initialize function - don't change ---------------------------

// ------------------ load language file - adjust as needed ----------------------------------------
$shLangIso = shLoadPluginLanguage('com_flexicontent', $shLangIso, '_SH404SEF_FLEXICONTENT_ADD', JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'sef_ext'.DS.'lang'.DS );
// ------------------ load language file - adjust as needed ----------------------------------------

// get DB
$database = ShlDbHelper::getDb();

// CHECK for if givent URL is home page, so we must return an empty string
$shHomePageFlag = false;
$shHomePageFlag = ! $shHomePageFlag ? shIsHomepage($string) : $shHomePageFlag;

if ($shHomePageFlag)
{ // this is homepage (optionally multipaged)
	$title[] = '/';
	$string = sef_404::sefGetLocation(
		$string, $title, null,
		(isset($limit) ? $limit : null),
		(isset($limitstart) ? $limitstart : null),
		(isset($shLangName) ? $shLangName : null),
		(isset($showall) ? $showall : null)    // currently ignored for non com_content components ?
	);
	return;
}


static $FC_sh404sef_init = null;
static $IS_FISH_SITE = null;
if (!$FC_sh404sef_init)
{
	$FC_sh404sef_init = true;
	
	// Include FC constants file
	require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
	$IS_FISH_SITE = FLEXI_FISH && JFactory::getApplication()->isSite();

	// Make sure that the global FC vars are arrays
	global $globalcats, $globalnopath, $globalnoroute;
	if (!is_array($globalcats))    $globalcats    = array();
	if (!is_array($globalnopath))  $globalnopath  = array();
	if (!is_array($globalnoroute)) $globalnoroute = array();
}

// Avoid PHP not set notices, by setting variables to null, also null will not break isset behaviour
$Itemid   = isset($Itemid)  ? $Itemid : null;
$view     = isset($view)    ? $view   : null;
$layout   = isset($layout)  ? $layout : null;
$task     = isset($task)    ? $task   : null;
$format		= isset($format)  ? $format : null;
$return 	= isset($return)  ? $return : null;

// start by inserting the menu element title (just an idea, this is not required at all)
//$shSampleName = shGetComponentPrefix($option);
//$shSampleName = empty($shSampleName) ? getMenuTitle($option, $task, $Itemid, null, $shLangName) : $shSampleName;
//$shSampleName = (empty($shSampleName) || $shSampleName == '/') ? 'FC_CONTENT' : $shSampleName;


// Itemid NOT found inside the non-sef URL
$Itemid_exists_in_URL = ! preg_match('/Itemid=[0-9]+/iu', $string);
if ( !$Itemid_exists_in_URL )
{
  // V 1.2.4.t moved back here
	if ($sefConfig->shInsertGlobalItemidIfNone && ! empty($shCurrentItemid))
	{
		$string .= '&Itemid=' . $shCurrentItemid; // append current Itemid
		$Itemid = $shCurrentItemid;
		shAddToGETVarsList('Itemid', $Itemid); // V 1.2.4.m
	}

	if ($sefConfig->shInsertTitleIfNoItemid)
	{
		$title[] = $sefConfig->shDefaultMenuItemName ?
			$sefConfig->shDefaultMenuItemName :
			getMenuTitle($option, (isset($view) ? $view : null), $shCurrentItemid, null, $shLangName);
	}
	$shItemidString = '';
	if ($sefConfig->shAlwaysInsertItemid && (! empty($Itemid) || ! empty($shCurrentItemid)))
	{
		$shItemidString = JText::_('COM_SH404SEF_ALWAYS_INSERT_ITEMID_PREFIX')
			. $sefConfig->replacement . (empty($Itemid) ? $shCurrentItemid : $Itemid);
	}
}

// Itemid found inside the non-sef URL
else
{
	$shItemidString = $sefConfig->shAlwaysInsertItemid ?
		JText::_('COM_SH404SEF_ALWAYS_INSERT_ITEMID_PREFIX') . $sefConfig->replacement . $Itemid : '';
	if ($sefConfig->shAlwaysInsertMenuTitle)
	{
		// global $Itemid; V 1.2.4.g we want the string option, not current page !
		if ($sefConfig->shDefaultMenuItemName)
			$title[] = $sefConfig->shDefaultMenuItemName; // V 1.2.4.q added
				                                              // force language
		elseif ($menuTitle = getMenuTitle($option, (isset($view) ? $view : null), $Itemid, '', $shLangName))
		{
			if ($menuTitle != '/')
				$title[] = $menuTitle;
		}
	}
}
// V 1.2.4.m
// Remove common URL variables from GET vars list, so that they don't show up as query string in the URL
shRemoveFromGETVarsList('option');
shRemoveFromGETVarsList('lang');
if (! empty($Itemid))   shRemoveFromGETVarsList('Itemid');
if (! empty($limit))    shRemoveFromGETVarsList('limit');

// Variables 'limitstart', 'start', 'showall' can be zero or empty string, so use isset
if (isset($limitstart))  shRemoveFromGETVarsList('limitstart');
if (isset($start))       shRemoveFromGETVarsList('start');
//if (isset($showall))     shRemoveFromGETVarsList('showall');  // Bug in SH404SEF, DO NOT unset this
if (empty($showall))     shRemoveFromGETVarsList('showall');  // only unset an zero or zero-length variables

// Preview feature (login via URL (normally disabled for security reasons)), do not add such URLS to SH404SEF URLs !!
if (! empty($fcu) || ! empty($fcp)) return


// Get Depth of parent category segmenets to use for item view
$item_segs = array(5=>0, 1=>-1, 2=>1, 3=>-2, 4=>2, 0=>999);
$cats_in_itemlnk = (isset($item_segs[$sefConfig->includeContentCat])) ? $item_segs[$sefConfig->includeContentCat] : 999;

// Get Depth of parent category segmenets to use for category view
$cat_segs = array(1=>-1, 2=>1, 3=>-2, 4=>2, 0=>999);
$cats_in_catlnk = (isset($cat_segs[$sefConfig->includeContentCatCategories])) ? $cat_segs[$sefConfig->includeContentCatCategories] : 999;


// Some FLEXIcontent views may only set task variable ... in this case set task variable as view
if ( !$view && $task == 'search' ) {
	$view = 'search';
	shRemoveFromGETVarsList ( 'task' );
}

// Do not convert to SEF urls, the urls for vote and favourites
if($format == 'raw') {
	if ($task == 'ajaxvote' || $task == 'ajaxfav') return;
}
// Do not convert autocomplete URLs
if ($task == 'txtautocomplete') return;



switch ($view)
{
	case 'item' :
	
		// Do not convert to SEF urls, the urls for item form
		if ($layout == 'form' || $task == 'edit' || $task == 'add') return;
		
		if (!empty($id))   // Existing item, empty ID means new item form or invalid URL
		{
			if (!$task)
			{
				$query	= 'SELECT i.id, i.title, i.alias, i.catid, ie.type_id, c.title AS cattitle, ty.alias AS typealias'
						. ' FROM #__content AS i'
						. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
						. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
						. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
						. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
						. ' WHERE i.id = ' . ( int ) $id;
				$database->setQuery ( $query );
				
				// Do not translate the items url (Falang extended Database class and overrides the method)
				$row = !$IS_FISH_SITE  ?  $database->loadObject()  :  $database->loadObject(null, false);
				
				if ($database->getErrorNum ())  die ( $database->stderr () );
				
				if ($row)
				{	
					if ($row->title)
					{
						// force using the default category if none is specified in the query string
						$catid = @$cid ? $cid : $row->catid;
						
						if (@$globalcats[$catid]->ancestorsarray) {
							$ancestors = $globalcats[$catid]->ancestorsarray;
							$cat_titles = array();
							foreach ($ancestors as $ancestor)
							{
								if (in_array($ancestor, $globalnoroute)) continue;
								
								if (shTranslateURL ( $option, $shLangName ) && FLEXI_FISH) {
									// Translating title and Falang is installed
									$query	= 'SELECT id, title, alias FROM #__categories WHERE id = ' . $ancestor;
									$database->setQuery ( $query );
									$row_cat = $database->loadObject();
									if (!$row_cat) { $cat_titles[] = $ancestor; continue; }
									$cat_titles[] = ($sefConfig->useCatAlias ? $row_cat->alias : $row_cat->title) . '/';
								}
								else if ( isset($globalcats[$ancestor]) ) {
									list($_cat_id, $_cat_alias) = explode( ":", $globalcats[$ancestor]->slug );
									// Not translating title or Falang not installed
									$cat_titles[] = ($sefConfig->useCatAlias ? $_cat_alias : $globalcats[$ancestor]->title) . '/';
								}
								else $cat_titles[] = $ancestor . '/';
							}
							
							$first_url_cat = ($cats_in_itemlnk >= 0) ? count($cat_titles) - $cats_in_itemlnk : 0;
							$first_url_cat = ($first_url_cat < 0) ? 0 : $first_url_cat;
							
							$last_url_cat  = ($cats_in_itemlnk >= 0) ? count($cat_titles)-1 : -($cats_in_itemlnk + 1);
							$last_url_cat  = ($last_url_cat > count($cat_titles)-1) ? count($cat_titles)-1 : $last_url_cat;
							/*echo count($cat_titles) . "<br><pre>"; print_r($cat_titles);
							echo "cats_in_itemlnk: ".$cats_in_itemlnk . "<br>";
							echo "first_url_cat: ". $first_url_cat . "<br>";
							echo "last_url_cat: ". $last_url_cat . "<br>";*/
							for($ccnt = $first_url_cat; $ccnt <= $last_url_cat; $ccnt++ ) $title[] = $cat_titles[$ccnt];
							/*print_r($title);
							die($row->title);
							exit;*/
						}
						// Add item title as URL segment
						$row_title  = $sefConfig->UseAlias ? $row->alias : $row->title;
						$row_title .= $sefConfig->ContentTitleInsertArticleId ? "-".$row->id : "";
						$title [] = $row_title;
						
						// V 1.2.4.j 2007/04/11 : numerical ID, on some categories only
						if ($sefConfig->shInsertNumericalId && isset($sefConfig->shInsertNumericalIdCatList) && !empty($id) && ($view == 'items') && !in_array($row->type_id, $globalnopath)) {
							$q = 'SELECT id, catid, created FROM #__content WHERE id = '.$database->Quote( $id);
							$database->setQuery($q);
							if (shTranslateUrl($option, $shLangName) || !$IS_FISH_SITE) // V 1.2.4.m
								$contentElement = $database->loadObject();
							else 
								$contentElement = $database->loadObject(null, false);
							if ($contentElement) {
								$foundCat = array_search($contentElement->catid, $sefConfig->shInsertNumericalIdCatList);
								if (($foundCat !== null && $foundCat !== false) || ($sefConfig->shInsertNumericalIdCatList[0] == ''))  { // test both in case PHP < 4.2.0
									$shTemp = explode(' ', $contentElement->created);
									$title[] = str_replace('-','', $shTemp[0]).$contentElement->id;
								}
							}
						}
						shMustCreatePageId( 'set', true);
					}
				}
			
				// Remove the item-id and category-id vars from the url
				shRemoveFromGETVarsList ( 'id' );
				shRemoveFromGETVarsList ( 'cid' );
				
			} elseif ($task == 'edit') {
				$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_EDIT'];
			}
			
		} else {
			return;   // SKIP !!! new item URLs, TODO this case
			//echo $Itemid."<br>";
			//echo $shCurrentItemid."<br>";
			//$shCurrentItemid = $Itemid;
			//shAddToGETVarsList('Itemid', $Itemid); // V 1.2.4.m
			//$dosef = false;
			
			$shName = shGetComponentPrefix($option);
			$shName = empty($shName) ? getMenuTitle($option, (isset($view) ? @$view : null), $Itemid ) : $shName;
			if (!empty($shName) && $shName != '/')
				echo $title[] = $shName;  // V x
			else
				echo $title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_ADD'];
			
			// Remove the vars from the url
			shRemoveFromGETVarsList ( 'layout' );
			//shRemoveFromGETVarsList ( 'typeid' );  // TODO more this is needed
		}
		
		shRemoveFromGETVarsList ( 'view' );
		
		// Remove 'ilayout' if empty
		if (empty($ilayout)) shRemoveFromGETVarsList ( 'ilayout' );
	break;
	
	case 'category' :
		
		if (!empty($cid) && empty($id)) {
			
			if (@$globalcats[$cid]->ancestorsarray) {
				$ancestors = $globalcats[$cid]->ancestorsarray;
				$cat_titles = array();
				foreach ($ancestors as $ancestor)
				{
					if (in_array($ancestor, $globalnoroute)) continue;
					
					if (shTranslateURL ( $option, $shLangName ) && FLEXI_FISH) {
						$query	= 'SELECT id, title, alias FROM #__categories WHERE id = ' . $ancestor;
						$database->setQuery ( $query );
						$row_cat = $database->loadObject();
						if (!$row_cat) { $cat_titles[] = $ancestor; continue; }
						$cat_titles[] = ($sefConfig->useCatAlias ? $row_cat->alias : $row_cat->title) . '/';
					}
					else if ( isset($globalcats[$ancestor]) ) {
						list($_cat_id, $_cat_alias) = explode( ":", $globalcats[$ancestor]->slug );
						// Not translating title or Falang not installed
						$cat_titles[] = ($sefConfig->useCatAlias ? $_cat_alias : $globalcats[$ancestor]->title) . '/';
					}
					else $cat_titles[] = $ancestor . '/';
				}
				$curr_cat_title = count($cat_titles) ? array_pop($cat_titles) : null;
				
				$first_url_cat = ($cats_in_catlnk >= 0) ? count($cat_titles) - $cats_in_catlnk : 0;
				$first_url_cat = ($first_url_cat < 0) ? 0 : $first_url_cat;
				
				$last_url_cat  = ($cats_in_catlnk >= 0) ? count($cat_titles)-1 : -($cats_in_catlnk + 1);
				$last_url_cat  = ($last_url_cat > count($cat_titles)-1) ? count($cat_titles)-1 : $last_url_cat;
				
				for($ccnt = $first_url_cat; $ccnt <= $last_url_cat; $ccnt++ ) $title[] = $cat_titles[$ccnt];
				if ($curr_cat_title) $title[] = $curr_cat_title;
			} else {
				$title [] = '/';
			}
			shMustCreatePageId( 'set', true);
		}
		
		// Remove the vars from the url
		shRemoveFromGETVarsList ( 'cid' );
		if (!empty($cid)) {
			shRemoveFromGETVarsList ( 'view' );  // only unset view if category ID was set
		}
		
		// HANDLE 'tags' layout of category view
		if (! empty ( $tagid )) {
			$query 	= 'SELECT id, name FROM #__flexicontent_tags'
					.' WHERE id = ' . ( int ) $tagid;
			$database->setQuery ( $query );
			
			if (shTranslateURL ( $option, $shLangName ) || !$IS_FISH_SITE) {
				$row = $database->loadObject();
			} else {
				$row = $database->loadObject(null, false);
			}
			
			if ($database->getErrorNum ()) {
				die ( $database->stderr () );
			} elseif ($row) {
				if ($row->name) {
					$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_TAGGED'] . '/';
					$title [] = $row->name;
				}
			}
			
			shRemoveFromGETVarsList ( 'tagid' );
			shRemoveFromGETVarsList ( 'layout' );
			shRemoveFromGETVarsList ( 'view' );
		}
		
		
		// HANDLE 'author' layout of category view
		if (! empty ( $authorid )) {
			$query 	= 'SELECT id, name FROM #__users'
					.' WHERE id = ' . ( int ) $authorid;
			$database->setQuery ( $query );
			
			if (shTranslateURL ( $option, $shLangName ) || !$IS_FISH_SITE) {
				$row = $database->loadObject ();
			} else {
				$row = $database->loadObject ( null, false );
			}
			
			if ($database->getErrorNum ()) {
				die ( $database->stderr () );
			} elseif ($row) {
				if ($row->name) {
					$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_AUTHORED'] . '/';
					$title [] = $row->name;
				}
			}
			
			shRemoveFromGETVarsList ( 'authorid' );
			shRemoveFromGETVarsList ( 'layout' );
			shRemoveFromGETVarsList ( 'view' );
		}
		
		
		// HANDLE 'myitems' layout of category view
		if ( !empty ( $layout ) && $layout=='myitems' )
		{
			$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_MYITEMS'];
			shRemoveFromGETVarsList ( 'layout' );
			shRemoveFromGETVarsList ( 'view' );
		}
		
		
		// HANDLE 'favs' layout of category view
		if ( !empty ( $layout ) && $layout=='favs' )
		{
			$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_FAVOURED'];
			shRemoveFromGETVarsList ( 'layout' );
			shRemoveFromGETVarsList ( 'view' );
		}
		
		// Remove 'clayout' if empty
		if (empty($clayout)) shRemoveFromGETVarsList ( 'clayout' );
	break;
	
	case 'tags' :
		if (! empty ( $id )) {
			$query 	= 'SELECT id, name FROM #__flexicontent_tags'
					.' WHERE id = ' . ( int ) $id;
			$database->setQuery ( $query );
			
			if (shTranslateURL ( $option, $shLangName ) || !$IS_FISH_SITE) {
				$row = $database->loadObject();
			} else {
				$row = $database->loadObject(null,false);
			}
			
			if ($database->getErrorNum ()) {
				die ( $database->stderr () );
			} elseif ($row) {
				if ($row->name) {
					$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_TAGS'] . '/';
					$title [] = $row->name;
				}
			}
		} else {
			$title [] = '/';
		}
		
		// Remove the vars from the url
		shRemoveFromGETVarsList ( 'id' );
		shRemoveFromGETVarsList ( 'view' );
	break;
	
	case 'search' :
		//$title [] = $shSampleName .'/';
		$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_SEARCH'] . '/';
		shRemoveFromGETVarsList ( 'view' );
	break;
	
	case 'favourites' :
		$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_FAVOURITES'] .  '/';
		shRemoveFromGETVarsList ( 'view' );
	break;
	
	case 'flexicontent' :
		$title [] = $sh_LANG [$shLangIso] ['_SH404SEF_FLEXICONTENT'] . '/';
		shRemoveFromGETVarsList ( 'view' );
		
		$rootcat_title = false;
		if (!empty($rootcat))
		{
			if (shTranslateURL ( $option, $shLangName ) && FLEXI_FISH) {
				// Translating title and Falang is installed
				$query	= 'SELECT id, title, alias FROM #__categories WHERE id = ' . (int) $rootcat;
				$database->setQuery ( $query );
				$row_cat = $database->loadObject();
				if ($row_cat) $rootcat_title = ($sefConfig->useCatAlias ? $row_cat->alias : $row_cat->title) . '/';
			}
			else if ( isset($globalcats[$ancestor]) ) {
				list($_cat_id, $_cat_alias) = explode( ":", $globalcats[$ancestor]->slug );
				// Not translating title or Falang not installed
				$rootcat_title = ($sefConfig->useCatAlias ? $_cat_alias : $globalcats[$ancestor]->title) . '/';
			}
			
			$title [] = $rootcat_title ? $rootcat_title : $rootcat . '/';
			shRemoveFromGETVarsList ( 'rootcat' );
		}
	break;
	
	case 'fileselement' :
		$dosef = false;
	break;
	
	case 'itemelement' :
		$dosef = false;
	break;
	
	case 'search' :
		//$dosef = false;
	break;
	
	default :
		$title [] = '/';
	break;
}

if ($task == 'download') {
	$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_DOWNLOAD'];
	shRemoveFromGETVarsList ( 'task' );
}

if ($task == 'weblink') {
	$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_WEBLINK'];
	shRemoveFromGETVarsList ( 'task' );
}

// Some special handling for pagination
if ($view=='item') $limit = 1;   // For item view limit needs to be 1, so that page numbers are calculated correctly
if (!isset($limitstart) &&  isset($start))  $limitstart = $start;   // Use 'start' if 'limitstart' is not set

// The following are done per case, to make sure that non-handled case will not unset the variables, and thus breaking the URL !!
//if (!empty($task))   shRemoveFromGETVarsList ( 'task' );
//if (!empty($view))   shRemoveFromGETVarsList ( 'view' );

// Never unset return URL
//if (!empty($return))   shRemoveFromGETVarsList ( 'return' );


// ------------------ standard plugin finalize function - don't change ---------------------------
if ($dosef)
{
	$string = shFinalizePlugin(
		$string, $title, $shAppendString, $shItemidString,
		(isset($limit) ? $limit : null),
		(isset($limitstart) ? $limitstart : null),
		(isset($shLangName) ? $shLangName : null),
		(isset($showall) ? $showall : null)    // currently ignored for non com_content components ?
	);
}
// ------------------ standard plugin finalize function - don't change ---------------------------
