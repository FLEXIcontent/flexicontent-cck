<?php
/**
 * @package         FLEXIcontent
 * @version         5.1
 *
 * @author          FLEXIcontent team, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright (C) 2025, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\Registry\Registry;

/**
 * Helper class for the FLEXIcontent notification emails.
 *
 * It maps the FLEXIcontent notification scenarios to Joomla mail templates
 * (com_mails / #__mail_templates) and sends the emails through MailTemplate.
 */
class FlexicontentHelperNotificationMail
{
	/**
	 * Prefix used by all FLEXIcontent notification templates.
	 */
	const TEMPLATE_PREFIX = 'com_flexicontent.notification.';

	/**
	 * Cached template definitions.
	 *
	 * @var array|null
	 */
	protected static $definitions = null;

	/**
	 * Whether the templates have already been checked during this request.
	 *
	 * @var bool
	 */
	protected static $templatesChecked = false;

	/**
	 * Cache of category parameters, indexed by category id.
	 *
	 * @var array
	 */
	protected static $categoryParams = array();


	/**
	 * Get the tags that are always available in the notification templates and
	 * that are supplied by FLEXIcontent itself (not by a custom field).
	 *
	 * @return array
	 */
	public static function getCoreTags()
	{
		return array(
			'sitename', 'siteurl', 'title', 'id', 'state', 'creator', 'modifier',
			'editor_name', 'editor_username', 'created', 'modified',
			'categories', 'categories_added', 'categories_removed',
			'view_url', 'edit_url_fe', 'edit_url_be',
			'introtext', 'fulltext', 'notify_text', 'category_text', 'fields',
		);
	}


	/**
	 * Get the template definitions.
	 *
	 * @return array
	 */
	public static function getDefinitions()
	{
		if (static::$definitions !== null)
		{
			return static::$definitions;
		}

		$commonTags = static::getCoreTags();

		static::$definitions = array(
			'new' => array(
				'template_id' => self::TEMPLATE_PREFIX . 'new',
				'subject'     => 'FLEXI_NF_MAIL_SUBJECT_NEW',
				'body'        => 'FLEXI_NF_MAIL_BODY_NEW',
				'tags'        => $commonTags,
			),
			'new_pending' => array(
				'template_id' => self::TEMPLATE_PREFIX . 'new_pending',
				'subject'     => 'FLEXI_NF_MAIL_SUBJECT_NEW_PENDING',
				'body'        => 'FLEXI_NF_MAIL_BODY_NEW_PENDING',
				'tags'        => $commonTags,
			),
			'existing' => array(
				'template_id' => self::TEMPLATE_PREFIX . 'existing',
				'subject'     => 'FLEXI_NF_MAIL_SUBJECT_EXISTING',
				'body'        => 'FLEXI_NF_MAIL_BODY_EXISTING',
				'tags'        => $commonTags,
			),
			'existing_reviewal' => array(
				'template_id' => self::TEMPLATE_PREFIX . 'existing_reviewal',
				'subject'     => 'FLEXI_NF_MAIL_SUBJECT_EXISTING_REVIEWAL',
				'body'        => 'FLEXI_NF_MAIL_BODY_EXISTING_REVIEWAL',
				'tags'        => $commonTags,
			),
			'approval_request' => array(
				'template_id' => self::TEMPLATE_PREFIX . 'approval_request',
				'subject'     => 'FLEXI_NF_MAIL_SUBJECT_APPROVAL_REQUEST',
				'body'        => 'FLEXI_NF_MAIL_BODY_APPROVAL_REQUEST',
				'tags'        => $commonTags,
			),
		);

		return static::$definitions;
	}


	/**
	 * Get the notification type from the saving flags.
	 *
	 * @param   bool  $isNew                     Is this a new item?
	 * @param   bool  $needsPublicationApproval  Does the item need publication approval?
	 * @param   bool  $needsVersionReviewal      Does the item need version reviewal?
	 * @param   bool  $manualApprovalRequest     Is this a manual approval request?
	 *
	 * @return  string
	 */
	public static function getTypeFromFlags($isNew, $needsPublicationApproval, $needsVersionReviewal, $manualApprovalRequest = false)
	{
		if ($manualApprovalRequest)
		{
			return 'approval_request';
		}

		if ($isNew)
		{
			return $needsPublicationApproval ? 'new_pending' : 'new';
		}

		return $needsVersionReviewal ? 'existing_reviewal' : 'existing';
	}


	/**
	 * Get the mail template id for a notification type.
	 *
	 * @param   string  $type  Notification type
	 *
	 * @return  string|null
	 */
	public static function getTemplateId($type)
	{
		$definitions = static::getDefinitions();

		return isset($definitions[$type]) ? $definitions[$type]['template_id'] : null;
	}


