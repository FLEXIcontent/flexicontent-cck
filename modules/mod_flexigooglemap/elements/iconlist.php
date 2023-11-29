<?php
/**
 * @version 0.6.0 stable $Id: default.php yannick berges
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2015 Berges Yannick - www.com3elles.com
 * @license GNU/GPL v2

 * special thanks to ggppdk and emmanuel dannan for flexicontent
 * special thanks to my master Marc Studer

 * FLEXIadmin module is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 **/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

jimport('joomla.form.formfield');

class JFormFieldIconlist extends JFormField
{
	protected $type = 'iconlist';

	// getLabel() left out

	public function getInput()
	{
		$icons = array(
		"" => "",
		"icon-joomla" => "icon-joomla",
		"icon-chevron-up" => "icon-chevron-up",
		'icon-uparrow' => 'icon-uparrow',
		'icon-arrow-up'=>'icon-arrow-up',
		'icon-chevron-right'=>'icon-chevron-right',
		'icon-rightarrow'=>'icon-rightarrow',
		'icon-chevron-down'=>'icon-chevron-down',
		'icon-chevron-left'=>'icon-chevron-left',
		'icon-arrow-first'=>'icon-arrow-first',
		'icon-arrow-last'=>'icon-arrow-last',
		'icon-arrow-up-2'=>'icon-arrow-up-2',
		'icon-arrow-right-2'=>'icon-arrow-right-2',
		'icon-arrow-down-2'=>'icon-arrow-down-2',
		'icon-share'=>'icon-share',
		'icon-redo'=>'icon-redo',
		'icon-undo'=>'icon-undo',
		'icon-forward-2'=>'icon-forward-2',
		'icon-backward-2'=>'icon-backward-2',
		'icon-reply'=>'icon-reply',
		'icon-unblock'=>'icon-unblock',
		'icon-refresh'=>'icon-refresh',
		'icon-redo-2'=>'icon-redo-2',
		'icon-undo-2'=>'icon-undo-2',
		'icon-move'=>'icon-move',
		'icon-play-circle'=>'icon-play-circle',
		'icon-pause-circle'=>'icon-pause-circle',
		'icon-stop-circle'=>'icon-stop-circle',
		'icon-backward-circle'=>'icon-backward-circle',
		'icon-forward-circle'=>'icon-forward-circle',
		'icon-loop'=>'icon-loop',
		'icon-shuffle'=>'icon-shuffle',
		'icon-search'=>'icon-search',
		'icon-apply'=>'icon-apply',
		'icon-edit'=>'icon-edit',
		'icon-pencil'=>'icon-pencil',
		'icon-brush'=>'icon-brush',
		'icon-save-new'=>'icon-save-new',
		'icon-ban-circle'=>'icon-ban-circle',
		'icon-publish'=>'icon-publish',
		'icon-save'=>'icon-save',
		'icon-ok'=>'icon-ok',
		'icon-checkmark'=>'icon-checkmark',
		'icon-unpublish'=>'icon-unpublish',
		'icon-cancel'=>'icon-cancel',
		'icon-cancel-circle'=>'icon-cancel-circle',
		'icon-checkmark-2'=>'icon-checkmark-2',
		'icon-info'=>'icon-info',
		'icon-question'=>'icon-question',
		'icon-help'=>'icon-help',
		'icon-notification'=>'icon-notification',
		'icon-pending'=>'icon-pending',
		'icon-warning'=>'icon-warning',
		'icon-checkbox-checked'=>'icon-checkbox-checked',
		'icon-square'=>'icon-square',
		'icon-circle'=>'icon-circle',
		'icon-grid-view'=>'icon-grid-view',
		'icon-grid-view-2'=>'icon-grid-view-2',
		'icon-list'=>'icon-list',
		'icon-folder'=>'icon-folder',
		'icon-file'=>'icon-file',
		'icon-file-add'=>'icon-file-add',
		'icon-file-remove'=>'icon-file-remove',
		'icon-save-copy'=>'icon-save-copy',
		'icon-tree'=>'icon-tree',
		'icon-box-add'=>'icon-box-add',
		'icon-box-remove'=>'icon-box-remove',
		'icon-download'=>'icon-download',
		'icon-upload'=>'icon-upload',
		'icon-home'=>'icon-upload',
		'icon-new-tab'=>'icon-new-tab',
		'icon-picture'=>'icon-picture',
		'icon-image'=>'icon-image',
		'icon-pictures'=>'icon-pictures',
		'icon-palette'=>'icon-palette',
		'icon-color-palette'=>'icon-color-palette',
		'icon-camera'=>'icon-camera',
		'icon-video'=>'icon-video',
		'icon-youtube'=>'icon-youtube',
		'icon-music'=>'icon-music',
		'icon-user'=>'icon-user',
		'icon-users'=>'icon-users',
		'icon-address'=>'icon-address',
		'icon-comment'=>'icon-comment',
		'icon-quote'=>'icon-quote',
		'icon-bubble-quote'=>'icon-bubble-quote',
		'icon-phone'=>'icon-phone',
		'icon-envelope'=>'icon-envelope',
		'icon-mail'=>'icon-mail',
		'icon-tag'=>'icon-tag',
		'icon-tags'=>'icon-tags',
		'icon-options'=>'icon-options',
		'icon-cog'=>'icon-cog',
		'icon-tools'=>'icon-tools',
		'icon-equalizer'=>'icon-equalizer',
		'icon-dashboard'=>'icon-dashboard',
		'icon-trash'=>'icon-trash',
		'icon-key'=>'icon-key',
		'icon-support'=>'icon-support',
		'icon-health'=>'icon-health',
		'icon-wand'=>'icon-wand',
		'icon-eye-open'=>'icon-eye-open',
		'icon-eye-close'=>'icon-eye-close',
		'icon-clock'=>'icon-clock',
		'icon-compass'=>'icon-compass',
		'icon-wifi'=>'icon-wifi',
		'icon-book'=>'icon-book',
		'icon-flash'=>'icon-flash',
		'icon-print'=>'icon-print',
		'icon-feed'=>'icon-feed',
		'icon-calendar'=>'icon-calendar',
		'icon-pie'=>'icon-pie',
		'icon-bars'=>'icon-bars',
		'icon-chart'=>'icon-chart',
		'icon-cube'=>'icon-cube',
		'icon-puzzle'=>'icon-puzzle',
		'icon-lamp'=>'icon-lamp',
		'icon-pin'=>'icon-pin',
		'icon-location'=>'icon-location',
		'icon-shield'=>'icon-shield',
		'icon-flag'=>'icon-flag',
		'icon-bookmark'=>'icon-bookmark',
		'icon-heart'=>'icon-heart',
		'icon-thumbs-up'=>'icon-thumbs-up',
		'icon-thumbs-down'=>'icon-thumbs-down',
		'icon-asterisk'=>'icon-asterisk',
		'icon-star-empty'=>'icon-star-empty',
		'icon-featured'=>'icon-featured',
		'icon-star'=>'icon-star',
		'icon-smiley'=>'icon-smiley',
		'icon-smiley-happy'=>'icon-smiley-happy',
		'icon-smiley-2'=>'icon-smiley-2',
		'icon-cart'=>'icon-cart',
		'icon-basket'=>'icon-basket',
		'icon-credit'=>'icon-credit'
		);

		## Initialize array to store dropdown options ##
		$options = array();

		foreach($icons as $key=>$value) :
		## Create $value ##
		$options[] = JHtml::_('select.option', $key, $value);
		endforeach;

		## Create <select name="icons" class="inputbox"></select> ##
		$dropdown = JHtml::_('select.genericlist', $options, $this->name, 'class="inputbox"', 'value', 'text', $this->value, $this->id);

		## Output created <select> list ##
		return $dropdown;
	}
}