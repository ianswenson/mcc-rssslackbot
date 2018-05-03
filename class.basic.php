<?php

/**
 * INCLUDED METHODS:
 *
 * debug( $var )
 * get_file( $url )
 * write_file( $content, $file )
 * get_include_contents( $file )
 * ap_style_month( $month )
 * error( $message )
 * death_to_high_ascii( $string )
 *
 */
class Basic {

  static public $debug = true;
  static public $local = true;



  function __construct()
  {
    static::$local = static::is_local();
  }


  /**
   * Outputs variable to screen
   *
   * @param mixed $var
   * @param boolean $showHtml
   * @param boolean $showFrom
   */
  static public function debug( $var = false, $showHtml = false, $showFrom = true )
  {
    if ($showFrom) {
      $calledFrom = debug_backtrace();

      if ( defined('ROOT') )
        echo '<strong>' . substr( str_replace( ROOT, '', $calledFrom[0]['file'] ), 1 ) . '</strong>';
      else
        echo '<strong>' . $calledFrom[0]['file'] . '</strong>';

      echo ' (line <strong>' . $calledFrom[0]['line'] . '</strong>)';
    }
    echo "\n<pre class=\"debug\">\n";

    $var = print_r( $var, true );

    if ( $showHtml )
      $var = str_replace( '<', '&lt;', str_replace( '>', '&gt;', $var ) );

    echo $var . "\n</pre>\n";
  }



  /**
   * Retrieves file contents
   *
   * @param string $url
   * @return string or boolean false on failure
   */
  static public function get_file( $url )
  {
    $ctx = stream_context_create( array(
      'http' => array(
        'timeout' => 120,
      )
    ));

    if ( !$file = @file_get_contents( $url, 0, $ctx ) )
      return false;
    else
      return $file;
  }



  /**
   * Gets RSS/XML feed and returns as an array
   *
   * @param string $url
   * @return object
   */
  static public function get_feed( $url )
  {
    libxml_use_internal_errors( true );

    $xml = simplexml_load_file( $url, 'SimpleXMLElement', LIBXML_NOCDATA );

    // Errors
    foreach (libxml_get_errors() as $error) {
      static::debug($error);
    }
    libxml_clear_errors();

    if ( is_object($xml) && !empty($xml) )
      return $xml;
    else {
      return false;
    }
  }



  /**
   *
   */
  static public function curl_url( $url )
  {
    $ch = curl_init();

    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6" );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 120 );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, false );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

    $result = curl_exec( $ch );

    curl_close( $ch );

    return $result;
  }



  /**
   * Writes file locally
   *
   * @param string $content
   * @param string $file
   */
  static public function write_file( $content, $file )
  {
    if ( empty($content) )
      static::error( 'No content to write to file.' . "\n" );

    if( !file_put_contents( $file, $content ) )
      static::error( 'Could not write to ' . $file . ".\n" );
  }



  /**
   * Gets contents of an include file
   * Effectively allows: $var = include '/path/to/foo.file';
   *
   * @param string $file
   * @param array $includes
   * @return string or boolean false
   */
  static public function get_include_contents( $file, $includes = array() )
  {
    // Set include variables
    extract( $includes, EXTR_SKIP );

    if ( is_file($file) ) {
      ob_start();
      include $file;
      return ob_get_clean();
    }
    return false;
  }



  /**
   * Conforms date( 'F' ); months to AP Style
   *
   * @param string $month
   * @return string or boolean false
   */
  static public function ap_style_month( $month )
  {
    if ( empty($month) || !is_string($month) )
      return false;

    switch ( $month ) {
      case 'March' :
      case 'April' :
      case 'May' :
      case 'June' :
      case 'July' :
        break;
      default :
        $month = substr( $month, 0, 3 ) . '.';
        break;
    }

    return $month;
  }



  /**
   * Returns formatted date string conforming months to AP Style
   *
   * @param string $format    PHP Date format
   * @param string $time      Timestamp or date string
   * @return string
   */
  static public function ap_style_date( $format = 'F j, Y', $time = null )
  {
    $date  = '';
    $regex = '/([FmMnt])/';

    if ( $time ) {
      if ( !is_numeric($time) ) {
        $time = strtotime( $time );
      }
    } else {
      $time = time();
    }

    $parts = preg_split( $regex, $format, -1, PREG_SPLIT_DELIM_CAPTURE );

    foreach ( $parts as $part ) {
      $date .= ( preg_match( $regex, $part ) ) ? static::ap_style_month( date( 'F', $time ) ) : date( $part, $time );
    }

    return $date;
  }



