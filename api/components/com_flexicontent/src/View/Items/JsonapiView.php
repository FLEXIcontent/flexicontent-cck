<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\View\Items;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;
use Joomla\Component\Flexicontent\Api\Serializer\ItemsSerializer;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The items API view.
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
    protected $type = 'items';

    /**
     * The fields to render in list documents.
     *
     * @var    array
     */
    protected $fieldsToRenderList = [
        'id',
        'type_id',
        'title',
        'alias',
        'catid',
        'state',
        'access',
        'created',
        'created_by',
        'modified',
        'publish_up',
        'publish_down',
        'language',
        'ordering',
        'featured',
    ];

    /**
     * The fields to render in item documents.
     *
     * @var    array
     */
    protected $fieldsToRenderItem = [
        'id',
        'type_id',
        'title',
        'alias',
        'catid',
        'state',
        'access',
        'created',
        'created_by',
        'modified',
        'publish_up',
        'publish_down',
        'language',
        'ordering',
        'featured',
        'introtext',
        'fulltext',
        'metakey',
        'metadesc',
        'images',
        'urls',
        'attribs',
        'metadata',
    ];

    /**
     * Constructor.
     *
     * @param   array  $config  A named configuration array for object construction.
     */
    public function __construct($config = [])
    {
        // Must be set before the parent constructor, which falls back to JoomlaSerializer.
        $this->serializer = new ItemsSerializer($config['contentType'] ?? $this->type);

        parent::__construct($config);
    }

    /**
     * Prepare item before render: normalize numeric columns.
     *
     * @param   object  $item  The model item.
     *
     * @return  object
     */
    protected function prepareItem($item)
    {
        if (!\is_object($item)) {
            return $item;
        }

        foreach (['id', 'type_id', 'catid', 'state', 'access', 'created_by', 'ordering', 'featured'] as $field) {
            if (isset($item->$field)) {
                $item->$field = (int) $item->$field;
            }
        }

        return $item;
    }
}