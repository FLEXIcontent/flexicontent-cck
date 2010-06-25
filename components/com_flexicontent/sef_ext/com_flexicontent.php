<?php
/**
 * @version 1.0 $Id: com_flexicontent.php 137 2008-08-04 20:57:18Z vistamedia $
 * @package Joomla
 * @subpackage QuickFAQ sh404sef plugin
 * @copyright (C) 2005 - 2008 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * QuickFAQ is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * QuickFAQ is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with QuickFAQ; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// no direct access
defined ( '_JEXEC' ) or die ( 'Restricted access' );

// ------------------  standard plugin initialize function - don't change ---------------------------


global $sh_LANG, $sefConfig;

$shLangName 	= '';
$shLangIso 		= '';
$title 			= array ( );
$shItemidString = '';
$dosef	 		= shInitializePlugin ( $lang, $shLangName, $shLangIso, $option );
$layout 		= JRequest::getString('layout', '');
$type 			= JRequest::getString('type', '');

if ($dosef == false) {
	return;
}

// ------------------  /standard plugin initialize function - don't change ---------------------------


// ------------------  load language file - adjust as needed ----------------------------------------

$shLangIso = shLoadPluginLanguage ( 'com_flexicontent', $shLangIso, '_SH404SEF_FLEXICONTENT_ADD', JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'sef_ext'.DS.'lang'.DS );

// ------------------  /load language file - adjust as needed ----------------------------------------


$view = isset ( $view ) ? @$view : null;
$Itemid = isset ( $Itemid ) ? @$Itemid : null;
$shFAQName = shGetComponentPrefix ( $option );
$shFAQName = empty ( $shFAQName ) ? 

getMenuTitle ( $option, $view, $Itemid, null, $shLangName ) : $shFAQName;

$shFAQName = (empty ( $shFAQName ) || $shFAQName == '/') ? 'FAQ' : $shFAQName;

if (! empty ( $shFAQName )) {
	$title [] = $shFAQName;
}

switch ( $view) {
	
	case 'items' :
		
		isset ( $task ) ? $task : $task = '';
		
		if (! empty ( $id ) && ! empty ( $cid )) {
			
			$query	= 'SELECT i.id, i.title, c.title AS cattitle'
					. ' FROM #__content AS i'
					. ' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
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
					$title [] = $row->cattitle;
					$title [] = $row->title;
					if ($task == 'edit') {
						$title [] = $sh_LANG [$shLangIso] ['_SH404SEF_FLEXICONTENT_EDIT'];
					}
				}
			}
		} else {
			if ($task == 'add' || $layout == 'form') {
				$title [] = $sh_LANG [$shLangIso] ['_SH404SEF_FLEXICONTENT_ADD'];
			} else {
				$title [] = '/';
			}
		}
		shRemoveFromGETVarsList ( 'id' );
		shRemoveFromGETVarsList ( 'cid' );
		shRemoveFromGETVarsList ( 'task' );
		shRemoveFromGETVarsList ( 'layout' );
		shRemoveFromGETVarsList ( 'type' );
	break;
	
	case 'category' :
		if (! empty ( $cid )) {
			$query 	= 'SELECT id, title FROM #__categories'
					.' WHERE id = ' . ( int ) $cid;
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
					$title [] = $row->title;
				}
			}
		} else {
			$title [] = '/';
		}
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
		$title [] = $sh_LANG [$shLangIso] ['_SH404SEF_FLEXICONTENT_FAVOURITES'] . $sefConfig->suffix;
	break;
/*
	
	case 'flexicontent' :
//		$title [] = $sh_LANG [$shLangIso] ['_SH404SEF_FLEXICONTENT'] . $sefConfig->suffix;
	break;
*/
	
	default :
		$title [] = '/';
	break;
}

shRemoveFromGETVarsList ( 'option' );
shRemoveFromGETVarsList ( 'lang' );

if (! empty ( $Itemid ))
	shRemoveFromGETVarsList ( 'Itemid' );

if (! empty ( $limit ))
	shRemoveFromGETVarsList ( 'limit' );

if (isset ( $limitstart ))
	shRemoveFromGETVarsList ( 'limitstart' ); // limitstart can be zero


if (! empty ( $view ))
	shRemoveFromGETVarsList ( 'view' );
	
// ------------------  standard plugin finalize function - don't change ---------------------------  


if ($dosef) {
	$string = shFinalizePlugin ( $string, $title, $shAppendString, $shItemidString, (isset ( $limit ) ? @$limit : null), (isset ( $limitstart ) ? @$limitstart : null), (isset ( $shLangName ) ? @$shLangName : null) );
}

// ------------------  /standard plugin finalize function - don't change ---------------------------
?>