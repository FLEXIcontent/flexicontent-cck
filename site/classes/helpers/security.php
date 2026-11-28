<?php
defined('_JEXEC') or die('Restricted access');

/** Security checks shared by field plugins and their public handlers. */
class flexicontent_security
{
    /** Return the active submission menu only when it can be used for this type. */
    public static function getItemSubmissionMenu($app, $typeId)
    {
        if (!$app->isClient('site')) return null;

        $menus = $app->getMenu();
        $menu = $menus->getActive();
        if (!$menu || ($menu->query['option'] ?? '') !== 'com_flexicontent'
            || ($menu->query['view'] ?? '') !== FLEXI_ITEMVIEW
            || ($menu->query['layout'] ?? '') !== 'form'
            || !$menus->authorise($menu->id))
        {
            return null;
        }

        $menuType = (int) ($menu->query['typeid'] ?? 0);
        return !$menuType || $menuType === (int) $typeId ? $menu : null;
    }

    /** Only a submission menu with a fixed type can waive the type's create ACL. */
    public static function canOverrideItemSubmissionType($menu, $user)
    {
        if (!$menu || empty($menu->query['typeid'])) return false;
        $mode = (int) $menu->getParams()->get('allowunauthorize', 0);
        return $mode === 1 || ($mode === 2 && !$user->guest);
    }

    /** A category override may serve guests only on menus that explicitly allow them. */
    public static function canUseSubmissionCategoryOverride($menu, $user)
    {
        return $menu && (!$user->guest || (int) $menu->getParams()->get('allowunauthorize', 0) === 1);
    }

    /** Submission settings may only be reused for the same menu, configuration and rendered type. */
    public static function getItemSubmitConfiguration($app, $menu, $typeId, $hash)
    {
        if (!$menu || !is_string($hash) || !preg_match('/^[a-f0-9]{32}$/D', $hash)) return array();

        $configs = $app->getSession()->get('item_submit_conf', array(), 'flexicontent');
        $config = $configs[$hash] ?? null;
        if (!is_array($config) || ($config['menu_id'] ?? null) !== (int) $menu->id
            || ($config['menu_params'] ?? null) !== hash('sha256', $menu->getParams()->toString())
            || !in_array((int) $typeId, $config['type_ids'] ?? array(), true))
        {
            return array();
        }
        return $config;
    }

    /** Accept only arrays and scalar data; never let PHP objects into field values. */
    public static function isDataValue($value, $depth = 0, &$nodes = null)
    {
        if ($nodes === null) $nodes = 0;
        if ($depth > 64 || ++$nodes > 100000 || is_object($value) || is_resource($value))
        {
            return false;
        }
        if (is_array($value))
        {
            foreach ($value as $child)
            {
                if (!self::isDataValue($child, $depth + 1, $nodes)) return false;
            }
        }
        return true;
    }

    /** Legacy serialized scalar/array support without object instantiation. */
    public static function unserialize($value)
    {
        if (!is_string($value) || $value === '' || strlen($value) > 8388608) return false;
        // Validate the wire format before invoking PHP. In particular, allowed_classes
        // does not block enum autoloading, so reject all class-bearing tokens here.
        $offset = 0;
        $nodes = 0;
        if (!self::scanSerializedData($value, $offset, $nodes) || $offset !== strlen($value)) return false;
        try
        {
            $decoded = @unserialize($value, array('allowed_classes' => false, 'max_depth' => 64));
            return self::isDataValue($decoded) ? $decoded : false;
        }
        catch (\Throwable $error)
        {
            return false;
        }
    }

