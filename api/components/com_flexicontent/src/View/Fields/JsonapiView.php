<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\View\Fields;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * FLEXIcontent fields API view.
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
    protected $type = 'fields';

    /**
     * Fields rendered in list documents.
     *
     * @var    array
     */
    protected $fieldsToRenderList = [
        'id',
        'name',
        'label',
        'field_type',
        'iscore',
        'published',
        'access',
        'ordering',
    ];

    /**
     * Fields rendered in item documents.
     *
     * @var    array
     */
    protected $fieldsToRenderItem = [
        'id',
        'name',
        'label',
        'field_type',
        'description',
        'iscore',
        'published',
        'access',
        'ordering',
    ];
}
