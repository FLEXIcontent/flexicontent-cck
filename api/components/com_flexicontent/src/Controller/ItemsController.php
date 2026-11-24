<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Controller;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\CMS\MVC\Controller\Exception\Save as SaveException;
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;
use Joomla\Component\Flexicontent\Api\Helper\WriteHelper;
use Joomla\Component\Flexicontent\Api\Legacy\LegacyBootstrap;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The items API controller.
 *
 * GET uses the API list/detail models. POST/PATCH/DELETE prepare a flat JSON
 * payload for the legacy FLEXIcontent item model and delegate persistence to
 * its store()/delete() methods, so field plugins, versioning, tags, categories
 * and search indexes stay consistent with the administrator application.
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

        if (\array_key_exists('state', $apiFilterInfo) && AccessHelper::canManage()) {
            $this->modelState->set('filter.published', $filter->clean($apiFilterInfo['state'], 'INT'));
        } elseif (!AccessHelper::canManage()) {
            // Non-managers must never see unpublished/archived/trashed items
            $this->modelState->set('filter.published', 1);
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

        if (\array_key_exists('author', $apiFilterInfo)) {
            $this->modelState->set('filter.created_by', $filter->clean($apiFilterInfo['author'], 'INT'));
        }

        if (\array_key_exists('access', $apiFilterInfo)) {
            $this->modelState->set('filter.access', $filter->clean($apiFilterInfo['access'], 'INT'));
        }

        if (\array_key_exists('featured', $apiFilterInfo)) {
            $this->modelState->set('filter.featured', $filter->clean($apiFilterInfo['featured'], 'INT'));
        }

        if (\array_key_exists('tag', $apiFilterInfo)) {
            $this->modelState->set('filter.tag', $filter->clean($apiFilterInfo['tag'], 'INT'));
        }

        if (\array_key_exists('field', $apiFilterInfo)) {
            $this->modelState->set('filter.field', $filter->clean($apiFilterInfo['field'], 'STRING'));
        }

        if (\array_key_exists('field_value', $apiFilterInfo)) {
            $this->modelState->set('filter.field_value', $filter->clean($apiFilterInfo['field_value'], 'STRING'));
        }

        if (\array_key_exists('field_match', $apiFilterInfo)) {
            $this->modelState->set('filter.field_match', $filter->clean($apiFilterInfo['field_match'], 'WORD'));
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
     * Create a new item (POST).
     *
     * @return  static
     */
    public function add()
    {
        LegacyBootstrap::boot();

        $payload = $this->getPayload();
        $payload = $this->normalizeCreatePayload($payload);

        if (!$this->allowAdd($payload)) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED', 403);
        }

        $id = $this->saveItem(WriteHelper::prepare($payload, null, true));

        // Joomla's core API returns 200 here; 201 is the correct status for a
        // created resource and is used by the FLEXIcontent API contract.
        $this->app->setHeader('status', 201);

        $stored = WriteHelper::loadStoredItem($id);

        // An author may create an item in a workflow state that the read API
        // intentionally hides. Do not turn a successful POST into a 404.
        if ($stored !== null && AccessHelper::isItemVisible($stored)) {
            $this->displayItem($id);
        }

        return $this;
    }

    /**
     * Update an existing item (PATCH).
     *
     * @return  static
     */
    public function edit()
    {
        LegacyBootstrap::boot();

        $id = (int) $this->input->getInt('id');

        if (!$id) {
            throw new ResourceNotFound('JLIB_APPLICATION_ERROR_RECORD', 404);
        }

        $stored = WriteHelper::loadStoredItem($id);

        if ($stored === null) {
            throw new ResourceNotFound('JLIB_APPLICATION_ERROR_RECORD', 404);
        }

        if (!$this->allowEdit(['id' => $id], 'id')) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED', 403);
        }

        $payload = $this->getPayload();

        // State changes are a separate FLEXIcontent permission.
        if (
            \array_key_exists('state', $payload)
            && (int) $payload['state'] !== (int) $stored->state
            && !AccessHelper::canEditStateItem($stored)
        ) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED', 403);
        }

        $this->saveItem(WriteHelper::prepare($payload, $stored, false));

        return $this->displayItem($id);
    }

    /**
     * Delete an item (DELETE).
     *
     * @param   integer  $id  The primary key to delete item.
     *
     * @return  static
     */
    public function delete($id = null)
    {
        LegacyBootstrap::boot();

        if (!$this->allowDelete()) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED', 403);
        }

        $id = $id === null ? (int) $this->input->getInt('id') : (int) $id;

        if (!$id) {
            throw new ResourceNotFound('JLIB_APPLICATION_ERROR_RECORD', 404);
        }

        $stored = WriteHelper::loadStoredItem($id);

        if ($stored === null) {
            throw new ResourceNotFound('JLIB_APPLICATION_ERROR_RECORD', 404);
        }

        if (!AccessHelper::canDeleteItem($stored)) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED', 403);
        }

        $model = $this->getModel('item', '', ['ignore_request' => true]);

        if (!$model || !$model->deleteViaLegacy($id)) {
            // Do not expose internal/legacy errors to API clients.
            throw new SaveException('JLIB_APPLICATION_ERROR_DELETE_FAILED', 500);
        }

        $this->app->setHeader('status', 204);

        return $this;
    }

    /**
     * Decode the request payload. Joomla's API controllers expect a flat JSON
     * object; a JSON:API "data.attributes" envelope is also accepted.
     *
     * @return  array
     */
    private function getPayload(): array
    {
        $raw  = (string) $this->input->json->getRaw();
        $json = $raw !== '' ? json_decode($raw, true) : null;

        // Prefer the request body: a 'data' query parameter must not be able to
        // replace or pollute the JSON payload sent by the client.
        $data = \is_array($json) ? $json : $this->input->get('data', [], 'array');

        if (!\is_array($data)) {
            throw new SaveException('Invalid JSON payload.', 400);
        }

        return WriteHelper::unwrap($data);
    }

    /**
     * Check and complete the mandatory fields of a creation payload.
     *
     * @param   array  $payload  The normalised API payload.
     *
     * @return  array
     */
    private function normalizeCreatePayload(array $payload): array
    {
        if (empty($payload['catid'])) {
            $categories = $payload['categories'] ?? $payload['category_ids'] ?? [];

            if (\is_string($categories)) {
                $categories = explode(',', $categories);
            }

            $categories = array_values((array) $categories);

            if (!empty($categories[0])) {
                $payload['catid'] = (int) $categories[0];
            }
        }

        if (trim((string) ($payload['title'] ?? '')) === '' || empty($payload['catid']) || empty($payload['type_id'])) {
            throw new SaveException('title, catid and type_id are required to create an item.', 422);
        }

        return $payload;
    }

    /**
     * Delegate persistence to the API item model.
     *
     * @param   array  $data  The legacy-shaped item data.
     *
     * @return  integer
     */
    private function saveItem(array $data): int
    {
        $model = $this->getModel('item', '', ['ignore_request' => true]);

        if (!$model) {
            throw new SaveException('JLIB_APPLICATION_ERROR_MODEL_CREATE', 500);
        }

        $id = $model->saveViaLegacy($data);

        if ($id === false) {
            throw new SaveException($model->getError() ?: 'JLIB_APPLICATION_ERROR_SAVE_FAILED', 422);
        }

        $model->clearCache();

        return (int) $id;
    }

    /**
     * Can the current user create FLEXIcontent items?
     *
     * @param   array  $data  An array of input data.
     *
     * @return  boolean
     */
    protected function allowAdd($data = [])
    {
        LegacyBootstrap::boot();

        if (!AccessHelper::canCreate()) {
            return false;
        }

        $typeId = (int) ($data['type_id'] ?? 0);

        return $typeId === 0 || AccessHelper::canCreateType($typeId);
    }

    /**
     * Can the current user edit this item?
     *
     * @param   array   $data  An array of input data.
     * @param   string  $key   The name of the key for the primary key.
     *
     * @return  boolean
     */
    protected function allowEdit($data = [], $key = 'id')
    {
        LegacyBootstrap::boot();

        $id = (int) ($data[$key] ?? 0);
        $stored = WriteHelper::loadStoredItem($id);

        return $stored !== null && AccessHelper::canEditItem($stored);
    }

    /**
     * Can the current user delete FLEXIcontent items?
     *
     * Per-item checks are done in delete().
     *
     * @return  boolean
     */
    protected function allowDelete(): bool
    {
        $user = AccessHelper::getIdentity();

        return $user->authorise('core.manage', 'com_flexicontent')
            || $user->authorise('core.delete', 'com_flexicontent')
            || $user->authorise('core.delete.own', 'com_flexicontent');
    }
}
