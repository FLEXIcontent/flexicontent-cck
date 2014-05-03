<?php

/**
 * Title: Thai Splitter Lib
 * Author: Suwicha Phuak-im
 * Email: suwichalala@gmail.com
 * Website: http://www.projecka.com
 */
class Segment {

    private $_input_string;
    private static $_dictionary_array = array();
    private $_thcharacter_obj;
    private $_unicode_obj;
    private $_segmented_result = array();
    
    
    
    function __construct() {
        require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'thcharacter.php');
        require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'unicode.php');
        
        if (empty(self::$_dictionary_array)) self::$_dictionary_array = self::loadDictionary();
        
        // Load Helper Class/
        $this->_unicode_obj = new Unicode();
        $this->_thcharacter_obj = new Thchracter();
    }
    
    
    public static function loadDictionary() {
    	$_dictionary_array = array();
    	
      $file_handle = fopen(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'dictionary' . DIRECTORY_SEPARATOR . 'dictionary.txt', "rb");
      while (!feof($file_handle)) {
          $line_of_text = fgets($file_handle);
          $_dictionary_array[crc32(trim($line_of_text))] = trim($line_of_text);
      }
      fclose($file_handle);
      
    	return $_dictionary_array;
    }
    
    public static function setDictionary( &$dictionary ) {
    	self::$_dictionary_array = $dictionary;
    }
    

    private function clear_duplicated($string) {
        //หดรูปตัวอักษรซ้ำๆ//
        $input_string_split = $this->_unicode_obj->uni_strsplit($string);
        $previous_char = '';
        $previous_string = '';
        $dup_list_array = array();
        $dup_list_array_replace = array();
        foreach ($input_string_split as $current_char) {

            if ($previous_char == $current_char) {
                $previous_char = $current_char;
                $previous_string .= $current_char;
            } else {
                if (mb_strlen($previous_string) > 3) {
                    $dup_list_array[] = $previous_string;
                    $dup_list_array_replace[] = $current_char;
                    $string = str_replace($previous_string, $previous_char, $string);
                }
                $previous_char = $current_char;
                $previous_string = $current_char;
            }
        }
        if (mb_strlen($previous_string) > 3) {
            $dup_list_array[] = $previous_string;
            $dup_list_array_replace = $current_char;
        }
        return str_replace($dup_list_array, $dup_list_array_replace, $string);
    }

    public function get_segment_array($clear_previous, $input_string) {
        if ($clear_previous)  $this->_segmented_result = array();
        $this->_input_string = $input_string;

        // ลบเครื่องหมายคำพูด, ตัวแบ่งประโยค //
        $this->_input_string = str_replace(array('\'', '‘', '’', '“', '”', '"', '-', '/', '(', ')', '{', '}', '...', '..', '…', '', ',', ':', '|', '\\'), '', $this->_input_string);
        // เปลี่ยน newline ให้กลายเป็น Space เพื่อที่ใช้สำหรับ Trim
        $this->_input_string = str_replace(array("\r", "\r\n", "\n"), ' ', $this->_input_string);


        // กำจัดซ้ำ //
        $this->_input_string = $this->clear_duplicated($this->_input_string);


        // แยกประโยคจากช่องว่าง (~เผื่อไว้สำหรับภาษาอังกฤษ) //
        $this->_input_string_exploded = explode(' ', $this->_input_string);



        // Reverse Array สำหรับการใช้ Dictionary แบบ Reverse //
        foreach ($this->_input_string_exploded as $input_string_exploded_row) {
            $current_string_reverse_array = array_reverse($this->_unicode_obj->uni_strsplit(trim($input_string_exploded_row)));



            $current_array_result = $this->_segment_by_dictionary_reverse($current_string_reverse_array);
            foreach ($current_array_result as $each_result) {
                if (trim($each_result) != '')
                    $this->_segmented_result[] = trim($each_result);
            }
        }

        // จัดการคำที่ตัดที่ยาวผิดปกติ (~อาจจะเป็นเพราะว่าพิมผิด) โดยการตัดตาม Dict แบบธรรมดา//
        $tmp_result = array();
        foreach ($this->_segmented_result as $result_row) {
            if (mb_strlen($result_row) > 10) {

                $current_string_array = $this->_unicode_obj->uni_strsplit(trim($result_row));
                $current_array_result = $this->_segment_by_dictionary($current_string_array);

                foreach ($current_array_result as $current_result_row) {
                    $tmp_result[] = trim($current_result_row);
                }
            } else {
                $tmp_result[] = $result_row;
            }
        }
        $this->_segmented_result = $tmp_result;
        return $this->_segmented_result;
    }

    private function _segment_by_dictionary($input_array) {

        $result_array = array();
        $tmp_string = '';

        $pointer = 0;
        $length_of_string = count($input_array)-1;

        while ($pointer <= $length_of_string) {

            $tmp_string .= $input_array[$pointer];

            if (isset(self::$_dictionary_array[crc32($tmp_string)])) { // ถ้าเจอใน Dict //
                $dup_array = array();
                $dup_array[] = array(
                    'title' => $tmp_string,
                    'to_mark' => $pointer + 1,
                );
                $count_more = 0;
                $more_tmp = $tmp_string;
                //echo $more_tmp.'<br/>';


                for ($i = $pointer + 1; $i <= $length_of_string; $i++) {
                    $more_tmp .= $input_array[$i];
                    //echo $more_tmp.'<br/>';
                    //echo $more_tmp.'<br/>';
                    if (isset(self::$_dictionary_array[crc32($more_tmp)])) {
                        $dup_array[] = array(
                            'title' => $more_tmp,
                            'to_mark' => $i + 1,
                        );
                        //print_r($dup_array);
                    }

                    $count_more++;
                }

                if (count($dup_array) > 0) {
                    $result_array[] = $dup_array[count($dup_array) - 1]['title'];

                    $pointer = $dup_array[count($dup_array) - 1]['to_mark'];

                    //echo $to_mark;
                } else {
                    
                }
                //echo '-------------------<br/>';
                $dup_array = array();
                $tmp_string = '';
                continue;
            }

            $pointer++;
        }

        if ($tmp_string != '') { //  ส่วนที่เหลือ ถ้าไม่เจอใน Dict
            $result_array[] = $tmp_string;
        }

        if (count($result_array) == 0) {
            return array(implode($input_array));
        }
        return $result_array;
    }

    private function _segment_by_dictionary_reverse($input_array) {

        $result_array = array();
        $tmp_string = '';

        $pointer = 0;
        $length_of_string = count($input_array)-1;

        while ($pointer <= $length_of_string) {

            $tmp_string = $input_array[$pointer] . $tmp_string;

            if (isset(self::$_dictionary_array[crc32($tmp_string)])) { // ถ้าเจอใน Dict //
                $dup_array = array();
                $dup_array[] = array(
                    'title' => $tmp_string,
                    'to_mark' => $pointer + 1,
                );
                $count_more = 0;
                $more_tmp = $tmp_string;
                //echo $more_tmp.'<br/>';


                for ($i = $pointer + 1; $i <= $length_of_string; $i++) {
                    $more_tmp = $input_array[$i] . $more_tmp;
                    //echo $more_tmp.'<br/>';
                    //echo $more_tmp.'<br/>';
                    if (isset(self::$_dictionary_array[crc32($more_tmp)])) {
                        $dup_array[] = array(
                            'title' => $more_tmp,
                            'to_mark' => $i + 1,
                        );
                        //print_r($dup_array);
                    }

                    $count_more++;
                }

                if (count($dup_array) > 0) {
                    $result_array[] = $dup_array[count($dup_array) - 1]['title'];

                    $pointer = $dup_array[count($dup_array) - 1]['to_mark'];

                    //echo $to_mark;
                } else {
                    
                }
                //echo '-------------------<br/>';
                $dup_array = array();
                $tmp_string = '';
                continue;
            }

            $pointer++;
        }

        if ($tmp_string != '') { //  ส่วนที่เหลือ ถ้าไม่เจอใน Dict
            $result_array[] = $tmp_string;
        }

        if (count($result_array) == 0) {
            return array(implode(array_reverse($input_array)));
        }
        return array_reverse($result_array);
    }

}
