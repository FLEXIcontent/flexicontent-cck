<?php
/** Run the templates' real cookie normalization on stale/malformed nested state. */
foreach (['news', 'swiper', 'carousel'] as $layout) {
    $source = file_get_contents(__DIR__ . '/../modules/mod_flexicontent/tmpl/' . $layout . '.php');
    if (!preg_match('/\$active_tagids_feat = .*?\$active_tagids_std\s*= [^\n]+;/s', $source, $match)) throw new RuntimeException('Missing state block: ' . $layout);
    foreach (['{}', '{"active_tagids_feat":42,"active_tagids_std":[]}', '{"active_tagids_feat":"stale","active_tagids_std":null}', '{"active_tagids_feat":{"one":null},"active_tagids_std":{"two":"panel"}}'] as $cookie) {
        $fcMod_conf = json_decode($cookie);
        eval($match[0]);
        if (!is_object($active_tagids_feat) || !is_object($active_tagids_std)) throw new RuntimeException($layout . ': non-object cookie state reaches property_exists');
        if (str_contains($cookie, 'panel') && (!property_exists($active_tagids_feat, 'one') || $active_tagids_std->two !== 'panel')) throw new RuntimeException('Valid saved state lost');
    }
}
echo "PASS: module cookie groups normalize stale nested data and preserve closed/active panels\n";
