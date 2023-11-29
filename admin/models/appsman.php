<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

jimport('legacy.model.list');

/**
 * FLEXIcontent Component types Model
 *
 */
class FlexicontentModelAppsman extends JModelList
{
	/**
	 * Constructor
	 *
	 * @since 1.5
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

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



	function getTableRows($table, $id_colname, $ids, $id_is_unique=true, $fid_colname=null, $fids=array())
	{
		$_ids = array();
		$_fids = array();

		foreach($ids as $id)
			$_ids[] = $this->_db->Quote($id);

		foreach($fids as $fid)
			$_fids[] = $this->_db->Quote($fid);

		$query = 'SELECT * FROM '.$table . ' WHERE 1 ';

		if (count($_ids))
			$query .= ' AND '.$id_colname.' IN ('.implode(',', $_ids).')';

		if ($fid_colname && count($_fids))
			$query .= ' AND '.$fid_colname.' IN ('.implode(',', $_fids).')';

		$this->_db->setQuery($query);
		$rows = $id_is_unique ? $this->_db->loadAssocList($id_colname) : $this->_db->loadAssocList();
		return $rows;
	}



	/*** NEEDS PHP 5.5.4+ ***/
	function create_CSV_file($rows, $table, $id_colname=null, $clear_id=false)
	{
		$app         = JFactory::getApplication();
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

		$nullDate = $this->_db->getNullDate();
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
				if ($colname=='checked_out_time')  $coldata = $nullDate;
				else if ($colname=='checked_out')  $coldata = '0';
				else $coldata = ($clear_id && $id_colname==$colname) ? '0' : $coldata;

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

		$nullDate = $this->_db->getNullDate();
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
				if ($colname=='checked_out_time')  $coldata = $nullDate;
				else if ($colname=='checked_out')  $coldata = '0';
				else $coldata = ($clear_id && $id_colname==$colname) ? '0' : $coldata;

				$coldata_str = $coldata===null ? "NULL" : '"'.str_replace( "\n","\\n", addslashes(htmlspecialchars($coldata, ENT_NOQUOTES, 'UTF-8')) ).'"';
				$content .= "\n".str_repeat($istr, $indent). '<'.$colname.'>' .$coldata_str. '</'.$colname.'>';
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
		if (empty($type_ids)) return array();

		// *************************
		// Get fields-type relations
		// *************************
		$fields_type_rows = $this->getTableRows('#__flexicontent_fields_type_relations', 'type_id', $type_ids, $id_is_unique=false);

		// Get related fields
		$field_ids = array();
		$related_ids['flexicontent_fields'] = array();
		foreach($fields_type_rows as $fields_type_row) {
			$field_id = $fields_type_row['field_id'];
			$related_ids['flexicontent_fields'][$field_id] = $field_id;
			$field_ids[] = $field_id;
		}


		// *****************************************************
		// Get templates, (we need to load type's configuration)
		// *****************************************************

		$template_names = array();
		$type = JTable::getInstance('flexicontent_types', '');
		foreach ($type_ids as $type_id)
		{
			$type->id = $type_id;
			$type->load();
			$type->params = new JRegistry($type->attribs);
			$ilayout = $type->params->get('ilayout', 'grid');   // template folder name
			$related_ids['flexicontent_templates'][$ilayout] = $ilayout;
		}


		// ****************************************
		// Get asset records of types and of fields
		// ****************************************

		$type_rows = $this->getTableRows('#__flexicontent_types', 'id', $type_ids, $id_is_unique=true);
		foreach ($type_rows as $row)
		{
			$asset_id = $row['asset_id'];
			$related_ids['assets'][$asset_id] = $asset_id;
		}

		$field_rows = empty($field_ids) ? array() : $this->getTableRows('#__flexicontent_fields', 'id', $field_ids, $id_is_unique=true);
		foreach ($field_rows as $row)
		{
			$asset_id = $row['asset_id'];
			$related_ids['assets'][$asset_id] = $asset_id;
		}

		return $related_ids;
	}


	function getExtraData_flexicontent_types($rows)
	{
		if (!count($rows)) return '';
		$content = '';

		// *************************
		// Get fields-type relations
		// *************************

		// Get DB data
		$tbl = '#__flexicontent_fields_type_relations';
		$id_colname = 'type_id';
		$type_ids = array_keys($rows);
		$rows = $this->getTableRows($tbl, $id_colname, $type_ids, $id_is_unique=false);

		// Convert them to XML format
		$content .= $this->create_XML_records($rows, $tbl, $id_colname, $clear_id=false);

		return $content;
	}


	function getExtraFiles_flexicontent_templates($rows, $zip)
	{
		if (!count($rows)) return '';
		$content = '';

		// Get DB data
		$template_names = array_keys($rows);
		foreach($template_names as $template_name) {
			$dir = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$template_name;
			if (file_exists($dir))
			{
				$zip->addDir($dir, 'templates/'.$template_name);
			}
		}
		return;
	}

	function doImport_assets($rows, $remap)
	{
		echo 'doImport_assets<br/>';
		return;
	}


	function doImport_flexicontent_fields_type_relations($rows, $remap)
	{
		$table_name = 'flexicontent_fields_type_relations';
		foreach ($rows as $row)
		{
			$obj = new stdClass();
			foreach($row as $col => $val)
			{
				$val = trim((string)$val,'"');
				switch ($col) {
					case 'field_id': $obj->$col = $remap['flexicontent_fields'][$val]; break;
					case 'type_id':  $obj->$col = $remap['flexicontent_types'][$val];  break;
					default: $obj->$col = $val;  break;
				}
			}
			echo $table_name." <br/><pre>";	print_r($obj); echo "</pre>";
			exit;

			// Insert record in DB
			//$db->insertObject('#__'.$table_name, $obj);
		}
		return;
	}
}