	/**
	 * Get the category text parameter name for a notification type.
	 *
	 * @param   string  $type  Notification type
	 *
	 * @return  string|null
	 */
	public static function getCategoryTextParam($type)
	{
		$map = array(
			'new'               => 'cats_text_notify_new',
			'new_pending'       => 'cats_text_notify_new_pending',
			'existing'          => 'cats_text_notify_existing',
			'existing_reviewal' => 'cats_text_notify_existing_reviewal',
			'approval_request'  => 'cats_text_notify_new_pending',
		);

		return isset($map[$type]) ? $map[$type] : null;
	}


	/**
	 * Get the category specific text for a notification type.
	 *
	 * @param   int     $catid  Main category id
	 * @param   string  $type   Notification type
	 *
	 * @return  string
	 */
	public static function getCategoryText($catid, $type)
	{
		$catid = (int) $catid;
		$param = static::getCategoryTextParam($type);

		if (!$catid || !$param)
		{
			return '';
		}

		if (!isset(static::$categoryParams[$catid]))
		{
			$db    = Factory::getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName('params'))
				->from($db->quoteName('#__categories'))
				->where($db->quoteName('id') . ' = ' . $catid);

			static::$categoryParams[$catid] = new Registry((string) $db->setQuery($query)->loadResult());
		}

