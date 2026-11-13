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
	 * Maximum size of a template field that will be processed for conditions.
	 */
	const CONDITION_MAX_LENGTH = 1048576;

	/**
	 * Maximum nesting depth of conditional blocks.
	 */
	const CONDITION_MAX_DEPTH = 10;

	/**
	 * Maximum number of conditional blocks in a template field.
	 */
	const CONDITION_MAX_BLOCKS = 500;

	/**
	 * Maximum length of a single condition expression.
	 */
	const CONDITION_MAX_EXPRESSION = 1000;

	/**
	 * Maximum number of diagnostics kept for the debug output.
	 */
	const CONDITION_MAX_DIAGNOSTICS = 50;

	/**
	 * Condition diagnostics of the last send.
	 *
	 * @var array
	 */
	protected static $lastConditionErrors = array();


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
			$tagName = static::normalizeFieldName($name);

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
	 * Build the raw field context used to evaluate the template conditions.
	 *
	 * Unlike getFieldTags(), the values are not flattened: arrays and objects are
	 * kept as is, so multi-value fields can be tested with "at least one" semantics.
	 *
	 * @param   array  $fields  Field objects (with name and value properties)
	 *
	 * @return  array
	 */
	public static function getFieldConditionContext(array $fields)
	{
		$context = array();

		foreach ($fields as $field)
		{
			$name    = isset($field->name) ? (string) $field->name : '';
			$tagName = static::normalizeFieldName($name);

			if ($tagName === '')
			{
				continue;
			}

			$context['FIELD_' . $tagName] = isset($field->value) ? $field->value : null;
		}

		return $context;
	}


	/**
	 * Normalize a field name the same way as the {FIELD_<NAME>} tags.
	 *
	 * @param   string  $name  Field name
	 *
	 * @return  string
	 */
	public static function normalizeFieldName($name)
	{
		return strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', (string) $name));
	}


	/**
	 * Get the diagnostics of the conditions evaluated during the last send.
	 *
	 * @return  array
	 */
	public static function getLastConditionErrors()
	{
		return static::$lastConditionErrors;
	}


	/**
	 * Check whether the FlexiContent mail template subclass can be used.
	 *
	 * Conditions are rendered by getTemplate() during a scoped send(), before
	 * Joomla translates the template fields and substitutes user-supplied tags.
	 *
	 * @return  bool
	 */
	public static function canRenderConditions()
	{
		if (!static::isAvailable() || !class_exists('ReflectionMethod'))
		{
			return false;
		}

		try
		{
			foreach (array('getTemplate', 'send') as $name)
			{
				$method = new \ReflectionMethod('\\Joomla\\CMS\\Mail\\MailTemplate', $name);

				if ($method->isPrivate() || $method->isFinal())
				{
					return false;
				}
			}

			return true;
		}
		catch (\Throwable $e)
		{
			return false;
		}
	}


	/**
	 * Create the mailer instance, using the condition aware subclass when needed.
	 *
	 * @param   string  $templateId         Template id
	 * @param   string  $language           Template language
	 * @param   array   $conditionContext   Raw condition context
	 *
	 * @return  MailTemplate
	 */
	protected static function createMailer($templateId, $language, array $conditionContext = array())
	{
		if ($conditionContext && static::canRenderConditions())
		{
			if (!class_exists('FlexicontentMailTemplate', false))
			{
				require_once __DIR__ . '/notificationmailtemplate.php';
			}

			$mailer = new \FlexicontentMailTemplate($templateId, $language);
			$mailer->setConditionContext($conditionContext);

			return $mailer;
		}

		return new MailTemplate($templateId, $language);
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
	 * @param   bool    $sendAsBcc          Send recipients as BCC
	 * @param   array   $conditionContext   Raw context used to evaluate the {if ...} blocks
	 *
	 * @return  bool|null  True on success, false on failure, null when the template system is unavailable
	 */
	public static function send($type, $recipients, array $data, $language = '', $sendAsBcc = false, array $conditionContext = array())
	{
		$recipients = (array) $recipients;

		static::$lastConditionErrors = array();

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
			$mailer = static::createMailer($templateId, $language, $conditionContext);

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

			$result = $mailer->send();

			if (method_exists($mailer, 'getConditionErrors'))
			{
				static::$lastConditionErrors = $mailer->getConditionErrors();
			}

			return $result;
		}
		catch (\Throwable $e)
		{
			if (isset($mailer) && method_exists($mailer, 'getConditionErrors'))
			{
				static::$lastConditionErrors = $mailer->getConditionErrors();
			}

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


	/**
	 * Render the {if ...} blocks of a template field.
	 *
	 * The text is parsed into a small AST and evaluated against the raw context.
	 * Invalid conditions never block the email: the content is kept and a
	 * diagnostic is recorded for the debug output.
	 *
	 * @param   string  $text         Raw template field (subject, body or htmlbody)
	 * @param   array   $context      Raw condition context
	 * @param   array   $diagnostics  Collected diagnostics (for the debug output)
	 *
	 * @return  string
	 */
	public static function renderConditions($text, array $context, array &$diagnostics = array())
	{
		if (!is_string($text) || $text === '' || strpos($text, '{') === false)
		{
			return $text;
		}

		if (strlen($text) > self::CONDITION_MAX_LENGTH)
		{
			static::conditionAddDiagnostic($diagnostics, 'Template too large for conditions, content kept');

			return static::conditionStripMarkers($text);
		}

		$tokens = static::conditionTokenize($text, $diagnostics);

		if ($tokens === null)
		{
			return static::conditionStripMarkers($text);
		}

		$index = 0;
		$nodes = static::conditionParseNodes($tokens, $index, 0, $diagnostics, false);

		if ($nodes === null)
		{
			return static::conditionStripMarkers($text);
		}

		return static::conditionEvaluateNodes($nodes, $context, $diagnostics);
	}


	/**
	 * Split a template field into text and condition marker tokens.
	 *
	 * @param   string  $text         Template field
	 * @param   array   $diagnostics  Collected diagnostics
	 *
	 * @return  array|null  Tokens, or null when the structure is too large
	 */
	protected static function conditionTokenize($text, array &$diagnostics)
	{
		$tokens = array();
		$buffer = '';
		$length = strlen($text);
		$index  = 0;
		$blocks = 0;

		while ($index < $length)
		{
			$position = strpos($text, '{', $index);

			if ($position === false)
			{
				$buffer .= substr($text, $index);
				break;
			}

			$buffer .= substr($text, $index, $position - $index);
			$marker  = static::conditionReadMarker($text, $position);

			if ($marker === null)
			{
				$buffer .= '{';
				$index   = $position + 1;
				continue;
			}

			if ($buffer !== '')
			{
				$tokens[] = array('type' => 'text', 'value' => $buffer);
				$buffer   = '';
			}

			if ($marker['type'] === 'if' && ++$blocks > self::CONDITION_MAX_BLOCKS)
			{
				static::conditionAddDiagnostic($diagnostics, 'Too many conditional blocks, content kept');

				return null;
			}

			$tokens[] = $marker;
			$index    = $marker['end'];
		}

		if ($buffer !== '')
		{
			$tokens[] = array('type' => 'text', 'value' => $buffer);
		}

		return $tokens;
	}


	/**
	 * Try to read a condition marker at the given position.
	 *
	 * @param   string  $text   Template field
	 * @param   int     $position  Position of the opening brace
	 *
	 * @return  array|null  Marker token, or null when this is not a marker
	 */
	protected static function conditionReadMarker($text, $position)
	{
		$length = strlen($text);
		$index  = $position + 1;

		while ($index < $length && ctype_space($text[$index]))
		{
			$index++;
		}

		if ($index >= $length)
		{
			return null;
		}

		$rest = substr($text, $index);

		if (preg_match('/^\/if\b/i', $rest, $match))
		{
			$end = static::conditionMarkerEnd($text, $index + strlen($match[0]));

			return $end === null ? null : array('type' => 'endif', 'end' => $end);
		}

		if (preg_match('/^elseif\b/i', $rest, $match))
		{
			$expression = static::conditionReadExpression($text, $index + strlen($match[0]), $end);

			return $expression === null ? null : array('type' => 'elseif', 'expr' => $expression, 'end' => $end);
		}

		if (preg_match('/^else\b/i', $rest, $match))
		{
			$end = static::conditionMarkerEnd($text, $index + strlen($match[0]));

			return $end === null ? null : array('type' => 'else', 'end' => $end);
		}

		if (preg_match('/^if\b/i', $rest, $match))
		{
			$expression = static::conditionReadExpression($text, $index + strlen($match[0]), $end);

			return $expression === null ? null : array('type' => 'if', 'expr' => $expression, 'end' => $end);
		}

		return null;
	}


	/**
	 * Read the closing brace of a marker that has no expression.
	 *
	 * @param   string  $text   Template field
	 * @param   int     $start  Position after the keyword
	 *
	 * @return  int|null  Position after the closing brace
	 */
	protected static function conditionMarkerEnd($text, $start)
	{
		$length = strlen($text);
		$index  = $start;

		while ($index < $length && ctype_space($text[$index]))
		{
			$index++;
		}

		return ($index < $length && $text[$index] === '}') ? $index + 1 : null;
	}


	/**
	 * Read a condition expression until the closing brace, respecting quotes.
	 *
	 * @param   string  $text   Template field
	 * @param   int     $start  Position after the keyword
	 * @param   int     $end    Position after the closing brace
	 *
	 * @return  string|null  Trimmed expression, or null when unterminated
	 */
	protected static function conditionReadExpression($text, $start, &$end)
	{
		$length = strlen($text);
		$quote  = null;
		$result = '';

		for ($index = $start; $index < $length; $index++)
		{
			$char = $text[$index];

			if ($quote !== null)
			{
				$result .= $char;

				if ($char === '\\' && $index + 1 < $length)
				{
					$result .= $text[++$index];
				}
				elseif ($char === $quote)
				{
					$quote = null;
				}

				continue;
			}

			if ($char === '"' || $char === "'")
			{
				$quote   = $char;
				$result .= $char;
				continue;
			}

			if ($char === '}')
			{
				$end = $index + 1;

				return trim($result);
			}

			$result .= $char;
		}

		// Fail open: if a quote is not closed, fall back to the first closing brace
		// so that the block structure can still be recovered.
		$fallback = strpos($text, '}', $start);

		if ($fallback !== false)
		{
			$end = $fallback + 1;

			return trim(substr($text, $start, $fallback - $start));
		}

		return null;
	}


	/**
	 * Parse a token list into condition nodes.
	 *
	 * @param   array  $tokens       Tokens
	 * @param   int    $index        Current token index
	 * @param   int    $depth        Current nesting depth
	 * @param   array  $diagnostics  Collected diagnostics
	 * @param   bool   $insideBlock  Are we inside a block?
	 *
	 * @return  array|null
	 */
	protected static function conditionParseNodes(array $tokens, &$index, $depth, array &$diagnostics, $insideBlock)
	{
		$nodes = array();
		$count = count($tokens);

		while ($index < $count)
		{
			$token = $tokens[$index];

			if ($token['type'] === 'text')
			{
				$nodes[] = $token;
				$index++;
				continue;
			}

			if ($token['type'] === 'if')
			{
				$index++;
				$block = static::conditionParseBlock($tokens, $index, $depth + 1, $diagnostics);

				if ($block === null)
				{
					return null;
				}

				$nodes[] = $block;
				continue;
			}

			if ($insideBlock)
			{
				return $nodes;
			}

			static::conditionAddDiagnostic($diagnostics, 'Orphan {' . $token['type'] . '} marker ignored');
			$index++;
		}

		return $nodes;
	}


	/**
	 * Parse a single conditional block.
	 *
	 * @param   array  $tokens       Tokens
	 * @param   int    $index        Current token index
	 * @param   int    $depth        Current nesting depth
	 * @param   array  $diagnostics  Collected diagnostics
	 *
	 * @return  array|null
	 */
	protected static function conditionParseBlock(array $tokens, &$index, $depth, array &$diagnostics)
	{
		if ($depth > self::CONDITION_MAX_DEPTH)
		{
			static::conditionAddDiagnostic($diagnostics, 'Maximum condition nesting depth exceeded, content kept');

			return null;
		}

		$count    = count($tokens);
		$branches = array(array('expr' => $tokens[$index - 1]['expr'], 'nodes' => array()));
		$current  = 0;
		$hasElse  = false;

		while ($index < $count)
		{
			$token = $tokens[$index];

			if ($token['type'] === 'text')
			{
				$branches[$current]['nodes'][] = $token;
				$index++;
				continue;
			}

			if ($token['type'] === 'if')
			{
				$index++;
				$child = static::conditionParseBlock($tokens, $index, $depth + 1, $diagnostics);

				if ($child === null)
				{
					return null;
				}

				$branches[$current]['nodes'][] = $child;
				continue;
			}

			if ($token['type'] === 'elseif')
			{
				if ($hasElse)
				{
					static::conditionAddDiagnostic($diagnostics, '{elseif} after {else} ignored');
					$index++;
					continue;
				}

				$branches[] = array('expr' => $token['expr'], 'nodes' => array());
				$current    = count($branches) - 1;
				$index++;
				continue;
			}

			if ($token['type'] === 'else')
			{
				if ($hasElse)
				{
					static::conditionAddDiagnostic($diagnostics, 'Duplicate {else} ignored');
					$index++;
					continue;
				}

				$hasElse    = true;
				$branches[] = array('expr' => null, 'nodes' => array());
				$current    = count($branches) - 1;
				$index++;
				continue;
			}

			if ($token['type'] === 'endif')
			{
				$index++;
				$else = null;

				if ($hasElse)
				{
					$else = $branches[$current]['nodes'];
					array_pop($branches);
				}

				return array('type' => 'cond', 'branches' => $branches, 'else' => $else);
			}
		}

		static::conditionAddDiagnostic($diagnostics, 'Unclosed {if} block, content kept');

		return null;
	}


	/**
	 * Parse an expression into an AST.
	 *
	 * @param   string  $expression   Condition expression
	 * @param   array   $diagnostics  Collected diagnostics
	 *
	 * @return  array|null  AST, or null when the expression is invalid
	 */
	protected static function conditionParseExpression($expression, array &$diagnostics)
	{
		// A template edited in a WYSIWYG editor stores the comparison operators
		// HTML encoded (&gt;=, &lt;, &quot;). Decode them before parsing, otherwise
		// the condition is invalid and its content is always kept.
		$expression = trim(html_entity_decode((string) $expression, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

		if ($expression === '')
		{
			static::conditionAddDiagnostic($diagnostics, 'Empty condition expression, content kept');

			return null;
		}

		if (strlen($expression) > self::CONDITION_MAX_EXPRESSION)
		{
			static::conditionAddDiagnostic($diagnostics, 'Condition expression too long, content kept');

			return null;
		}

		$tokens = static::conditionTokenizeExpression($expression, $diagnostics);

		if ($tokens === null)
		{
			return null;
		}

		$index = 0;
		$ast   = static::conditionParseOr($tokens, $index, $diagnostics);

		if ($ast === null || $index < count($tokens))
		{
			static::conditionAddDiagnostic($diagnostics, 'Invalid condition "' . $expression . '", content kept');

			return null;
		}

		return $ast;
	}


	/**
	 * Tokenize a condition expression.
	 *
	 * @param   string  $expression   Condition expression
	 * @param   array   $diagnostics  Collected diagnostics
	 *
	 * @return  array|null
	 */
	protected static function conditionTokenizeExpression($expression, array &$diagnostics)
	{
		$tokens = array();
		$length = strlen($expression);
		$index  = 0;

		while ($index < $length)
		{
			$char = $expression[$index];

			if (ctype_space($char))
			{
				$index++;
				continue;
			}

			if ($char === '"' || $char === "'")
			{
				$quote  = $char;
				$value  = '';
				$closed = false;
				$index++;

				while ($index < $length)
				{
					$current = $expression[$index];

					if ($current === '\\' && $index + 1 < $length)
					{
						$value .= $expression[$index + 1];
						$index += 2;
						continue;
					}

					if ($current === $quote)
					{
						$index++;
						$closed = true;
						break;
					}

					$value .= $current;
					$index++;
				}

				if (!$closed)
				{
					static::conditionAddDiagnostic($diagnostics, 'Unterminated string in condition, content kept');

					return null;
				}

				$tokens[] = array('type' => 'value', 'value' => $value);
				continue;
			}

			$two = substr($expression, $index, 2);

			if (in_array($two, array('==', '!=', '>=', '<=', '&&', '||'), true))
			{
				$tokens[] = array('type' => 'op', 'value' => $two);
				$index += 2;
				continue;
			}

			if (strpos('=!<>', $char) !== false)
			{
				$tokens[] = array('type' => 'op', 'value' => $char);
				$index++;
				continue;
			}

			if ($char === ',')
			{
				$tokens[] = array('type' => 'comma');
				$index++;
				continue;
			}

			if ($char === '(' || $char === ')' || $char === '&' || $char === '|')
			{
				static::conditionAddDiagnostic($diagnostics, 'Unsupported operator in condition, content kept');

				return null;
			}

			$start = $index;

			while ($index < $length)
			{
				$current = $expression[$index];

				if (ctype_space($current) || strpos(',=!<>()&|"\'', $current) !== false)
				{
					break;
				}

				$index++;
			}

			$word = substr($expression, $start, $index - $start);

			if ($word === '')
			{
				static::conditionAddDiagnostic($diagnostics, 'Invalid token in condition, content kept');

				return null;
			}

			$lower = strtolower($word);

			if (in_array($lower, array('and', 'or', 'not', 'in', 'contains', 'empty'), true))
			{
				$tokens[] = array('type' => 'keyword', 'value' => $lower);
			}
			elseif ($lower === 'true' || $lower === 'false')
			{
				$tokens[] = array('type' => 'value', 'value' => $lower === 'true' ? 1 : 0);
			}
			elseif ($lower === 'null')
			{
				$tokens[] = array('type' => 'value', 'value' => null);
			}
			elseif (is_numeric($word))
			{
				$tokens[] = array('type' => 'value', 'value' => $word + 0);
			}
			else
			{
				$tokens[] = array('type' => 'word', 'value' => $word);
			}
		}

		return $tokens;
	}


	/**
	 * Parse an OR expression.
	 *
	 * @param   array  $tokens       Tokens
	 * @param   int    $index        Current token index
	 * @param   array  $diagnostics  Collected diagnostics
	 *
	 * @return  array|null
	 */
	protected static function conditionParseOr(array $tokens, &$index, array &$diagnostics)
	{
		$left = static::conditionParseAnd($tokens, $index, $diagnostics);

		while ($left !== null && $index < count($tokens))
		{
			$token = $tokens[$index];

			if (($token['type'] === 'keyword' && $token['value'] === 'or')
				|| ($token['type'] === 'op' && $token['value'] === '||'))
			{
				$index++;
				$right = static::conditionParseAnd($tokens, $index, $diagnostics);

				if ($right === null)
				{
					return null;
				}

				$left = array('type' => 'or', 'left' => $left, 'right' => $right);
				continue;
			}

			break;
		}

		return $left;
	}


	/**
	 * Parse an AND expression.
	 *
	 * @param   array  $tokens       Tokens
	 * @param   int    $index        Current token index
	 * @param   array  $diagnostics  Collected diagnostics
	 *
	 * @return  array|null
	 */
	protected static function conditionParseAnd(array $tokens, &$index, array &$diagnostics)
	{
		$left = static::conditionParseUnary($tokens, $index, $diagnostics);

		while ($left !== null && $index < count($tokens))
		{
			$token = $tokens[$index];

			if (($token['type'] === 'keyword' && $token['value'] === 'and')
				|| ($token['type'] === 'op' && $token['value'] === '&&'))
			{
				$index++;
				$right = static::conditionParseUnary($tokens, $index, $diagnostics);

				if ($right === null)
				{
					return null;
				}

				$left = array('type' => 'and', 'left' => $left, 'right' => $right);
				continue;
			}

			break;
		}

		return $left;
	}


	/**
	 * Parse a unary NOT expression.
	 *
	 * @param   array  $tokens       Tokens
	 * @param   int    $index        Current token index
	 * @param   array  $diagnostics  Collected diagnostics
	 *
	 * @return  array|null
	 */
	protected static function conditionParseUnary(array $tokens, &$index, array &$diagnostics)
	{
		if ($index < count($tokens))
		{
			$token = $tokens[$index];

			if (($token['type'] === 'op' && $token['value'] === '!')
				|| ($token['type'] === 'keyword' && $token['value'] === 'not'))
			{
				$index++;
				$operand = static::conditionParseUnary($tokens, $index, $diagnostics);

				return $operand === null ? null : array('type' => 'not', 'operand' => $operand);
			}
		}

		return static::conditionParsePredicate($tokens, $index, $diagnostics);
	}


	/**
	 * Parse a predicate: variable, optional operator and value list.
	 *
	 * @param   array  $tokens       Tokens
	 * @param   int    $index        Current token index
	 * @param   array  $diagnostics  Collected diagnostics
	 *
	 * @return  array|null
	 */
	protected static function conditionParsePredicate(array $tokens, &$index, array &$diagnostics)
	{
		if ($index >= count($tokens) || $tokens[$index]['type'] !== 'word')
		{
			return null;
		}

		$name = $tokens[$index]['value'];
		$index++;

		if ($index >= count($tokens))
		{
			return array('type' => 'predicate', 'name' => $name, 'operator' => 'presence', 'values' => array());
		}

		$token = $tokens[$index];

		if ($token['type'] === 'op' && in_array($token['value'], array('=', '==', '!=', '>', '>=', '<', '<='), true))
		{
			$operator = $token['value'] === '==' ? '=' : $token['value'];
			$index++;
			$values = static::conditionParseValueList($tokens, $index);

			return $values === null
				? null
				: array('type' => 'predicate', 'name' => $name, 'operator' => $operator, 'values' => $values);
		}

		if ($token['type'] === 'keyword' && $token['value'] === 'empty')
		{
			$index++;

			return array('type' => 'predicate', 'name' => $name, 'operator' => 'empty', 'values' => array());
		}

		if ($token['type'] === 'keyword' && in_array($token['value'], array('in', 'contains'), true))
		{
			$operator = $token['value'];
			$index++;
			$values = static::conditionParseValueList($tokens, $index);

			return $values === null
				? null
				: array('type' => 'predicate', 'name' => $name, 'operator' => $operator, 'values' => $values);
		}

		if ($token['type'] === 'keyword' && $token['value'] === 'not')
		{
			if ($index + 1 < count($tokens)
				&& $tokens[$index + 1]['type'] === 'keyword'
				&& in_array($tokens[$index + 1]['value'], array('in', 'contains'), true))
			{
				$operator = 'not ' . $tokens[$index + 1]['value'];
				$index += 2;
				$values = static::conditionParseValueList($tokens, $index);

				return $values === null
					? null
					: array('type' => 'predicate', 'name' => $name, 'operator' => $operator, 'values' => $values);
			}

			return null;
		}

		return null;
	}


	/**
	 * Parse a comma separated list of values.
	 *
	 * @param   array  $tokens  Tokens
	 * @param   int    $index   Current token index
	 *
	 * @return  array|null
	 */
	protected static function conditionParseValueList(array $tokens, &$index)
	{
		$value = static::conditionParseValue($tokens, $index);

		if (!$value['found'])
		{
			return null;
		}

		$values = array($value['value']);

		while ($index < count($tokens) && $tokens[$index]['type'] === 'comma')
		{
			$index++;
			$value = static::conditionParseValue($tokens, $index);

			if (!$value['found'])
			{
				return null;
			}

			$values[] = $value['value'];
		}

		return $values;
	}


	/**
	 * Parse a single literal value.
	 *
	 * @param   array  $tokens  Tokens
	 * @param   int    $index   Current token index
	 *
	 * @return  array  array('found' => bool, 'value' => mixed)
	 */
	protected static function conditionParseValue(array $tokens, &$index)
	{
		if ($index >= count($tokens))
		{
			return array('found' => false, 'value' => null);
		}

		$token = $tokens[$index];

		if ($token['type'] === 'value' || $token['type'] === 'word')
		{
			$index++;

			return array('found' => true, 'value' => $token['value']);
		}

		return array('found' => false, 'value' => null);
	}


	/**
	 * Evaluate a list of condition nodes.
	 *
	 * @param   array  $nodes        Nodes
	 * @param   array  $context      Condition context
	 * @param   array  $diagnostics  Collected diagnostics
	 *
	 * @return  string
	 */
	protected static function conditionEvaluateNodes(array $nodes, array $context, array &$diagnostics)
	{
		$output = '';

		foreach ($nodes as $node)
		{
			if ($node['type'] === 'text')
			{
				$output .= $node['value'];
				continue;
			}

			$output .= static::conditionEvaluateBlock($node, $context, $diagnostics);
		}

		return $output;
	}


	/**
	 * Evaluate a conditional block.
	 *
	 * @param   array  $node         Block node
	 * @param   array  $context      Condition context
	 * @param   array  $diagnostics  Collected diagnostics
	 *
	 * @return  string
	 */
	protected static function conditionEvaluateBlock(array $node, array $context, array &$diagnostics)
	{
		foreach ($node['branches'] as $branch)
		{
			$result = static::conditionEvaluateExpression($branch['expr'], $context, $diagnostics);

			static::conditionAddDiagnostic(
				$diagnostics,
				'if ' . $branch['expr'] . ' => ' . ($result ? 'true' : 'false')
			);

			if ($result)
			{
				return static::conditionEvaluateNodes($branch['nodes'], $context, $diagnostics);
			}
		}

		if ($node['else'] !== null)
		{
			return static::conditionEvaluateNodes($node['else'], $context, $diagnostics);
		}

		return '';
	}


	/**
	 * Parse and evaluate a single condition expression.
	 *
	 * @param   string  $expression   Condition expression
	 * @param   array   $context      Condition context
	 * @param   array   $diagnostics  Collected diagnostics
	 *
	 * @return  bool  True when the content must be kept (fail open)
	 */
	protected static function conditionEvaluateExpression($expression, array $context, array &$diagnostics)
	{
		$ast = static::conditionParseExpression($expression, $diagnostics);

		if ($ast === null)
		{
			return true;
		}

		return static::conditionEvaluateAst($ast, $context, $diagnostics);
	}


	/**
	 * Evaluate a condition AST.
	 *
	 * @param   array  $ast          AST node
	 * @param   array  $context      Condition context
	 * @param   array  $diagnostics  Collected diagnostics
	 *
	 * @return  bool
	 */
	protected static function conditionEvaluateAst(array $ast, array $context, array &$diagnostics)
	{
		switch ($ast['type'])
		{
			case 'or':
				return static::conditionEvaluateAst($ast['left'], $context, $diagnostics)
					|| static::conditionEvaluateAst($ast['right'], $context, $diagnostics);

			case 'and':
				return static::conditionEvaluateAst($ast['left'], $context, $diagnostics)
					&& static::conditionEvaluateAst($ast['right'], $context, $diagnostics);

			case 'not':
				return !static::conditionEvaluateAst($ast['operand'], $context, $diagnostics);

			case 'predicate':
				$value = static::resolveConditionOperand($ast['name'], $context, $diagnostics);

				if ($ast['operator'] === 'presence')
				{
					return static::isTruthyConditionValue(static::normalizeConditionValue($value));
				}

				return static::compareConditionValues($value, $ast['operator'], $ast['values'], $diagnostics);
		}

		return false;
	}


	/**
	 * Compare a value with the right operand(s) of a predicate.
	 *
	 * @param   mixed   $value        Left value
	 * @param   string  $operator     Operator
	 * @param   array   $values       Right values
	 * @param   array   $diagnostics  Collected diagnostics
	 *
	 * @return  bool
	 */
	protected static function compareConditionValues($value, $operator, array $values, array &$diagnostics)
	{
		$value  = static::normalizeConditionValue($value);
		$rights = array();

		foreach ($values as $right)
		{
			$rights[] = static::normalizeConditionValue($right);
		}

		switch ($operator)
		{
			case 'empty':
				return static::isEmptyConditionValue($value);

			case '=':
			case 'in':
				return static::conditionEquals($value, $rights);

			case '!=':
			case 'not in':
				return !static::conditionEquals($value, $rights);

			case 'contains':
				return static::conditionContains($value, $rights);

			case 'not contains':
				return !static::conditionContains($value, $rights);

			case '>':
			case '>=':
			case '<':
			case '<=':
				return static::conditionNumericCompare($value, $rights, $operator);
		}

		return false;
	}


	/**
	 * Check whether at least one value of the left operand equals one of the right values.
	 *
	 * @param   mixed  $left    Left value
	 * @param   array  $rights  Right values
	 *
	 * @return  bool
	 */
	protected static function conditionEquals($left, array $rights)
	{
		$list = is_array($left) ? $left : array($left);

		if (!$list || !$rights)
		{
			return false;
		}

		foreach ($list as $item)
		{
			foreach ($rights as $right)
			{
				if (static::conditionScalarEquals($item, $right))
				{
					return true;
				}
			}
		}

		return false;
	}


	/**
	 * Compare two scalar values.
	 *
	 * Numeric values are compared numerically, everything else as case
	 * insensitive strings.
	 *
	 * @param   mixed  $left   Left value
	 * @param   mixed  $right  Right value
	 *
	 * @return  bool
	 */
	protected static function conditionScalarEquals($left, $right)
	{
		if ($left === null || $right === null)
		{
			return $left === null && $right === null;
		}

		if (is_array($left))
		{
			$left = implode(',', $left);
		}

		if (is_array($right))
		{
			$right = implode(',', $right);
		}

		if (is_numeric($left) && is_numeric($right))
		{
			return (float) $left === (float) $right;
		}

		return mb_strtolower((string) $left, 'UTF-8') === mb_strtolower((string) $right, 'UTF-8');
	}


	/**
	 * Check whether at least one value of the left operand contains the needle.
	 *
	 * @param   mixed  $left    Left value
	 * @param   array  $rights  Right values
	 *
	 * @return  bool
	 */
	protected static function conditionContains($left, array $rights)
	{
		if (!$rights)
		{
			return false;
		}

		$needle = $rights[0];

		if (is_array($needle))
		{
			$needle = implode(',', $needle);
		}

		$needle = mb_strtolower(trim((string) $needle), 'UTF-8');

		if ($needle === '')
		{
			return false;
		}

		$list = is_array($left) ? $left : array($left);

		foreach ($list as $item)
		{
			if (is_array($item))
			{
				$item = implode(',', $item);
			}

			if (mb_strpos(mb_strtolower((string) $item, 'UTF-8'), $needle) !== false)
			{
				return true;
			}
		}

		return false;
	}


	/**
	 * Compare the numeric values of the left operand with the right value.
	 *
	 * @param   mixed   $left     Left value
	 * @param   array   $rights   Right values
	 * @param   string  $operator Operator
	 *
	 * @return  bool
	 */
	protected static function conditionNumericCompare($left, array $rights, $operator)
	{
		if (!$rights || is_array($rights[0]) || !is_numeric($rights[0]))
		{
			return false;
		}

		$right = (float) $rights[0];
		$list  = is_array($left) ? $left : array($left);

		foreach ($list as $item)
		{
			if (is_array($item) || !is_numeric($item))
			{
				continue;
			}

			$number = (float) $item;

			switch ($operator)
			{
				case '>':
					if ($number > $right)
					{
						return true;
					}
					break;

				case '>=':
					if ($number >= $right)
					{
						return true;
					}
					break;

				case '<':
					if ($number < $right)
					{
						return true;
					}
					break;

				case '<=':
					if ($number <= $right)
					{
						return true;
					}
					break;
			}
		}

		return false;
	}


	/**
	 * Resolve a variable or a custom field in the condition context.
	 *
	 * Context variables win over fields, then the field is looked up as
	 * FIELD_<NORMALIZED_NAME>.
	 *
	 * @param   string  $name         Variable name
	 * @param   array   $context      Condition context
	 * @param   array   $diagnostics  Collected diagnostics
	 *
	 * @return  mixed
	 */
	protected static function resolveConditionOperand($name, array $context, array &$diagnostics)
	{
		$key = strtolower((string) $name);

		if ($key !== 'fields' && array_key_exists($key, $context))
		{
			return $context[$key];
		}

		$fields = isset($context['fields']) && is_array($context['fields']) ? $context['fields'] : array();

		if (stripos((string) $name, 'field_') === 0)
		{
			$fieldKey = 'FIELD_' . static::normalizeFieldName(substr((string) $name, 6));
		}
		else
		{
			$fieldKey = 'FIELD_' . static::normalizeFieldName($name);
		}

		if (array_key_exists($fieldKey, $fields))
		{
			return $fields[$fieldKey];
		}

		static::conditionAddDiagnostic($diagnostics, 'Unknown condition variable "' . $name . '"');

		return null;
	}


	/**
	 * Normalize a value for the condition evaluation.
	 *
	 * Objects and arrays are flattened into a list of scalars, strings are
	 * HTML decoded, stripped of tags and trimmed.
	 *
	 * @param   mixed  $value  Raw value
	 *
	 * @return  mixed
	 */
	public static function normalizeConditionValue($value)
	{
		if (is_object($value))
		{
			$value = (array) $value;
		}

		if (is_array($value))
		{
			$flat = array();

			foreach ($value as $item)
			{
				$item = static::normalizeConditionValue($item);

				if (is_array($item))
				{
					foreach ($item as $sub)
					{
						if (!static::isEmptyConditionValue($sub))
						{
							$flat[] = $sub;
						}
					}
				}
				elseif (!static::isEmptyConditionValue($item))
				{
					$flat[] = $item;
				}
			}

			return $flat;
		}

		if (is_bool($value))
		{
			return $value ? 1 : 0;
		}

		if ($value === null || is_int($value) || is_float($value))
		{
			return $value;
		}

		if (!is_scalar($value))
		{
			return null;
		}

		$value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = strip_tags($value);

		return trim($value);
	}


	/**
	 * Check whether a normalized value is strictly empty.
	 *
	 * Zero, "0" and false are not empty.
	 *
	 * @param   mixed  $value  Normalized value
	 *
	 * @return  bool
	 */
	public static function isEmptyConditionValue($value)
	{
		if ($value === null)
		{
			return true;
		}

		if (is_array($value))
		{
			foreach ($value as $item)
			{
				if (!static::isEmptyConditionValue($item))
				{
					return false;
				}
			}

			return true;
		}

		if (is_string($value))
		{
			return trim($value) === '';
		}

		return false;
	}


	/**
	 * Check whether a normalized value is truthy.
	 *
	 * Unlike empty, zero and "0" are falsy here.
	 *
	 * @param   mixed  $value  Normalized value
	 *
	 * @return  bool
	 */
	public static function isTruthyConditionValue($value)
	{
		if ($value === null)
		{
			return false;
		}

		if (is_array($value))
		{
			foreach ($value as $item)
			{
				if (static::isTruthyConditionValue($item))
				{
					return true;
				}
			}

			return false;
		}

		if (is_bool($value))
		{
			return $value;
		}

		if (is_int($value) || is_float($value))
		{
			return $value != 0;
		}

		$value = (string) $value;

		return $value !== '' && $value !== '0';
	}


	/**
	 * Add a diagnostic, capped to avoid huge debug messages.
	 *
	 * @param   array   $diagnostics  Collected diagnostics
	 * @param   string  $message      Message
	 *
	 * @return  void
	 */
	protected static function conditionAddDiagnostic(array &$diagnostics, $message)
	{
		if (count($diagnostics) < self::CONDITION_MAX_DIAGNOSTICS)
		{
			$diagnostics[] = $message;
		}
	}


	/**
	 * Remove the condition markers from a text, keeping the content.
	 *
	 * @param   string  $text  Text
	 *
	 * @return  string
	 */
	public static function stripConditionMarkers($text)
	{
		return static::conditionStripMarkers($text);
	}


	/**
	 * Remove the condition markers from a text, keeping the content.
	 *
	 * Used as a fail open fallback when the block structure cannot be parsed.
	 *
	 * @param   string  $text  Template field
	 *
	 * @return  string
	 */
	protected static function conditionStripMarkers($text)
	{
		$text = preg_replace('/\{\s*\/if\s*\}/i', '', $text);
		$text = preg_replace('/\{\s*else\s*\}/i', '', $text);
		$text = preg_replace('/\{\s*(?:elseif|if)\b[^}]*\}/i', '', $text);

		return $text;
	}
}
