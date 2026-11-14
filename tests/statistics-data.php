<?php
/** Exercise the exact statistics template data blocks without booting Joomla. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$source = file_get_contents(__DIR__ . '/../admin/views/stats/tmpl/default.php');
function block($source, $start, $end) {
    $offset = strpos($source, $start);
    if ($offset === false || ($finish = strpos($source, $end, $offset)) === false) {
        throw new RuntimeException('Statistics block not found');
    }
    return substr($source, $offset, $finish - $offset);
}
$timeline = block($source, '$stasts ', '?>');
$workflow = block($source, '$workflow ', '?>');
$ratings = block($source, '$votesitems ', '?>');
$checks = 0;
class StatisticsFixture {
    public $itemsgraph;
    public $statestats;
    public $votesstats;
}
foreach ([[], [(object) ['year_month_text'=>'2026-Nov', 'item_count'=>'3']]] as $rows) {
    $fixture = new StatisticsFixture;
    $fixture->itemsgraph = [$rows];
    $result = (function () use ($timeline) { eval($timeline); return [$monthslist, $totalitemslist]; })->call($fixture);
    foreach ($result as $json) {
        if (!is_array(json_decode($json, true, 512, JSON_THROW_ON_ERROR))) throw new RuntimeException('Timeline is not an array');
        $checks++;
    }
    if (count(json_decode($result[0], true)) !== count($rows)) throw new RuntimeException('Timeline length changed');
    $checks++;
}
foreach ([['labels'=>'', 'values'=>''], ['labels'=>'Published|Draft', 'values'=>'3,0'],
          ['labels'=>'A "quote" </script> 中文|Other', 'values'=>'2,']] as $stats) {
    foreach ([[$workflow, 'statestats'], [$ratings, 'votesstats']] as [$code, $property]) {
        $fixture = new StatisticsFixture;
        $fixture->$property = $stats;
        $json = (function () use ($code) { eval($code); return $datalist; })->call($fixture);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (count($data) !== count(explode('|', $stats['labels']))) throw new RuntimeException('Pie labels lost');
        foreach ($data as $i=>$row) {
            if ($row['name'] !== explode('|', $stats['labels'])[$i]) throw new RuntimeException('Pie label changed');
            if (!is_numeric($row['value'])) throw new RuntimeException('Pie value is not numeric');
            $checks += 2;
        }
        if (stripos($json, '</script') !== false) throw new RuntimeException('Unsafe inline script terminator');
        $checks++;
    }
}
restore_error_handler();
echo $checks . " statistics data checks passed\n";
