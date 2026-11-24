<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Model;

use Joomla\CMS\MVC\Model\ItemModel as BaseItemModel;
use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Single FLEXIcontent field model for the API.
 *
 * @since  5.3
 */
class FieldModel extends BaseItemModel
{
    use TaxonomyWriteTrait;

    /**
     * Legacy model class used for persistence.
     *
     * @return  string
     */
    protected function legacyModelClass(): string
    {
        return \FlexicontentModelField::class;
    }

    /**
     * Legacy list model class used for deletion.
     *
     * @return  string
     */
    protected function legacyListModelClass(): string
    {
        return \FlexicontentModelFields::class;
    }

    /**
     * Get one FLEXIcontent field.
     *
     * @param   integer  $pk  The field id (defaults to the 'field.id' state).
     *
     * @return  object|null
     */
    public function getItem($pk = null)
    {
        $pk = (int) ($pk ?: $this->getState('field.id'));

        if (!$pk) {
            return null;
        }

        if (\array_key_exists($pk, (array) $this->_item)) {
            return $this->_item[$pk];
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $query->select([
                'a.id',
                'a.name',
                'a.label',
                'a.field_type',
                'a.description',
                'a.iscore',
                'a.published',
                'a.access',
                'a.ordering',
            ])
            ->from($db->quoteName('#__flexicontent_fields', 'a'))
            ->where($db->quoteName('a.id') . ' = ' . $pk)
            ->where('LEFT(' . $db->quoteName('a.name') . ', 5) <> ' . $db->quote('form_'));

        $row = $db->setQuery($query)->loadObject();

        $this->_item[$pk] = ($row && AccessHelper::isPublishedVisible($row)) ? $row : null;

        return $this->_item[$pk];
    }
}
