<?php
/**
 * Download links that were recorded for articles before a link had to pass a rights check are reviewed once when the
 * component is updated: a row stays when someone who saved the article could have recorded it under the present
 * rule, another row is removed. Runs the real review against tables held in memory.
 */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

define('_JEXEC', 1);

$root = dirname(__DIR__);
require $root . '/site/classes/helpers/security.php';

$checks = 0;
$failures = array();
$check = function ($condition, $message) use (&$checks, &$failures) {
    $checks++;
    if (!$condition) $failures[] = $message;
};

/** The statements of the review, run against arrays. Any other statement is an error. */
final class ReviewDatabase
{
    public $usage = array();
    public $content = array();
    public $files = array();
    public $versions = array();
    public $log = array();
    private $sql = '';

    public function quote($value) { return "'" . str_replace("'", "''", (string) $value) . "'"; }
    public function setQuery($sql) { $this->sql = (string) $sql; $this->log[] = $this->sql; return $this; }

    public function loadResult()
    {
        if (preg_match("/^SELECT COUNT\(\*\) FROM #__flexicontent_file_usage WHERE context = '([^']*)'$/", $this->sql, $m)) {
            return count(array_filter($this->usage, function ($row) use ($m) { return $row['context'] === $m[1]; }));
        }
        throw new LogicException('Unexpected statement: ' . $this->sql);
    }

    public function loadObjectList()
    {
        if (preg_match("/^SELECT DISTINCT u\.id AS item_id, u\.file_id, f\.uploaded_by, c\.created_by, c\.modified_by FROM #__flexicontent_file_usage AS u"
            . " JOIN #__content AS c ON c\.id = u\.id JOIN #__flexicontent_files AS f ON f\.id = u\.file_id WHERE u\.context = '([^']*)'$/", $this->sql, $m)) {
            $rows = array();
            foreach ($this->usage as $row) {
                if ($row['context'] !== $m[1] || !isset($this->content[$row['id']], $this->files[$row['file_id']])) continue;
                $rows[$row['id'] . ':' . $row['file_id']] = (object) array(
                    'item_id' => (string) $row['id'], 'file_id' => (string) $row['file_id'], 'uploaded_by' => (string) $this->files[$row['file_id']],
                    'created_by' => (string) $this->content[$row['id']][0], 'modified_by' => (string) $this->content[$row['id']][1],
                );
            }
            return array_values($rows);
        }
        if (preg_match('/^SELECT DISTINCT item_id, created_by FROM #__flexicontent_versions WHERE item_id IN \(([\d,]+)\)$/', $this->sql, $m)) {
            if ($this->versions === null) throw new RuntimeException("Table '#__flexicontent_versions' doesn't exist");
            $ids = array_map('intval', explode(',', $m[1]));
            $rows = array();
            foreach ($this->versions as $version) {
                if (in_array($version[0], $ids, true)) $rows[] = (object) array('item_id' => (string) $version[0], 'created_by' => (string) $version[1]);
            }
            return $rows;
        }
        throw new LogicException('Unexpected statement: ' . $this->sql);
    }

    public function execute()
    {
        if (preg_match("/^DELETE FROM #__flexicontent_file_usage WHERE context = '([^']*)' AND id = (\d+) AND file_id = (\d+)$/", $this->sql, $m)) {
            $this->usage = array_values(array_filter($this->usage, function ($row) use ($m) {
                return !($row['context'] === $m[1] && $row['id'] === (int) $m[2] && $row['file_id'] === (int) $m[3]);
            }));
            return true;
        }
        if (preg_match("/^INSERT INTO #__flexicontent_file_usage \(id, context, file_id, prop\) VALUES \(0, '([^']*)', 0, '(\d{4}-\d{2}-\d{2})'\)$/", $this->sql, $m)) {
            $this->usage[] = array('id' => 0, 'context' => $m[1], 'file_id' => 0, 'prop' => $m[2]);
            return true;
        }
        throw new LogicException('Unexpected statement: ' . $this->sql);
    }
}

$usage = function ($item, $file, $context = 'com_content.article', $prop = 'introtext') {
    return array('id' => $item, 'context' => $context, 'file_id' => $file, 'prop' => $prop);
};
$has = function (ReviewDatabase $db, $item, $file, $context = 'com_content.article') {
    return (bool) array_filter($db->usage, function ($row) use ($item, $file, $context) {
        return $row['id'] === $item && $row['file_id'] === $file && $row['context'] === $context;
    });
};

// Users: 10 author, 11 second author, 20 editor without rights on other users' files, 30 file manager, 40 administrator
$asked = array();
$rights = function ($userId) use (&$asked) {
    $asked[] = $userId;
    return in_array($userId, array(30, 40), true);
};

