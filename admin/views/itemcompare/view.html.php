<?php
/**
 * @version 1.5 stable $Id: view.html.php 1800 2013-11-01 04:30:57Z ggppdk $
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

defined('_JEXEC') or die('Restricted access');

jimport('legacy.view.legacy');

/**
 * View class for the FLEXIcontent item comparison screen
 */
class FlexicontentViewItemcompare extends JViewLegacy {

	function display($tpl = null)
	{
		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$option   = $jinput->getCmd('option');
		$db       = JFactory::getDbo();
		$document = JFactory::getDocument();

		// Initialise variables
		$template   = $app->getTemplate();
		$dispatcher = JEventDispatcher::getInstance();
		$version    = $jinput->get('version', 0, 'int');
		$codemode   = $jinput->getInt('codemode', 0);
		$cparams    = JComponentHelper::getParams('com_flexicontent');
		$isAdmin    = $app->isClient('administrator');

		// Add css to document
		if ($isAdmin)
		{
			!JFactory::getLanguage()->isRtl()
				? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
		}
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));

		// Fields common CSS
		$document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_form_fields.css', array('version' => FLEXI_VHASH));
		
		//a trick to avoid loosing general style in modal window
		$document->addStyleDeclaration('
			body, td, th {
				font-size: 14px;
			}
			td {
				border: 1px solid #e0e0e0;
			}
			.novalue {
				color: gray;
				font-style: italic;
			}
		');


		$allow_versioncomparing = (int) $cparams->get('allow_versioncomparing', 1);
		if (!$allow_versioncomparing)
		{
			echo '<div class="alert alert-warning">Version comparing has been disabled in component configuration</div>';
			return;
		}

		/**
		 * Get data from the model
		 */

		$model			= $this->getModel();
		$rows       = array();
		$fsets      = array();

		$rows[0]  = clone($model->getItem(null, false, true, 0));
		$fsets[0]	= $model->getExtrafields(true);
		$rows[$version]  = clone($model->getItem(null, false, true, $version));
		$fsets[$version] = $model->getExtrafields(true);
		$rows[$version]->version_id = $version;

		$vars  = null;
		$items = array($rows[0]);
		FlexicontentFields::getItemFields($items, $vars);

		$vars  = null;
		$items = array($rows[$version]);
		FlexicontentFields::getItemFields($items, $vars);

		$versions		= $model->getVersionList();

		// Get type parameters, these are needed besides the 'merged' item parameters, e.g. to get Type's default layout
		$tparams = $model->getTypeparams();
		$tparams = new JRegistry($tparams);
		
		// Add html to field object trought plugins
		foreach($fsets as $iver => $fields)
		{
			foreach ($fields as $field)
			{
				// Render current field value
				if ($field->iscore)
				{
					FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array(&$field, &$rows[$iver], &$rows[$iver]->parameters, false, false, false, false, false, null, 'display'));
				}
				elseif ($field->field_type === 'coreprops');
				else
				{
					//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $rows[$iver] ));
					$field_type = $field->field_type;
					FLEXIUtilities::call_FC_Field_Func($field_type, 'onDisplayFieldValue', array(&$field, $rows[$iver]));
				}

				if (!isset($field->display) && $field->field_type !== 'coreprops')
				{
					$field->display = '';
				}
			}
		}

		$this->document = $document;

		$this->rows  = $rows;
		$this->fsets = $fsets;

		$this->version  = $version;
		$this->versions = $versions;
		$this->tparams = $tparams;
		$this->cparams = $cparams;
		$this->codemode = $codemode;

		parent::display($tpl);
	}
}
?>