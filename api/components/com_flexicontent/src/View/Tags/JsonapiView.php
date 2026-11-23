<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\View\Tags;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * FLEXIcontent tags API view.
 *
 * @since  5.3
 */
class JsonapiView extends BaseApiView
{
    /**
     * The content type.
     *
     * @var    string
     */
    protected $type = 'tags';

    /**
     * Fields rendered in list documents.
     *
     * @var    array
     */
    protected $fieldsToRenderList = [
        'id',
        'name',
        'alias',
        'published',
        'jtag_id',
        'jtag_title',
        'item_count',
    ];

    /**
     * Fields rendered in item documents.
     *
     * @var    array
     */
    protected $fieldsToRenderItem = [
        'id',
        'name',
        'alias',
        'published',
        'checked_out',
        'checked_out_time',
        'jtag_id',
        'jtag_title',
        'jtag_alias',
        'jtag_published',
        'jtag_access',
        'item_count',
    ];
}
