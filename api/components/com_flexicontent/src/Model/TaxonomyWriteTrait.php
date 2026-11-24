<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\Component\Flexicontent\Api\Legacy\LegacyBootstrap;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Legacy save/delete bridge shared by the writable taxonomy models.
 *
 * The API models keep their own read queries and ACL, but persistence is
 * delegated to the same legacy models used by FLEXIcontent's administrator
 * application. This keeps alias generation, assets, plugin events, nested sets
 * and FLEXI/Joomla tag bridges in one place.
 *
 * @since  5.3
 */
trait TaxonomyWriteTrait
{
    /**
     * Legacy single-record model instance.
     *
     * @var    object|false|null
     */
    private $legacyTaxonomyModel = null;

    /**
     * Legacy FLEXIcontent model class used for persistence.
     *
     * @return  string
     */
    abstract protected function legacyModelClass(): string;

    /**
     * Legacy FLEXIcontent list model class used for deletion.
     *
     * @return  string
     */
    abstract protected function legacyListModelClass(): string;

    /**
     * Get the legacy model, booting the admin bootstrap when needed.
     *
     * @return  object|null
     */
    private function getLegacyTaxonomyModel()
    {
        if ($this->legacyTaxonomyModel === false) {
            return null;
        }

        if (\is_object($this->legacyTaxonomyModel)) {
            return $this->legacyTaxonomyModel;
        }

        try {
            LegacyBootstrap::boot();

            $class                 = $this->legacyModelClass();
            $this->legacyTaxonomyModel = new $class(['ignore_request' => true]);
        } catch (\Throwable $e) {
            $this->legacyTaxonomyModel = false;
            error_log('[FLEXIcontent API] legacy ' . $this->legacyModelClass() . ' unavailable: ' . $e->getMessage());

            return null;
        }

        return $this->legacyTaxonomyModel;
    }

    /**
     * Persist a taxonomy record through the legacy model.
     *
     * @param   array  $data  The data prepared by TaxonomyWriteHelper.
     *
     * @return  integer|false
     */
    public function saveViaLegacy(array $data)
    {
        $legacy = $this->getLegacyTaxonomyModel();

        if ($legacy === null) {
            $this->setError('Legacy FLEXIcontent model is unavailable.');

            return false;
        }

        try {
            $id = (int) ($data['id'] ?? 0);

            // In the API application JPATH_BASE points at api/, so legacy form
            // and field paths do not resolve. Register the administrator paths.
            Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/forms');
            Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/fields');

            // FLEXIcontent's _prepareBind() reads raw 'jform' values for some
            // fieldsets (notably layouts). Mirror the administrator request.
            $app = Factory::getApplication();
            $app->input->post->set('jform', $data);

            $legacy->setId($id);
            $legacy->setState($this->getName() . '.id', $id);
            $legacy->getItem($id);

            $form = $legacy->getForm($data, false);

            if (!$form) {
                $this->setError('Could not create the FLEXIcontent form.');

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

                $this->setError(implode("\n", $errors) ?: 'Invalid data.');

                return false;
            }

            // These values are outside the legacy XML forms or are merged by
            // the administrator controller after validation.
            foreach (['attribs', 'params', 'metadata', 'rules', 'tid', 'jtag_id', 'layouts', 'iflayout'] as $key) {
                if (\array_key_exists($key, $data)) {
                    $validData[$key] = $data[$key];
                }
            }

            $validData['id'] = $id;

            // Never allow an API client to create a core field.
            if ($this->getName() === 'field') {
                $validData['iscore'] = 0;
            }

            if (!$legacy->store($validData)) {
                $this->setError($legacy->getError() ?: 'The record could not be saved.');

                return false;
            }

            $savedId = (int) ($legacy->get('id') ?: $legacy->getState($legacy->getName() . '.id'));

            if (!$savedId) {
                $this->setError('The record was saved without an id.');

                return false;
            }

            return $savedId;
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());

            return false;
        }
    }

    /**
     * Delete a taxonomy record through the legacy list model.
     *
     * @param   integer  $id  The record id.
     *
     * @return  boolean
     */
    public function deleteViaLegacy(int $id): bool
    {
        $legacy = $this->getLegacyTaxonomyModel();

        if ($legacy === null) {
            $this->setError('Legacy FLEXIcontent model is unavailable.');

            return false;
        }

        try {
            LegacyBootstrap::boot();

            $class = $this->legacyListModelClass();
            $list  = new $class(['ignore_request' => true]);

            // The legacy list model only deletes ids whose ACL was checked by
            // its controller. The API controller has already done that check.
            $list->changeable_rows['core.delete'][$id] = 1;

            if (!$list->delete([$id], $legacy)) {
                $this->setError($list->getError() ?: 'The record could not be deleted.');

                return false;
            }

            $this->clearTaxonomyCache();

            return true;
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());

            return false;
        }
    }

    /**
     * Drop cached legacy records after a write.
     *
     * @return  void
     */
    public function clearTaxonomyCache(): void
    {
        $this->_item              = [];
        $this->legacyTaxonomyModel = null;
    }
}
