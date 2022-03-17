<?php
/*
 * diff.php
 *
 * @(#) $Id: diff.php,v 1.6 2014/01/30 04:07:41 mlemos Exp $
 *
 */

/*
{metadocument}<?xml version="1.0" encoding="ISO-8859-1" ?>
<class>

	<package>net.manuellemos.diff</package>

	<version>@(#) $Id: diff.php,v 1.6 2014/01/30 04:07:41 mlemos Exp $</version>
	<copyright>Copyright © (C) Manuel Lemos 2013</copyright>
	<title>OAuth client</title>
	<author>Manuel Lemos</author>
	<authoraddress>mlemos-at-acm.org</authoraddress>

	<documentation>
		<idiom>en</idiom>
		<purpose>.</purpose>
		<usage>.</usage>
	</documentation>

{/metadocument}
*/

class fc_diff_class
{
	var $error = '';
	var $insertedStyle = 'font-weight: bold';
	var $deletedStyle = 'text-decoration: line-through';

	Function SplitString($string, $separators, $end, &$positions)
	{
		$l = strlen($string);
		$split = array();
		for($p = 0; $p < $l;)
		{
			$e = strcspn($string, $separators.$end, $p);
			$e += strspn($string, $separators, $p + $e);
			$split[] = substr($string, $p, $e);
			$positions[] = $p;
			$p += $e;
			if(strlen($end)
			&& ($e = strspn($string, $end, $p)))
			{
				$split[] = substr($string, $p, $e);
				$positions[] = $p;
				$p += $e;
			}
		}
		$positions[] = $p;
		return $split;
	}

/*
{metadocument}
	<function>
		<name>Diff</name>
		<type>BOOLEAN</type>
		<documentation>
			<purpose>.</purpose>
			<usage>.</usage>
		</documentation>
		<argument>
			<name>before</name>
			<type>STRING</type>
			<documentation>
				<purpose>.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>after</name>
			<type>STRING</type>
			<documentation>
				<purpose>.</purpose>
			</documentation>
		</argument>
		<argument>
			<name>difference</name>
			<type>HASH</type>
			<out />
			<documentation>
				<purpose>.</purpose>
			</documentation>
		</argument>
		<do>
{/metadocument}
*/
	Function Diff($before, $after, &$difference)
	{
		$mode = (IsSet($difference->mode) ? $difference->mode : 'c');
		$for_patch = (IsSet($difference->patch) && $difference->patch);
		switch($mode)
		{
			case 'c':
				$lb = strlen($before);
				$la = strlen($after);
				break;

			case 'w':
				$before = $this->SplitString($before, " \t", "\r\n", $posb);
				$lb = count($before);
				$after = $this->SplitString($after, " \t", "\r\n", $posa);
				$la = count($after);
				break;

			case 'l':
				$before = $this->SplitString($before, "\r\n", '', $posb);
				$lb = count($before);
				$after = $this->SplitString($after, "\r\n", '', $posa);
				$la = count($after);
				break;

			default:
				$this->error = $mode.' is not a supported more for getting the text differences';
				return false;
		}
		$diff = array();
		for($b = $a = 0; $b < $lb && $a < $la;)
		{
			for($pb = $b; $a < $la && $pb < $lb && $after[$a] === $before[$pb]; ++$a, ++$pb);
			if($pb !== $b)
			{
				$diff[] = array(
					'change'=>'=',
					'position'=>($mode === 'c'  ? $b : $posb[$b]),
					'length'=>($mode === 'c' ? $pb - $b : $posb[$pb] - $posb[$b])
				);
				$b = $pb;
			}
			if($b === $lb)
				break;
			for($pb = $b; $pb < $lb; ++$pb)
			{
				for($pa = $a ; $pa < $la && $after[$pa] !== $before[$pb]; ++$pa);
				if($pa !== $la)
					break;
			}
			if($pb !== $b)
			{
				$diff[] = array(
					'change'=>'-',
					'position'=>($mode === 'c'  ? $b : $posb[$b]),
					'length'=>($mode === 'c' ? $pb - $b : $posb[$pb] - $posb[$b])
				);
				$b = $pb;
			}
			if($pa !== $a)
			{
				$position = ($mode === 'c'  ? $a : $posa[$a]);
				$length = ($mode === 'c' ? $pa - $a : $posa[$pa] - $posa[$a]);
				$change = array(
					'change'=>'+',
					'position'=>$position,
					'length'=>$length
				);
				if($for_patch)
				{
					if($mode === 'c')
						$patch = substr($after, $position, $length);
					else
					{
						$patch = $after[$a];
						for(++$a; $a < $pa; ++$a)
							$patch .= $after[$a];
					}
					$change['patch'] = $patch;
				}
				$diff[] = $change;
				$a = $pa;
			}
		}
		if($a < $la)
		{
			$position = ($mode === 'c'  ? $a : $posa[$a]);
			$length = ($mode === 'c' ? $la - $a : $posa[$la] - $posa[$a]);
			$change = array(
				'change'=>'+',
				'position'=>$position,
				'length'=>$length
			);
			if($for_patch)
			{
				if($mode === 'c')
					$patch = substr($after, $position, $length);
				else
				{
					$patch = $after[$a];
					for(++$a; $a < $la; ++$a)
						$patch .= $after[$a];
				}
				$change['patch'] = $patch;
			}
			$diff[] = $change;
		}
		$difference->difference = $diff;
		return true;
	}
/*
{metadocument}
		</do>
	</function>
{/metadocument}
*/

