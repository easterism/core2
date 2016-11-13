<?php 
/**
 * csv util class. csv = comma separated value.
 *
 * features:
 *   - supports any separator char sequence, default is semicolon ";"
 *   - supports separator characters in the values. eg you use a ; as separator, your line may look like
 *     blah;hello world;"foo";"foo;bar";"this is a ""string""";got it?;foo
 *     as you can see, the values can be in "quotes". if your text uses quotes itself as in the "string"
 *     example, they are escaped in ms-style with 2 quotes. and by using quotes we can even have your
 *     separator inside the text (example "foo;bar").
 *   - line breaks. a csv line may spread over multiple lines using crlf in a field value.
 *     see the checkMultiline param and the _checkMultiline() method.
 *
 * missing:
 *   - option to change quote char (") to something else
 *
 * thanks to: steffen at hung dot ch
 *
 * dependencies: none.
 *
 * @author     andrej arn <andrej at blueshoes dot org>
 * @copyright  blueshoes.org
 * @version    4.2.$id$
 * @package    util
 * @access     pseudostatic
 *
 *
 *
 *
 *
 *
 * DEPRECATED
 *
 *
 *
 */
class Bs_CsvUtil {
  
  
  /**
  * Constructor.
  */
  function Bs_CsvUtil() {
  }
  
  
  /**
  * reads in a cvs-file and returns it as a 2-dim vector.
  * @param  string $fullPath (fullpath to the cvs file)
  * @param  bool   $checkMultiline (default is FALSE, see _checkMultiline())
  * @see    csvArrayToArray()
  */
  function csvFileToArray($fullPath, $separator=';', $trim='none', $removeHeader=FALSE, $removeEmptyLines=FALSE, $checkMultiline=FALSE) {
    $fileContent = file($fullPath);
    if (!$fileContent) return FALSE;
    
    //hrm, having similar prob as in csvStringToArray() here except this time i need it for \n not \r.
    //so let's remove that aswell ... --andrej
    while (list($k) = each($fileContent)) {
      if ((substr($fileContent[$k], -1) == "\r") || (substr($fileContent[$k], -1) == "\n")) {
        $fileContent[$k] = substr($fileContent[$k], 0, -1);
      }
    }
    reset($fileContent);
    
    if ($checkMultiline) $fileContent = $this->_checkMultiline($fileContent);
    return $this->csvArrayToArray($fileContent, $separator, $trim, $removeHeader, $removeEmptyLines);
  }
  
  
  /**
  * takes a csv-string and returns it as a 2-dim vector.
  * @param  string $string
  * @param  bool   $checkMultiline (default is FALSE, see _checkMultiline())
  * @see csvArrayToArray()
  */
  function csvStringToArray($string, $separator=';', $trim='none', $removeHeader=FALSE, $removeEmptyLines=FALSE, $checkMultiline=FALSE) {
    if (empty($string)) return array();
    $array = explode("\n", $string);
    
    //short hack: on windows we should explode by "\r\n". if not, the elements in $array still end with \r.
    //so let's remove that ... --andrej
    while (list($k) = each($array)) {
      if (substr($array[$k], -1) == "\r") {
        $array[$k] = substr($array[$k], 0, -1);
      }
    }
    reset($array);
    
    if ((!is_array($array)) || empty($array)) return array();
    if ($checkMultiline) $array = $this->_checkMultiline($array);
    return $this->csvArrayToArray($array, $separator, $trim, $removeHeader, $removeEmptyLines);
  }
  
  
  /**
  * 
  * reads in a cvs array and returns it as a 2-dim vector.
  * 
  * cvs = comma separated value. you can easily export that from 
  * an excel file for example. it looks like:
  * 
  * headerCellOne;headerCellTwo;headerCellThree
  * dataCellOne;dataCellTwo;dataCellThree
  * apple;peach;banana;grapefruit
  * linux;windows;mac
  * 1;2;3
  * 
  * note  I: all returned array elements are strings even if the values were numeric.
  * note II: it may be that one array has another array-length than another. in the example 
  *          above, the fruits have 4 elements while the others just have 3. this is not 
  *          catched. ideally every sub-array would have 4 elements. this would have to be 
  *          added when needed, maybe with another param in the function call.
  * 
  * @access public pseudostatic
  * @param  string $fullPath (fullpath to the cvs file)
  * @param  array $array (hash or vector where the values are the csv lines)
  * @param  string $separator (cell separator, default is ';')
  * @param  string $trim (if we should trim the cells, default is 'none', can also be 'left', 'right' or 'both'. 'none' kinda makes it faster, omits many function calls, remember that.)
  * @param  bool   $removeHeader (default is FALSE. would remove the first line which usually is the title line.)
  * @param  bool   $removeEmptyLines (default is FALSE. would remove empty lines, that is, lines where the cells are empty. white spaces count as empty aswell.)
  * @return array (2-dim vector. it may be an empty array if there is no data.)
  * @throws bool FALSE on any error.
  * @see csvStringToArray()
  */
  function csvArrayToArray($array, $separator=';', $trim='none', $removeHeader=FALSE, $removeEmptyLines=FALSE) {
    switch ($trim) {
      case 'none':
        $trimFunction = FALSE;
        break;
      case 'left':
        $trimFunction = 'ltrim';
        break;
      case 'right':
        $trimFunction = 'rtrim';
        break;
      default: //'both':
        $trimFunction = 'trim';
        break;
    }
    
    $sepLength = strlen($separator);
    
    if ($removeHeader) {
      array_shift($array);
    }
    
    $ret = array();
    reset($array);
    while (list(,$line) = each($array)) {
      $offset    = 0;
      $lastPos   = 0;
      $lineArray = array();
      do {
        //find the next separator
        $pos = strpos($line, $separator, $offset);
        if ($pos === FALSE) {
          //no more separators.
          $lineArray[] = substr($line, $lastPos);
          break;
        }
        //now let's see if it is inside a field value (text) or it is a real separator. 
        //it can only be a separator if the number of quotes (") since the last separator 
        //is straight (not odd).
        $currentSnippet = substr($line, $lastPos, $pos-$lastPos);
        $numQuotes = substr_count($currentSnippet, '"');
        if ($numQuotes % 2 == 0) {
          //that's good, we got the next field. the separator was a real one.
          $lineArray[] = substr($line, $lastPos, $pos-$lastPos);
          $lastPos = $pos + $sepLength;
        } else {
          //have to go on, separator was inside a field value.
        }
        $offset = $pos + $sepLength;
      } while (TRUE);
      
      //trim if needed
      if ($trimFunction !== FALSE) {
        while (list($k) = each($lineArray)) {
          $lineArray[$k] = $trimFunction($lineArray[$k]);
        }
        reset($lineArray);
      }
      
      //remove quotes around cell values, and unescape other quotes.
      while (list($k) = each($lineArray)) {
        if ((substr($lineArray[$k], 0, 1) == '"') && (substr($lineArray[$k], 1, 1) != '"') && (substr($lineArray[$k], -1) == '"')) {
          //string has to look like "hello world" and may not look like ""hello. 
          //if two quotes are together, it's an escaped one. csv uses ms-escape style.
          $lineArray[$k] = substr($lineArray[$k], 1, -1);
        }
        //now un-escape the other quotes
        $lineArray[$k] = str_replace('""', '"', $lineArray[$k]);
      }
      reset($lineArray);
      
      //removeEmptyLines
      $addIt = TRUE;
      if ($removeEmptyLines) {
        do {
          while (list($k) = each($lineArray)) {
            if (!empty($lineArray[$k])) break 2;
          }
          $addIt = FALSE;
        } while (FALSE);
        reset($lineArray);
      }
      
      if ($addIt) {
        $ret[] = $lineArray;
      }
    }
    
    return $ret;
  }
  
  
  /**
  * takes an array and creates a csv string from it.
  * 
  * the given param $array may be a simple 1-dim array like this:
  * $arr = array('madonna', 'alanis morisette', 'falco');
  * that will result in the string: "madonna;alanis morisette;falco"
  * 
  * if the param is a 2-dim array, it goes like this:
  * $arr = array(
  *          array('madonna', 'pop', 'usa'), 
  *          array('alanis morisette', 'rock', 'canada'), 
  *          array('falco', 'pop', 'austria'), 
  *        );
  * result: madonna;pop;usa
  *         alanis morisette;rock;canada
  *         falco;pop;austria
  * 
  * todo: add param "fill to fit max length"?
  * 
  * @access public
  * @param  array  $array (see above)
  * @param  string $separator (default is ';')
  * @param  string $trim  (if we should trim the cells, default is 'none', can also be 'left', 'right' or 'both'. 'none' kinda makes it faster, omits many function calls, remember that.)
  * @param  bool   $removeEmptyLines (default is TRUE. removes "lines" that have no value, would come out empty.)
  * @return string (empty string if there is nothing at all)
  */
  function arrayToCsvString($array, $separator=';', $trim='none', $removeEmptyLines=TRUE) {
    if (!is_array($array) || empty($array)) return '';
    
    switch ($trim) {
      case 'none':
        $trimFunction = FALSE;
        break;
      case 'left':
        $trimFunction = 'ltrim';
        break;
      case 'right':
        $trimFunction = 'rtrim';
        break;
      default: //'both':
        $trimFunction = 'trim';
        break;
    }
    
    $ret = array();
    reset($array);
    if (is_array(current($array))) {
      while (list(,$lineArr) = each($array)) {
        if (!is_array($lineArr)) {
          //could issue a warning ...
          $ret[] = array();
        } else {
          $subArr = array();
          while (list(,$val) = each($lineArr)) {
            $val      = $this->_valToCsvHelper($val, $separator, $trimFunction);
            $subArr[] = $val;
          }
        }
        $ret[] = join($separator, $subArr);
      }
      return join("\n", $ret);
    } else {
      while (list(,$val) = each($array)) {
        $val   = $this->_valToCsvHelper($val, $separator, $trimFunction);
        $ret[] = $val;
      }
      return join($separator, $ret);
    }
  }
  
  
  /**
  * works on a string to include in a csv string/file.
  * @access private
  * @param  string      $val
  * @param  string      $separator
  * @param  string|bool $trimFunction (bool FALSE or 'rtrim' or so.)
  * @return string
  * @see    arrayToCsvString() and others.
  */
  function _valToCsvHelper($val, $separator, $trimFunction) {
    if ($trimFunction) $val = $trimFunction($val);
    //if there is a separator (;) or a quote (") or a linebreak in the string, we need to quote it.
    $needQuote = FALSE;
    do {
      if (strpos($val, '"') !== FALSE) {
        $val = str_replace('"', '""', $val);
        $needQuote = TRUE;
        break;
      }
      if (strpos($val, $separator) !== FALSE) {
        $needQuote = TRUE;
        break;
      }
      if ((strpos($val, "\n") !== FALSE) || (strpos($val, "\r") !== FALSE)) { // \r is for mac
        $needQuote = TRUE;
        break;
      }
    } while (FALSE);
    if ($needQuote) {
      $val = '"' . $val . '"';
    }
    return $val;
  }
  
  
  
  /**
  * takes an array and combines elements (lines) if needed.
  * @access private
  * @param  array $in
  * @return array
  */
  function _checkMultiline($in) {
    $ret = array();
    
    $stack = FALSE;
    reset($in);
    while (list(,$line) = each($in)) {
      $c = substr_count($line, '"');
      if ($c % 2 == 0) {
        if ($stack === FALSE) {
          $ret[] = $line;
        } else {
          $stack .= "\n" . $line;
        }
      } else {
        //odd number
        if ($stack === FALSE) {
          $stack = $line;
        } else {
          $ret[] = $stack . "\n" . $line;
          $stack = FALSE;
        }
      }
    }
    return $ret;
  }
  
  
} // end Class
