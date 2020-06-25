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

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Table\Table;

jimport('legacy.model.list');

/**
 * FLEXIcontent Component Import Model
 *
 */
class FlexicontentModelImport extends JModelList
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
		$id_col   = $fcform ? $jinput->get('id_col', 0, 'int')         :  $app->getUserStateFromRequest( $p.'id_col', 'id_col', $this->cparams->get('import_id_col', 0), 'int');
		$type_id  = $fcform ? $jinput->get('type_id', 0, 'int')        :  $app->getUserStateFromRequest( $p.'type_id', 'type_id', 0, 'int');
		$language = $fcform ? $jinput->get('language', '*', 'string')  :  $app->getUserStateFromRequest( $p.'language', 'language', $this->cparams->get('import_lang', '*'), 'string');
		$state    = $fcform ? $jinput->get('state', 1, 'int')          :  $app->getUserStateFromRequest( $p.'state', 'state', $this->cparams->get('import_state', 1), 'int');
		$access   = $fcform ? $jinput->get('access', 1, 'int')         :  $app->getUserStateFromRequest( $p.'access', 'access', $this->cparams->get('import_access', 1), 'int');

		$this->setState('id_col', $id_col);
		$this->setState('type_id', $type_id);
		$this->setState('language', $language);
		$this->setState('state', $state);
		$this->setState('access', $access);

		$app->setUserState($p.'id_col', $id_col);
		$app->setUserState($p.'type_id', $type_id);
		$app->setUserState($p.'language', $language);
		$app->setUserState($p.'state', $state);
		$app->setUserState($p.'access', $access);


		// Main and secondary categories, tags
		$maincat     = $fcform ? $jinput->get('maincat', 0, 'int')      :  $app->getUserStateFromRequest( $p.'maincat', 'maincat', 0, 'int');
		$maincat_col = $fcform ? $jinput->get('maincat_col', 0, 'int')  :  $app->getUserStateFromRequest( $p.'maincat_col', 'maincat_col', 0, 'int');
		$seccats     = $fcform ? $jinput->get('seccats', 0, 'int')      :  $app->getUserStateFromRequest( $p.'seccats', 'seccats', false, 'array');
		$seccats_col = $fcform ? $jinput->get('seccats_col', 0, 'int')  :  $app->getUserStateFromRequest( $p.'seccats_col', 'seccats_col', 0, 'int');
		$tags_col    = $fcform ? $jinput->get('tags_col', 0, 'int')     :  $app->getUserStateFromRequest( $p.'tags_col', 'tags_col', 0, 'int');

		if (!is_array($seccats))    $seccats    = strlen($seccats)    ? array($seccats)    : array();

		$this->setState('maincat', $maincat);
		$this->setState('maincat_col', $maincat_col);
		$this->setState('seccats', $seccats);
		$this->setState('seccats_col', $seccats_col);
		$this->setState('tags_col', $tags_col);

		$app->setUserState($p.'maincat', $maincat);
		$app->setUserState($p.'maincat_col', $maincat_col);
		$app->setUserState($p.'seccats', $seccats);
		$app->setUserState($p.'seccats_col', $seccats_col);
		$app->setUserState($p.'tags_col', $tags_col);


		// Publication: Author/modifier
		$created_by_col  = $fcform ? $jinput->get('created_by_col', 0, 'int')   :  $app->getUserStateFromRequest( $p.'created_by_col', 'created_by_col', $this->cparams->get('import_created_by_col', 0), 'int');
		$modified_by_col = $fcform ? $jinput->get('modified_by_col', 0, 'int')  :  $app->getUserStateFromRequest( $p.'modified_by_col', 'modified_by_col', $this->cparams->get('import_modified_by_col', 0), 'int');

		$this->setState('created_by_col', $created_by_col);
		$this->setState('modified_by_col', $modified_by_col);

		$app->setUserState($p.'created_by_col', $created_by_col);
		$app->setUserState($p.'modified_by_col', $modified_by_col);


		// Publication: META data
		$metadesc_col = $fcform ? $jinput->get('metadesc_col', 0, 'int')  :  $app->getUserStateFromRequest( $p.'metadesc_col', 'metadesc_col', $this->cparams->get('import_metadesc_col', 0), 'int');
		$metakey_col  = $fcform ? $jinput->get('metakey_col', 0, 'int')   :  $app->getUserStateFromRequest( $p.'metakey_col', 'metakey_col', $this->cparams->get('import_metakey_col', 0), 'int');
		$custom_ititle_col = $fcform ? $jinput->get('custom_ititle_col', 0, 'int')   :  $app->getUserStateFromRequest( $p.'custom_ititle_col', 'custom_ititle_col', $this->cparams->get('import_custom_ititle_col', 0), 'int');

		$this->setState('metadesc_col', $metadesc_col);
		$this->setState('metakey_col', $metakey_col);
		$this->setState('custom_ititle_col', $custom_ititle_col);

		$app->setUserState($p.'metadesc_col', $metadesc_col);
		$app->setUserState($p.'metakey_col', $metakey_col);
		$app->setUserState($p.'custom_ititle_col', $custom_ititle_col);


		// Publication: dates
		$modified_col = $fcform ? $jinput->get('modified_col', 0, 'int')  :  $app->getUserStateFromRequest( $p.'modified_col', 'modified_col', $this->cparams->get('import_modified_col', 0), 'int');
		$created_col  = $fcform ? $jinput->get('created_col', 0, 'int')   :  $app->getUserStateFromRequest( $p.'created_col', 'created_col', $this->cparams->get('import_created_col', 0), 'int');
		$publish_up_col   = $fcform ? $jinput->get('publish_up_col', 0, 'int')    :  $app->getUserStateFromRequest( $p.'publish_up_col', 'publish_up_col', $this->cparams->get('import_publish_up_col', 0), 'int');
		$publish_down_col = $fcform ? $jinput->get('publish_down_col', 0, 'int')  :  $app->getUserStateFromRequest( $p.'publish_down_col', 'publish_down_col', $this->cparams->get('import_publish_down_col', 0), 'int');

		$this->setState('modified_col', $modified_col);
		$this->setState('created_col', $created_col);
		$this->setState('publish_up_col', $publish_up_col);
		$this->setState('publish_down_col', $publish_down_col);

		$app->setUserState($p.'modified_col', $modified_col);
		$app->setUserState($p.'created_col', $created_col);
		$app->setUserState($p.'publish_up_col', $publish_up_col);
		$app->setUserState($p.'publish_down_col', $publish_down_col);


		// Advanced configuration
		$ignore_unused_cols = $fcform ? $jinput->get('ignore_unused_cols', 0, 'int')  :  $app->getUserStateFromRequest( $p.'ignore_unused_cols', 'ignore_unused_cols', $this->cparams->get('import_ignore_unused_cols', 0), 'int');
		$items_per_step     = $fcform ? $jinput->get('items_per_step', 0, 'int')      :  $app->getUserStateFromRequest( $p.'items_per_step', 'items_per_step', $this->cparams->get('import_items_per_step', 5), 'int');

		if ( $items_per_step > 50 ) $items_per_step = 50;
		if ( ! $items_per_step )    $items_per_step = 5;

		$this->setState('ignore_unused_cols', $ignore_unused_cols);
		$this->setState('items_per_step', $items_per_step);

		$app->setUserState($p.'ignore_unused_cols', $ignore_unused_cols);
		$app->setUserState($p.'items_per_step', $items_per_step);


		// CSV file format
		$mval_separator   = $fcform ? $jinput->get('mval_separator',   '', 'string')  :  $app->getUserStateFromRequest( $p.'mval_separator', 'mval_separator', $this->cparams->get('csv_field_mval_sep', '%%'), 'string');
		$mprop_separator  = $fcform ? $jinput->get('mprop_separator',  '', 'string')  :  $app->getUserStateFromRequest( $p.'mprop_separator', 'mprop_separator', $this->cparams->get('csv_field_mprop_sep', '!!'), 'string');
		$field_separator  = $fcform ? $jinput->get('field_separator',  '', 'string')  :  $app->getUserStateFromRequest( $p.'field_separator', 'field_separator', $this->cparams->get('csv_field_sep', '~~'), 'string');
		$enclosure_char   = $fcform ? $jinput->get('enclosure_char',   '', 'string')  :  $app->getUserStateFromRequest( $p.'enclosure_char', 'enclosure_char', $this->cparams->get('csv_field_enclose_char', ''), 'string');
		$record_separator = $fcform ? $jinput->get('record_separator', '', 'string')  :  $app->getUserStateFromRequest( $p.'record_separator', 'record_separator', $this->cparams->get('csv_item_record_sep', '\n~~'), 'string');
		$debug_records    = $fcform ? $jinput->get('debug_records',    0, 'int')      :  $app->getUserStateFromRequest( $p.'debug_records', 'debug_records', $this->cparams->get('csv_debug_records', 0), 'int');

		$this->setState('mval_separator', $mval_separator);
		$this->setState('mprop_separator', $mprop_separator);
		$this->setState('field_separator', $field_separator);
		$this->setState('enclosure_char', $enclosure_char);
		$this->setState('record_separator', $record_separator);
		$this->setState('debug_records', $debug_records);

		$app->setUserState($p.'mval_separator', $mval_separator);
		$app->setUserState($p.'mprop_separator', $mprop_separator);
		$app->setUserState($p.'field_separator', $field_separator);
		$app->setUserState($p.'enclosure_char', $enclosure_char);
		$app->setUserState($p.'record_separator', $record_separator);
		$app->setUserState($p.'debug_records', $debug_records);

		// Folders for media / file fields
		$media_folder = $app->getUserStateFromRequest( $p.'media_folder', 'media_folder', $this->cparams->get('import_media_folder', 'tmp/fcimport_media'), 'string');
		$docs_folder  = $app->getUserStateFromRequest( $p.'docs_folder', 'docs_folder', $this->cparams->get('import_docs_folder', 'tmp/fcimport_docs'), 'string');

		$this->setState('media_folder', $media_folder);
		$this->setState('docs_folder', $docs_folder);

		$app->setUserState($p.'media_folder', $media_folder);
		$app->setUserState($p.'docs_folder', $docs_folder);
	}
}