		return (string) static::$categoryParams[$catid]->get($param, '');
	}


	/**
	 * Build the mail template tags for a list of FlexiContent fields.
	 *
	 * Each published field is exposed as {FIELD_<FIELD_NAME>} and, as a shortcut, as
	 * {<FIELD_NAME>} unless the name conflicts with a core tag such as {TITLE}.
	 * All non-core fields with a non empty value are also available in the {FIELDS} summary tag.
	 *
	 * @param   array  $fields  Field objects (with name, label, value and iscore properties)
	 *
	 * @return  array
	 */
	public static function getFieldTags(array $fields)
	{
		$tags     = array('fields' => '');
		$reserved = array_flip(static::getCoreTags());
		$list     = array();

		foreach ($fields as $field)
		{
			$name    = isset($field->name) ? (string) $field->name : '';
			$tagName = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $name));

			if ($tagName === '')
			{
				continue;
			}

			$value = static::flattenValue(isset($field->value) ? $field->value : null);

			$tags['field_' . $tagName] = $value;

			// Shortcut tag, e.g. {FLOORS}, but never override a core tag like {TITLE}
			if (!isset($reserved[strtolower($tagName)]))
			{
				$tags[$tagName] = $value;
			}

			if (empty($field->iscore) && $value !== '')
			{
				$label  = \Joomla\CMS\Language\Text::_(!empty($field->label) ? $field->label : $name);
				$list[] = $label . ': ' . $value;
			}
		}

		$tags['fields'] = implode("\n", $list);

		return $tags;
	}


	/**
	 * Get the language tag to use for the mail template.
	 *
	 * @param   string  $itemLanguage  Language of the content item
	 *
	 * @return  string
	 */
	public static function getLanguage($itemLanguage = '')
	{
		if ($itemLanguage && $itemLanguage !== '*')
		{
			return $itemLanguage;
		}

		return ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
	}


	/**
	 * Check whether the Joomla mail template API is available.
	 *
	 * @return  bool
	 */
	public static function isAvailable()
	{
		return class_exists('\\Joomla\\CMS\\Mail\\MailTemplate');
	}


	/**
	 * Create the missing FLEXIcontent mail templates.
	 *
	 * Existing templates are never modified, so user customisations are preserved.
	 *
	 * @param   bool  $force  Force checking all templates again
	 *
	 * @return  bool
	 */
	public static function ensureTemplates($force = false)
	{
		if (!static::isAvailable())
		{
			return false;
		}

		if (static::$templatesChecked && !$force)
		{
			return true;
		}

		static::$templatesChecked = true;

		$template_ids = array();

		foreach (static::getDefinitions() as $definition)
		{
			$template_ids[] = $definition['template_id'];

			if (!MailTemplate::getTemplate($definition['template_id'], ''))
			{
				MailTemplate::createTemplate(
					$definition['template_id'],
					$definition['subject'],
					$definition['body'],
					$definition['tags'],
					''
				);
			}
		}

		// Align the 'extension' column with the component name, so that the templates
		// are listed by com_mails: its list query filters on #__extensions.name
		static::syncTemplateExtension($template_ids);

		return true;
	}


	/**
	 * Make sure the extension of the notification templates matches the name
	 * of the component, as stored in #__extensions.
	 *
	 * com_mails only lists the mail templates whose 'extension' is the name of an
	 * enabled extension, while #__extensions.name is 'FLEXIcontent' for this component
	 * (instead of 'com_flexicontent'). Only our own template ids are touched.
	 *
	 * @param   array  $template_ids  Template ids to synchronise
	 *
	 * @return  void
	 */
	protected static function syncTemplateExtension(array $template_ids)
	{
		if (!$template_ids)
		{
			return;
		}

		try
		{
			$db = Factory::getDbo();

			$extension_name = $db->setQuery(
				$db->getQuery(true)
					->select($db->quoteName('name'))
					->from($db->quoteName('#__extensions'))
					->where($db->quoteName('element') . ' = ' . $db->quote('com_flexicontent'))
					->where($db->quoteName('type') . ' = ' . $db->quote('component'))
			)->loadResult();

			if (!$extension_name)
			{
				return;
			}

			$quoted_ids = array();

			foreach ($template_ids as $template_id)
			{
				$quoted_ids[] = $db->quote($template_id);
			}

			$query = $db->getQuery(true)
				->update($db->quoteName('#__mail_templates'))
				->set($db->quoteName('extension') . ' = ' . $db->quote($extension_name))
				->where($db->quoteName('template_id') . ' IN (' . implode(', ', $quoted_ids) . ')')
				->where($db->quoteName('extension') . ' <> ' . $db->quote($extension_name));

			$db->setQuery($query)->execute();
		}
		catch (\Throwable $e)
		{
			// Not critical: the emails are still sent, only the com_mails listing may be affected
		}
	}


	/**
	 * Send a notification email using a Joomla mail template.
	 *
	 * @param   string  $type         Notification type
	 * @param   array   $recipients   Recipient email addresses
	 * @param   array   $data         Tag replacement data
	 * @param   string  $language     Template language
	 * @param   bool    $sendAsBcc    Send recipients as BCC
	 *
	 * @return  bool|null  True on success, false on failure, null when the template system is unavailable
	 */
	public static function send($type, $recipients, array $data, $language = '', $sendAsBcc = false)
	{
		$recipients = (array) $recipients;

		if (!static::isAvailable())
		{
			return null;
		}

		$templateId = static::getTemplateId($type);

		if (!$templateId)
		{
			return null;
		}

		// Make sure the templates exist, but do not fail the whole notification if this is not possible
		try
		{
			static::ensureTemplates();
		}
		catch (\Throwable $e)
		{
			// Ignore, the legacy fallback will be used below when the template cannot be loaded
		}

		$language = static::getLanguage($language);

		// Template missing (creation failed or table unavailable), let the caller use its legacy fallback
		try
		{
			$template = MailTemplate::getTemplate($templateId, $language);
		}
		catch (\Throwable $e)
		{
			return null;
		}

		if (!$template)
		{
			return null;
		}

		try
		{
			$app    = Factory::getApplication();
			$mailer = new MailTemplate($templateId, $language);

			$mailer->addTemplateData($data);

			// Escape all user supplied values in the HTML version of the email
			$mailer->addUnsafeTags(array_keys($data));

			$from = $app->get('mailfrom');
			$seen = array();

			if ($sendAsBcc)
			{
				// The site email is the main recipient, to avoid deliverability problems
				$mailer->addRecipient($from);
				$seen[$from] = 1;

				foreach ($recipients as $email)
				{
					$email = trim((string) $email);

					if (!$email || isset($seen[$email]))
					{
						continue;
					}

					$seen[$email] = 1;
					$mailer->addRecipient($email, null, 'bcc');
				}
			}
			else
			{
				foreach ($recipients as $email)
				{
					$email = trim((string) $email);

					if (!$email || isset($seen[$email]))
					{
						continue;
					}

					$seen[$email] = 1;
					$mailer->addRecipient($email);
				}
			}

			if (!$seen)
			{
				return false;
			}

			return $mailer->send();
		}
		catch (\Throwable $e)
		{
			$app = Factory::getApplication();
			$app->enqueueMessage('FLEXIcontent notification mail: ' . $e->getMessage(), 'warning');

			return false;
		}
	}


	/**
	 * Flatten a field value into a plain text string.
	 *
	 * @param   mixed  $value  Field value
	 *
	 * @return  string
	 */
	public static function flattenValue($value)
	{
		if (is_object($value))
		{
			$value = (array) $value;
		}

		if (is_array($value))
		{
			$parts = array();

			foreach ($value as $part)
			{
				$part = static::flattenValue($part);

				if ($part !== '')
				{
					$parts[] = $part;
				}
			}

			return implode(', ', $parts);
		}

		if (is_bool($value))
		{
			return $value ? '1' : '';
		}

		if (!is_scalar($value) || $value === null)
		{
			return '';
		}

		$value = (string) $value;

		// Try to decode serialized or JSON encoded values stored by some field types
		if (strlen($value) > 2 && $value[0] === 'a' && $value[1] === ':')
		{
			$decoded = @unserialize($value, array('allowed_classes' => false));

			if ($decoded !== false && $decoded !== $value)
			{
				return static::flattenValue($decoded);
			}
		}

		if (strlen($value) > 1 && ($value[0] === '{' || $value[0] === '['))
		{
			$decoded = json_decode($value, true);

			if (json_last_error() === JSON_ERROR_NONE)
			{
				return static::flattenValue($decoded);
			}
		}

		return $value;
	}
}