  /**
   * Gets a timestamp for cache breaking
   *
   * @param string $grain
   * @return string
   */
  static public function make_timestamp( $grain = 'd' )
  {
    date_default_timezone_set('America/Los_Angeles');

    $grain = strtolower( $grain );
    switch ( $grain ) {
      case 'y' :
      case 'year' :
        $str = 'y';
        break;
      case 'm' :
      case 'month' :
        $str = 'ym';
        break;
      case 'h' :
      case 'hour' :
        $str = 'ymdH';
        break;
      case 'min' :
      case 'minute' :
        $str = 'ymdHi';
        break;
      case 's' :
      case 'sec' :
      case 'second' :
        $str = 'ymdHis';
        break;
      case 'd' :
      case 'day' :
      default :
        $str = 'ymd';
        break;
    }

    return date( $str );
  }



  /**
   * Determines if locally hosted
   *
   * @return boolean
   */
  static public function is_local()
  {
    $whitelist = array( 'localhost', '127.0.0.1' );

    if ( in_array( $_SERVER['HTTP_HOST'], $whitelist ) )
      return true;
    else
      return false;
  }



  /**
   * Prints error message if set to do so, then exits
   *
   * @param string $msg
   */
  static public function error( $msg, $showFrom = true )
  {
    $open = $close = $file = '';
    if ( $showFrom ) {
      $calledFrom = debug_backtrace();
      $file = ( defined('ROOT') )
        ? substr( str_replace( ROOT, '', $calledFrom[0]['file'] ), 1 )
        : $calledFrom[0]['file'];
      $file .= ":\n\n";
    }

    if ( static::$local ) {
      $open   = '<h3>';
      $close  = '</h3>';
    }

    if ( static::$debug )
      echo "$open$file$msg$close";

    exit();
  }



  /**
   * Converts/strips high ASCII from strings
   *
   * @param string $str
   * @return string
   */
  static public function death_to_high_ascii( $str )
  {
    $count = 1;
    $out = '';
    $temp  = array();

    for ( $i = 0, $s = strlen($str); $i < $s; $i++ ) {
      $ordinal = ord( $str[$i] );

      if ( $ordinal < 128 )
        $out .= $str[$i];

      else {
        if ( count($temp) == 0 )
          $count = ( $ordinal < 224 ) ? 2 : 3;

        $temp[] = $ordinal;

        if ( count($temp) == $count ) {
          $number = ( $count == 3 )
            ? ( ($temp['0'] % 16) * 4096 ) + ( ($temp['1'] % 64) * 64 ) + ( $temp['2'] % 64 )
            : ( ($temp['0'] % 32) * 64 ) + ( $temp['1'] % 64 );

          $out .= '&#' . $number . ';';
          $count = 1;
          $temp = array();
        }
      }

    }

    // Strip any high ASCII remaining
    $out = preg_replace( '/[\x80-\xFF]/', '', $out );

    return $out;
  }



  /**
   * Creates new array based on one value from associative array
   *
   * @param array $array
   * @param string $master_val
   * @param string $master_key
   */
  static public function array_pluck( $array, $master_val, $master_key = null )
  {
    $output_array = array();

    foreach ( $array as $key => $sub_array ) {
      if ( $master_key ) $key = $sub_array[$master_key];
      $output_array[$key]     = $sub_array[$master_val];
    }

    return $output_array;
  }



