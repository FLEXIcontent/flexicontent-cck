<?php
/**
 * @version 1.5 beta 5 $Id: fcordering.php 567 2011-04-13 11:06:52Z emmanuel.danan@gmail.com $
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

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('list');   // JFormFieldList

/**
 * Renders an FLEXIcontent field ordering list
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcordering extends JFormFieldList
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Fcordering';

	static $order_names = array(
		'date'=>'date',
		'rdate'=>'rdate',
		'modified'=>'modified',
		'ralpha'=>'ralpha',
		'hits'=>'hits',
		'order'=>'order'
	);
	static $legacy_names = array(   // module ordering groups (1st level)
		'date'=>'addedrev',
		'rdate'=>'added',
		'modified'=>'updated',
		'ralpha'=>'alpharev',
		'hits'=>'popular',
		'order'=>'catorder'
	);


	function getOptions()
	{
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];

		$is_legacy = !empty($attributes['is_legacy']);
		$o = $is_legacy ? self::$legacy_names : self::$order_names;

		$s = !empty($attributes['skip_orders']) ? preg_split("/\s*,\s*/u", $attributes['skip_orders']) : array();
		$s = array_flip($s);

		if ( !empty($attributes['add_global']) )
		{
			$ordering[] = JHtml::_('select.option',  '', 	JText::_( 'FLEXI_USE_GLOBAL' ) );
		}

		if ( !isset($s['date']) )      $ordering[] = JHtml::_('select.option',  $o['date'],			JText::_( 'FLEXI_ORDER_OLDEST_FIRST' ) );         // 'addedrev'
		if ( !isset($s['rdate']) )     $ordering[] = JHtml::_('select.option',  $o['rdate'],			JText::_( 'FLEXI_ORDER_MOST_RECENT_FIRST' ) );    // 'added'

		if ( !isset($s['modified']) )  $ordering[] = JHtml::_('select.option',  $o['modified'],	JText::_( 'FLEXI_ORDER_LAST_MODIFIED_FIRST' ) );  // 'updated'

		if ( !empty($attributes['add_expired_scheduled']) )
		{
			$ordering[] = JHtml::_('select.option',  'published',        JText::_( 'FLEXI_ORDER_RECENTLY_PUBLISHED_SCHEDULED_FIRST' ) );
			$ordering[] = JHtml::_('select.option',  'published_oldest', JText::_( 'FLEXI_ORDER_OLDEST_PUBLISHED_SCHEDULED_FIRST' ) );
			$ordering[] = JHtml::_('select.option',  'expired',	         JText::_( 'FLEXI_ORDER_RECENTLY_EXPIRING_EXPIRED_FIRST' ) );
			$ordering[] = JHtml::_('select.option',  'expired_oldest',   JText::_( 'FLEXI_ORDER_OLDEST_EXPIRING_EXPIRED_FIRST' ) );
		} else {
			$ordering[] = JHtml::_('select.option',  'published',        JText::_( 'FLEXI_ORDER_RECENTLY_PUBLISHED_FIRST' ) );
			$ordering[] = JHtml::_('select.option',  'published_oldest', JText::_( 'FLEXI_ORDER_OLDEST_PUBLISHED_FIRST' ) );
		}

		if ( !isset($s['alpha']) )      $ordering[] = JHtml::_('select.option',  'alpha',      JText::_( 'FLEXI_ORDER_TITLE_ALPHABETICAL' ) );
		if ( !isset($s['ralpha']) )     $ordering[] = JHtml::_('select.option',  $o['ralpha'], JText::_( 'FLEXI_ORDER_TITLE_ALPHABETICAL_REVERSE' ) );    // 'alpharev'

		if ( !isset($s['author']) )     $ordering[] = JHtml::_('select.option',  'author',     JText::_( 'FLEXI_ORDER_AUTHOR_ALPHABETICAL' ) );
		if ( !isset($s['rauthor']) )    $ordering[] = JHtml::_('select.option',  'rauthor',    JText::_( 'FLEXI_ORDER_AUTHOR_ALPHABETICAL_REVERSE' ) );

		if ( !isset($s['hits']) )       $ordering[] = JHtml::_('select.option',  $o['hits'],   JText::_( 'FLEXI_ORDER_MOST_HITS' ) );    // 'popular'
		if ( !isset($s['rhits']) )      $ordering[] = JHtml::_('select.option',  'rhits',      JText::_( 'FLEXI_ORDER_LEAST_HITS' ) );

		if ( !isset($s['id']) )         $ordering[] = JHtml::_('select.option',  'id',         JText::_( 'FLEXI_ORDER_HIGHEST_ITEM_ID' ) );
		if ( !isset($s['rid']) )        $ordering[] = JHtml::_('select.option',  'rid',        JText::_( 'FLEXI_ORDER_LOWEST_ITEM_ID' ) );

		if ( !isset($s['commented']) )  $ordering[] = JHtml::_('select.option',  'commented',  JText::_( 'FLEXI_ORDER_MOST_COMMENTED' ) );
		if ( !isset($s['rated']) )      $ordering[] = JHtml::_('select.option',  'rated',      JText::_( 'FLEXI_ORDER_BEST_RATED' ) );
		if ( !isset($s['order']) )      $ordering[] = JHtml::_('select.option',  $o['order'],  JText::_( 'FLEXI_ORDER_CONFIGURED_ORDER' ) );    // 'catorder'
		if ( !isset($s['rorder']) )     $ordering[] = JHtml::_('select.option',  'rorder',     JText::_( 'FLEXI_ORDER_CONFIGURED_ORDER_REVERSE' ) );    // 'catorder reverse'
		if ( !isset($s['jorder']) )     $ordering[] = JHtml::_('select.option',  'jorder',     JText::_( 'FLEXI_ORDER_CONFIGURED_ORDER_JOOMLA' ) );
		if ( !isset($s['rjorder']) )    $ordering[] = JHtml::_('select.option',  'rjorder',    JText::_( 'FLEXI_ORDER_CONFIGURED_ORDER_JOOMLA_REVERSE' ) );

		if ( !isset($s['random']) )     $ordering[] = JHtml::_('select.option',  'random',     JText::_( 'FLEXI_ORDER_RANDOM' ) . ' ' . JText::_( 'FLEXI_PER_SESSION_PAGINATION_USABLE' ) );
		if ( !isset($s['random_ppr']) ) $ordering[] = JHtml::_('select.option',  'random_ppr', JText::_( 'FLEXI_ORDER_RANDOM' ) . ' ' . JText::_( 'FLEXI_PER_VIEW_PAGINATION_NOT_USABLE' ) );
		if ( !isset($s['alias']) )      $ordering[] = JHtml::_('select.option',  'alias',      JText::_( 'FLEXI_ORDER_ALIAS' ) );
		if ( !isset($s['ralias']) )     $ordering[] = JHtml::_('select.option',  'ralias',     JText::_( 'FLEXI_ORDER_ALIAS_REVERSE' ) );

		if ( !empty($attributes['add_field_order']) )
		{
			$ordering[] = JHtml::_('select.option',  'field', 		JText::_( 'FLEXI_CUSTOM_FIELD' ) );
		}

		return $ordering;
	}
}