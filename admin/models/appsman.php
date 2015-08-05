<?php
/**
 * @version 1.5 stable $Id: types.php 1223 2012-03-30 08:34:34Z ggppdk $
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
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.modellist');

/**
 * FLEXIcontent Component types Model
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since		1.0
 */
class FlexicontentModelAppsman extends JModelList
{	
	/**
	 * Constructor
	 *
	 * @since 1.5
	 */
	function __construct()
	{
		parent::__construct();
		
		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'cmd');
		$view   = $jinput->get('view', '', 'cmd');
		$fcform = $jinput->get('fcform', 0, 'int');
		$p      = $option.'.'.$view.'.';
		
		// Parameters of the view, in our case it is only the component parameters
		$this->cparams = JComponentHelper::getParams( 'com_flexicontent' );
		
		
		// **************
		// Form variables
		// **************
		
		// Retrieve Basic configuration
		$table = $fcform ? $jinput->get('table', '*', 'string')  :  $app->getUserStateFromRequest( $p.'table', 'table', $this->cparams->get('import_lang', '*'), 'string');
		
		$this->setState('table', $table);
		
		$app->setUserState($p.'table', $table);
	}
	
	
	
	function getTableCreateSQL($table, $backup_filename=false, $where='', $id_colname=null, $clear_id=false)
	{
		$app = JFactory::getApplication();
		$dbprefix = $app->getCfg('dbprefix');
		$dbtype   = $app->getCfg('dbtype');
		
		$this->_db->setQuery('SHOW CREATE TABLE '.$table);
		$showTable = $this->_db->loadRow();
		$tableCreation = $showTable[1];
		$tableCreation = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $tableCreation);
		$tableCreation = str_replace($dbprefix, '#__', $tableCreation);
		
		return $tableCreation;
	}
	
	
	
	function getTableRows($table, $id_colname, $cid, $id_is_unique=false)
	{
		$query = 'SELECT * FROM '.$table
			.' WHERE '.$id_colname.' IN ('.implode(',', $cid).')';
		$this->_db->setQuery($query);
		$rows = $id_is_unique ? $this->_db->loadAssocList($id_colname) : $this->_db->loadAssocList();
		return $rows;
	}
	
	
	
	/*** NEEDS PHP 5.5.4+ ***/
	function create_CSV_file($rows, $table, $id_colname=null, $clear_id=false)
	{
		$temp_stream = fopen('php://temp', 'r+');
		if (!$temp_stream)
		{
			$app->enqueueMessage("Failed to open php://temp", 'error');
			return false;
		}
		
		$delimiter = ",";  $enclosure = '"';   $escape_char = "\\";
		
		$_row = reset($rows);
		fputcsv($temp_stream, array_keys($_row), $delimiter, $enclosure, $escape_char);
		foreach($rows as $row)
		{
			$row[$id_colname] = $clear_id && $id_colname  ?  0  :  $row[$id_colname];
			fputcsv($temp_stream, array_values($row), $delimiter, $enclosure, $escape_char);
		}
		
		rewind($temp_stream);
		$content = stream_get_contents($temp_stream);
		return $content;
	}

	
	
	
	function create_SQL_file($rows, $table, $id_colname=null, $clear_id=false)
	{
		$rows_cnt = 0;
		$rows_num = count($rows);
		
		$content = "";
		foreach($rows as $row)
		{
			// when started (and every after 100 command cycle):
			if ($rows_cnt%100 == 0 || $rows_cnt == 0 )
			{
				$content .= "\nINSERT INTO ".$table." VALUES";
			}
			$content .= "\n(";
			
			$j = 0;
			foreach($row as $colname => $coldata)
			{
				$coldata = $clear_id && $id_colname==$colname  ?  '0'  :  $coldata;
				$coldata = str_replace("\n","\\n", addslashes($coldata) );
				$content .= '"'.$coldata.'"';
				
				$j++;
				if ( $j < count($row) ) $content.= ',';
			}
			$content .=")";
			
			//every after 100 command cycle [or at last line] ....p.s. but should be inserted 1 cycle eariler
			if ( (($rows_cnt+1)%100==0 && $rows_cnt!=0) || $rows_cnt+1==$rows_num )
				$content .= ";";
			else
				$content .= ",";
			
			$rows_cnt++;
		}
		$content .="\n\n\n";
		
		return $content;
	}
	
	
	
	function create_XML_records($rows, $table, $id_colname=null, $clear_id=false)
	{
		if (!count($rows)) return '';
		$rows_cnt = 0;
		$rows_num = count($rows);
		
		$indent = 1;
		$istr   = "  ";
		$content = "\n".str_repeat($istr, $indent)."<rows table=\"".str_replace('#__', '',$table)."\">";
		$indent++;
		
		foreach($rows as $row)
		{
			$content .= "\n".str_repeat($istr, $indent)."<row>";
			$indent++;
			
			foreach($row as $colname => $coldata)
			{
				$coldata = $clear_id && $id_colname==$colname  ?  '0'  :  $coldata;
				$coldata = str_replace("\n","\\n", addslashes($coldata) );
				$content .= "\n".str_repeat($istr, $indent). '<'.$colname.'>"' .$coldata. '"</'.$colname.'>';
			}
			
			$indent--;
			$content .= "\n".str_repeat($istr, $indent)."</row>";
			
			$rows_cnt++;
		}
		
		$indent--;
		$content .= "\n".str_repeat($istr, $indent)."</rows>\n";
		
		return $content;
	}
	
	
	function getRelatedIds_flexicontent_types($type_ids)
	{
		// Get fields-type relations
		$fields_type_rows = $this->getTableRows('#__flexicontent_fields_type_relations', 'type_id', $type_ids, $id_is_unique=false);
		
		// Get related fields
		$related_ids['flexicontent_fields'] = array();
		foreach($fields_type_rows as $fields_type_row) {
			$related_ids['flexicontent_fields'][] = $fields_type_row['field_id'];
		}
		
		return $related_ids;
	}
	
	
	function getExtraData_flexicontent_types($rows)
	{
		if (!count($rows)) return '';
		
		// Get fields-type relations
		$type_ids = array_keys($rows);
		$fields_type_rows = $this->getTableRows('#__flexicontent_fields_type_relations', 'type_id', $type_ids, $id_is_unique=false);
		
		// Return the extra data
		$content = '';
		$content .= $this->create_XML_records($fields_type_rows, '#__flexicontent_fields_type_relations', $id_colname=null, $clear_id=false);
		return $content;
	}
	
}
