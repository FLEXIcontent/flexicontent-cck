<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Controller;

use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\MVC\Controller\ApiController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The items API controller.
 *
 * MVP spike: read-only (GET list / GET item). Write operations are refused
 * until the legacy item save/delete logic is adapted (later phase).
 *
 * @since  5.3
 */
class ItemsController extends ApiController
{
    /**
     * The content type of the item.
     *
     * @var    string
     */
    protected $contentType = 'items';

    /**
     * The default view for the display method.
     *
     * @var    string
     */
    protected $default_view = 'items';

    /**
     * The URL option for the component.
     *
     * @var    string
     */
    protected $option = 'com_flexicontent';

    /**
     * Item list view amended to add filtering of data.
     *
     * @return  static  A BaseController object to support chaining.
     */
    public function displayList()
    {
        $apiFilterInfo = $this->input->get('filter', [], 'array');
        $filter        = InputFilter::getInstance();

        if (\array_key_exists('search', $apiFilterInfo)) {
            $this->modelState->set('filter.search', $filter->clean($apiFilterInfo['search'], 'STRING'));
        }

        if (\array_key_exists('state', $apiFilterInfo)) {
            $this->modelState->set('filter.published', $filter->clean($apiFilterInfo['state'], 'INT'));
        }

        if (\array_key_exists('category', $apiFilterInfo)) {
            $this->modelState->set('filter.category_id', $filter->clean($apiFilterInfo['category'], 'INT'));
        }

        if (\array_key_exists('type', $apiFilterInfo)) {
            $this->modelState->set('filter.type_id', $filter->clean($apiFilterInfo['type'], 'INT'));
        }

        if (\array_key_exists('language', $apiFilterInfo)) {
            $this->modelState->set('filter.language', $filter->clean($apiFilterInfo['language'], 'STRING'));
        }

        $apiListInfo = $this->input->get('list', [], 'array');

        if (\array_key_exists('ordering', $apiListInfo)) {
            $this->modelState->set('list.ordering', $filter->clean($apiListInfo['ordering'], 'STRING'));
        }

        if (\array_key_exists('direction', $apiListInfo)) {
            $this->modelState->set('list.direction', $filter->clean($apiListInfo['direction'], 'STRING'));
        }

        return parent::displayList();
    }

    /**
     * MVP spike is read-only: refuse record creation.
     *
     * @param   array  $data  An array of input data.
     *
     * @return  boolean
     */
    protected function allowAdd($data = [])
    {
        return false;
    }

    /**
     * MVP spike is read-only: refuse record edition.
     *
     * @param   array   $data  An array of input data.
     * @param   string  $key   The name of the key for the primary key.
     *
     * @return  boolean
     */
    protected function allowEdit($data = [], $key = 'id')
    {
        return false;
    }

    /**
     * MVP spike is read-only: refuse record deletion.
     *
     * @return  boolean
     */
    protected function allowDelete(): bool
    {
        return false;
    }
}