    private static function scanSerializedData($data, &$offset, &$nodes, $depth = 0, $key = false)
    {
        if ($depth > 64 || ++$nodes > 100000 || !isset($data[$offset])) return false;
        $type = $data[$offset++];
        if ($key && $type !== 'i' && $type !== 's') return false;
        if ($type === 'N') return substr($data, $offset++, 1) === ';';
        if (!in_array($type, array('b', 'i', 'd', 's', 'a', 'R'), true) || substr($data, $offset++, 1) !== ':') return false;
        if ($type === 's' || $type === 'a')
        {
            $end = strpos($data, ':', $offset);
            if ($end === false || $end - $offset > 10) return false;
            $number = substr($data, $offset, $end - $offset);
            if ($number === '' || !ctype_digit($number)) return false;
            $number = (int) $number;
            $offset = $end + 1;
            if ($type === 's')
            {
                if (substr($data, $offset++, 1) !== '"' || $number > strlen($data) - $offset) return false;
                $offset += $number;
                if (substr($data, $offset, 2) !== '";') return false;
                $offset += 2;
                return true;
            }
            if ($number > 50000 || substr($data, $offset++, 1) !== '{') return false;
            for ($i = 0; $i < $number; ++$i)
            {
                if (!self::scanSerializedData($data, $offset, $nodes, $depth + 1, true)
                    || !self::scanSerializedData($data, $offset, $nodes, $depth + 1)) return false;
            }
            return substr($data, $offset++, 1) === '}';
        }
        $end = strpos($data, ';', $offset);
        if ($end === false || $end - $offset > 64) return false;
        $number = substr($data, $offset, $end - $offset);
        $offset = $end + 1;
        if ($type === 'b') return $number === '0' || $number === '1';
        if ($type === 'R') return ctype_digit($number) && (int) $number > 0;
        if ($type === 'i') return (bool) preg_match('/^-?\d+$/D', $number);
        return (bool) preg_match('/^(?:-?INF|NAN|[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[Ee][+-]?\d+)?)$/D', $number);
    }

