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
use Joomla\Component\Flexicontent\Api\Helper\TaxonomyWriteHelper;
use Joomla\Component\Flexicontent\Api\Legacy\LegacyBootstrap;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Common base for the writable FLEXIcontent taxonomy controllers.
 *
 * GET keeps the shared filter handling. POST/PATCH/DELETE delegate persistence
 * to the legacy FLEXIcontent models, exactly like ItemsController does for
 * content items, so aliases, assets, plugins and relations stay consistent with
 * the administrator application.
 *
 * @since  5.3
 */
abstract class TaxonomyController extends ApiController
{
    /**
     * The URL option for the component.
     *
     * @var    string
     */
    protected $option = 'com_flexicontent';

    /**
     * API resource name, e.g. "types".
     *
     * @var    string
     */
    protected $resource;

    /**
     * Singular model name for the resource.
     *
     * @var    string
     */
    protected $singular;

    /**
     * Apply the common "search" filter.
     *
     * @param   array   $filters  The raw filter array from the request.
     * @param   string  $stateKey The model state key to set.
     *
     * @return  void
     */
    protected function applySearchFilter(array $filters, string $stateKey): void
    {
        if (!\array_key_exists('search', $filters)) {
            return;
        }

        $this->modelState->set($stateKey, InputFilter::getInstance()->clean($filters['search'], 'STRING'));
    }

    /**
     * Apply a validated string filter.
     *
     * @param   array   $filters  The raw filter array from the request.
     * @param   string  $name     The filter name.
     * @param   string  $stateKey The model state key to set.
     *
     * @return  void
     */
    protected function applyStringFilter(array $filters, string $name, string $stateKey): void
    {
        if (!\array_key_exists($name, $filters)) {
            return;
        }

        $this->modelState->set($stateKey, InputFilter::getInstance()->clean($filters[$name], 'STRING'));
    }

    /**
     * Apply the common publication state filter.
     *
     * Non-managers are always forced to published records: they must not be
     * able to request unpublished, archived or trashed taxonomy records.
     *
     * @param   array  $filters  The raw filter array from the request.
     *
     * @return  void
     */
    protected function applyStateFilter(array $filters): void
    {
        if (!AccessHelper::canManage()) {
            // Non-managers must never see unpublished, archived or trashed taxonomy records.
            $this->modelState->set('filter.published', 1);

            return;
        }

        if (\array_key_exists('state', $filters)) {
            $this->modelState->set('filter.published', InputFilter::getInstance()->clean($filters['state'], 'INT'));
        }
    }

    /**
     * Apply a validated integer filter.
     *
     * @param   array   $filters  The raw filter array from the request.
     * @param   string  $name     The filter name.
     * @param   string  $stateKey The model state key to set.
     *
     * @return  void
     */
    protected function applyIntFilter(array $filters, string $name, string $stateKey): void
    {
        if (!\array_key_exists($name, $filters)) {
            return;
        }

        $this->modelState->set($stateKey, InputFilter::getInstance()->clean($filters[$name], 'INT'));
    }

    /**
     * Apply the JSON:API "list[ordering]" and "list[direction]" parameters.
     *
     * @return  void
     */
    protected function applyListOrdering(): void
    {
        $list   = $this->input->get('list', [], 'array');
        $filter = InputFilter::getInstance();

        if (\array_key_exists('ordering', $list)) {
            $this->modelState->set('list.ordering', $filter->clean($list['ordering'], 'STRING'));
        }

        if (\array_key_exists('direction', $list)) {
            $this->modelState->set('list.direction', $filter->clean($list['direction'], 'WORD'));
        }
    }

    /**
     * Create a taxonomy record (POST).
     *
     * @return  static
     */
    public function add()
    {
        LegacyBootstrap::boot();

        $payload = TaxonomyWriteHelper::normalize(
            $this->resource,
            $this->getPayload(),
            null,
            true
        );

        TaxonomyWriteHelper::validateCreate($this->resource, $payload);

        if (!$this->allowAdd($payload)) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED', 403);
        }

        $id = $this->saveRecord($payload);

        // 201 is the correct status for a created resource.
        $this->app->setHeader('status', 201);

        $stored = TaxonomyWriteHelper::loadStored($this->resource, $id);

        if ($stored !== null && $this->isStoredVisible($stored)) {
            $this->displayItem($id);
        }