  /**
   * Merges arrays on matching keys
   * Forms multidimensional array on return
   * Accepts multiple arrays as params with specific keys
   *
   * @param array
   *        'array' => $array    Array with data for matching
   *        'key'   => $key      Key to match on
   *        'name'  => $name     Name of key to place array in match
   *        'multi' => true      [OPTIONAL] Include if multi-dimensional [DO NOT USE AS FIRST PARAM]
   * @return array
   */
  static public function match_arrays()
  {
    $info  = func_get_args();
    $num   = func_num_args();
    $match = array();

    // Use the first array as the pivot
    foreach ( $info[0]['array'] as $arr1 ) {

      // Go through the second through nth arrays
      for ( $i = 1; $i < $num; $i++ ) {
        foreach( $info[$i]['array'] as $arr2 ) {

          // Space saver; we'll use this as the main key
          $key = $info[0]['key'];

          // If we have a match
          if ( $arr1[$key] == $arr2[$info[$i]['key']] ) {

            // Put the pivot array in the match array
            if ( !isset( $match[$info[0]['name']] ) ) {
              $match[$arr1[$key]][$info[0]['name']] = $arr1;
            }

            // If it's a multi array
            if ( isset( $info[$i]['multi'] ) ) {

              // Create the empty multi array if we haven't already
              if ( !isset( $match[$arr1[$key]][$info[$i]['name']] ) ) {
                $match[$arr1[$key]][$info[$i]['name']] = array();
              }

              // Add this array to the multi
              $match[$arr1[$key]][$info[$i]['name']][] = $arr2;
            } else {

              // Add the matching nth array in the match array
              $match[$arr1[$info[0]['key']]][$info[$i]['name']] = $arr2;
            }

          }
        }
      }
    }

    return $match;
  }



  /**
   * [sortArrayByArray description]
   * @see  http://stackoverflow.com/questions/348410/sort-an-array-by-keys-based-on-another-array
   *
   * @param  Array  $array      [description]
   * @param  Array  $orderArray [description]
   * @return [type]             [description]
   */
  static public function sort_array_by_array( Array $array, Array $orderArray )
  {
    $ordered = array();

    foreach( $orderArray as $key ) {
      if( array_key_exists( $key, $array ) ) {
        $ordered[$key] = $array[$key];
        unset( $array[$key] );
      }
    }

    return $ordered + $array;
  }



  /**
   * Checks whether deep associated array keys are set
   *
   * @param array $arr        Array to check
   * @param string/int ...    One or more keys to check
   * @return boolean
   */
  static public function isset_chain( $arr )
  {
    $keys = func_get_args();
    $size = count( $keys );

    if ( $size - 1 < 1 ) {
      return true;
    }

    for( $i = 1; $i < $size; $i++ ) {
      if ( isset( $arr[$keys[$i]] ) ) {
        $arr = $arr[$keys[$i]];
      } else {
        return false;
      }
    }

    return true;
  }



  /**
   * Returns human readable time difference between two timestamps
   *
   * @author WordPress, slightly modified
   * @param int $from   UNIX timestamp
   * @param int $to     UNIX timestamp, leave blank for now
   * @return string
   */
  static public function human_time_diff( $from, $to = '' )
  {
    if ( empty( $to ) )
      $to = time();

    $diff = (int) abs( $to - $from );

    if ( $diff <= 3600 ) {

      $mins = round( $diff / 60 );

      if ( $mins <= 1 )
        $mins = 1;

      $since = sprintf( static::n( '%s min', '%s mins', $mins ), $mins );

    } elseif ( ($diff <= 86400) && ($diff > 3600) ) {

      $hours = round($diff / 3600);

      if ($hours <= 1)
        $hours = 1;

      $since = sprintf( static::n( '%s hour', '%s hours', $hours ), $hours );

    } elseif ( $diff >= 86400 ) {

      $days = round($diff / 86400);

      if ($days <= 1)
        $days = 1;

      $since = sprintf( static::n( '%s day', '%s days', $days ), $days );

    }

    return $since . ' ago';
  }



  /**
   * Returns singular or plural unit
   *
   * @param string $singular  Unit in singular form
   * @param string $plural    Unit in plural form
   * @param int/float $num    Number to check
   * @return string
   */
  static public function n( $singular, $plural, $num )
  {
    if ( $num === 1 )
      return $singular;
    else
      return $plural;
  }

}






  /**
   * Outputs variable to screen
   *
   * @param mixed $var
   * @param boolean $showHtml
   * @param boolean $showFrom
   */
  function debug( $var = false, $showHtml = false, $showFrom = true )
  {
    if ($showFrom) {
      $calledFrom = debug_backtrace();

      if ( defined('ROOT') )
        echo '<strong>' . substr( str_replace( ROOT, '', $calledFrom[0]['file'] ), 1 ) . '</strong>';
      else
        echo '<strong>' . $calledFrom[0]['file'] . '</strong>';

      echo ' (line <strong>' . $calledFrom[0]['line'] . '</strong>)';
    }
    echo "\n<pre class=\"debug\">\n";

    $var = print_r( $var, true );

    if ( $showHtml )
      $var = str_replace( '<', '&lt;', str_replace( '>', '&gt;', $var ) );

    echo $var . "\n</pre>\n";
  }

?>