<?php
define('_JEXEC', 1);
require dirname(__DIR__, 2) . '/site/classes/helpers/fileeditor.php';
$cases = [
 ['report.ccc','stored.pdf','report.pdf'],
 ['report.docx','stored.pdf','report.pdf'],
 ['report.PDF','stored.pdf','report.pdf'],
 ['report','stored.pdf','report.pdf'],
 ['annual.report.v2.pdf','stored.pdf','annual.report.v2.pdf'],
 ['中文報告.ccc','stored.pdf','中文報告.pdf'],
 ['../report.exe','stored.pdf','report.pdf'],
 ['C:\\temp\\report.exe','stored.pdf','report.pdf'],
 ["re\r\nport.exe",'stored.pdf','report.pdf'],
 ['<b>report</b>.exe','stored.pdf','report.pdf'],
 ['', 'stored.pdf','stored.pdf'],
 ['.ccc','stored.pdf','stored.pdf'],
 ['report.pdf','replacement.docx','report.docx'],
 ['report.ccc','https://example.invalid/files/stored.PDF?download=1','report.pdf'],
];
foreach ($cases as [$requested,$stored,$expected]) {
 $actual=flexicontent_fileeditor::downloadName($requested,$stored);
 if($actual!==$expected)throw new RuntimeException(json_encode([$requested,$expected,$actual]));
}
echo count($cases)." download-name checks passed\n";
