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
use Joomla\Component\Flexicontent\Api\Legacy\LegacyBootstrap;

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
     * Add published/access read restrictions to a taxonomy-like list query.
     *
     * Used by categories, tags and types, whose publication column is named
     * "published" instead of the "state" column used by content items.
     *
     * @param   QueryInterface     $query           The list query to restrict.
     * @param   DatabaseInterface  $db              The database driver.
     * @param   string             $alias           The alias of the queried table.
     * @param   ?string            $accessColumn    The access column, or null to skip the access check.
     * @param   boolean            $allowNullAccess Whether a NULL access value is visible (Joomla tags bridge).
     *
     * @return  void
     */
    public static function applyPublishedRestrictions(
        QueryInterface $query,
        DatabaseInterface $db,
        string $alias,
        ?string $accessColumn = 'access',
        bool $allowNullAccess = false
    ): void {
        if (self::canManage()) {
            return;
        }

        $query->where($db->quoteName($alias . '.published') . ' = 1');

        if ($accessColumn === null) {
            return;
        }

        $levels = self::getViewLevels();
        $column = $db->quoteName($alias . '.' . $accessColumn);
        $access = $column . ' IN (' . implode(',', $levels) . ')';

        if ($allowNullAccess) {
            $access = '(' . $column . ' IS NULL OR ' . $access . ')';
        }

        $query->where($access);
    }

    /**
     * Restrict an item-count subquery to items readable by the current user.
     *
     * The taxonomy endpoints expose an item_count. For non-managers it must
     * count only published items within their viewing access levels, otherwise
     * the metadata would reveal the existence of hidden content.
     *
     * @param   QueryInterface     $query        The count subquery to restrict.
     * @param   DatabaseInterface  $db           The database driver.
     * @param   string             $alias        Alias of the relation/ext table in the subquery.
     * @param   string             $itemIdColumn Column holding the content item id.
     *
     * @return  void
     */
    public static function applyItemCountRestrictions(
        QueryInterface $query,
        DatabaseInterface $db,
        string $alias,
        string $itemIdColumn
    ): void {
        if (self::canManage()) {
            return;
        }

        $query->join(
                'INNER',
                $db->quoteName('#__content', 'c')
                . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName($alias . '.' . $itemIdColumn)
            )
            ->where($db->quoteName('c.state') . ' = 1')
            ->where($db->quoteName('c.access') . ' IN (' . implode(',', self::getViewLevels()) . ')');
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

    /**
     * Check whether a loaded taxonomy row may be returned to the current user.
     *
     * @param   object   $item            The row.
     * @param   ?string  $accessColumn    Property holding the access level, or null to skip the access check.
     * @param   boolean  $allowNullAccess Whether a NULL access value is visible (Joomla tags bridge).
     *
     * @return  boolean
     */
    public static function isPublishedVisible(object $item, ?string $accessColumn = 'access', bool $allowNullAccess = false): bool
    {
        if (self::canManage()) {
            return true;
        }

        if ((int) ($item->published ?? 0) !== 1) {
            return false;
        }

        if ($accessColumn === null) {
            return true;
        }

        $access = $item->{$accessColumn} ?? null;

        if ($access === null) {
            return $allowNullAccess;
        }

        return \in_array((int) $access, self::getViewLevels(), true);
    }

    /**
     * Load the legacy permission helper once, for write ACL checks.
     *
     * @return  void
     */
    private static function bootWriteAcl(): void
    {
        LegacyBootstrap::boot();
    }

    /**
     * Can the current user create FLEXIcontent items?
     *
     * @return  boolean
     */
    public static function canCreate(): bool
    {
        $user = self::getIdentity();

        if ($user->authorise('core.manage', 'com_flexicontent')) {
            return true;
        }

        self::bootWriteAcl();

        return (bool) \FlexicontentHelperPerm::getPerm()->CanAdd;
    }

    /**
     * Can the current user create items of the given FLEXIcontent type?
     *
     * @param   integer  $typeId  The FLEXIcontent type id.
     *
     * @return  boolean
     */
    public static function canCreateType(int $typeId): bool
    {
        if ($typeId <= 0) {
            return false;
        }

        $user = self::getIdentity();

        if ($user->authorise('core.manage', 'com_flexicontent')) {
            return true;
        }

        // FLEXIcontent checks core.create on the type asset. Do it directly:
        // FlexicontentHelperPerm::checkTypeAccess() short-circuits when no type
        // object is supplied and would therefore grant access to everyone.
        return $user->authorise('core.create', 'com_flexicontent.type.' . $typeId);
    }

    /**
     * Can the current user edit the given item?
     *
     * Mirrors ParentClassItem::canEdit(): FLEXIcontent uses the Joomla content
     * article asset, not a com_flexicontent.item asset.
     *
     * @param   object  $item  The stored item row.
     *
     * @return  boolean
     */
    public static function canEditItem(object $item): bool
    {
        $user = self::getIdentity();

        if ($user->authorise('core.manage', 'com_flexicontent')) {
            return true;
        }

        self::bootWriteAcl();

        $asset   = 'com_content.article.' . (int) $item->id;
        $isOwner = (int) ($item->created_by ?? 0) > 0 && (int) $item->created_by === (int) $user->id;

        $hasTypeEdit     = !empty($item->type_id) && \FlexicontentHelperPerm::checkTypeAccess($item->type_id, 'core.edit');
        $hasTypeEditOwn  = !empty($item->type_id) && \FlexicontentHelperPerm::checkTypeAccess($item->type_id, 'core.edit.own');
        $globalEdit      = $user->authorise('core.edit', 'com_flexicontent');
        $globalEditOwn   = $user->authorise('core.edit.own', 'com_flexicontent');

        return ($globalEdit || $hasTypeEdit) && $user->authorise('core.edit', $asset)
            || ($globalEditOwn || $hasTypeEditOwn) && $user->authorise('core.edit.own', $asset) && $isOwner;
    }

    /**
     * Can the current user delete the given item?
     *
     * @param   object  $item  The stored item row.
     *
     * @return  boolean
     */
    public static function canDeleteItem(object $item): bool
    {
        $user = self::getIdentity();

        if ($user->authorise('core.manage', 'com_flexicontent')) {
            return true;
        }

        $asset   = 'com_content.article.' . (int) $item->id;
        $isOwner = (int) ($item->created_by ?? 0) > 0 && (int) $item->created_by === (int) $user->id;
        $global  = $user->authorise('core.delete', 'com_flexicontent')
            || $user->authorise('core.delete.own', 'com_flexicontent');

        return $user->authorise('core.delete', $asset)
            || ($global || $user->authorise('core.delete.own', $asset)) && $isOwner;
    }

    /**
     * Can the current user change the publication state of the given item?
     *
     * @param   object  $item  The stored item row.
     *
     * @return  boolean
     */
    public static function canEditStateItem(object $item): bool
    {
        $user = self::getIdentity();

        if ($user->authorise('core.manage', 'com_flexicontent')) {
            return true;
        }

        $asset   = 'com_content.article.' . (int) $item->id;
        $isOwner = (int) ($item->created_by ?? 0) > 0 && (int) $item->created_by === (int) $user->id;

        return $user->authorise('core.edit.state', $asset)
            || $user->authorise('core.edit.state.own', $asset) && $isOwner
            || $user->authorise('core.edit.state', 'com_flexicontent')
            || $user->authorise('core.edit.state.own', 'com_flexicontent') && $isOwner;
    }
}
