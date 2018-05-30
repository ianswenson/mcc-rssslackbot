<?php

defined( 'DS' ) or define( 'DS', DIRECTORY_SEPARATOR );
defined( 'WEBPUB_PATH' ) or define( 'WEBPUB_PATH', dirname(__FILE__) . DS );
defined( 'INCL_PATH' ) or define( 'INCL_PATH', dirname(dirname(__FILE__)) . DS );

include_once( INCL_PATH . 'class.basic.php' );


/**
 *
 */
class RSSScrape extends Basic
{
  protected $feeds  = array();
  protected $folder = '';


  public function __construct()
  {
    // Nothing to init
  }



  /**
   *
   */
  public function init()
  {
    if ( !empty( $this->feeds ) && !empty( $this->folder ) ) {
      $this->fetch_feeds();
    }
  }



  /**
   *
   */
  public function set_feeds( $feeds )
  {
    if ( is_array($feeds) ) {
      $this->feeds = $feeds;
    }
  }



  /**
   *
   */
  public function set_folder( $name )
  {
    if ( is_string($name) ) {
      $this->folder = $name;
    }
  }



  /**
   *
   */
  protected function fetch_feeds()
  {
    foreach ( $this->feeds as $feed_name => $feed_url ) {

      $filename = WEBPUB_PATH . $this->folder . DS . "{$feed_name}.rss";
      $rss      = $this->curl_get($feed_url);

      $this->write_file( $rss, $filename );
    }
  }



  /**
   *
   */
  protected function curl_get( $url )
  {
    if ( !function_exists('curl_init') ){
      die( 'Sorry cURL is not installed!' );
    }

    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1; rv:5.0) Gecko/20100101 Firefox/5.0 Firefox/5.0' );
    curl_setopt( $ch, CURLOPT_HEADER, 0 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
    curl_setopt( $ch, CURLOPT_ENCODING, 'gzip,deflate' );
    curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
    $output = curl_exec($ch);
    curl_close($ch);

    return $output;
  }

}

?>
