<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Legacy;

use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Legacy type model adapted to the API application.
 *
 * The legacy mergeAttributes() resolves its XML forms through JPATH_BASE,
 * which points at api/ in this application. TaxonomyWriteHelper already merges
 * the attribs registry from the stored row and the request payload, so the API
 * only needs to bind the resulting value directly.
 *
 * @since  5.3
 */
class ApiTypeModel extends \FlexicontentModelType
{
    /**
     * Bind the merged attribs without consulting the legacy form XML.
     *
     * @param   object  $record  The table record.
     * @param   array   $data    The validated data (by reference).
     *
     * @return  void
     */
    protected function _prepareBind($record, &$data)
    {
        if (isset($data['attribs'])) {
            $record->attribs = (new Registry((array) $data['attribs']))->toString();
            unset($data['attribs']);
        }

        \FCModelAdmin::_prepareBind($record, $data);
    }
}
