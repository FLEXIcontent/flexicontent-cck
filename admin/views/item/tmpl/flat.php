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

include "common/load_config_n_acl.php";
//include "common/render_buttons.php";
include "common/render_fields.php";


/**
 * About customizing files: LAYOUTNAME.php and LAYOUTNAME_main.php.
 * Please create a duplicate of them first and rename LAYOUTNAME to 'myLAYOUTNAME'
 * Then select the new layout name in the item type configuration
 */
include "common/form_start.php";
include $this->getLayout() . "_main.php";
include "common/form_end.php";