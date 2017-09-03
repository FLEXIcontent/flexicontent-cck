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
	function encodeCSVField($string)
	{
		if(strpos($string, ',') !== false || strpos($string, '"') !== false || strpos($string, "\n") !== false) 
		{
			$string = '"' . str_replace('"', '""', $string) . '"';
		}
		//return mb_convert_encoding($string, 'UTF-16LE', 'UTF-8');
		return $string;
	}
	
	
	/**
	 * Creates the page's display
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		// Initialize framework variables
		$user    = JFactory::getUser();
		$aid     = JAccess::getAuthorisedViewLevels($user->id);
		$app     = JFactory::getApplication();
		$jinput  = $app->input;

		// Get model
		$model  = $this->getModel();

		// Get the category, loading category data and doing parameters merging
		$category = $this->get('Category');

		// Get category parameters as VIEW's parameters (category parameters are merged parameters in order: layout(template-manager)/component/ancestors-cats/category/author/menu)
		$params = $category->parameters;

		// Check if CSV export button is enabled for current view
		$show_csvbutton = $app->isAdmin() ? $params->get('show_csvbutton_be', 0) : $params->get('show_csvbutton', 0) ;
		if ( ! $show_csvbutton ) die('CSV export not enabled for this view');



		// ***********************
		// Get data from the model
		// ***********************

		$items   = $this->get('Data');

		// Get field values
		$_vars = null;
		FlexicontentFields::getItemFields($items, $_vars, $_view='category', $aid);

		// Zero unneeded search index text
		foreach ($items as $item) $item->search_index = '';

		// Use &test=1 to test / preview item data of first item
		/*if ($jinput->get('test', 0, 'cmd'))
		{
			$item = reset($items); echo "<pre>"; print_r($item); exit;
		}*/



		// ********************************************
		// Find fields that will be added to CSV export
		// ********************************************

		// Map of CORE to item properties
		$props = array('title'=>'title', 'created_by'=>'author', 'modified_by'=>'modifier', 'created'=>'created', 'modified'=>'modified');

		$total_fields = 0;
		$delim = "";
		$item0 = reset($items);
		
		foreach( $item0->fields as $field )
		{
			FlexicontentFields::loadFieldConfig($field, $item0);

			$include_in_csv_export = $field->parameters->get('include_in_csv_export', 0);
			if ( !$include_in_csv_export )
			{
				continue;
			}
			$total_fields++;

			// Render field's CSV display
			if ( $include_in_csv_export == 2 )
			{
				FlexicontentFields::getFieldDisplay($items, $field->name, $values=null, $method='csv_export');
			}
		}
	
		if ($total_fields==0)
		{
			$app->enqueueMessage("Fist record in list does not have any fields that supports CSV export and that are also marked as CSV exportable in their configuration", "notice");

			$referer = @$_SERVER['HTTP_REFERER'];
			$referer = flexicontent_html::is_safe_url($referer) ? $referer : JUri::base();
			$app->redirect($referer);
		}



		// **********************
		// 1. Output HTTP HEADERS
		// **********************

		@ob_end_clean();
		header("Pragma: no-cache");
		header("Cache-Control: no-cache");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header('Content-Encoding: UTF-8');
		header('Content-type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename=EXPORT.csv');
		//header("Content-Transfer-Encoding: binary");
		echo "\xEF\xBB\xBF"; // UTF-8 BOM



		// *********************
		// 2. Output HEADERS row
		// *********************

		foreach( $item0->fields as $field )
		{
			$include_in_csv_export = $field->parameters->get('include_in_csv_export', 0);
			if ( !$include_in_csv_export )
			{
				continue;
			}

			echo $delim . $this->encodeCSVField($field->label);
			$delim = ",";
			$total_fields++;
		}
		echo "\n";



		// *******************
		// 3. Output data rows
		// *******************
		
		foreach($items as $item)
		{
			$delim = "";
			foreach($item0->fields as $field_name => $field)
			{
				$include_in_csv_export = $field->parameters->get('include_in_csv_export', 0);
				if ( !$include_in_csv_export )
				{
					continue;
				}

				echo $delim;
				$delim = ",";

				if ($field->iscore)
				{
					$prop = isset($props[$field_name]) ? $props[$field_name] : $field_name;
					echo $this->encodeCSVField( $item->$prop );
				}

				else if (isset($item->fieldvalues[$field->id]))
				{
					if ($include_in_csv_export==1)
					{
						$vals = reset( $item->fieldvalues[$field->id] );
						$vals = is_array($vals) ? $vals : $item->fieldvalues[$field->id];
						echo $this->encodeCSVField( implode(",", $vals ) );
					}
					else
					{
						echo $this->encodeCSVField( isset($item->onDemandFields[$field->name]->csv_export) ? $item->onDemandFields[$field->name]->csv_export : '' );
					}
				}
			}

			echo "\n";
		}
		jexit();  // need to exist here !! to avoid any other output
	}
}
