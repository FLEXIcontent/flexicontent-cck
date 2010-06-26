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


global $sh_LANG, $sefConfig, $globalcats;

//dump($globalcats,'$globalcats');

$shLangName 	= '';
$shLangIso 		= '';
$title 			= array ( );
$shItemidString = '';
$dosef	 		= shInitializePlugin ( $lang, $shLangName, $shLangIso, $option );
$layout 		= JRequest::getString('layout', '');
$typeid 		= JRequest::getString('typeid', '');
$task 			= JRequest::getString('task', '');

if ($dosef == false) {
	return;
}

// ------------------  /standard plugin initialize function - don't change ---------------------------


// ------------------  load language file - adjust as needed ----------------------------------------

$shLangIso = shLoadPluginLanguage ( 'com_flexicontent', $shLangIso, '_SH404SEF_FLEXICONTENT_ADD', JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'sef_ext'.DS.'lang'.DS );

// ------------------  /load language file - adjust as needed ----------------------------------------

$view 			= isset ( $view ) ? @$view : null;
$Itemid 		= isset ( $Itemid ) ? @$Itemid : null;


switch ( $view) {
	
	case 'items' :
		
if ($typeid || $task == 'download' || $task == 'weblink') {
	$dosef = false;
	return;
} else {
/*
	shRemoveFromGETVarsList ( 'id' );
	shRemoveFromGETVarsList ( 'cid' );
	shRemoveFromGETVarsList ( 'task' );
	shRemoveFromGETVarsList ( 'option' );
	shRemoveFromGETVarsList ( 'lang' );
	shRemoveFromGETVarsList ( 'view' );
	shRemoveFromGETVarsList ( 'layout' );
	shRemoveFromGETVarsList ( 'typeid' );
*/
}

		isset ( $task ) ? $task : $task = '';
		
		if (!empty($id) && !empty($cid) && empty($task) ) {
			
			$query	= 'SELECT i.id, i.title, c.title AS cattitle, ty.alias AS typealias'
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
					. ' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.itemid = i.id'
					. ' LEFT JOIN #__categories AS c ON c.id = rel.catid'
					. ' WHERE i.id = ' . ( int ) $id;
			$database->setQuery ( $query );
			
			if (shTranslateURL ( $option, $shLangName )) {
				$row = $database->loadObject ();
			} else {
				$row = $database->loadObject ( null, false );
			}
			
			if ($database->getErrorNum ()) {
				die ( $database->stderr () );
			} elseif ($row) {
				if ($row->title) {
					if ($globalcats[$cid]->ancestorsarray) {
						$ancestors = $globalcats[$cid]->ancestorsarray;
						foreach ($ancestors as $ancestor) {
							$title[] = $globalcats[$ancestor]->title . '/';
						}		
					}
					
					$title [] = $row->title;
				}
			}

		shRemoveFromGETVarsList ( 'id' );
		shRemoveFromGETVarsList ( 'cid' );
//		shRemoveFromGETVarsList ( 'task' );
		shRemoveFromGETVarsList ( 'option' );
		shRemoveFromGETVarsList ( 'lang' );
		shRemoveFromGETVarsList ( 'view' );
		shRemoveFromGETVarsList ( 'layout' );
//		shRemoveFromGETVarsList ( 'typeid' );

		}
		
	break;
	
	case 'category' :

		if (!empty($cid) && empty($id)) {

			if ($globalcats[$cid]->ancestorsarray) {
				$ancestors = $globalcats[$cid]->ancestorsarray;
				foreach ($ancestors as $ancestor) {
					$title[] = $globalcats[$ancestor]->title . '/';
				}		
			} else {
				$title [] = '/';
			}
		}

		shRemoveFromGETVarsList ( 'option' );
		shRemoveFromGETVarsList ( 'lang' );
		shRemoveFromGETVarsList ( 'view' );
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
					$title [] = $row->name;
				}
			}
		} else {
			$title [] = '/';
		}
		shRemoveFromGETVarsList ( 'id' );
	break;
	
	case 'favourites' :
		$title [] = $sh_LANG[$shLangIso]['_SH404SEF_FLEXICONTENT_FAVOURITES'] .  '/';
shRemoveFromGETVarsList ( 'option' );
shRemoveFromGETVarsList ( 'lang' );
shRemoveFromGETVarsList ( 'view' );
	break;

	
	case 'flexicontent' :
		$title [] = $sh_LANG [$shLangIso] ['_SH404SEF_FLEXICONTENT'] . '/';
shRemoveFromGETVarsList ( 'option' );
shRemoveFromGETVarsList ( 'lang' );
shRemoveFromGETVarsList ( 'view' );
	break;

	
	default :
//		$title [] = '/';
	break;
}


if (! empty ( $Itemid ))
	shRemoveFromGETVarsList ( 'Itemid' );

if (! empty ( $limit ))
	shRemoveFromGETVarsList ( 'limit' );

if (isset ( $limitstart ))
	shRemoveFromGETVarsList ( 'limitstart' ); // limitstart can be zero
	
// ------------------  standard plugin finalize function - don't change ---------------------------  


if ($dosef) {
	$string = shFinalizePlugin ( $string, $title, $shAppendString, $shItemidString, (isset ( $limit ) ? @$limit : null), (isset ( $limitstart ) ? @$limitstart : null), (isset ( $shLangName ) ? @$shLangName : null) );
}

// ------------------  /standard plugin finalize function - don't change ---------------------------
?>