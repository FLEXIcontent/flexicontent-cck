<?php
/**
 * Title: Thai Splitter Lib
 * Author: Suwicha Phuak-im
 * Email: suwichalala@gmail.com
 * Website: http://www.projecka.com
 */
class Thchracter
{
    function is_consonant($char_number)
    {
        if ($char_number >= 3585 && 3630 >= $char_number)
        {
            return true;
        }
        return false;
    }
    
    public function is_vowel($char_number)
    {
        if ($char_number >= 3632 && 3653 >= $char_number)
        {
            return true;
        }
        return false;
    }
    
    public function is_token($char_number)
    {
        if ($char_number >= 3656 && 3659)
        {
            return true;
        }
        return false;
    }
}
