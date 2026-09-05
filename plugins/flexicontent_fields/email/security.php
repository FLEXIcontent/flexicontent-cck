<?php
defined('_JEXEC') or die('Restricted access');

/** Validation shared by the contact form renderer and submission handler. */
class flexicontent_email_security
{
    public static function recipientKey($itemId, $fieldId, $address, $secret)
    {
        return hash_hmac('sha256', 'contact:' . (int) $itemId . ':' . (int) $fieldId . ':' . $address, $secret);
    }

    public static function recipient($itemId, $field, $values, $key, $secret)
    {
        if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) throw new \RuntimeException('Invalid contact form', 403);
        if (!$values && (int) $field->parameters->get('default_value_use', 0) === 2)
        {
            $values = array(\Joomla\CMS\Language\Text::_($field->parameters->get('default_value', '')));
        }
        foreach ($values as $value)
        {
            $decoded = flexicontent_security::unserialize($value);
            $address = is_array($decoded) ? ($decoded['addr'] ?? '') : $value;
            if (is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL)
                && hash_equals(self::recipientKey($itemId, $field->id, $address, $secret), $key)) return $address;
        }
        throw new \RuntimeException('Contact address is no longer available', 403);
    }

    public static function schema($params)
    {
        $schema = array();
        foreach ((array) $params->get('viewlayout_form_fields', array()) as $field)
        {
            $field = (object) $field;
            $name = \Joomla\CMS\Language\Text::_($field->field_name ?? '');
            if (!is_string($name) || $name === '' || strlen($name) > 100 || preg_match('/[\[\]\x00-\x1f]/', $name))
                throw new \RuntimeException('Invalid contact form configuration', 500);
            if (isset($schema[$name])) throw new \RuntimeException('Duplicate contact form field', 500);
            $schema[$name] = $field;
        }
        if (count($schema) > 64) throw new \RuntimeException('Contact form is too large', 500);
        return $schema;
    }

    public static function validateData($data, $schema)
    {
        if (!is_array($data) || count($data) > 64 || array_diff_key($data, $schema))
            throw new \RuntimeException('Invalid contact fields', 400);
        $clean = array();
        $bytes = 0;
        foreach ($schema as $name => $field)
        {
            $type = $field->field_type ?? '';
            if ($type === 'file' || $type === 'freehtml') continue;
            $value = $data[$name] ?? ($type === 'checkbox' ? array() : '');
            if ($type === 'hidden') $value = preg_replace('#[^a-zA-Z-0-9]#', '', \Joomla\CMS\Language\Text::_($field->field_value ?? ''));
            if ($type === 'checkbox')
            {
                if (!is_array($value) || count($value) > 50) throw new \RuntimeException('Invalid checkbox value', 400);
                foreach ($value as $part) if (!is_string($part)) throw new \RuntimeException('Invalid checkbox value', 400);
            }
            elseif (!is_string($value)) throw new \RuntimeException('Invalid contact value', 400);
            foreach ((array) $value as $part)
            {
                if (strlen($part) > 10000 || strpos($part, "\0") !== false) throw new \RuntimeException('Contact value is too long', 400);
                $bytes += strlen($part);
            }
            if ($bytes > 65536) throw new \RuntimeException('Contact message is too long', 400);
            if (!empty($field->field_required) && ($value === array() || trim((string) (is_array($value) ? implode('', $value) : $value)) === ''))
                throw new \RuntimeException('A required contact field is empty', 400);
            if (in_array($type, array('radio', 'select', 'checkbox'), true))
            {
                $options = array_map(array('\Joomla\CMS\Language\Text', '_'), explode(';;', $field->field_value ?? ''));
                foreach ((array) $value as $part) if ($part !== '' && !in_array($part, $options, true)) throw new \RuntimeException('Invalid contact option', 400);
            }
            if ($value !== '' && $value !== array())
            {
                if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Invalid email address', 400);
                if ($type === 'url' && !flexicontent_security::safeUrl($value, false)) throw new \RuntimeException('Invalid URL', 400);
                if ($type === 'date' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $date) || !checkdate((int) $date[2], (int) $date[3], (int) $date[1]))) throw new \RuntimeException('Invalid date', 400);
                if ($type === 'datetime-local' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/D', $value)) throw new \RuntimeException('Invalid date/time', 400);
                if ($type === 'range')
                {
                    $range = explode(';;', $field->field_value ?? '');
                    if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < (float) ($range[0] ?? 0) || (float) $value > (float) ($range[1] ?? 100)) throw new \RuntimeException('Invalid range value', 400);
                }
            }
            $clean[$name] = is_array($value) ? implode(', ', $value) : trim($value);
        }
        return $clean;
    }

    /** Use PHP's server-generated upload paths; never create a public staging folder. */
    public static function attachments($files, $schema)
    {
        $files = $files ?: array();
        if (!is_array($files) || array_diff_key($files, $schema)) throw new \RuntimeException('Unexpected attachment', 400);
        $result = array();
        $total = 0;
        foreach ($schema as $name => $field)
        {
            if (($field->field_type ?? '') !== 'file')
            {
                if (isset($files[$name])) throw new \RuntimeException('Unexpected attachment', 400);
                continue;
            }
            $group = $files[$name] ?? array();
            if (!is_array($group)) throw new \RuntimeException('Invalid attachments', 400);
            $options = explode(';;', $field->field_value ?? '');
            $max = ($options[1] ?? '') === 'multiple' ? min(5, max(1, (int) ($options[2] ?? 5))) : 1;
            $count = 0;
            foreach ($group as $file)
            {
                if (!is_array($file) || !isset($file['error'])) throw new \RuntimeException('Invalid attachment', 400);
                if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) continue;
                if (++$count > $max || count($result) >= 5) throw new \RuntimeException('Too many attachments', 400);
                if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name']))
                    throw new \RuntimeException('Attachment upload failed', 400);
                $size = filesize($file['tmp_name']);
                $total += $size;
                if (!$size || $size > 5 * 1024 * 1024 || $total > 10 * 1024 * 1024) throw new \RuntimeException('Attachment size limit exceeded', 400);
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (!self::attachmentTypeAllowed($file['name'] ?? '', $mime, $options[0] ?? '')) throw new \RuntimeException('Attachment type is not allowed', 400);
                $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename(str_replace('\\', '/', $file['name'])));
                $result[] = array($file['tmp_name'], substr($filename, -150));
            }
            if (!empty($field->field_required) && !$count) throw new \RuntimeException('A required attachment is missing', 400);
        }
        return $result;
    }

    public static function attachmentTypeAllowed($filename, $mime, $accept)
    {
        if (!is_string($filename) || strlen($filename) > 255) return false;
        $allowed = array('pdf' => array('application/pdf'), 'txt' => array('text/plain'),
            'csv' => array('text/plain', 'text/csv'), 'jpg' => array('image/jpeg'), 'jpeg' => array('image/jpeg'),
            'png' => array('image/png'), 'gif' => array('image/gif'), 'webp' => array('image/webp'));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) return false;
        if (trim($accept) === '') return true;
        foreach (explode(',', strtolower($accept)) as $entry)
        {
            $entry = trim($entry);
            if ($entry === '.' . $extension || $entry === $mime || ($entry === 'image/*' && strpos($mime, 'image/') === 0)) return true;
        }
        return false;
    }

    public static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
