<?php
// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * If you wish to customize this form, please so the component and type configuration
 * if the above way is not enough to achieve your results, then
 * 1. Create a file like form_somename.php 
 * 2. Copy the contents of the backend file here (replacing the "include" statement below)
 * 3. Select to use it in the ITEM TYPE configuration of the FRONTEND form
 */

$form_layout = $this->params->get('form_layout_fe', 'tabs');
$this->setLayout($form_layout);

include JPATH_ROOT.DS."administrator".DS."components".DS."com_flexicontent".DS."views".DS."item".DS."tmpl".DS. $form_layout . ".php";
