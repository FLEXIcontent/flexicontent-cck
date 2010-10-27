<?php
/**
 * @version		$Id: categoryparent.php 18808 2010-09-08 05:44:54Z eddieajau $
 * @copyright	Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;

jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');

/**
 * Form Field class for the Joomla Framework.
 *
 * @package		Joomla.Administrator
 * @subpackage	com_categories
 * @since		1.6
 */
class JFormFieldCategoryParent extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'CategoryParent';

	/**
	 * Method to get the field options.
	 *
	 * @return	array	The field option objects.
	 * @since	1.6
	 */
	protected function getOptions() {
		global $globalcats;
		$user =& JFactory::getUser();
		$cid = JRequest::getVar('cid');
		
		$permission = FlexicontentHelperPerm::getPerm();

		//$usercats 		= FAccess::checkUserCats($user->gmid);
		$usercats		= array();
		//$viewallcats 	= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usercats', 'users', $user->gmid) : 1;
		$viewallcats 	= $permission->CanUserCats;
		//$viewtree 		= ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'cattree', 'users', $user->gmid) : 1;
		$viewtree 		= $permission->CanViewTree;

		$catlist 	= array();
		$top = (int)$this->element->getAttribute('top');
		$published = (bool)$this->element->getAttribute('published');
		$filter = (bool)$this->element->getAttribute('filter');
		if($top == 1) {
			$obj = new stdClass;
			$obj->value = '';
			$obj->level = 0;
			$obj->text = JText::_( 'FLEXI_TOPLEVEL' );
			$catlist[] 	= $obj;
		} else if($top == 2) {
			$obj = new stdClass;
			$obj->value = '';
			$obj->level = 0;
			$obj->text = JText::_( 'FLEXI_SELECT_CAT' );
			$catlist[] 	= $obj;
		}
		
		foreach ($globalcats as $item) {
			if ((!$published) || ($published && $item->published)) {
				//if ((JRequest::getVar('controller') == 'categories') && (JRequest::getVar('task') == 'edit') && ($cid[0] == $item->id)) {
				if ((JRequest::getVar('controller') == 'categories') && (JRequest::getVar('task') == 'edit') && ($item->lft >= $globalcats[$cid[0]]->lft && $item->rgt <= $globalcats[$cid[0]]->rgt)) {
					if($top == 2) {
						if($cid[0] != $item->id) {
							$obj = new stdClass;
							$obj->value = $item->id;
							$obj->text = $item->treename;
							$obj->level = $item->level;
							$catlist[] = $obj;
						}else {
							$catlist[] = JHtml::_('select.option', $item->id, $item->treename, 'value', 'text', true);
						}
					}
				} else if ($filter) {
					if (FLEXI_ACCESS && (!in_array($item->id, $usercats)) && ($user->gid < 25)) {
						if ($viewallcats) { // only disable cats in the list else don't show them at all
							$catlist[] = JHTML::_( 'select.option', $item->id, $item->treename, 'value', 'text', true );
						}
					} else {
						$item->treename = str_replace("&nbsp;", "_", strip_tags($item->treename));
						// FLEXIaccess rule $viewtree enables tree view
						$catlist[] = JHTML::_( 'select.option', $item->id, ($viewtree ? $item->treename : $item->title) );
					}
				} else {
					$obj = new stdClass;
					$obj->value = $item->id;
					$obj->text = $item->treename;
					$obj->level = $item->level;
					$catlist[] = $obj;
				}
			}
		}

		// Merge any additional options in the XML definition.
		//$catlist = array_merge(parent::getOptions(), $catlist);

		return $catlist;
	}
}