<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');
use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

trait flexicontent_basetable_trait
{
	/**
	 * Original code form: Phoca International Alias Plugin for Joomla 1.5
	 *
	 * This method processes a string and replaces all accented UTF-8 characters by unaccented
	 * ASCII-7 "equivalents", whitespaces are replaced by hyphens and the string is lowercased.
	 *
	 * @param   string   $string    The text to trasliterate
	 * @param   string   $language  Language of the give text
	 *
	 * @return  string   The trasliterated text
	 *
	 * @since   3.0
	 */
	function transliterate($string, $language)
	{
		$langFrom    = array();
		$langTo      = array();

		switch($language)
		{
			// BULGARIAN
			case 'bg-BG':
				$bgLangFrom = array('А', 'а', 'Б', 'б', 'В', 'в', 'Г', 'г', 'Д', 'д', 'Е', 'е', 'Ж', 'ж', 'З', 'з', 'И', 'и', 'Й', 'й', 'К', 'к', 'Л', 'л', 'М', 'м', 'Н', 'н', 'О', 'о', 'П', 'п', 'Р', 'р', 'С', 'с', 'Т', 'т', 'У', 'у', 'Ф', 'ф', 'Х', 'х', 'Ц', 'ц', 'Ч', 'ч', 'Ш', 'ш', 'Щ', 'щ', 'Ъ', 'ъ', 'Ьо', 'ьо', 'Ю', 'ю', 'Я', 'я');
				$bgLangTo   = array('A', 'a', 'B', 'b', 'V', 'v', 'G', 'g', 'D', 'd', 'E', 'e', 'Zh', 'zh', 'Z', 'z', 'I', 'i', 'J', 'j', 'K', 'k', 'L', 'l', 'M', 'm', 'N', 'n', 'O', 'o', 'P', 'p', 'R', 'r', 'S', 's', 'T', 't', 'U', 'u', 'F', 'f', 'H', 'h', 'C', 'c', 'Ch', 'ch', 'Sh', 'sh', 'Sht', 'sht', 'Y', 'y', 'Io', 'io', 'Ju', 'ju', 'Ja', 'ja');
				$langFrom   = array_merge ($langFrom, $bgLangFrom);
				$langTo     = array_merge ($langTo, $bgLangTo);
				break;

			// CZECH
			case 'cz-CZ':
				$czLangFrom = array('á','č','ď','é','ě','í','ň','ó','ř','š','ť','ú','ů','ý','ž','Á','Č','Ď','É','Ě','Í','Ň','Ó','Ř','Š','Ť','Ú','Ů','Ý','Ž');
				$czLangTo   = array('a','c','d','e','e','i','n','o','r','s','t','u','u','y','z','a','c','d','e','e','i','ň','o','r','s','t','u','u','y','z');
				$langFrom   = array_merge ($langFrom, $czLangFrom);
				$langTo     = array_merge ($langTo, $czLangTo);
				break;

			// CROATIAN
			case 'hr-HR':
			case 'hr-BA':
				$hrLangFrom = array('č','ć','đ','š','ž','Č','Ć','Đ','Š','Ž');
				$hrLangTo   = array('c','c','d','s','z','c','c','d','s','z');
				$langFrom   = array_merge ($langFrom, $hrLangFrom);
				$langTo     = array_merge ($langTo, $hrLangTo);
				break;

			// GREEK
			case 'el-GR':
				$grLangFrom = array('α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ',  'η', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ',  'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ',  'ω', 'Α', 'Β', 'Γ', 'Δ', 'Ε', 'Ζ', 'Η', 'Θ',  'Ι', 'Κ', 'Λ', 'Μ', 'Ν', 'Ξ',  'Ο', 'Π', 'Ρ', 'Σ', 'Τ', 'Υ', 'Φ', 'Χ', 'Ψ',  'Ω', 'Ά', 'Έ', 'Ή', 'Ί', 'Ύ', 'Ό', 'Ώ', 'ά', 'έ', 'ή', 'ί', 'ύ', 'ό', 'ώ', 'ΰ', 'ΐ', 'ϋ', 'ϊ', 'ς', '«', '»' );
				$grLangTo   = array('a', 'b', 'g', 'd', 'e', 'z', 'h', 'th', 'i', 'i', 'k', 'l', 'm', 'n', 'ks', 'o', 'p', 'r', 's', 't', 'u', 'f', 'x', 'ps', 'o', 'A', 'B', 'G', 'D', 'E', 'Z', 'I', 'Th', 'I', 'K', 'L', 'M', 'N', 'Ks', 'O', 'P', 'R', 'S', 'T', 'Y', 'F', 'X', 'Ps', 'O', 'A', 'E', 'I', 'I', 'U', 'O', 'O', 'a', 'e', 'i', 'i', 'u', 'o', 'o', 'u', 'i', 'u', 'i', 's', '_', '_' );
				$langFrom   = array_merge ($langFrom, $grLangFrom);
				$langTo     = array_merge ($langTo, $grLangTo);
				break;

			// HUNGARIAN
			case 'hu-HU':
				$huLangFrom = array('á','é','ë','í','ó','ö','ő','ú','ü','ű','Á','É','Ë','Í','Ó','Ö','Ő','Ú','Ü','Ű');
				$huLangTo   = array('a','e','e','i','o','o','o','u','u','u','a','e','e','i','o','o','o','u','u','u');
				$langFrom   = array_merge ($langFrom, $huLangFrom);
				$langTo     = array_merge ($langTo, $huLangTo);
				break;

			// POLISH
			case 'pl-PL':
				$plLangFrom = array('ą','ć','ę','ł','ń','ó','ś','ź','ż','Ą','Ć','Ę','Ł','Ń','Ó','Ś','Ź','Ż');
				$plLangTo   = array('a','c','e','l','n','o','s','z','z','a','c','e','l','n','o','s','z','z');
				$langFrom   = array_merge ($langFrom, $plLangFrom);
				$langTo     = array_merge ($langTo, $plLangTo);
				break;

			// RUSSIAN
			case 'ru-RU':
				$ruLangFrom = array('А', 'а', 'Б', 'б', 'В', 'в', 'Г', 'г', 'Д', 'д', 'Е', 'е', 'Ё', 'ё', 'Ж', 'ж', 'З', 'з', 'И', 'и', 'Й', 'й', 'К', 'к', 'Л', 'л', 'М', 'м', 'Н', 'н', 'О', 'о', 'П', 'п', 'Р', 'р', 'С', 'с', 'Т', 'т', 'У', 'у', 'Ф', 'ф', 'Х', 'х', 'Ц', 'ц', 'Ч', 'ч', 'Ш', 'ш', 'Щ', 'щ', 'Ъ', 'ъ', 'Ы', 'ы', 'Ь', 'ь', 'Э', 'э', 'Ю', 'ю', 'Я', 'я');
				$ruLangTo   = array('A', 'a', 'B', 'b', 'V', 'v', 'G', 'g', 'D', 'd', 'E', 'e', 'Jo', 'jo', 'Zh', 'zh', 'Z', 'z', 'I', 'i', 'J', 'j', 'K', 'k', 'L', 'l', 'M', 'm', 'N', 'n', 'O', 'o', 'P', 'p', 'R', 'r', 'S', 's', 'T', 't', 'U', 'u', 'F', 'f', 'H', 'h', 'C', 'c', 'Ch', 'ch', 'Sh', 'sh', 'Shh', 'shh', '', '', 'Y', 'y', '', '', 'Je', 'je', 'Ju', 'ju', 'Ja', 'ja');
				$langFrom   = array_merge ($langFrom, $ruLangFrom);
				$langTo     = array_merge ($langTo, $ruLangTo);
				break;

			// SLOVAK
			case 'sk-SK':
				$skLangFrom = array('á','ä','č','ď','é','í','ľ','ĺ','ň','ó','ô','ŕ','š','ť','ú','ý','ž','Á','Ä','Č','Ď','É','Í','Ľ','Ĺ','Ň','Ó','Ô','Ŕ','Š','Ť','Ú','Ý','Ž');
				$skLangTo   = array('a','a','c','d','e','i','l','l','n','o','o','r','s','t','u','y','z','a','a','c','d','e','i','l','l','n','o','o','r','s','t','u','y','z');
				$langFrom   = array_merge ($langFrom, $skLangFrom);
				$langTo     = array_merge ($langTo, $skLangTo);
				break;

			// SLOVENIAN
			case 'sl-SI':
				$slLangFrom = array('č','š','ž','Č','Š','Ž');
				$slLangTo   = array('c','s','z','c','s','z');
				$langFrom   = array_merge ($langFrom, $slLangFrom);
				$langTo     = array_merge ($langTo, $slLangTo);
				break;

			// LITHUANIAN
			case 'lt-LT':
				$ltLangFrom = array('ą','č','ę','ė','į','š','ų','ū','ž','Ą','Č','Ę','Ė','Į','Š','Ų','Ū','Ž');
				$ltLangTo   = array('a','c','e','e','i','s','u','u','z','A','C','E','E','I','S','U','U','Z');
				$langFrom   = array_merge ($langFrom, $ltLangFrom);
				$langTo     = array_merge ($langTo, $ltLangTo);
				break;

			// ICELANDIC
			case 'is-IS':
				$isLangFrom = array('þ', 'æ', 'ð', 'ö', 'í', 'ó', 'é', 'á', 'ý', 'ú', 'Þ', 'Æ', 'Ð', 'Ö', 'Í', 'Ó', 'É', 'Á', 'Ý', 'Ú');
				$isLangTo   = array('th','ae','d', 'o', 'i', 'o', 'e', 'a', 'y', 'u', 'Th','Ae','D', 'O', 'I', 'O', 'E', 'A', 'Y', 'U');
				$langFrom   = array_merge ($langFrom, $isLangFrom);
				$langTo     = array_merge ($langTo, $isLangTo);
				break;

			// TURKISH
			case 'tr-TR':
				$tuLangFrom = array('ş','ı','ö','ü','ğ','ç','Ş','İ','Ö','Ü','Ğ','Ç');
				$tuLangTo   = array('s','i','o','u','g','c','S','I','O','U','G','C');
				$langFrom   = array_merge ($langFrom, $tuLangFrom);
				$langTo     = array_merge ($langTo, $tuLangTo);
				break;

			default:
				break;
		}

		/**
		 * Because default code in JLanguage::transliterate will call
		 *
 		 * a. (ll_CC)Localise::transliterate() ... if it exists
		 * b. Transliterate::utf8_latin_to_ascii($string)
		 *
		 * the above will handle letters like: 'ä','ö','ü','ß','Ä','Ö','Ü'
		 * that is if the above letters were not already transliterate for the given language by this
		 */

		// DISABLED ... to match the default behaviour decribed above
		if (0)
		{
			/**
			 * GERMAN - because of german names used in Czech, Hungarian, Polish or Slovak (because of possible
			 * match - e.g. German ä => ae, but Slovak ä => a ... for all but German language we will use:
			 * a not ae, u not ue, o not oe, but ß will be ss
			 */
			$deLangFrom  = array('ä','ö','ü','ß','Ä','Ö','Ü');
			$deLangTo    = substr($language, 0, 3) === 'de-'
				? array('ae','oe','ue','ss','ae','oe','ue')
				: array('a','o','u','ss','a','o','u');

			$langFrom = array_merge ($langFrom, $deLangFrom);
			$langTo   = array_merge ($langTo, $deLangTo);
			$string   = StringHelper::str_ireplace($langFrom, $langTo, $string);
			$string   = StringHelper::strtolower($string);
		}

		return $string;
	}


	/**
	 * A replacement of JApplicationHelper::stringURLSafe(), that adds support for languages that does not define (ll_CC)Localise::transliterate() , but do need transliteration
	 *
	 * Make given string safe, also transliterating it - EITHER - if unicode aliases are not enabled - OR - if force ascii alias for current record type is true
	 *
	 * @param   string   $string       The string to make safe
	 * @param   string   $language     The language of the string
	 * @param   boolean  $force_ascii  Whether to force transliteration
	 *
	 * @return  string   A safe string, possibly transliterated
	 *
	 * @since   3.2
	 */
	public function stringURLSafe($string, $language, $force_ascii)
	{
		// Return a cleaned unicode alias
		if (JFactory::getConfig()->get('unicodeslugs') && !$force_ascii)
		{
			return JFilterOutput::stringURLUnicodeSlug($string);
		}


		/**
		 * Proceed to create a transliterated ASCII alias
		 */

		// Use record's language or use SITE's default language in case of record's language is ALL (or empty)
		$language = !empty($language) && $language !== '*'
			? $language
			: JComponentHelper::getParams('com_languages')->get('site', '*');
		$title = $this->_title;
		$alias = $this->_alias;

		if ($language !== '*')
		{
			/**
			 * If language does not have (ll_CC)Localise::transliterate()
			 * then run our own transliterate method
			 */
			if (!JLanguage::getInstance($language)->getTransliterator())
			{
				// Remove any '-' from the string since they will be used as concatenaters
				$string = str_replace('-', ' ', $string);

				// Do the transliteration using our custom transliterate method
				$string = $this->transliterate($string, $language);
			}

			/**
			 * Language has (ll_CC)Localise::transliterate(), run it ONLY if ASCII alias is being forced
			 * since if not forced then it will be run by JFilterOutput::stringURLSafe($string, $language)
			 */
			else
			{
				// Detect that unicode aliases are enabled but this ascii alias is forced for this record
				$unicodeslugs_override = JFactory::getConfig()->get('unicodeslugs') && $force_ascii;

				// Detect old joomla versions (Joomla <=3.5.x) that will not run the transliterator element's language
				$r = new ReflectionMethod('JApplicationHelper', 'stringURLSafe');
				$supports_content_language_transliteration = count( $r->getParameters() ) > 1;

				if ($unicodeslugs_override || !$supports_content_language_transliteration)
				{
					// Remove any '-' from the string since they will be used as concatenaters
					$this->$alias = str_replace('-', ' ', $this->$alias);

					// Do the transliteration according to ELEMENT's language transliterator: (ll_CC)Localise::transliterate()
					$this->$alias = JLanguage::getInstance($language)->transliterate($this->$alias);
				}
			}
		}

		/**
		 * Make alias safe and transliterate it, using default implementation this will call
		 * a. Prepare string doing things like: Replace any '-' with ' '
		 * b. (ll_CC)Localise::transliterate() ... if it exists
		 * c. Transliterate::utf8_latin_to_ascii($string)
		 * d. Replace remaining non valid characters, and trim dashes '-'
		 */
		$output = JFilterOutput::stringURLSafe($string, $language);

		return $output;
	}


	/**
	 * Check if a record is valid
	 *
	 * @param   object   $config       A configuration object
	 *
	 * @return  bool     Return true on success, otherwise false
	 *
	 * @since   3.3
	 */
	protected function _check_record($config = null)
	{
		// Use record's language or use SITE's default language in case of record's language is ALL (or empty)
		$language = !empty($this->language) && $this->language !== '*'
			? $this->language
			: JComponentHelper::getParams('com_languages')->get('site', '*');
		$title = $this->_title;
		$alias = $this->_alias;
		$original_alias = $this->$alias;


		/**
		 * Check if 'title' was not given
		 */
		if (trim($this->$title) == '')
		{
			$msg = JText::_('FLEXI_ADD_' . strtoupper($title));
			JFactory::getApplication()->enqueueMessage($msg, 'error');
			$this->setError($msg);
			return false;
		}

		$valid_pattern = $this->_allow_underscore ? '/^[a-z_0-9-]+$/i' : '/^[a-z0-9-]+$/i' ;

		$automatic_alias = $config && isset($config->automatic_alias) ? $config->automatic_alias : true;
		$alias_is_valid  = preg_match($valid_pattern, $this->$alias);

		if (!$alias_is_valid)
		{
			// Store bad alias to include it in a message
			$invalid_chars_alias = $original_alias;

			/**
			 * Create a valid alias from title, if automatic alias is allowed,
			 * otherwise we will filter current alias, and fail if either result or original alias are empty
			 */
			$this->$alias = $automatic_alias ? $this->$title : $this->$alias;
		}

		// Use 'title' as alias if 'alias' is empty
		if (empty($this->$alias))
		{
			$this->$alias = $this->$title;
		}


		/**
		 * Make alias ascii, unless it is already ascii
		 */

		if (!$alias_is_valid)
		{
			// Make alias safe, also transliterating it - EITHER - if unicode aliases are not enabled - OR - if force ascii alias for current record type is true
			$this->$alias = $this->stringURLSafe($this->$alias, $language, $this->_force_ascii_alias);

			// Check for empty alias and fallback to using current date
			if (trim(str_replace('-', '', $this->$alias)) == '')
			{
				$this->$alias = JFactory::getDate()->format('Y-m-d-H-i-s');
			}
		}

		// Force dash instead of underscore (if such configuration)
		if (!$this->_allow_underscore)
		{
			$this->$alias = str_replace('_', '-', $this->$alias);
		}


		/**
		 * Make alias unique
		 */

		$n = 1;
		$max_retries = 1000;
		$possible_alias = $this->$alias;

		while ($n < $max_retries)
		{
			$query = $this->_db->getQuery(true)
				->select('COUNT(id)')
				->from('#__' . $this->_records_dbtbl)
				->where($this->_db->quoteName($alias) . ' = ' . $this->_db->Quote($possible_alias))
				->where($this->_db->quoteName('id') . ' <> ' . (int) $this->id)
			;

			// CURRENTLY for unique alias check only main category,
			// as checking all categories (should we choose to do it), should be done in a different way, checking every category with individual SQL query in a loop
			/*if (!empty($this->categories))
			{
				$query->where($this->_db->quoteName('catid') . ' IN (' . implode(', ', ArrayHelper::toInteger($this->categories)) . ')');
			}
			else*/if (!empty($this->catid))
			{
				$query->where($this->_db->quoteName('catid') . ' = ' . (int) $this->catid);
			}

			if (!empty($this->language) && $this->language !== '*' && $this->_record_name !== 'tag')  // Do not use language FOR TAGS yet ...
			{
				$query->where(
					'(' .
					$this->_db->quoteName('language') . ' = ' . $this->_db->Quote($this->language) . ' OR ' .
					$this->_db->quoteName('language') . ' = ' . $this->_db->Quote('*') .
					')'
				);
			}
			
			$duplicate_alias = (boolean) $this->_db->setQuery($query)->loadResult();

			if ($duplicate_alias)
			{
				$non_unique_alias = $original_alias;
				$possible_alias = $this->$alias . '-' . (++$n);
				continue;
			}

			break;
		}

		$this->$alias = $possible_alias;


		/**
		 * Add some warning messages
		 */

		// Warn on too many duplicate aliases
		if ($n >= $max_retries)
		{
			$msg = 'Too many retries ' . $max_retries . ' to find a unique alias for alias: ' . $original_alias;
			JFactory::getApplication()->enqueueMessage($msg, 'warning');
		}

		// Warn on invalid characters alias changed (but do not add message on empty original alias)
		elseif (!empty($invalid_chars_alias) && $invalid_chars_alias !== $this->alias)
		{
			$msg = JText::sprintf('FLEXI_WARN_' . $this->_NAME . '_' . strtoupper($alias) . '_CORRECTED', $invalid_chars_alias, $this->$alias);
			JFactory::getApplication()->enqueueMessage($msg, 'notice');
		}

		// Warn on non unique original alias changed (but do not add message on empty original alias)
		elseif (!empty($non_unique_alias))
		{
			//$msg = JText::sprintf('FLEXI_THIS_' . strtoupper($alias) . '_ALREADY_EXIST', $non_unique_alias);
			//JFactory::getApplication()->enqueueMessage($msg, 'warning');
		}

		return true;
	}


	/**
	 * Method to set the publishing state for a row or list of rows in the database table.
	 *
	 * The method respects checked out rows by other users and will attempt to checkin rows that it can after adjustments are made.
	 *
	 * @param   mixed    $pks     An optional array of primary key values to update. If not set the instance property value is used.
	 * @param   integer  $state   The publishing state. eg. [0 = unpublished, 1 = published]
	 * @param   integer  $userId  The user ID of the user performing the operation.
	 *
	 * @return  boolean  True on success; false if $pks is empty.
	 *
	 * @since   3.3
	 */
	public function publish($pks = null, $state = 1, $userId = 0)
	{
		$record = JTable::getInstance($this->_jtbls[$this->_tbl][0], $this->_jtbls[$this->_tbl][1]);
		$record->_tbl = $this->_tbl;
		$record->_tbl_key = $this->_tbl_key;
		$record->setColumnAlias('published', $this->_jtbls[$this->_tbl][2]);

		$ext_ids = array();
		$tmp_ids = array();

		foreach($pks as $pk)
		{
			$record->load($pk);

			if (!empty($this->_tbl_ext))
			{
				$ext_ids[] = $record->{$this->_tbl_key_ext};
			}

			if (!empty($this->_tbl_tmp))
			{
				$tmp_ids[] = $record->{$this->_tbl_key_tmp};
			}
		}

		if (!empty($this->_tbl_ext) && $this->_jtbls[$this->_tbl_ext][2])
		{
			$record_ext = JTable::getInstance($this->_jtbls[$this->_tbl_ext][0], $this->_jtbls[$this->_tbl_ext][1]);
			$record_ext->_tbl = $this->_tbl_ext;
			$record_ext->_tbl_key = $this->_frn_key_ext;
			$record_ext->setColumnAlias('published', $this->_jtbls[$this->_tbl_ext][2]);
			$record_ext->publish($ext_ids, $state, $userId);
		}

		if (!empty($this->_tbl_tmp) && $this->_jtbls[$this->_tbl_tmp][2])
		{
			$record_tmp = JTable::getInstance($this->_jtbls[$this->_tbl_tmp][0], $this->_jtbls[$this->_tbl_tmp][1]);
			$record_tmp->_tbl = $this->_tbl_tmp;
			$record_tmp->_tbl_key = $this->_frn_key_tmp;
			$record_tmp->setColumnAlias('published', $this->_jtbls[$this->_tbl_tmp][2]);
			$record_tmp->publish($tmp_ids, $state, $userId);
		}

		return parent::publish($pks, $state, $userId);
	}


	/**
	 * Get asset name
	 *
	 * @return  string   The asset name of the currently loaded record
	 *
	 * @since   3.3.0
	 */
	public function getAssetName()
	{
		return $this->_getAssetName();
	}


	/**
	 * Get asset title
	 *
	 * @return  string   The asset title of the currently loaded record
	 *
	 * @since   3.3.0
	 */
	public function getAssetTitle()
	{
		return $this->_getAssetTitle();
	}


	/**
	 * Method to return the title to use for the asset table
	 *
	 * @return  string   The asset title of the currently loaded record, this should be title of the record !
	 *
	 * @since   3.4.0
	 */
	protected function _getAssetTitle()
	{
		if (!empty($this->{$this->_title}))
		{
			return $this->{$this->_title};
		}
		elseif (!empty($this->title))
		{
			return $this->title;
		}
		elseif (!empty($this->label))
		{
			return $this->label;
		}
		elseif (!empty($this->name))
		{
			return $this->name;
		}

		return $this->_getAssetName();
	}


	/**
	 * Get asset prefix of this record type (this the asset name excluding the .id)
	 *
	 * @return  string   The asset prefix
	 *
	 * @since   3.3.0
	 */
	public function getAssetPrefix()
	{
		$assetName = $this->_getAssetName();
		$lastDot   = strrpos($assetName, '.');

		if ($assetName !== 'root.1' && $lastDot !== false)
		{
			return substr($assetName, 0, $lastDot);
		}

		return 'com_flexicontent.' . $this->_record_name;
	}
}
