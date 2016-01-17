<?php
/**
 * @version 0.6.0 stable $Id: default.php yannick berges
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2015 Berges Yannick - www.com3elles.com
 * @license GNU/GPL v2
 
 * special thanks to ggppdk and emmanuel dannan for flexicontent
 * special thanks to my master Marc Studer
 
 * This is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
**/
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField
//jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('...');   // JFormField...


class JFormFieldMicrodataprops extends JFormField {

	protected $type = 'microdataprops';

	// getLabel() left out

	public function getInput() {
		$values = array(
			'url' => 'Url',
			'name' => 'Name',
			'job-title' => 'Jobtitle',
			'description'=>'description',
			'address'=>'Address',
			'streetAddress'=>'streetAddress',
			'postOfficeBoxNumber'=>'postOfficeBoxNumber',
			'addressLocality'=>'addressLocality',
			'addressRegion'=>'addressRegion',
			'addressCountry'=>'addressCountry',
			'email'=>'email',
			'telephone'=>'telephone',
			'birthDate'=>'birthDate',
			'image'=>'image',
			'brand'=>'brand',
			'review'=>'aggregateRating',
			'offers'=>'offers',
			'sku'=>'sku',
			'price'=>'price',
			'priceCurrency'=>'priceCurrency',
			'priceValidUntil'=>'priceValidUntil',
			'availability'=>'availability',
			'itemOffered'=>'itemOffered',
			'lowPrice'=>'lowPrice',
			'highPrice'=>'highPrice',
			'priceCurrency'=>'priceCurrency',
			'offerCount'=>'offerCount',
			'manufacturer'=>'manufacturer',
			'model'=>'model',
			'productId'=>'productId',
			'recipeCategory'=>'recipeCategory',
			'datePublished'=>'datePublished',
			'prepTime'=>'prepTime',
			'cookTime'=>'cookTime',
			'totalTime'=>'totalTime',
			'nutrition'=>'nutrition',
			'recipeInstructions'=>'recipeInstructions',
			'recipeYield'=>'recipeYield',
			'ingredients'=>'ingredients',
			'author'=>'author',
			'location'=>'location',
			'startDate'=>'startDate',
			'offers.price'=>'offers.price',
			'duration'=>'duration',
			'postalCode'=>'postalCode',
			'reviewBody'=>'reviewBody',
			'reviewRating'=>'reviewRating',
			'reviewRating.ratingValue'=>'reviewRating.ratingValue',
			'reviewRating.bestRating'=>'reviewRating.bestRating',
			'reviewRating.worstRating'=>'reviewRating.worstRating',
			'thumbnailUrl'=>'thumbnailUrl',
			'uploadDate'=>'uploadDate',
			'contentUrl'=>'contentUrl',
			'embedUrl'=>'embedUrl',
			'director'=>'director',
			'actors'=>'actors',
			'publisher'=>'publisher',
			'bookEdition'=>'bookEdition',
			'isbn'=>'isbn',
			'bookFormat'=>'bookFormat',
			'color'=>'color'
		);

		## Initialize array to store dropdown options ##
		$options = array();
		$options[] = JHTML::_('select.option','', '-- '.JText::_('FLEXI_DISABLE').' --');

		foreach($values as $key=>$value) :
		## Create $value ##
		$options[] = JHTML::_('select.option', $key, $value);
		endforeach;

		## Create <select name="icons" class="inputbox"></select> ##
		$dropdown = JHTML::_('select.genericlist', $options, $this->name, 'class="use_select2_lib inputbox"', 'value', 'text', $this->value, $this->id);

		## Output created <select> list ##
		return $dropdown;
	}
}