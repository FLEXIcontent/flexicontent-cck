<?php
/**
 * @version 1.5 stable $Id: view.html.php 1959 2014-09-18 00:15:15Z ggppdk $
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
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('legacy.view.legacy');
jimport('joomla.filesystem.file');

/**
 * HTML View class for the Category View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewCategory extends JViewLegacy
{
	/**
	 * Creates the page's display
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		$doc = JFactory::getDocument();
		$doc->setMimeEncoding('application/xml'); 
		$domain = $_SERVER['HTTP_HOST'];
		$dm = new DOMDocument('1.0', 'UTF-8');

		//$items	= $this->items;

		//$xml = $dm->createElement("itemlist");
		//$xml = $dm->appendChild($xml);
		//$xml->appendChild($dm->createElement('total',$count = $this->pageNav->total));
		/*
		foreach ($items as $item) {
			$xmlItem = $dm->createElement("item");
			$xmlItem = $xml->appendChild($xmlItem);
			$xmlItem->appendChild($dm->createElement('id',$item->id));
			
				$fieldCDATA = $dm->createCDATASection($item->title);
				$maintitle = $dm->createElement('title');
				$maintitle->appendChild($fieldCDATA);
				$xmlItem->appendChild($maintitle);

				foreach ($item->fields as $field) {
					if( !isset($field->display) == 0 ){
						switch ($field->field_type) {

							case "maintext":
								$fieldCDATA = $dm->createCDATASection($field->display);
								$mainText = $dm->createElement($field->name);
								$mainText->appendChild($fieldCDATA);
								$xmlItem->appendChild($mainText);

								break;

							case "image":

								$qualidade = 50;
								$widthImage = 100 ;
								$heighImage = 80 ;
								$finalCut = "&amp;w=" . $widthImage . "&amp;h=" . $heighImage . "&amp;q=" . $qualidade;
								$imageSrcSemDomain = explode($domain, $field->display_medium_src)[0];
								$finalImage = $domain."/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=" . $imageSrcSemDomain . $finalCut;
								$xmlItem->appendChild($dm->createElement(	$field->name	    ,  	$finalImage	));
								break;
							
							case "email":
								$xmlItem->appendChild($dm->createElement(	$field->name	    ,  	$field->parameters->get('default_value') 		));
								break;

							case "radioimage":
								$fieldimage  = simplexml_load_string($field->display);
								if($fieldimage[0]['src'] != "" ){		
									$xmlItem->appendChild($dm->createElement(	$field->name	,  	$fieldimage[0]['src']			));
								}

								break;

							case "text":
								if($field->display != "")
								{
									//$xmlItem->appendChild($dm->createElement(	$field->name	    ,  	$field->display 				));
									$fieldCDATA = $dm->createCDATASection($field->display);
									$text = $dm->createElement($field->name);
									$text->appendChild($fieldCDATA);
									$xmlItem->appendChild($text);
								}

								break;

							default:
								if($field->display != "")
								{
									//$xmlItem->appendChild($dm->createElement(	$field->name	    ,  	$field->display 				));
									$fieldCDATA = $dm->createCDATASection($field->display);
									$default = $dm->createElement($field->name);
									$default->appendChild($fieldCDATA);
									$xmlItem->appendChild($default);
								}

								break;
						}
					}
				}
			}
			

	*/
			echo $dm->saveXML();
	
	}
}
?>
