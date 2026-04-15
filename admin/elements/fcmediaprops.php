<?php
/**
 * @version 1.5 stable $Id: filters.php 1829 2014-01-05 22:18:17Z ggppdk $
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

// jimport removed J5: use Joomla\CMS\HTML\HTMLHelper; // TODO: add use statement at top      // JHtml
// jimport removed J5: use Joomla\CMS\...  /* cms.html.select */; // TODO: add use statement at top    // \Joomla\CMS\HTML\Helpers\Select

// jimport removed J5: use Joomla\CMS\...  /* joomla.form.helper */; // TODO: add use statement at top // \Joomla\CMS\Form\FormHelper
\Joomla\CMS\Form\FormHelper::loadFieldClass('list');   // \Joomla\CMS\Form\Field\ListField

require_once("fcsortablelist.php");


/**
 * Renders a filter element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcMediaprops extends JFormFieldFcSortableList
{
	/**
	 * \Joomla\CMS\Form\FormField type
	 * @access	protected
	 * @var		string
	 */
	
	protected $type = 'FcMediaprops';
	
	// Record list
	protected static $records = array(
		'media_type'=>'media_type',
		'codec_type'=>'codec_type',
		'codec_name'=>'codec_name',
		'codec_long_name'=>'codec_long_name',
		'resolution'=>'resolution',
		'fps'=>'fps',
		'media_format'=>'media_format',
		'bit_rate'=>'bit_rate',
		'bits_per_sample'=>'bits_per_sample',
		'sample_rate'=>'sample_rate',
		'duration'=>'duration',
		'channels'=>'channels',
		'channel_layout'=>'channel_layout',
	);
}