    /** Validate browser URL semantics separately from HTML attribute escaping. */
    public static function safeUrl($value, $relative = true, $schemes = array('http', 'https'))
    {
        if (!is_string($value)) return '';
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || preg_match('/[\x00-\x20\x7f\\\\<>"\']/', $value)) return '';
        // Browser schemes are lexical. PHP can interpret a colon followed by digits as host:port.
        $scheme = preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $parts) ? strtolower($parts[1]) : null;
        if ($scheme !== null && !in_array($scheme, $schemes, true)) return '';
        if ($scheme === null && preg_match('~^[^/?#]*:~', $value)) return '';
        $networkScheme = in_array($scheme, array('http', 'https', 'ftp', 'ftps'), true);
        if ($networkScheme && substr($value, strlen($scheme) + 1, 2) !== '//') return '';
        if ($networkScheme || substr($value, 0, 2) === '//')
        {
            $host = parse_url($value, PHP_URL_HOST);
            if (!$host || ($host[0] === '[' && !filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))) return '';
        }
        return $scheme !== null || $relative ? $value : '';
    }

    /**
     * A user who writes content: may create or edit items somewhere. The record pickers that the site offers
     * to editors (item, category) are for these users, a login alone is not enough.
     */
    public static function isContentAuthor($user)
    {
        if (!$user || !empty($user->guest) || empty($user->id)) return false;

        foreach (array('core.create', 'core.edit', 'core.edit.own') as $action)
        {
            if ($user->authorise($action, 'com_flexicontent') || $user->getAuthorisedCategories('com_content', $action)) return true;
        }

        return false;
    }

    /**
     * Check that the current user may make a file record a value of a file field of an item.
     *
     * A file that is already a value of that field in that item is kept. Otherwise the rule of the file picker applies:
     * own uploads, or any file for users who may view all files. A file that the same field already offers in another
     * item that the user may view is accepted too (this is what a copy of an item needs).
     */
    public static function canAssignFileToField($field_id, $file_id, $item_id, $user = null)
    {
        $field_id = (int) $field_id; $file_id = (int) $file_id; $item_id = (int) $item_id;
        if ($file_id <= 0 || $field_id <= 0) return false;

        $user = $user ?: \Joomla\CMS\Factory::getApplication()->getIdentity();
        $db   = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        if ($item_id > 0 && $db->setQuery('SELECT 1 FROM #__flexicontent_fields_item_relations WHERE field_id = ' . $field_id
            . ' AND item_id = ' . $item_id . ' AND value = ' . $db->quote((string) $file_id), 0, 1)->loadResult())
        {
            return true;
        }

        if (!$user) return false;

        if ($user->authorise('core.admin', 'com_flexicontent') || $user->authorise('flexicontent.viewallfiles', 'com_flexicontent'))
        {
            return true;
        }

        $uploaded_by = $db->setQuery('SELECT uploaded_by FROM #__flexicontent_files WHERE id = ' . $file_id)->loadResult();
        if ($uploaded_by === null) return false;
        if ((int) $uploaded_by === (int) $user->id) return true;

        $levels = implode(',', array_map('intval', $user->getAuthorisedViewLevels())) ?: '0';

        return (bool) $db->setQuery('SELECT 1 FROM #__flexicontent_fields_item_relations AS rel'
            . ' JOIN #__content AS c ON c.id = rel.item_id'
            . ' WHERE rel.field_id = ' . $field_id . ' AND rel.value = ' . $db->quote((string) $file_id)
            . ' AND c.access IN (' . $levels . ') AND (c.state IN (1, -5) OR (c.created_by = ' . (int) $user->id . ' AND c.created_by > 0))', 0, 1)->loadResult();
    }

    /**
     * Normalise a folder given relative to the site root and check that it is an existing folder inside the site.
     *
     * @return  string|false  The folder without leading and trailing separators (empty for the site root), false if refused
     */
    public static function siteRelativeFolder($folder)
    {
        if (!is_string($folder) || strpos($folder, "\0") !== false) return false;

        $folder = trim(str_replace('\\', '/', $folder), "/ \t\r\n");
        if ($folder === '') return '';

        // No drive letters or stream wrappers, no "." or ".." segments
        if (strpos($folder, ':') !== false || preg_match('#(^|/)\.\.?(/|$)#', $folder)) return false;

        $root = realpath(JPATH_SITE);
        $real = $root !== false ? realpath($root . '/' . $folder) : false;

        return $real !== false && is_dir($real) && self::isContainedPath($real, $root) ? $folder : false;
    }

    public static function isContainedPath($path, $base)
    {
        $path = str_replace('\\', '/', $path);
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
        if (DIRECTORY_SEPARATOR === '\\') { $path = strtolower($path); $base = strtolower($base); }
        return strpos($path, $base) === 0;
    }

    /**
     * Check that a temporary upload ID was issued by this site to the current user
     * (or, for guests, the current session) within the last 24 hours.
     *
     * The ID carries its own signature, so the check reads no session data. Another
     * request of the same session that ends later and writes back an older copy of
     * the session (Joomla does not lock sessions) can no longer invalidate an open
     * form. A client-chosen or altered ID still fails.
     */
    public static function isIssuedTemporaryItemId($app, $id, $option = 'com_flexicontent')
    {
        if (!is_string($id) || !preg_match('/^(_(\d{4})_(\d{2})_(\d{2})_(\d{2})_(\d{2})_(\d{2})_[0-9a-f]{32})([0-9a-f]{32})$/D', $id, $m)) return false;
        $issuedAt = gmmktime((int) $m[5], (int) $m[6], (int) $m[7], (int) $m[3], (int) $m[4], (int) $m[2]);
        if ($issuedAt === false || $issuedAt > time() + 300 || $issuedAt < time() - 86400) return false;
        return hash_equals(self::signTemporaryItemId($app, $m[1]), $m[8]);
    }

    /** Reuse the ID kept for a failed save's retry only if it verifies; otherwise issue a new one. */
    public static function getOrCreateTemporaryItemId($app, $option = 'com_flexicontent')
    {
        $stateKey = $option . '.edit.item.unique_tmp_itemid';
        $id = $app->getUserState($stateKey);
        if (!self::isIssuedTemporaryItemId($app, $id, $option))
        {
            // Keep the "_Y_m_d_" start: old temporary folders are cleaned up by that date.
            $base = gmdate('_Y_m_d_H_i_s_') . bin2hex(random_bytes(16));
            $id = $base . self::signTemporaryItemId($app, $base);
        }
        $app->setUserState($stateKey, $id);
        return $id;
    }

    /** Signature of a temporary upload ID, bound to the user (guests: the session) with the site secret. */
    private static function signTemporaryItemId($app, $base)
    {
        $user = (method_exists($app, 'getIdentity') ? $app->getIdentity() : null) ?: \Joomla\CMS\Factory::getApplication()->getIdentity();
        $session = method_exists($app, 'getSession') ? $app->getSession() : \Joomla\CMS\Factory::getApplication()->getSession();
        $owner = $user->guest ? 'session:' . $session->getId() : 'user:' . (int) $user->id;
        return substr(hash_hmac('sha256', 'flexicontent.unique_tmp_itemid|' . $owner . '|' . $base, (string) $app->get('secret')), 0, 32);
    }

    public static function canViewItemState($item, $user)
    {
        $now = time();
        $up = empty($item->publish_up) || $item->publish_up === '0000-00-00 00:00:00' ? 0 : strtotime($item->publish_up . ' UTC');
        $down = empty($item->publish_down) || $item->publish_down === '0000-00-00 00:00:00' ? PHP_INT_MAX : strtotime($item->publish_down . ' UTC');
        if (in_array((int) $item->state, array(1, -5), true) && $up <= $now && $down > $now) return true;
        $asset = 'com_content.article.' . (int) $item->id;
        return !$user->guest && ($user->authorise('core.edit', $asset)
            || ((int) $item->created_by === (int) $user->id && $user->authorise('core.edit.own', $asset)));
    }

    /** Resolve a field from its actual item/type, checking every viewing boundary. */
    public static function loadItemField($itemId, $fieldId, $fieldType)
    {
        $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $user = \Joomla\CMS\Factory::getApplication()->getIdentity();
        $levels = implode(',', array_unique(array_merge(array(0), array_map('intval', $user->getAuthorisedViewLevels()))));
        $item = $db->setQuery('SELECT i.*, e.type_id FROM #__content AS i'
            . ' INNER JOIN #__flexicontent_items_ext AS e ON e.item_id = i.id'
            . ' INNER JOIN #__flexicontent_types AS t ON t.id = e.type_id'
            . ' INNER JOIN #__categories AS c ON c.id = i.catid'
            . ' WHERE i.id = ' . (int) $itemId . ' AND i.access IN (' . $levels . ')'
            . ' AND t.published = 1 AND t.access IN (' . $levels . ')'
            . ' AND c.published = 1 AND c.access IN (' . $levels . ')'
            . ' AND NOT EXISTS (SELECT 1 FROM #__categories AS p WHERE p.lft <= c.lft AND p.rgt >= c.rgt'
            . ' AND p.id <> 1 AND (p.published <> 1 OR p.access NOT IN (' . $levels . ')))')->loadObject();
        if (!$item || !self::canViewItemState($item, $user)) throw new \RuntimeException('Item not available', 403);
        $field = $db->setQuery('SELECT f.* FROM #__flexicontent_fields AS f'
            . ' INNER JOIN #__flexicontent_fields_type_relations AS r ON r.field_id = f.id'
            . ' WHERE f.id = ' . (int) $fieldId . ' AND r.type_id = ' . (int) $item->type_id
            . ' AND f.field_type = ' . $db->quote($fieldType)
            . ' AND f.published = 1 AND f.access IN (' . $levels . ')')->loadObject();
        if (!$field) throw new \RuntimeException('Field not available', 403);
        $field->parameters = new \Joomla\Registry\Registry($field->attribs);
        return array($item, $field);
    }

    /** Changing executable configuration is equivalent to deploying PHP code. */
    public static function assertTrustedConfigurationChange($old, $new, $user)
    {
        if ($user->authorise('core.admin')) return $new;
        $submitted = $new;
        $old = new \Joomla\Registry\Registry($old);
        $new = new \Joomla\Registry\Registry($new);
        $settings = array(
            array('auto_title', 2, 'auto_title_code'),
            array('format_output', -1, 'output_custom_func'),
            array('default_image_custom', 2, 'default_image_custom_code'),
            array('auto_value', 2, 'auto_value_code'),
            array('auto_filename', 2, 'auto_filename_code'),
        );
        foreach ($settings as $setting)
        {
            list($mode, $active, $code) = $setting;
            if (((int) $old->get($mode, 0) === $active || (int) $new->get($mode, 0) === $active)
                && ((int) $old->get($mode, 0) !== (int) $new->get($mode, 0)
                    || self::comparablePhpConfiguration((string) $old->get($code, '')) !== self::comparablePhpConfiguration((string) $new->get($code, ''))))
            {
                throw new \RuntimeException('Only Super Users may change executable PHP configuration', 403);
            }
        }
        $restoredQuery = false;
        foreach (array(
            array('sql_mode', 'field_elements', array()),
            array('filter_customize_options', 'filter_custom_options', array(1)),
            array('select_field_mode', 'select_field_elements', array(2, -2)),
        ) as $setting)
        {
            list($mode, $query, $sqlModes) = $setting;
            $oldMode = (int) $old->get($mode, 0);
            $newMode = (int) $new->get($mode, 0);
            $isSql = $sqlModes ? (in_array($oldMode, $sqlModes, true) || in_array($newMode, $sqlModes, true)) : ($oldMode || $newMode);
            if (!$isSql) continue;

            $oldQuery = $old->get($query, '');
            $newQuery = $new->get($query, '');
            if ($oldMode !== $newMode || !is_string($oldQuery) || !is_string($newQuery)
                || str_replace(array("\r\n", "\r"), "\n", $oldQuery) !== str_replace(array("\r\n", "\r"), "\n", $newQuery))
            {
                throw new \RuntimeException('Only Super Users may change SQL field configuration', 403);
            }

            // Accept browser newline conversion while keeping the stored query byte-for-byte.
            if ($oldQuery !== $newQuery)
            {
                $new->set($query, $oldQuery);
                $restoredQuery = true;
            }
        }
        return $restoredQuery ? $new->toString() : $submitted;
    }

    /** Ignore browser line-ending conversion only outside PHP string literals. */
    private static function comparablePhpConfiguration($code)
    {
        $normalized = '';
        foreach (token_get_all('<?php ' . $code) as $token)
        {
            if (!is_array($token)) { $normalized .= $token; continue; }
            $text = $token[1];
            if (in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true))
            {
                $text = str_replace(array("\r\n", "\r"), "\n", $text);
            }
            $normalized .= $text;
        }
        return $normalized;
    }

    /** Do not accept GET requests for actions which send mail or issue tokens. */
    public static function requirePostToken()
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')
        {
            throw new \RuntimeException('POST request required', 405);
        }
        if (!\Joomla\CMS\Session\Session::checkToken('post'))
        {
            throw new \RuntimeException('Invalid security token', 403);
        }
    }

    /** Tokens are scoped to their resource, including each member of a download. */
    public static function couponMatchesFile($coupon, $fileId)
    {
        return $coupon && preg_match('/^[a-f0-9]{64}$/D', (string) $coupon->token)
            && (int) $coupon->file_id === (int) $fileId
            && empty($coupon->has_expired) && empty($coupon->has_reached_limit);
    }

    public static function isPreviewablePdf($file)
    {
        return $file && (int) $file->id > 0 && (int) ($file->published ?? 0) === 1
            && strtolower((string) $file->ext) === 'pdf' && in_array((int) $file->url, array(0, 2), true);
    }

    /** Issue a short-lived preview URL under the file picker's access rules. */
    public static function pdfThumbnailUrl($file, $limitByUploader = false)
    {
        if (!class_exists('Imagick') || !is_file(JPATH_PLUGINS . '/flexicontent_fields/file/pdf_thumb.php')) return '';
        $app = \Joomla\CMS\Factory::getApplication();
        $user = $app->getIdentity();
        if ($user->guest || !self::isPreviewablePdf($file))
        {
            return '';
        }

        if ((int) $file->uploaded_by !== (int) $user->id
            && ($limitByUploader || !$user->authorise('flexicontent.viewallfiles', 'com_flexicontent')))
        {
            return '';
        }

        if ((int) $file->access !== 0 && !$user->authorise('core.admin')
            && !in_array((int) $file->access, $user->getAuthorisedViewLevels()))
        {
            return '';
        }

        $secret = (string) $app->get('secret');
        if ($secret === '') return '';

        $expires = (int) (ceil(time() / 300) * 300) + 600;
        $key = self::pdfThumbnailSignature($file, $expires, $secret);
        return \Joomla\CMS\Uri\Uri::root() . 'plugins/flexicontent_fields/file/pdf_thumb.php?'
            . http_build_query(array('id' => (int) $file->id, 'exp' => $expires, 'k' => $key));
    }

    public static function pdfThumbnailSignature($file, $expires, $secret)
    {
        $claims = array((int) $file->id, (int) $expires, (int) $file->access,
            (int) $file->uploaded_by, (int) $file->url, (int) $file->secure, (int) $file->published, (string) $file->filename);
        return hash_hmac('sha256', 'flexicontent.pdfthumb|' . implode('|', $claims), $secret);
    }

    /** Sign the autocomplete display configuration emitted by the module. */
    public static function autocompleteInfoSignature($source, $fields)
    {
        $secret = (string) \Joomla\CMS\Factory::getApplication()->get('secret');
        return hash_hmac('sha256', 'flexicontent.autocomplete.info|' . $source . '|' . $fields, $secret);
    }

    /** Hide raw item properties when the corresponding core field cannot be viewed. */
    public static function redactCoreFields($item)
    {
        $propertiesByField = array(
            'text' => array('text', 'introtext', 'fulltext', 'description'),
            'created_by' => array('author', 'creator', 'created_by_alias'),
            'modified_by' => array('modifier'),
            'created' => array('created'),
            'modified' => array('modified'),
            'title' => array('title'),
            'hits' => array('hits'),
            'version' => array('version', 'current_version'),
            'state' => array('state'),
            'document_type' => array('document_type', 'typename'),
            'categories' => array('categories', 'cats'),
            'tags' => array('tags'),
            'favourites' => array('favourites', 'favs'),
            'voting' => array('voting', 'vote'),
        );

        foreach ($propertiesByField as $name => $properties)
        {
            if (!empty($item->fields[$name]->has_access)) continue;
            foreach ($properties as $property) unset($item->$property);
        }
    }

    /** A locked, bounded IP bucket prevents bypass by creating a fresh session. */
    public static function consumeMailQuota($scope, $limit = 5, $window = 900)
    {
        $app = \Joomla\CMS\Factory::getApplication();
        $directory = rtrim($app->get('tmp_path', JPATH_ROOT . '/tmp'), '/\\');
        $address = @inet_pton($_SERVER['REMOTE_ADDR'] ?? '');
        if ($address !== false && strlen($address) === 16)
        {
            $ipv4Mapped = str_repeat("\0", 10) . "\xff\xff";
            $address = substr($address, 0, 12) === $ipv4Mapped ? substr($address, 12) : substr($address, 0, 8);
        }

        $scopeKey = hash_hmac('sha256', 'flexicontent.mail.scope|' . $scope, $app->get('secret'));
        $clientKey = hash_hmac('sha256', (string) $address, $scopeKey);
        $prefix = $directory . '/flexicontent-mail-' . substr($scopeKey, 0, 16) . '-';

        // Fixed-size client buckets bound disk use. The scope bucket also limits traffic from many networks.
        self::consumeQuotaBucket($prefix . substr($clientKey, 0, 3) . '.php', $limit, $window);
        self::consumeQuotaBucket($prefix . 'site.php', $limit * 20, $window);
    }

    private static function consumeQuotaBucket($path, $limit, $window)
    {
        $handle = @fopen($path, 'c+b');
        if (!$handle || !flock($handle, LOCK_EX))
        {
            if ($handle) fclose($handle);
            throw new \RuntimeException('Mail rate limiter unavailable', 503);
        }
        try
        {
            $stored = stream_get_contents($handle, 4096);
            $state = json_decode(substr($stored, strpos($stored, "\n") + 1), true);
            $now = time();
            if (!is_array($state) || $now - (int) ($state['start'] ?? 0) >= $window)
            {
                $state = array('start' => $now, 'count' => 0);
            }
            if ((int) $state['count'] >= $limit)
            {
                throw new \RuntimeException('Too many mail requests. Please try again later.', 429);
            }
            ++$state['count'];
            rewind($handle);
            $bytes = "<?php die('Restricted access'); ?>\n" . json_encode($state);
            if (!ftruncate($handle, 0) || fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle))
            {
                throw new \RuntimeException('Mail rate limiter unavailable', 503);
            }
        }
        finally
        {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Check that the user may work with the upload folder of an item: the id of a saved item that the user may edit,
     * or the temporary id that this site issued to the user for an item that is not saved yet.
     *
     * "May edit" is the edit right, the edit-own right of the author, or the edit access that the item form keeps for
     * the session (item that stays editable until logoff, edit coupon). Anything else is refused, also an id that is
     * not written the way the site writes it, because the id becomes part of a folder name.
     */
    public static function canUseItemUploadFolder($app, $u_item_id, $user = null)
    {
        if (is_int($u_item_id)) $u_item_id = (string) $u_item_id;
        if (!is_string($u_item_id) || $u_item_id === '' || $u_item_id === '0') return false;

        if (!ctype_digit($u_item_id)) return self::isIssuedTemporaryItemId($app, $u_item_id);

        $item_id = (int) $u_item_id;
        $user = $user ?: (method_exists($app, 'getIdentity') ? $app->getIdentity() : null);
        if (!$user || $item_id <= 0 || (string) $item_id !== $u_item_id) return false;

        $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $created_by = $db->setQuery('SELECT created_by FROM #__content WHERE id = ' . $item_id)->loadResult();
        if ($created_by === null || $created_by === false) return false;

        $asset = 'com_content.article.' . $item_id;
        if ($user->authorise('core.edit', $asset)
            || ((int) $user->id > 0 && (int) $created_by === (int) $user->id && $user->authorise('core.edit.own', $asset)))
        {
            return true;
        }

        $session = method_exists($app, 'getSession') ? $app->getSession() : \Joomla\CMS\Factory::getApplication()->getSession();
        $granted = (array) $session->get('rendered_uneditable', array(), 'flexicontent');

        return !empty($granted[$item_id]);
    }

    /**
     * Check that the current user may make an image of the file manager a value of an image field in DB mode.
     *
     * The name must be the name of a published file record in the folder that the field uses. That record must be an
     * upload of the user. Users who may view all files can use any image, as in the image picker of the field: when
     * the field lists all images and is not limited to the uploader. An image that the same field already shows in an
     * item that the user may view is accepted too (this is what a copy of an item needs); users who may view all files
     * can take it from any item, unless the field is limited to the uploader.
     *
     * Values that the item already has are not looked at here, the caller keeps them.
     */
    public static function canAssignImageToField($field, $filename, $user = null)
    {
        $field_id = isset($field->id) ? (int) $field->id : 0;
        if ($field_id <= 0 || !is_string($filename) || $filename === '') return false;

        $user = $user ?: \Joomla\CMS\Factory::getApplication()->getIdentity();
        if (!$user) return false;

        $db     = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $secure = (int) $field->parameters->get('target_dir', 1) ? 1 : 0;

        // The database compares names without regard to case, the file system may not: only the exact name counts
        $uploaders = array();
        $records = $db->setQuery('SELECT filename, uploaded_by FROM #__flexicontent_files WHERE filename = ' . $db->quote($filename)
            . ' AND secure = ' . $secure . ' AND url = 0 AND published = 1')->loadObjectList();
        foreach ((array) $records as $record)
        {
            if ($record->filename === $filename) $uploaders[] = (int) $record->uploaded_by;
        }
        if (!$uploaders) return false;
        if (in_array((int) $user->id, $uploaders, true)) return true;

        $any_file = !(int) $field->parameters->get('limit_by_uploader', 0)
            && ($user->authorise('core.admin', 'com_flexicontent') || $user->authorise('flexicontent.viewallfiles', 'com_flexicontent'));
        if ($any_file && (int) $field->parameters->get('list_all_media_files', 0)) return true;

        // Values of the field that name the image: a serialized value with this original name, or the name alone
        $levels = implode(',', array_map('intval', $user->getAuthorisedViewLevels())) ?: '0';
        $like   = $db->quote('%' . $db->escape('"originalname";s:' . strlen($filename) . ':"' . $filename . '";', true) . '%', false);
        $values = $db->setQuery('SELECT rel.value FROM #__flexicontent_fields_item_relations AS rel'
            . ($any_file ? '' : ' JOIN #__content AS c ON c.id = rel.item_id')
            . ' WHERE rel.field_id = ' . $field_id . ' AND (rel.value = ' . $db->quote($filename) . ' OR rel.value LIKE ' . $like . ')'
            . ($any_file ? '' : ' AND c.access IN (' . $levels . ') AND (c.state IN (1, -5) OR (c.created_by = ' . (int) $user->id . ' AND c.created_by > 0))'))->loadColumn();
        foreach ((array) $values as $value)
        {
            $data = self::unserialize($value);
            if ((is_array($data) ? ($data['originalname'] ?? null) : $value) === $filename) return true;
        }

        return false;
    }

    /**
     * Check that the user may delete a file record: any file with the right to delete files, an upload of the user with
     * the right to delete own files. This is the rule of the file manager, for code that deletes a file because its
     * value was removed from an item.
     */
    public static function canDeleteFileRecord($file, $user = null)
    {
        $user = $user ?: \Joomla\CMS\Factory::getApplication()->getIdentity();
        if (!$file || !$user) return false;

        if ($user->authorise('flexicontent.deletefile', 'com_flexicontent')) return true;

        return (int) $user->id > 0 && (int) $file->uploaded_by === (int) $user->id
            && $user->authorise('flexicontent.deleteownfile', 'com_flexicontent');
    }

    /** A PDF file starts with its signature. The extension of an upload says nothing about what the file contains. */
    public static function isPdfFile($path)
    {
        if (!is_string($path) || !is_file($path)) return false;

        $handle = @fopen($path, 'rb');
        if (!$handle) return false;
        $head = fread($handle, 5);
        fclose($handle);

        return $head === '%PDF-';
    }

    /**
     * Count one download on a download coupon, unless its limit is used up. One statement checks the limit and counts,
     * so requests that arrive together can not take more downloads than the coupon allows.
     */
    public static function claimDownloadCouponHit($db, $coupon_id)
    {
        $db->setQuery('UPDATE #__flexicontent_download_coupons SET hits = hits + 1'
            . ' WHERE id = ' . (int) $coupon_id . ' AND (hits_limit = -1 OR hits < hits_limit)')->execute();

        return (int) $db->getAffectedRows() > 0;
    }

    /** Context of the row in #__flexicontent_file_usage that says the review below was done. */
    const FILE_LINK_REVIEW_CONTEXT = 'com_flexicontent.recorded_links_review';

    /**
     * Review, once, the download links that were recorded for articles before a link had to pass a rights check.
     *
     * A row of #__flexicontent_file_usage lets everyone who may view the article download the file. Rows are now
     * written only for a file that the saving user may use (a file manager, or the uploader of the file), but rows
     * written earlier were recorded for any file id found in the saved text. Nothing says who added such a link, so
     * the review keeps a row when someone who saved the article could have recorded it under the present rule: the
     * creator, the last editor, or an author of one of its versions is the uploader of the file or may use all files.
     * A row that nobody behind the article could have recorded is removed: the file is then governed by the items it
     * is really assigned to, as if the link had been refused when it was saved.
     *
     * The review cannot tell a link apart that one user added and a file manager saved later: such a row stays.
     *
     * @param   object         $db              The database driver
     * @param   callable|null  $mayUseAllFiles  function (int $userId): bool, default: the rights of a file manager
     *
     * @return  array|null  reviewed (number of rows), removed (list of array(item id, file id)); null when the review
     *                      was done before
     */
    public static function reviewRecordedFileLinks($db, $mayUseAllFiles = null)
    {
        $marker  = $db->quote(self::FILE_LINK_REVIEW_CONTEXT);
        $context = $db->quote('com_content.article');

        if ((int) $db->setQuery('SELECT COUNT(*) FROM #__flexicontent_file_usage WHERE context = ' . $marker)->loadResult() > 0) return null;

        $mayUseAllFiles = $mayUseAllFiles ?: static function ($userId) {
            return \Joomla\CMS\Access\Access::check($userId, 'core.admin', 'com_flexicontent')
                || \Joomla\CMS\Access\Access::check($userId, 'flexicontent.viewallfiles', 'com_flexicontent');
        };

        // Rows of an article or a file that no longer exists are left alone: they give nobody access to a file
        $rows = $db->setQuery('SELECT DISTINCT u.id AS item_id, u.file_id, f.uploaded_by, c.created_by, c.modified_by'
            . ' FROM #__flexicontent_file_usage AS u'
            . ' JOIN #__content AS c ON c.id = u.id'
            . ' JOIN #__flexicontent_files AS f ON f.id = u.file_id'
            . ' WHERE u.context = ' . $context)->loadObjectList() ?: array();

        // Everyone who saved a version of these articles
        $savers = array();
        foreach ($rows as $row) {
            $savers[(int) $row->item_id][(int) $row->created_by] = true;
            $savers[(int) $row->item_id][(int) $row->modified_by] = true;
        }
        foreach (array_chunk(array_keys($savers), 500) as $ids) {
            try {
                $versions = $db->setQuery('SELECT DISTINCT item_id, created_by FROM #__flexicontent_versions'
                    . ' WHERE item_id IN (' . implode(',', array_map('intval', $ids)) . ')')->loadObjectList() ?: array();
            } catch (\Throwable $e) {
                // No versions table: the creator and the last editor are all that is known
                $versions = array();
            }
            foreach ($versions as $version) $savers[(int) $version->item_id][(int) $version->created_by] = true;
        }

        $managers = array();
        $removed  = array();

        foreach ($rows as $row) {
            $users = array_keys($savers[(int) $row->item_id]);
            $keep  = (int) $row->uploaded_by > 0 && in_array((int) $row->uploaded_by, $users, true);

            foreach ($users as $userId) {
                if ($keep || $userId <= 0) continue;
                if (!isset($managers[$userId])) $managers[$userId] = (bool) $mayUseAllFiles($userId);
                $keep = $managers[$userId];
            }
            if ($keep) continue;

            $db->setQuery('DELETE FROM #__flexicontent_file_usage WHERE context = ' . $context
                . ' AND id = ' . (int) $row->item_id . ' AND file_id = ' . (int) $row->file_id)->execute();
            $removed[] = array((int) $row->item_id, (int) $row->file_id);
        }

        // Rows written from now on passed the check when they were saved: the review must not run on them
        $db->setQuery('INSERT INTO #__flexicontent_file_usage (id, context, file_id, prop) VALUES (0, ' . $marker . ', 0, '
            . $db->quote(gmdate('Y-m-d')) . ')')->execute();

        return array('reviewed' => count($rows), 'removed' => $removed);
    }
}
