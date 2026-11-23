<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\View\Types;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * FLEXIcontent content types API view.
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
    protected $type = 'types';

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
        'access',
        'itemscreatable',
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
        'description',
        'published',
        'access',
        'itemscreatable',
        'checked_out',
        'checked_out_time',
        'item_count',
    ];
}