	Function FormatDiffAsHtml($before, $after, &$difference)
	{
		if(!$this->diff($before, $after, $difference))
		{
			return false;
		}
		$html = '';
		$insertedStyle = (strlen($this->insertedStyle) ? ' style="'.HtmlSpecialChars($this->insertedStyle).'"' : '');
		$deletedStyle = (strlen($this->deletedStyle) ? ' style="'.HtmlSpecialChars($this->deletedStyle).'"' : '');
		$td = count($difference->difference);
		for($d = 0; $d < $td; ++$d)
		{
			$diff = $difference->difference[$d];
			switch($diff['change'])
			{
				case '=':
					$html .= nl2br(HtmlSpecialChars(substr($before, $diff['position'], $diff['length'])));
					break;
				case '-':
					$html .= '<del'.$deletedStyle.'>'.nl2br(HtmlSpecialChars(substr($before, $diff['position'], $diff['length']))).'</del>';
					break;
				case '+':
					$html .= '<ins'.$insertedStyle.'>'.nl2br(HtmlSpecialChars(substr($after, $diff['position'], $diff['length']))).'</ins>';
					break;
				default:
					$this->error = $diff['change'].' is not an expected difference change type';
					return false;
			}
		}
		$difference->html = $html;
		return true;
	}

	Function Patch($before, $difference, &$after_patch)
	{
		$after = '';
		$b = 0;
		foreach($difference as $segment)
		{
			switch($segment['change'])
			{
				case '-':
					if($segment['position'] !== $b)
					{
						$this->error = 'removed segment position is '.$segment->position.' and not '.$b.' as expected';
						return false;
					}
					$b += $segment['length'];
					break;

				case '+':
					$after .= $segment['patch'];
					break;

				case '=':
					if($segment['position'] !== $b)
					{
						$this->error = 'removed segment position is '.$segment->position.' and not '.$b.' as expected';
						return false;
					}
					$b += $segment['length'];
					$after .= substr($before, $segment['position'], $segment['length']);
					break;

				default:
					$this->error = $segment['change'].' change type is not supported';
					return false;
			}
		}
		$after_patch->after = $after;
		return true;
	}
};

/*

{metadocument}
</class>
{/metadocument}

*/

?>