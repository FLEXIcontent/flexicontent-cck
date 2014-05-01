<?php
/**
 * Title: Thai Splitter Lib
 * Author: Suwicha Phuak-im
 * Email: suwichalala@gmail.com
 * Website: http://www.projecka.com
 */
class Unicode {

    function uni_ord_list_to_string($ords, $encoding = 'UTF-8') {
        /*
          By Darien Hager, Jan 2007... Use however you wish, but please
          please give credit in source comments.
         */
        $str = '';
        for ($i = 0; $i < sizeof($ords); $i++) {
            $v = $ords[$i];
            $str .= pack("N", $v);
        }
        $str = mb_convert_encoding($str, $encoding, "UCS-4BE");
        return($str);
    }

    function string_to_uni_ord_list($str, $encoding = 'UTF-8') {
        /*
          By Darien Hager, Jan 2007... Use however you wish, but please
          please give credit in source comments.
         */
        $str = mb_convert_encoding($str, "UCS-4BE", $encoding);
        $ords = array();
        for ($i = 0; $i < mb_strlen($str, "UCS-4BE"); $i++) {
            $s2 = mb_substr($str, $i, 1, "UCS-4BE");
            $val = unpack("N", $s2);
            $ords[] = $val[1];
        }
        return($ords);
    }

    function uni_chr($ord, $encoding = 'UTF-8') {
        return mb_convert_encoding(pack("N", $ord), $encoding, "UCS-4BE");
    }

    function uni_ord($chr, $encoding = 'UTF-8') {
        return unpack("N", mb_convert_encoding($chr, "UCS-4BE", $encoding));
    }

    function uni_strsplit($string, $split_length=1) {
        preg_match_all('`.`u', $string, $arr);
        $arr = array_chunk($arr[0], $split_length);
        $arr = array_map('implode', $arr);
        return $arr;
    }

}