$db = new ReviewDatabase;
// Files: id => uploader
$db->files = array(1 => 10, 2 => 20, 3 => 30, 4 => 11, 5 => 40, 6 => 0, 7 => 99);
// Articles: id => (creator, last editor)
$db->content = array(
    100 => array(10, 10),   // an author alone
    101 => array(10, 30),   // an author, saved by a file manager
    102 => array(10, 20),   // an author, saved by an editor
    103 => array(10, 10),   // an author alone, an editor saved an earlier version
    104 => array(40, 40),   // an administrator alone
    105 => array(10, 0),    // never edited
    106 => array(0, 0),     // no creator recorded
    107 => array(11, 11),   // another author
);
$db->versions = array(array(103, 10), array(103, 20), array(100, 10), array(107, 11), array(999, 30));
$db->usage = array(
    $usage(100, 1),                      // own upload: stays
    $usage(100, 2),                      // a file of the editor, nobody behind the article may use it: removed
    $usage(100, 3, 'com_content.article', 'custom.notes.0'), // a file of the file manager in a field value: removed
    $usage(100, 5),                      // a file of the administrator: removed
    $usage(101, 2),                      // a file manager saved the article: stays (the review cannot tell who added the link)
    $usage(101, 4),                      // the same article, any file: stays
    $usage(102, 2),                      // the editor saved the article and uploaded the file: stays
    $usage(102, 3),                      // the editor saved the article, the file is not the editor's: removed
    $usage(103, 2),                      // the editor is the author of a version: stays
    $usage(103, 4),                      // nobody behind the article uploaded it: removed
    $usage(104, 1),                      // an administrator: stays
    $usage(104, 6),                      // an administrator, a file without uploader: stays
    $usage(105, 1),                      // never edited, own upload: stays
    $usage(105, 6),                      // a file without uploader, an author: removed
    $usage(106, 6),                      // neither creator nor uploader known: removed
    $usage(107, 1),                      // another author links the file of the first: removed
    $usage(107, 4), $usage(107, 4, 'com_content.article', 'fulltext'), // own upload, linked twice: both stay
    $usage(100, 2, 'com_modules.module'), // the same ids in another context: not a row of an article
    $usage(555, 2),                      // the article no longer exists: left alone
    $usage(100, 888),                    // the file no longer exists: left alone
    $usage(100, 7),                      // the uploader no longer exists and nobody behind the article may use it: removed
);
$before = count($db->usage);

$result = flexicontent_security::reviewRecordedFileLinks($db, $rights);

$check(is_array($result) && $result['reviewed'] === 18, 'the review did not look at the 18 different links of existing articles and files: ' . json_encode($result));
$removed = array_map(function ($pair) { return $pair[0] . ':' . $pair[1]; }, $result['removed']);
sort($removed);
$expected = array('100:2', '100:3', '100:5', '100:7', '102:3', '103:4', '105:6', '106:6', '107:1');
$check($removed === $expected, 'the review removed other links than expected: ' . implode(' ', $removed));
foreach ($expected as $pair) {
    list($item, $file) = array_map('intval', explode(':', $pair));
    $check(!$has($db, $item, $file), 'a link that nobody behind the article could record is still there: ' . $pair);
}
foreach (array('100:1', '101:2', '101:4', '102:2', '103:2', '104:1', '104:6', '105:1', '107:4', '555:2', '100:888') as $pair) {
    list($item, $file) = array_map('intval', explode(':', $pair));
    $check($has($db, $item, $file), 'a link that stays was removed: ' . $pair);
}
$check(count(array_filter($db->usage, function ($row) { return $row['id'] === 107 && $row['file_id'] === 4; })) === 2, 'one of two rows of the same link was removed');
$check($has($db, 100, 2, 'com_modules.module'), 'a row of another context was removed');
$check(count($db->usage) === $before - 9 + 1, 'the table has ' . count($db->usage) . ' rows, expected ' . ($before - 9 + 1));

// The rights of a user are asked once, never for the id 0, and not when the uploader is among the savers
$check(count($asked) === count(array_unique($asked)), 'the rights of a user were asked more than once: ' . implode(',', $asked));
$check(!in_array(0, $asked, true), 'the rights of user 0 were asked');
$check(in_array(10, $asked, true) && in_array(30, $asked, true) && in_array(40, $asked, true), 'the rights of the savers were not asked');

// The marker: one row that no reader of the table takes for a link
$marker = array_values(array_filter($db->usage, function ($row) { return $row['context'] === flexicontent_security::FILE_LINK_REVIEW_CONTEXT; }));
$check(count($marker) === 1 && $marker[0]['id'] === 0 && $marker[0]['file_id'] === 0 && $marker[0]['prop'] === gmdate('Y-m-d'), 'the review did not leave its marker');
$check(flexicontent_security::FILE_LINK_REVIEW_CONTEXT !== 'com_content.article' && strpos(flexicontent_security::FILE_LINK_REVIEW_CONTEXT, 'com_content') !== 0, 'the marker has the context of an article');

// A second run does nothing: links recorded after the first one passed the check when they were saved
$db->usage[] = $usage(100, 2);
$db->log = array();
$check(flexicontent_security::reviewRecordedFileLinks($db, function () { throw new LogicException('rights asked'); }) === null, 'the review ran a second time');
$check($has($db, 100, 2) && count($db->log) === 1, 'the second run touched the table');

