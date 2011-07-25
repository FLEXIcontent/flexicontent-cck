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

// no direct access
defined ( '_JEXEC' ) or die ( 'Restricted access' );

// ------------------  standard plugin initialize function - don't change ---------------------------

global $sh_LANG, $globalcats;

$sefConfig 		= & shRouter::shGetConfig();
$shLangName 	= '';
$shLangIso 		= '';
$title 			= array ( );
$shItemidString = '';
$dosef	 		= shInitializePlugin ( $lang, $shLangName, $shLangIso, $option );
$layout 		= JRequest::getVar('layout', null);
$typeid	 		= JRequest::getInt('typeid', null);

if ($dosef == false) {
	return;
}

// ------------------  /standard plugin initialize function - don't change ---------------------------


// ------------------  load language file - adjust as needed ----------------------------------------

$shLangIso = shLoadPluginLanguage ( 'com_flexicontent', $shLangIso, '_SH404SEF_FLEXICONTENT_ADD', JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'sef_ext'.DS.'lang'.DS );

// ------------------  /load language file - adjust as needed ----------------------------------------

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

switch ($view) {
	
	case 'items' :

		if (!empty($id)) {
		
			if (!$task) {
			
				$query	= 'SELECT i.id, i.title, c.title AS cattitle, ty.alias AS typealias'
						. ' FROM #__content AS i'
						. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
						. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
						. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
						. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
						. ' WHERE i.id = ' . ( int ) $id;
				$database->setQuery ( $query );

				// Do not translate the items url
				$row = $database->loadObject ( null, false );
/*
				if (shTranslateURL ( $option, $shLangName )) {
					$row = $database->loadObject ();
				} else {
					$row = $database->loadObject ( null, false );
				}
*/
				
				if ($database->getErrorNum ()) {
					die ( $database->stderr () );
				} elseif ($row) {
					
					if ($row->title && !empty($cid)) {
						if ($globalcats[$cid]->ancestorsarray) {
							$ancestors = $globalcats[$cid]->ancestorsarray;
							foreach ($ancestors as $ancestor) {
								if (shTranslateURL ( $option, $shLangName )) {
									$query	= 'SELECT id, title, alias FROM #__categories WHERE id = ' . $ancestor;
									$database->setQuery ( $query );
									$row_cat = $database->loadObject ();
									$title[] = $row_cat->title . '/';
								} else {
									$title[] = $globalcats[$ancestor]->title . '/';
								}
							}		
						}
						
						$title [] = $row->title;
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
			$shName = shGetComponentPrefix($option);
			$shName = empty($shName) ? getMenuTitle($option, (isset($view) ? @$view : null), $Itemid ) : $shName;
			if (!empty($shName) && $shName != '/') $title[] = $shName;  // V x
			//$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_ADD'];
	
			// Remove the vars from the url
			shRemoveFromGETVarsList ( 'layout' );
			shRemoveFromGETVarsList ( 'typeid' );
		}

	break;
	
	case 'category' :

		if (!empty($cid) && empty($id)) {

			if ($globalcats[$cid]->ancestorsarray) {
				$ancestors = $globalcats[$cid]->ancestorsarray;
				foreach ($ancestors as $ancestor) {
					if (shTranslateURL ( $option, $shLangName )) {
						$query	= 'SELECT id, title, alias FROM #__categories WHERE id = ' . $ancestor;
						$database->setQuery ( $query );
						$row = $database->loadObject ();
						$title[] = $row->title . '/';
					} else {
						$title[] = $globalcats[$ancestor]->title . '/';
					}
				}		
			} else {
				$title [] = '/';
			}
		}

		// Remove the vars from the url
		if (!empty($cid))
			shRemoveFromGETVarsList ( 'cid' );

	break;
	
	case 'tags' :
		if (! empty ( $id )) {
			$query 	= 'SELECT id, name FROM #__flexicontent_tags'
					.' WHERE id = ' . ( int ) $id;
			$database->setQuery ( $query );
			
			if (shTranslateURL ( $option, $shLangName )) {
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

	break;
	
	case 'favourites' :
		$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_FAVOURITES'] .  '/';
	break;

	case 'flexicontent' :
		$title [] = $sh_LANG [$shLangIso] ['_SH404SEF_FLEXICONTENT'] . '/';
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

if (!empty($view))
	shRemoveFromGETVarsList ( 'view' );

if (!empty($Itemid))
	shRemoveFromGETVarsList ( 'Itemid' );

if (!empty($limit))
	shRemoveFromGETVarsList ( 'limit' );

if (isset($limitstart))
	shRemoveFromGETVarsList ( 'limitstart' ); // limitstart can be zero
	
// ------------------  standard plugin finalize function - don't change ---------------------------  


if ($dosef) {
	$string = shFinalizePlugin ( $string, $title, $shAppendString, $shItemidString, (isset ( $limit ) ? @$limit : null), (isset ( $limitstart ) ? @$limitstart : null), (isset ( $shLangName ) ? @$shLangName : null) );
}

// ------------------  /standard plugin finalize function - don't change ---------------------------
?>