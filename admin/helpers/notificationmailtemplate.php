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

use Joomla\CMS\Mail\MailTemplate;

/**
 * MailTemplate subclass that renders the FLEXIcontent {if ...} blocks.
 *
 * The conditions are evaluated on the raw template fields, before Joomla
 * translates the language keys and replaces the {TAG} tags. This way the value
 * of a custom field can never be interpreted as a condition.
 */
class FlexicontentMailTemplate extends MailTemplate
{
	/** Mailers currently sending; a stack also isolates nested sends. */
	protected static $sendingMailers = array();

	/**
	 * Raw condition context.
	 *
	 * @var array
	 */
	protected $conditionContext = array();

	/**
	 * Diagnostics collected while rendering the conditions.
	 *
	 * @var array
	 */
	protected $conditionErrors = array();

	/**
	 * Set the raw condition context.
	 *
	 * @param   array  $context  Condition context
	 *
	 * @return  void
	 */
	public function setConditionContext(array $context)
	{
		$this->conditionContext = $context;
	}

	/**
	 * Get the diagnostics collected while rendering the conditions.
	 *
	 * @return  array
	 */
	public function getConditionErrors()
	{
		return $this->conditionErrors;
	}

	/**
	 * Scope the context used by Joomla's static template loader to this send.
	 *
	 * @return  boolean
	 */
	public function send()
	{
		self::$sendingMailers[] = $this;

		try
		{
			return parent::send();
		}
		finally
		{
			array_pop(self::$sendingMailers);
		}
	}

	/**
	 * Load the raw template before Joomla translates fields and replaces tags.
	 *
	 * @param   string  $key       Template identifier
	 * @param   string  $language  Language code of the template
	 *
	 * @return  object|null
	 */
	public static function getTemplate($key, $language)
	{
		$mail = parent::getTemplate($key, $language);
		$mailer = end(self::$sendingMailers);

		return $mailer ? $mailer->renderTemplateConditions($mail) : $mail;
	}

	/**
	 * Joomla 6 loads the template with $this->loadTemplate() inside send(). Its static
	 * getTemplate() uses a plain MailTemplate instance and is not called by send().
	 *
	 * @param   string  $key       Template identifier
	 * @param   string  $language  Language code of the template
	 *
	 * @return  \stdClass|null
	 */
	protected function loadTemplate(string $key, string $language): ?\stdClass
	{
		return $this->renderTemplateConditions(parent::loadTemplate($key, $language));
	}

	/** Render only template fields, never data substituted by replaceTags(). */
	protected function renderTemplateConditions($mail)
	{
		if (!$mail || !$this->conditionContext)
		{
			return $mail;
		}

		$original = array(
			'subject'  => $mail->subject ?? '',
			'body'     => $mail->body ?? '',
			'htmlbody' => $mail->htmlbody ?? '',
		);

		foreach (array('subject', 'body', 'htmlbody') as $property)
		{
			if (isset($mail->$property) && $mail->$property !== '')
			{
				$mail->$property = FlexicontentHelperNotificationMail::renderConditions(
					$mail->$property,
					$this->conditionContext,
					$this->conditionErrors
				);
			}
		}

		// Never send an email without subject when the original subject was not empty
		if (trim((string) $mail->subject) === '' && trim((string) $original['subject']) !== '')
		{
			$mail->subject = FlexicontentHelperNotificationMail::stripConditionMarkers($original['subject']);
			$this->conditionErrors[] = 'Empty rendered subject, original subject kept';
		}

		if (trim((string) $mail->body) === '' && trim((string) $mail->htmlbody) === ''
			&& (trim((string) $original['body']) !== '' || trim((string) $original['htmlbody']) !== ''))
		{
			$this->conditionErrors[] = 'Empty rendered body';
		}

		return $mail;
	}
}