        return $this;
    }

    /**
     * Update a taxonomy record (PATCH).
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

        $stored = TaxonomyWriteHelper::loadStored($this->resource, $id);

        if ($stored === null) {
            throw new ResourceNotFound('JLIB_APPLICATION_ERROR_RECORD', 404);
        }

        if (!$this->allowEdit(['id' => $id], 'id')) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED', 403);
        }

        $payload = TaxonomyWriteHelper::normalize(
            $this->resource,
            $this->getPayload(),
            $stored,
            false
        );

        $this->saveRecord($payload);
        $this->clearModelCache();

        return $this->displayItem($id);
    }

    /**
     * Delete a taxonomy record (DELETE).
     *
     * @param   integer  $id  The primary key to delete.
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

        $stored = TaxonomyWriteHelper::loadStored($this->resource, $id);

        if ($stored === null) {
            throw new ResourceNotFound('JLIB_APPLICATION_ERROR_RECORD', 404);
        }

        if (!$this->allowDeleteRecord($stored)) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED', 403);
        }

        $model = $this->getModel($this->singularName(), '', ['ignore_request' => true]);

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
    protected function getPayload(): array
    {
        $raw  = (string) $this->input->json->getRaw();
        $json = $raw !== '' ? json_decode($raw, true) : null;

        // A 'data' query parameter must not pollute the JSON request body.
        $data = \is_array($json) ? $json : $this->input->get('data', [], 'array');

        if (!\is_array($data)) {
            throw new SaveException('Invalid JSON payload.', 400);
        }

        return TaxonomyWriteHelper::unwrap($data);
    }

    /**
     * Persist a record through the legacy model.
     *
     * @param   array  $data  The legacy-shaped record data.
     *
     * @return  integer
     */
    private function saveRecord(array $data): int
    {
        $model = $this->getModel($this->singularName(), '', ['ignore_request' => true]);

        if (!$model) {
            throw new SaveException('JLIB_APPLICATION_ERROR_MODEL_CREATE', 500);
        }

        $id = $model->saveViaLegacy($data);

        if ($id === false) {
            throw new SaveException($model->getError() ?: 'JLIB_APPLICATION_ERROR_SAVE_FAILED', 422);
        }

        $model->clearTaxonomyCache();

        return (int) $id;
    }

    /**
     * Drop cached records after a write.
     *
     * @return  void
     */
    private function clearModelCache(): void
    {
        $model = $this->getModel($this->singularName(), '', ['ignore_request' => true]);

        if ($model) {
            $model->clearTaxonomyCache();
        }
    }

    /**
     * Singular model name for the current resource.
     *
     * @return  string
     */
    private function singularName(): string
    {
        return $this->singular;
    }

    /**
     * Can the API render the stored record after a write?
     *
     * @param   object  $stored  The raw stored row.
     *
     * @return  boolean
     */
    private function isStoredVisible(object $stored): bool
    {
        if (AccessHelper::canManage()) {
            return true;
        }

        if (!AccessHelper::isPublishedVisible($stored, 'access', $this->resource === 'tags')) {
            return false;
        }

        // Fields named form_* are configuration records and hidden from reads.
        return $this->resource !== 'fields' || strpos((string) ($stored->name ?? ''), 'form_') !== 0;
    }

    /**
     * Can the current user create this resource?
     *
     * @param   array  $data  The normalised payload.
     *
     * @return  boolean
     */
    protected function allowAdd($data = [])
    {
        return TaxonomyWriteHelper::canCreate($this->resource, $data);
    }

    /**
     * Can the current user edit this resource?
     *
     * @param   array   $data  An array of input data.
     * @param   string  $key   The name of the key for the primary key.
     *
     * @return  boolean
     */
    protected function allowEdit($data = [], $key = 'id')
    {
        return TaxonomyWriteHelper::canEdit($this->resource, (int) ($data[$key] ?? 0));
    }

    /**
     * Can the current user delete records of this resource in general?
     *
     * @return  boolean
     */
    protected function allowDelete(): bool
    {
        return TaxonomyWriteHelper::canDelete($this->resource);
    }

    /**
     * Can the current user delete this specific record?
     *
     * @param   object  $stored  The stored record.
     *
     * @return  boolean
     */
    protected function allowDeleteRecord(object $stored): bool
    {
        return TaxonomyWriteHelper::canDeleteRecord($this->resource, $stored);
    }
}
