<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Helper;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Read access rules shared by the FLEXIcontent API models.
 *
 * The MVP exposes published items to everyone holding a valid Joomla API
 * token, restricted to their Joomla viewing access levels. Users allowed to
 * manage FLEXIcontent keep the legacy behaviour (all states, all access
 * levels), so the admin back office is not affected.
 *
 * @since  5.3
 */
final class AccessHelper
{
    /**
     * Get the current API user.
     *
     * @return  User
     */
    public static function getIdentity(): User
    {
        return Factory::getApplication()->getIdentity();
    }

    /**
     * Is the current user allowed to manage FLEXIcontent records?
     *
     * @return  boolean
     */
    public static function canManage(): bool
    {
        return self::getIdentity()->authorise('core.manage', 'com_flexicontent');
    }

    /**
     * Get the Joomla viewing access levels of the current user.
     *
     * @return  integer[]
     */
    public static function getViewLevels(): array
    {
        $levels = Access::getAuthorisedViewLevels(self::getIdentity()->id);

        return array_values(array_unique(array_map('intval', $levels ?: [1])));
    }

    /**
     * Add the read restrictions to a list query.
     *
     * @param   QueryInterface     $query  The list query to restrict.
     * @param   DatabaseInterface  $db     The database driver.
     * @param   string             $alias  The alias of the content table.
     *
     * @return  void
     */
    public static function applyListRestrictions(QueryInterface $query, DatabaseInterface $db, $alias = 'a'): void
    {
        if (self::canManage()) {
            return;
        }

        $query->where($db->quoteName($alias . '.state') . ' = 1');

        $levels = self::getViewLevels();
        $query->where($db->quoteName($alias . '.access') . ' IN (' . implode(',', $levels) . ')');
    }

    /**
     * Check whether a loaded item may be returned to the current user.
     *
     * @param   object  $item  The item row.
     *
     * @return  boolean
     */
    public static function isItemVisible(object $item): bool
    {
        if (self::canManage()) {
            return true;
        }

        if ((int) ($item->state ?? 0) !== 1) {
            return false;
        }

        return \in_array((int) ($item->access ?? 1), self::getViewLevels(), true);
    }
}
