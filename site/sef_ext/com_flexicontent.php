<?php
/**
 * @version 1.5 stable $Id: com_flexicontent.php 1500 2012-09-29 07:21:46Z ggppdk $
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
defined( '_JEXEC' ) or die( 'Direct Access to this location is not allowed.' );

// ------------------  standard plugin initialize function - don't change ---------------------------
global $sh_LANG;
$sefConfig 		= & shRouter::shGetConfig();
$shLangName = '';
$shLangIso = '';
$title = array();
$shItemidString = '';
$dosef = shInitializePlugin( $lang, $shLangName, $shLangIso, $option);
if ($dosef == false) return;
// ------------------  standard plugin initialize function - don't change ---------------------------

// ------------------  load language file - adjust as needed ----------------------------------------
$shLangIso = shLoadPluginLanguage ( 'com_flexicontent', $shLangIso, '_SH404SEF_FLEXICONTENT_ADD', JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'sef_ext'.DS.'lang'.DS );
// ------------------  load language file - adjust as needed ----------------------------------------


require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
global $globalcats, $globalnopath, $globalnoroute;

// Get some needed HTTP Request variable
$_layout	= JRequest::getVar('layout', null);
$_typeid	= JRequest::getInt('typeid', null);
$_fcu	= JRequest::getVar('fcu', null);
$_fcp	= JRequest::getInt('fcp', null);

// Insure that the global vars are array
if (!is_array($globalnopath))	$globalnopath	= array();
if (!is_array($globalnoroute))	$globalnoroute	= array();



// do something about that Itemid thing
if (!preg_match( '/Itemid=[0-9]+/i', $string)) { // if no Itemid in non-sef URL
  //global $Itemid;
  if ($sefConfig->shInsertGlobalItemidIfNone && !empty($shCurrentItemid)) {
    $string .= '&Itemid='.$shCurrentItemid;  // append current Itemid 
    $Itemid = $shCurrentItemid;
    shAddToGETVarsList('Itemid', $Itemid); // V 1.2.4.m
  }  
  if ($sefConfig->shInsertTitleIfNoItemid)
  	$title[] = $sefConfig->shDefaultMenuItemName ? 
      $sefConfig->shDefaultMenuItemName : getMenuTitle($option, null, $shCurrentItemid );
  $shItemidString = $sefConfig->shAlwaysInsertItemid ? 
    _COM_SEF_SH_ALWAYS_INSERT_ITEMID_PREFIX.$sefConfig->replacement.$shCurrentItemid
    : '';
} else {  // if Itemid in non-sef URL
  $shItemidString = $sefConfig->shAlwaysInsertItemid ? 
    _COM_SEF_SH_ALWAYS_INSERT_ITEMID_PREFIX.$sefConfig->replacement.$Itemid
    : '';
}

$view 		= isset ($view) ? @$view : null;
$Itemid		= isset ($Itemid) ? @$Itemid : null;
$task 		= isset($task) ? @$task : null;
$format		= isset($format) ? @$format : null;
$return 	= isset ($return) ? @$return : null;

// remove common URL from GET vars list, so that they don't show up as query string in the URL
shRemoveFromGETVarsList('option');
shRemoveFromGETVarsList('lang');
if (!empty($Itemid))      shRemoveFromGETVarsList('Itemid');
if (!empty($limit))       shRemoveFromGETVarsList('limit');
if (isset($limitstart))   shRemoveFromGETVarsList('limitstart');

// Added for the preview feature in FLEXIcontent 1.5.5 
if (!empty($_fcu)) {
	shRemoveFromGETVarsList('fcu');
	$dosef = false;
} else if (!empty($_fcp)) {
	shRemoveFromGETVarsList('fcp');
	$dosef = false;
}

if (FLEXI_J16GE) {
	$item_segs = array(5=>0, 1=>-1, 2=>1, 3=>-2, 4=>2, 0=>999);
	$cats_in_itemlnk = (isset($item_segs[$sefConfig->includeContentCat])) ? $item_segs[$sefConfig->includeContentCat] : 999;

	$cat_segs = array(1=>-1, 2=>1, 3=>-2, 4=>2, 0=>999);
	$cats_in_catlnk = (isset($cat_segs[$sefConfig->includeContentCatCategories])) ? $cat_segs[$sefConfig->includeContentCatCategories] : 999;
} else {
	$cats_in_itemlnk = 999;
	$cats_in_catlnk = 999;
}

// Some FLEXIcontent views may only set task variable ... in this case set task variable as view
if ( !$view && $task == 'search' ) {
	$view = 'search';
}

// Do not convert to SEF urls, the urls for vote and favourites
if($format == 'raw') {
	if ($task == 'ajaxvote' || $task == 'ajaxfav') return;
}

switch ($view) {
	
	case 'items' : 	case 'item' :

		// Do not convert to SEF urls, the urls for item form
		if ($_layout == 'form' || $task == 'edit' || $task == 'add') return;
		
		if (!empty($id)) {
		
			if (!$task) {
			
				$query	= 'SELECT i.id, i.title, i.alias, i.catid, ie.type_id, c.title AS cattitle, ty.alias AS typealias'
						. ' FROM #__content AS i'
						. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
						. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
						. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
						. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
						. ' WHERE i.id = ' . ( int ) $id;
				$database->setQuery ( $query );

				// Do not translate the items url (Joomfish extended Database class and overrides the method)
				if (!FLEXI_FISH) {
					$row = $database->loadObject ( );
				} else {
					$row = $database->loadObject ( null, false );
				}
				
				if ($database->getErrorNum ()) {
					die ( $database->stderr () );
				} elseif ($row) {
					
					if ($row->title) {
						// force using the default category if none is specified in the query string
						$catid = @$cid ? $cid : $row->catid;
						
						if (@$globalcats[$catid]->ancestorsarray) {
							$ancestors = $globalcats[$catid]->ancestorsarray;
							$cat_titles = array();
							foreach ($ancestors as $ancestor) {
								if (!in_array($ancestor, $globalnoroute)) {
									if (shTranslateURL ( $option, $shLangName ) && FLEXI_FISH) {
										// Translating title and Joomfish is installed
										$query	= 'SELECT id, title, alias FROM #__categories WHERE id = ' . $ancestor;
										$database->setQuery ( $query );
										$row_cat = $database->loadObject ();
										$cat_titles[] = ($sefConfig->useCatAlias ? $row_cat->alias : $row_cat->title) . '/';
									} else {
										list($_cat_id, $_cat_alias) = explode( ":", $globalcats[$ancestor]->slug );
										// Not translating title or Joomfish not installed
										$cat_titles[] = ($sefConfig->useCatAlias ? $_cat_alias : $globalcats[$ancestor]->title) . '/';
									}
								}
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
							if (shTranslateUrl($option, $shLangName) || !FLEXI_FISH) // V 1.2.4.m
								$contentElement = $database->loadObject( );
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
			
				// Remove the vars from the url
				if (!empty($id))
					shRemoveFromGETVarsList ( 'id' );
				if (!empty($cid))
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
			shRemoveFromGETVarsList ( 'typeid' );
		}
		
		shRemoveFromGETVarsList ( 'view' );
	break;
	
	case 'category' :

		if (!empty($cid) && empty($id)) {

			if (@$globalcats[$cid]->ancestorsarray) {
				$ancestors = $globalcats[$cid]->ancestorsarray;
				$cat_titles = array();
				foreach ($ancestors as $ancestor) {
					if (!in_array($ancestor, $globalnoroute)) {
						if (shTranslateURL ( $option, $shLangName ) && FLEXI_FISH) {
							$query	= 'SELECT id, title, alias FROM #__categories WHERE id = ' . $ancestor;
							$database->setQuery ( $query );
							$row = $database->loadObject ();
							$cat_titles[] = ($sefConfig->useCatAlias ? $row->alias : $row->title) . '/';
						} else {
							list($_cat_id, $_cat_alias) = explode( ":", $globalcats[$ancestor]->slug );
							// Not translating title or Joomfish not installed
							$cat_titles[] = ($sefConfig->useCatAlias ? $_cat_alias : $globalcats[$ancestor]->title) . '/';
						}
					}
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
		if (!empty($cid)) {
			shRemoveFromGETVarsList ( 'cid' );
			shRemoveFromGETVarsList ( 'view' );  // only unset view if category ID was set
		}
		
		
		// HANDLE 'tags' layout of category view
		if (! empty ( $tagid )) {
			$query 	= 'SELECT id, name FROM #__flexicontent_tags'
					.' WHERE id = ' . ( int ) $tagid;
			$database->setQuery ( $query );
			
			if (shTranslateURL ( $option, $shLangName ) || !FLEXI_FISH) {
				$row = $database->loadObject ();
			} else {
				$row = $database->loadObject ( null, false );
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
			
			if (shTranslateURL ( $option, $shLangName ) || !FLEXI_FISH) {
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
	break;
	
	case 'tags' :
		if (! empty ( $id )) {
			$query 	= 'SELECT id, name FROM #__flexicontent_tags'
					.' WHERE id = ' . ( int ) $id;
			$database->setQuery ( $query );
			
			if (shTranslateURL ( $option, $shLangName ) || !FLEXI_FISH) {
				$row = $database->loadObject ();
			} else {
				$row = $database->loadObject ( null, false );
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
		if (!empty($id))
			shRemoveFromGETVarsList ( 'id' );
		shRemoveFromGETVarsList ( 'view' );
	break;
	
	case 'search' :
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
	break;

	case 'fileselement' :
		$dosef = false;
	break;

	case 'itemelement' :
		$dosef = false;
	break;

	case 'search' :
		$dosef = false;
	break;
	
	default :
		$title [] = '/';
	break;
}

if ($task == 'download') {
	$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_DOWNLOAD'];
}

if ($task == 'weblink') {
	$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_WEBLINK'];
}

// Remove the remaining common vars from the url
if (!empty($option))
	shRemoveFromGETVarsList ( 'option' );

if (!empty($task))
	shRemoveFromGETVarsList ( 'task' );

if (!empty($lang))
	shRemoveFromGETVarsList ( 'lang' );

// This is done per view, to make sure that non-handled views will not unset the view variable, and thus breaking the URL !!
//if (!empty($view))
//	shRemoveFromGETVarsList ( 'view' );

if (!empty($Itemid))
	shRemoveFromGETVarsList ( 'Itemid' );

if (!empty($limit))
	shRemoveFromGETVarsList ( 'limit' );

if (isset($limitstart))
	shRemoveFromGETVarsList ( 'limitstart' ); // limitstart can be zero
	
//if (!empty($return))
//	shRemoveFromGETVarsList ( 'return' );

// ------------------  standard plugin finalize function - don't change ---------------------------
if ($dosef){
  $string = shFinalizePlugin( $string, $title, $shAppendString, $shItemidString,
      (isset($limit) ? @$limit : null), (isset($limitstart) ? @$limitstart : null),
      (isset($shLangName) ? @$shLangName : null));
}
// ------------------  standard plugin finalize function - don't change ---------------------------

