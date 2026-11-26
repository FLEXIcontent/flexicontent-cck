<?php
namespace Joomla\CMS\Uri {
    final class Uri { public static function root($path = false) { return $path ? '' : 'https://fixture.example/'; } }
}
namespace {
    define('_JEXEC', 1);
    define('JPATH_SITE', sys_get_temp_dir() . '/fc-thumb-widths-' . getmypid());
    mkdir(JPATH_SITE);
    function pngFixture($path, $width, $height) {
        $chunk = static function ($type, $data) { return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data)); };
        file_put_contents($path, "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress(str_repeat("\0" . str_repeat("\x80\x80\x80", $width), $height))) . $chunk('IEND', ''));
    }
    function methodSource($source, $name) {
        $start = strpos($source, 'function ' . $name . '(');
        $method=''; $depth=0; $opened=false;
        foreach (token_get_all('<?php ' . substr($source,$start)) as $token) {
            if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
            $method .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { ++$depth; $opened=true; }
            if ($token === '}' && --$depth === 0 && $opened) break;
        }
        return $method;
    }
    require __DIR__ . '/../modules/mod_flexicontent/helper.php';
    $source=file_get_contents(__DIR__ . '/../site/templates/grid/category_items.php');
    eval(methodSource($source,'fc_cat_srcset_url'));
    eval(methodSource($source,'fc_cat_make_picture_from_thumbs'));
    $params=new class { public function get($name,$default) { return array('w_s'=>120,'w_m'=>400,'w_l'=>800)[$name] ?? $default; } };
    $failures=array(); $checks=0;
    try {
        foreach (array('s'=>60,'m'=>90,'l'=>90,'second'=>180) as $name=>$width) pngFixture(JPATH_SITE.'/'.$name.'.png',$width,160);
        $webp=base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA');
        foreach (array('s','m','l') as $name) file_put_contents(JPATH_SITE.'/'.$name.'.webp',$webp);
        $field=(object)array('parameters'=>$params,'thumbs_src'=>array('small'=>array('/s.png'),'medium'=>array('/m.png'),'large'=>array('/l.png')));
        $urls=array('small'=>'/s.png','medium'=>'/m.png','large'=>'/l.png');
        $cases=array('small source and duplicate sizes'=>array($field,$urls), 'missing medium'=>array(clone $field,$urls), 'second field'=>array(clone $field,$urls));
        $cases['missing medium'][0]->thumbs_src['medium']=array('/absent.png'); $cases['missing medium'][1]['medium']='/absent.png';
        $cases['second field'][0]->thumbs_src['large']=array('/second.png'); $cases['second field'][1]['large']='/second.png';
        foreach ($cases as $name=>[$f,$u]) {
            foreach (array('module'=>modFlexicontentHelper::makePictureFromThumbs($f,'large','Fixture',110,160),
                'grid'=>fc_cat_make_picture_from_thumbs($u,'large',$u['large'],'Fixture','','',110,160)) as $helper=>$html) {
                preg_match('/srcset="([^"]+)"/',$html,$match); $seen=array();
                foreach (explode(',',$match[1] ?? '') as $candidate) {
                    if (!preg_match('/\s*(\S+)\s+(\d+)w\s*$/',$candidate,$c)) { $failures[]="$name $helper missing descriptor"; continue; }
                    $width=(int)$c[2]; $dimensions=@getimagesize(JPATH_SITE.$c[1]);
                    if (!$dimensions || $dimensions[0] !== $width) $failures[]="$name $helper incorrect descriptor $candidate";
                    if (isset($seen[$width])) $failures[]="$name $helper duplicate width $width";
                    $seen[$width]=true; ++$checks;
                }
                $sizes = $helper === 'module' ? 'auto, 100vw' : '100vw';
                if (strpos($html,'sizes="'.$sizes.'"') === false) $failures[]="$name $helper uses intrinsic widths as display sizes";
                ++$checks;
            }
        }
        $field->thumbs_src['original']=array('/l.png');
        foreach (array('large','original') as $size) {
            foreach (array(modFlexicontentHelper::makePictureFromThumbs($field,$size,'Fixture',110,160,true),
                fc_cat_make_picture_from_thumbs($urls,$size,'/l.png','Fixture','','',110,160,true)) as $html) {
                preg_match('/<source[^>]*srcset="([^"]+)"/',$html,$match);
                $seen=array();
                foreach (explode(',',$match[1] ?? '') as $candidate) {
                    if (!preg_match('/\s*(\S+)\s+(\d+)w\s*$/',$candidate,$c)) { $failures[]='Missing WebP descriptor'; continue; }
                    $dimensions=getimagesize(JPATH_SITE.$c[1]); $width=(int)$c[2];
                    if ($dimensions[0] !== $width || isset($seen[$width])) $failures[]='Incorrect or duplicate WebP width';
                    $seen[$width]=true; ++$checks;
                }
            }
        }
        // JPEG candidates can collapse to one width while WebP widths still differ.
        // getimagesize reads the RIFF VP8X canvas without requiring the GD extension.
        $vp8x = static function ($width) {
            $payload = "\0\0\0\0" . substr(pack('V', $width - 1), 0, 3) . "\0\0\0";
            return 'RIFF' . pack('V', 22) . 'WEBPVP8X' . pack('V', 10) . $payload;
        };
        foreach (array('s'=>2,'m'=>3,'l'=>4) as $name=>$width) {
            pngFixture(JPATH_SITE.'/'.$name.'.png',90,160);
            file_put_contents(JPATH_SITE.'/'.$name.'.webp',$vp8x($width));
        }
        $html=fc_cat_make_picture_from_thumbs($urls,'large','/l.png','Fixture','','',110,160,true);
        if (!preg_match('/<source[^>]*sizes="100vw"[^>]*srcset="[^"]+2w,[^"]+3w,[^"]+4w"/', $html)) {
            $failures[]='WebP display sizes incorrectly depend on JPEG candidates';
        }
        ++$checks;
        $html=fc_cat_make_picture_from_thumbs($urls,'large','/l.png','Fixture','','loading="lazy"',110,160,true);
        if (strpos($html,'sizes="auto, 100vw"') === false) $failures[]='Lazy grid source does not use its layout width';
        ++$checks;
        $field->thumbs_src=array('small'=>array('https://remote.example/s.png'),'large'=>array('https://remote.example/l.png'));
        $urls=array('small'=>'https://remote.example/s.png','large'=>'https://remote.example/l.png');
        foreach (array(modFlexicontentHelper::makePictureFromThumbs($field,'large','Fixture',110,160),
            fc_cat_make_picture_from_thumbs($urls,'large',$urls['large'],'Fixture','','',110,160)) as $html) {
            if (strpos($html,'srcset=') !== false) $failures[]='Unverified remote widths advertised';
            ++$checks;
        }
    } finally {
        foreach (glob(JPATH_SITE.'/*') as $file) unlink($file);
        rmdir(JPATH_SITE);
    }
    if ($failures) { fwrite(STDERR,implode("\n",$failures)."\n"); exit(1); }
    echo "$checks thumbnail width checks passed\n";
}