// Nothing recorded (a new site): the marker is written, nothing else
$empty = new ReviewDatabase;
$result = flexicontent_security::reviewRecordedFileLinks($empty, $rights);
$check($result === array('reviewed' => 0, 'removed' => array()) && count($empty->usage) === 1, 'the review of an empty table is wrong');
$check(!array_filter($empty->log, function ($sql) { return strpos($sql, '#__flexicontent_versions') !== false || strpos($sql, 'DELETE') === 0; }), 'the review of an empty table read versions or deleted rows');

// A site without the versions table: the creator and the last editor decide
$plain = new ReviewDatabase;
$plain->versions = null;
$plain->files = array(1 => 10, 2 => 20);
$plain->content = array(100 => array(10, 20), 103 => array(10, 10));
$plain->usage = array($usage(100, 1), $usage(100, 2), $usage(103, 2));
$result = flexicontent_security::reviewRecordedFileLinks($plain, $rights);
$check($result['reviewed'] === 3 && $result['removed'] === array(array(103, 2)), 'without the versions table the review is wrong: ' . json_encode($result));
$check($has($plain, 100, 1) && $has($plain, 100, 2) && !$has($plain, 103, 2), 'without the versions table the wrong rows were removed');

// More articles than one lookup of versions takes
$large = new ReviewDatabase;
$large->files = array(1 => 10, 2 => 20);
for ($i = 1; $i <= 1100; $i++) {
    $large->content[$i] = array(10, 10);
    $large->usage[] = $usage($i, $i % 2 ? 1 : 2);
    if ($i > 1000) $large->versions[] = array($i, 20);
}
$result = flexicontent_security::reviewRecordedFileLinks($large, $rights);
$check($result['reviewed'] === 1100 && count($result['removed']) === 500, 'the review of many articles is wrong: ' . count($result['removed']) . ' removed');
$check(count(array_filter($large->log, function ($sql) { return strpos($sql, '#__flexicontent_versions') !== false; })) === 3, 'the versions of many articles are not read in parts');
$check($has($large, 1002, 2) && !$has($large, 2, 2) && $has($large, 1, 1), 'the versions of the last part of the articles were not used');

// The default rule for "may use all files" is the rule of the download check and of the save
$helper = str_replace("\r\n", "\n", file_get_contents($root . '/site/classes/helpers/security.php'));
$check(preg_match("/Access::check\(\\\$userId, 'core\.admin', 'com_flexicontent'\)\s*\|\| \\\\Joomla\\\\CMS\\\\Access\\\\Access::check\(\\\$userId, 'flexicontent\.viewallfiles', 'com_flexicontent'\)/", $helper) === 1, 'the default rights of the review are not those of a file manager');
$plugin = str_replace("\r\n", "\n", file_get_contents($root . '/plugins/system/flexisystem/flexisystem.php'));
$check(strpos($plugin, "\$user->authorise('core.admin', 'com_flexicontent') || \$user->authorise('flexicontent.viewallfiles', 'com_flexicontent')") !== false, 'the save no longer uses the rights the review assumes');
$controller = str_replace("\r\n", "\n", file_get_contents($root . '/site/controller.php'));
$check(strpos($controller, "AND usage_row.context = ' . \$db->Quote('com_content.article')") !== false, 'the download check reads rows of another context than the review');

// The installer runs the review once the table exists, and reports what it removed
$script = str_replace("\r\n", "\n", file_get_contents($root . '/script.php'));
$call = strpos($script, 'flexicontent_security::reviewRecordedFileLinks($db)');
$check($call !== false && substr_count($script, 'reviewRecordedFileLinks($db)') === 1, 'the installer does not run the review exactly once');
$check($call > strpos($script, 'CREATE TABLE IF NOT EXISTS `#__flexicontent_file_usage`'), 'the installer reviews before the table exists');
$check($call > strpos($script, 'function postflight(') && $call < strpos($script, 'function uninstall('), 'the review is not part of the post-install steps');
$block = substr($script, $call - 400, 2600);
$check(strpos($block, "require_once JPATH_SITE . '/components/com_flexicontent/classes/helpers/security.php';") !== false, 'the installer does not load the helper it calls');
$check(strpos($block, 'catch (\Throwable $e)') !== false, 'an error of the review stops the installation');
$check(strpos($block, "'flexicontent.file_links_review.php'") !== false && strpos($block, "count(\$review['removed'])") !== false, 'the installer does not report or log the removed links');
$check(preg_match('/echo [^;]*\$e->getMessage\(\)/', $block) === 1 && preg_match('/echo [^;]*htmlspecialchars\(\$e->getMessage\(\)/', $block) === 1, 'the error message of the review is printed without encoding');

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n" . count($failures) . ' of ' . $checks . " checks failed\n");
    exit(1);
}
echo $checks . " checks passed\n";
