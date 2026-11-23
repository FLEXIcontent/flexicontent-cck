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
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Common base for the read-only FLEXIcontent API controllers.
 *
 * Write operations stay disabled until the legacy save/delete paths are
 * adapted. The helpers below keep filter handling consistent between the
 * category, tag and type endpoints.
 *
 * @since  5.3
 */
abstract class ReadOnlyController extends ApiController
{
    /**
     * The URL option for the component.
     *
     * @var    string
     */
    protected $option = 'com_flexicontent';

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
     * @param   array   $filters  The raw filter array from the request.
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
     * Refuse record edition before Joomla tries to load a legacy table.
     *
     * The parent edit() method loads the model table before calling allowEdit(),
     * which fails for the API-only taxonomy models. All write operations are
     * disabled for this read-only phase, so we fail early with a clean 403.
     *
     * @return  static
     */
    public function edit()
    {
        throw new NotAllowed('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED', 403);
    }

    /**
     * Refuse record creation.
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
     * Refuse record edition.
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
     * Refuse record deletion.
     *
     * @return  boolean
     */
    protected function allowDelete(): bool
    {
        return false;
    }
}
