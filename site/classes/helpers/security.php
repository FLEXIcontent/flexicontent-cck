<?php
defined('_JEXEC') or die('Restricted access');

/** Security checks shared by field plugins and their public handlers. */
class flexicontent_security
{
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
        $scheme = parse_url($value, PHP_URL_SCHEME);
        if ($scheme === false) return '';
        if (in_array(strtolower((string) $scheme), array('http', 'https', 'ftp', 'ftps'), true) || substr($value, 0, 2) === '//')
        {
            $host = parse_url($value, PHP_URL_HOST);
            if (!$host || ($host[0] === '[' && !filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))) return '';
        }
        if ($scheme !== null && $scheme !== false)
        {
            return in_array(strtolower($scheme), $schemes, true) ? $value : '';
        }
        return $relative ? $value : '';
    }

    public static function isContainedPath($path, $base)
    {
        $path = str_replace('\\', '/', $path);
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
        if (DIRECTORY_SEPARATOR === '\\') { $path = strtolower($path); $base = strtolower($base); }
        return strpos($path, $base) === 0;
    }

    /** A saved form retry must not turn a client-supplied id into an issued id. */
    public static function isIssuedTemporaryItemId($app, $id, $option = 'com_flexicontent')
    {
        if (!is_string($id) || !preg_match('/^_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}_[0-9a-f.]{13,}$/D', $id)) return false;
        $issued = (array) $app->getUserState($option . '.edit.item.active_tmp_itemids', array());
        return isset($issued[$id]) && (int) $issued[$id] >= time() - 86400;
    }

    /** Reuse only session-issued IDs; retry form data is not an authority registry. */
    public static function getOrCreateTemporaryItemId($app, $option = 'com_flexicontent')
    {
        $stateKey = $option . '.edit.item.unique_tmp_itemid';
        $id = $app->getUserState($stateKey);
        if (!self::isIssuedTemporaryItemId($app, $id, $option))
        {
            $id = date('_Y_m_d_H_i_s_') . bin2hex(random_bytes(16));
        }

        // Embedded wrappers save under their component's namespace, while upload
        // removal always runs through com_flexicontent. Record verified issuance
        // in both namespaces; never promote an unchecked retry value here.
        $now = time();
        foreach (array_unique(array($option, 'com_flexicontent')) as $namespace)
        {
            $registryKey = $namespace . '.edit.item.active_tmp_itemids';
            $issued = (array) $app->getUserState($registryKey, array());
            $issued = array_filter($issued, function ($timestamp) use ($now) {
                return (int) $timestamp >= $now - 86400;
            });
            unset($issued[$id]);
            $issued[$id] = $now;
            if (count($issued) > 20)
            {
                asort($issued);
                $issued = array_slice($issued, -20, null, true);
            }
            $app->setUserState($registryKey, $issued);
        }
        $app->setUserState($stateKey, $id);
        return $id;
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
        $db = \Joomla\CMS\Factory::getDbo();
        $user = \Joomla\CMS\Factory::getUser();
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
        if ($user->authorise('core.admin')) return;
        $old = new \Joomla\Registry\Registry($old);
        $new = new \Joomla\Registry\Registry($new);
        $settings = array(
            array('auto_title', 2, 'auto_title_code'),
            array('format_output', -1, 'output_custom_func'),
            array('default_image_custom', 2, 'default_image_custom_code'),
            array('auto_value', 2, 'auto_value_code'),
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

    /** A locked, bounded IP bucket prevents bypass by creating a fresh session. */
    public static function consumeMailQuota($scope, $limit = 5, $window = 900)
    {
        $app = \Joomla\CMS\Factory::getApplication();
        $directory = rtrim($app->get('tmp_path', JPATH_ROOT . '/tmp'), '/\\');
        $key = hash_hmac('sha256', $scope . ':' . ($_SERVER['REMOTE_ADDR'] ?? ''), $app->get('secret'));
        // A fixed number of buckets bounds disk use; colliding clients share a limit.
        $path = $directory . '/flexicontent-mail-' . substr($key, 0, 3) . '.php';
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
}
