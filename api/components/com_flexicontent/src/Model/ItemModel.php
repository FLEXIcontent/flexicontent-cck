<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Model;

use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\ItemModel as BaseItemModel;
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;
use Joomla\Component\Flexicontent\Api\Helper\FlexiFieldsHelper;
use Joomla\Component\Flexicontent\Api\Legacy\ApiLegacyItemModel;
use Joomla\Component\Flexicontent\Api\Legacy\LegacyBootstrap;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Single item model for the API.
 *
 * Adapter around the legacy site FlexicontentModelItem: reads delegate to the
 * legacy model and enforce the API read ACL, while POST/PATCH/DELETE delegate
 * to the same legacy store()/delete() logic used by FLEXIcontent itself. The
 * original SQL lookup is kept as a safe fallback for reads.
 * The ApiController sets the requested id in the 'item.id' model state.
 *
 * @since  5.3
 */
class ItemModel extends BaseItemModel
{
    /**
     * Legacy item model instance. Null = not created yet, false = disabled after an error.
     *
     * @var    \FlexicontentModelItem|false|null
     */
    private $legacyModel = null;

    /**
     * Get the legacy FLEXIcontent site item model.
     *
     * @return  \FlexicontentModelItem|null
     */
    private function getLegacyModel()
    {
        if ($this->legacyModel === false) {
            return null;
        }

        if ($this->legacyModel instanceof \FlexicontentModelItem) {
            return $this->legacyModel;
        }

        try {
            LegacyBootstrap::boot();
            $this->legacyModel = new ApiLegacyItemModel(['ignore_request' => true]);
        } catch (\Throwable $e) {
            $this->legacyModel = false;
            error_log('[FLEXIcontent API] legacy item model unavailable: ' . $e->getMessage());

            return null;
        }

        return $this->legacyModel;
    }

    /**
     * Method to get a single item.
     *
     * @param   integer  $pk  The id of the item to load (defaults to the 'item.id' state).
     *
     * @return  object|null  The item data object, or null when not found / not a FLEXIcontent item.
     */
    public function getItem($pk = null)
    {
        $pk = (int) ($pk ?: $this->getState('item.id'));

        if (!$pk) {
            return null;
        }

        if (\array_key_exists($pk, (array) $this->_item)) {
            return $this->_item[$pk];
        }

        $legacy = $this->getLegacyModel();

        if ($legacy !== null) {
            try {
                $legacy->setId($pk);
                $item = $legacy->getItem($pk);

                if (!\is_object($item) || !AccessHelper::isItemVisible($item)) {
                    $item = null;
                }

                if ($item !== null) {
                    FlexiFieldsHelper::attach($item);
                }

                $this->_item[$pk] = $item;

                return $this->_item[$pk];
            } catch (\Throwable $e) {
                $this->legacyModel = false;
                error_log('[FLEXIcontent API] legacy item lookup failed: ' . $e->getMessage());
            }
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $query->select('a.*')
            ->select($db->quoteName('ext.type_id'))
            ->from($db->quoteName('#__content', 'a'))
            ->join(
                'INNER',
                $db->quoteName('#__flexicontent_items_ext', 'ext'),
                $db->quoteName('ext.item_id') . ' = ' . $db->quoteName('a.id')
            )
            ->where($db->quoteName('a.id') . ' = ' . $pk);

        $item = $db->setQuery($query)->loadObject();

        // Do not leak the existence of an item the user is not allowed to see
        if ($item && AccessHelper::isItemVisible($item)) {
            FlexiFieldsHelper::attach($item);
            $this->_item[$pk] = $item;
        } else {
            $this->_item[$pk] = null;
        }

        return $this->_item[$pk];
    }

    /**
     * Drop cached item rows after a write, so the response reflects the saved data.
     *
     * @return  void
     */
    public function clearCache(): void
    {
        $this->_item       = [];
        $this->legacyModel = null;
    }

    /**
     * Persist an item through the legacy FLEXIcontent save pipeline.
     *
     * The legacy model performs its own ACL checks, field validation, plugin
     * events, versioning, tag/category relations and search indexing. The API
     * only prepares a safe data array and converts failures into model errors.
     *
     * @param   array  $data  The data prepared by WriteHelper.
     *
     * @return  integer|false  The saved item id, or false on failure.
     */
    public function saveViaLegacy(array $data)
    {
        $legacy = $this->getLegacyModel();

        if ($legacy === null) {
            $this->setError('Legacy FLEXIcontent item model is unavailable.');

            return false;
        }

        try {
            $id = (int) ($data['id'] ?? 0);

            $legacy->setId($id);
            $legacy->setState('item.id', $id);
            $legacy->getItem($id, false, true);

            // In the API application JPATH_BASE points at api/, so the legacy
            // model's own form paths do not resolve. Register the admin paths.
            Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/forms');
            Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/fields');

            $form = $legacy->getForm($data, false);

            if (!$form) {
                $this->setError('Could not create the FLEXIcontent item form.');

                return false;
            }

            $validData = $legacy->validate($form, $data);

            if ($validData === false) {
                $errors = [];

                foreach ($form->getErrors() as $error) {
                    $errors[] = $error instanceof \Exception ? $error->getMessage() : (string) $error;
                }

                if (!$errors) {
                    $errors = $legacy->getErrors();
                }

                $this->setError(implode("\n", $errors) ?: 'Invalid item data.');

                return false;
            }

            // FLEXIcontent keeps these outside the jform XML, exactly as its
            // own controller does after validation.
            foreach (['custom', 'tags', 'categories', 'jfdata'] as $key) {
                if (\array_key_exists($key, $data)) {
                    $validData[$key] = $data[$key];
                }
            }

            $validData['jfdata'] = $validData['jfdata'] ?? [];
            $validData['id']     = $id;
            $validData['vstate'] = (int) ($data['vstate'] ?? 2);

            if (!$legacy->store($validData, true)) {
                $this->setError($legacy->getError() ?: 'The item could not be saved.');

                return false;
            }

            $savedId = (int) $legacy->get('id');

            // ParentClassItem::_loadRecord() keeps a static per-id cache. The
            // pre-save getItem() call populated it with the old row, so refresh
            // it before the API renders the response.
            $legacy->setId($savedId);
            $legacy->getItem($savedId, false, true);

            return $savedId;
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());

            return false;
        }
    }

    /**
     * Delete an item through the legacy FLEXIcontent delete pipeline.
     *
     * @param   integer  $id  The item id.
     *
     * @return  boolean
     */
    public function deleteViaLegacy(int $id): bool
    {
        $legacyItem = $this->getLegacyModel();

        if ($legacyItem === null) {
            $this->setError('Legacy FLEXIcontent item model is unavailable.');

            return false;
        }

        try {
            LegacyBootstrap::boot();
            $items = new \FlexicontentModelItems(['ignore_request' => true]);

            if (!$items->delete([$id], $legacyItem)) {
                $this->setError($items->getError() ?: 'The item could not be deleted.');

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());

            return false;
        }
    }
}
