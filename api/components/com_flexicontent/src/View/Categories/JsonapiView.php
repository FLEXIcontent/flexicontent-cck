<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\View\Categories;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * FLEXIcontent categories API view.
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
    protected $type = 'categories';

    /**
     * Fields rendered in list documents.
     *
     * @var    array
     */
    protected $fieldsToRenderList = [
        'id',
        'title',
        'alias',
        'extension',
        'published',
        'access',
        'parent_id',
        'level',
        'path',
        'language',
        'item_count',
    ];

    /**
     * Fields rendered in item documents.
     *
     * @var    array
     */
    protected $fieldsToRenderItem = [
        'id',
        'title',
        'alias',
        'note',
        'description',
        'extension',
        'published',
        'access',
        'parent_id',
        'level',
        'path',
        'language',
        'metadesc',
        'metakey',
        'metadata',
        'created_user_id',
        'created_time',
        'modified_user_id',
        'modified_time',
        'hits',
        'item_count',
    ];
}
