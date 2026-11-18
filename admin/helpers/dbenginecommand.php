<?php
/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once __DIR__ . '/dbengine.php';

/**
 * Console command that moves FLEXIcontent's database tables to InnoDB, for sites that are too large
 * to convert through the browser:
 *
 *   php cli/joomla.php flexicontent:database:innodb --check
 *   php cli/joomla.php flexicontent:database:innodb --backup-confirmed
 */
class FlexicontentDbEngineCommand extends AbstractCommand
{
	protected static $defaultName = 'flexicontent:database:innodb';


	protected function configure(): void
	{
		$this->setDescription("Move FLEXIcontent's database tables from MyISAM to InnoDB");
		$this->addOption('check', null, InputOption::VALUE_NONE, 'Only show the checks and the tables that would be converted');
		$this->addOption('backup-confirmed', null, InputOption::VALUE_NONE, 'Confirm that a current database backup exists (required to convert)');
		$this->addOption('utf8mb4', null, InputOption::VALUE_NONE, 'First convert the tables that still use the 3-byte utf8 character set to utf8mb4');
		$this->setHelp(
			"Each table is copied while it is converted, so make a database backup first and expect the site's item saves"
			. " to wait while a table is being converted.\n"
			. "The command converts one table after the other and verifies each one (row count, checksum of the key columns,"
			. " FULLTEXT indexes). It can be stopped and started again: tables that are already InnoDB are skipped.\n"
			. "To go back, restore the backup, or run for a table: ALTER TABLE `<table>` ENGINE=MyISAM"
		);
	}


	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$db = Factory::getContainer()->get(DatabaseInterface::class);

		$io->title("FLEXIcontent: move database tables to InnoDB");

		// Optional first step, when the installed version has the utf8mb4 conversion
		$charset_helper = __DIR__ . '/dbcharset.php';

		if ($input->getOption('utf8mb4') && !$input->getOption('check'))
		{
			if (!file_exists($charset_helper))
			{
				$io->error('This FLEXIcontent version has no utf8mb4 conversion');

				return Command::FAILURE;
			}

			require_once $charset_helper;

			foreach (FlexicontentDbCharset::convertPending($db) as $name => $result)
			{
				$io->writeln(sprintf('  %s utf8mb4  %s: %s', $result[0] ? '<info>ok</info>  ' : '<error>FAIL</error>', $name, $result[1]));

				if (!$result[0])
				{
					return Command::FAILURE;
				}
			}
		}

		$preflight = FlexicontentDbEngine::getPreflight($db);
		$rows      = array();

		foreach ($preflight->checks as $check)
		{
			$rows[] = array($check->ok === true ? 'ok' : ($check->ok === false ? 'NO' : ''), $check->label, $check->value);
		}

		$io->table(array('', 'Check', 'Result'), $rows);

		if (!$preflight->pending)
		{
			$io->success('All FLEXIcontent tables are InnoDB already');

			return Command::SUCCESS;
		}

		$rows = array();

		foreach ($preflight->pending as $table)
		{
			$rows[] = array($table->name, $table->engine, $table->rows, FlexicontentDbEngine::formatBytes($table->bytes), FlexicontentDbEngine::formatSeconds($table->seconds));
		}

		$io->table(array('Table', 'Engine', 'Rows (estimate)', 'Size', 'Estimated time'), $rows);

		if ($input->getOption('check'))
		{
			return $preflight->ok ? Command::SUCCESS : Command::FAILURE;
		}

		if (!$preflight->ok)
		{
			$io->error('The conversion cannot start: see the checks marked NO');

			return Command::FAILURE;
		}

		if (!$input->getOption('backup-confirmed')
			&& !$io->confirm('Each table is copied while it is converted. Do you have a current backup of the database?', false)
		)
		{
			$io->warning('Nothing was converted. Make a backup, then run the command again with --backup-confirmed');

			return Command::FAILURE;
		}

		$start = microtime(true);
		$done  = 0;

		while ($result = FlexicontentDbEngine::convertNext($db))
		{
			$io->writeln(sprintf('  %s  %s: %s', $result[1] ? '<info>ok</info>  ' : '<error>FAIL</error>', $result[0], $result[2]));

			if (!$result[1])
			{
				$io->error('Stopped at the first table that failed. Tables converted so far stay InnoDB; run the command again after solving the problem');

				return Command::FAILURE;
			}

			$done++;
		}

		$io->success(sprintf('%d table(s) converted and verified in %s', $done, FlexicontentDbEngine::formatSeconds(microtime(true) - $start)));

		return Command::SUCCESS;
	}
}
