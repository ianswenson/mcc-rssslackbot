<?php

defined( 'DS' ) or define( 'DS', DIRECTORY_SEPARATOR );
defined( 'INCL_PATH' ) or define( 'INCL_PATH', dirname(dirname(__FILE__)) . DS );

include_once( INCL_PATH . 'class.meekrodb.php' );
include_once( INCL_PATH . 'class.slackbot.php' );


/**
 * @todo
 *
 * FLAW IN PLAN:
 *
 * Embargoed URLs can have a lower ID number
 */
class WebPubSlackBot extends SlackBot
{
  protected $feeds = array(
    'business'            => 'http://www.thenewstribune.com/news/business/?widgetName=rssfeed&widgetContentId=%d&getXmlFeed=true',
    'entertainment'       => 'http://www.thenewstribune.com/entertainment/?widgetName=rssfeed&widgetContentId=%d&getXmlFeed=true',
    'living'              => 'http://www.thenewstribune.com/living/?widgetName=rssfeed&widgetContentId=%d&getXmlFeed=true',
    'local'               => 'http://www.thenewstribune.com/news/local/?widgetName=rssfeed&widgetContentId=%d&getXmlFeed=true',
    'outdoors'            => 'http://www.thenewstribune.com/outdoors/?widgetName=rssfeed&widgetContentId=%d&getXmlFeed=true',
    'politics-government' => 'http://www.thenewstribune.com/news/politics-government/?widgetName=rssfeed&widgetContentId=%d&getXmlFeed=true',
    'sports'              => 'http://www.thenewstribune.com/sports/?widgetName=rssfeed&widgetContentId=%d&getXmlFeed=true',
    'weather'             => 'http://www.thenewstribune.com/news/weather/?widgetName=rssfeed&widgetContentId=%d&getXmlFeed=true',
    // 'opinion'             => 'http://www.thenewstribune.com/opinion/?widgetName=rssfeed&widgetContentId=%d&getXmlFeed=true',
  );

  protected $byline_whitelist = array(
    'thenewstribune',
    'theolympian',
    'staff reports',
    'staff report',
    'staff',
    'contributing writer',
    'seattle times',
    'kiro',
  );

  protected $url_blacklist = array(
    'Community',
  );

  protected $is_fulltext         = false;
  protected $fulltext_content_id = 'FULLTEXT_CONTENT_ID';
  protected $public_content_id   = 712015;
  protected $before_date         = '2017-10-12';
  protected $stories             = array();

  protected $db;
  protected $database = array(
    'host'     => 'HOST',
    'login'    => 'LOGIN',
    'password' => 'PASSWORD',
    'database' => 'DATABASE'
  );


  /**
   *
   */
  function __construct( $is_fulltext = false, $channel = null )
  {
    date_default_timezone_set( 'America/Los_Angeles' );

    if ( is_null($channel) ) {
      parent::__construct( 'rosie' );
    } else {
      parent::__construct( 'rosie', $channel );
    }

    $this->set_is_fulltext( $is_fulltext );

    $this->init();
  }



  /**
   *
   */
  public function init()
  {
    $this->connect();

    foreach ( $this->feeds as $name => $url ) {
      $feed = $this->get_rss_feed( $name );

      /**
       * @todo  Check last updated
       */

      $this->find_stories( $feed );
    }

    if ( !empty( $this->stories ) ) {
      $this->slack_stories();
      $this->log_stories();
    }
  }



  /**
   *
   */
  public function set_is_fulltext( $is_fulltext = false )
  {
    if ( is_bool( $is_fulltext ) ) {
      $this->is_fulltext = $is_fulltext;
    }
  }



  /**
   * Connects to database
   */
  protected function connect()
  {
    $this->db = new MeekroDB( $this->database['host'], $this->database['login'], $this->database['password'], $this->database['database'] );
  }


  /**
   *
   */
  protected function find_stories( $feed_obj )
  {
    if ( is_object($feed_obj) && isset( $feed_obj->channel ) && isset( $feed_obj->channel->item ) ) {
      foreach ( $feed_obj->channel->item as $item ) {

        $dc = $item->children( 'http://dublincore.org/documents/dcmi-terms/' );

        if ( $this->is_whitelisted( $dc->creator ) ) {

          $id = $this->parse_article_id( $item->link );

          if ( $this->is_new_id( $id ) ) {
            $this->add_new_story( $item, $dc->creator, $id );
          }
        }

      }
    }
  }



  /**
   *
   */
  protected function add_new_story( $item, $byline, $id )
  {
    if ( !isset( $this->stories[$id] ) ) {
      $this->stories[$id] = array(
        'id'       => $id,
        'headline' => trim( preg_replace( '/(\w):(\w)/', '$1: $2', $item->title ) ),
        'url'      => (string) $item->guid,
        'date'     => strtotime( $item->pubDate ),
        'byline'   => trim( preg_replace( '/\s+/', ' ', preg_replace( '/<[^>]+>/', ' ', $byline ) ) ),
        'section'  => $this->parse_section( (string) $item->guid ),
      );

      if ( $this->is_blacklisted_section( $id ) || $this->is_before_date( $id ) ) {
        unset( $this->stories[$id] );
      }
    }
  }



  /**
   *
   */
  protected function slack_stories()
  {
    foreach ( $this->stories as $story ) {

      $attachment = $this->build_slack_attachment( $story );
      $url        = $this->build_url( $attachment, true );
      $response   = $this->curl_url( $url );

    }
  }



  /**
   *
   */
  protected function build_slack_attachment( $story )
  {
    // Store CUE URL since it's used twice
    $cue = $this->cue_url( $story['id'] );

    $attachment = array(
      'fallback'   => $story['headline'],
      'title'      => $story['headline'],
      'title_link' => $story['url'],
      'color'      => '#21d1ff',
      'fields'     => array(
        array(
          'title' => 'ID',
          'value' => "<{$cue}|{$story['id']}>",
          'short' => true
        ),
        array(
          'title' => 'PUB TIME',
          'value' => date( 'g:i A (n/j/y)', $story['date'] ),
          'short' => true
        ),
        array(
          'title' => 'BYLINE',
          'value' => $this->clean_byline( $story['byline'] ),
          'short' => true
        ),
        array(
          'title' => 'SECTION',
          'value' => $story['section'],
          'short' => true
        ),
      ),
      'actions'   => array(
        array(
          'type'  => 'button',
          'text'  => 'Facebook',
          'url'   => $this->facebook_share_url( $story['url'] ),
        ),
        array(
          'type'  => 'button',
          'text'  => 'Twitter',
          'url'   => $this->twitter_share_url( $story['url'], $story['headline'] ),
        ),
        array(
          'type'  => 'button',
          'text'  => 'Reddit',
          'url'   => $this->reddit_share_url( $story['url'] ),
        ),
        array(
          'type'  => 'button',
          'text'  => 'CUE',
          'url'   => $cue,
        ),
      ),
    );

    // Add chartbeat link
    $chartbeat = $this->chartbeat_url( $story['url'] );
    if ( $chartbeat ) {
      $attachment['actions'][] = array(
        'type'  => 'button',
        'text'  => 'Chartbeat',
        'url'   => $chartbeat,
      );
    }

    return $attachment;
  }



  /**
   *
   */
  protected function log_stories()
  {
    foreach ( $this->stories as $story ) {

      $data = array(
        'id'         => $story['id'],
        'section_id' => $story['section'],
        'headline'   => $this->convert_smart_quotes( $story['headline'] ),
        'byline'     => $this->clean_byline( $story['byline'] ),
        'pub_date'   => date( 'Y-m-d H:i:s', $story['date'] ),
      );

      $this->db->insert( 'posts', $data );

    }
  }



  /**
   *
   */
  protected function get_rss_feed( $feed )
  {
    $content_id = ( $this->is_fulltext ) ? $this->fulltext_content_id : $this->public_content_id;

    $url = sprintf( $this->feeds[$feed], $content_id );

    return $this->get_feed( $url );
  }



  /**
   *
   */
  protected function is_whitelisted( $byline )
  {
    $is_whitelisted = false;

    foreach ( $this->byline_whitelist as $byline_check ) {
      if ( strpos( strtolower($byline), $byline_check ) !== false ) {
        $is_whitelisted = true;
      }
    }

    return $is_whitelisted;
  }



  /**
   *
   */
  protected function is_blacklisted_section( $id )
  {
    foreach ( $this->url_blacklist as $section ) {
      if ( strpos( $this->stories[$id]['section'], $section ) !== false ) {
        return true;
      }
    }

    return false;
  }



  /**
   *
   */
  protected function is_before_date( $id )
  {
    $time = strtotime( $this->before_date );

    return ( $this->stories[$id]['date'] < $time );
  }



  /**
   *
   */
  protected function is_new_id( $id )
  {
    $query = "SELECT EXISTS( SELECT 1 FROM posts WHERE id = %d LIMIT 1 )";

    $result = $this->db->queryFirstField( $query, $id );

    return !($result == 1);
  }



  /**
   *
   */
  protected function parse_article_id( $url )
  {
    $match = false;

    preg_match( '/article(\d+)\.html/', $url, $matches );

    if ( isset( $matches[1] ) ) {
      $match = $matches[1];
    }

    return $match;
  }



  /**
   *
   */
  protected function clean_byline( $byline )
  {
    $byline = preg_replace( '/\bby /i', '', $byline );
    $byline = preg_replace( '/ Contributing writers?/', '', $byline );
    $byline = preg_replace( '/\S+@\S+/', '', $byline );

    return trim( $byline );
  }



  /**
   *
   */
  protected function parse_section( $url )
  {
    $middle = preg_replace( '/^.+\.com\/(.+)\/article.+$/', '$1', $url );
    $parts  = preg_split( '/\//', $middle, -1, PREG_SPLIT_NO_EMPTY );

    return implode( ' » ', array_map( array( 'WebPubSlackBot', 'upperc' ), $parts ) );
  }



  /**
   *
   */
  protected function parse_reporters( $story )
  {
    $bylines = $this->clean_reporter_byline( $this->clean_byline( $story['byline'] ) );

    foreach ( $bylines as $byline ) {
      $this->parse_byline( $byline, $story['id'] );
    }
  }



  /**
   *
   */
  protected function clean_reporter_byline( $byline )
  {
    $byline = strtolower( trim($byline) );
    // $byline = str_replace( '  ', ' ', $byline );
    $byline = preg_replace( '/\b(b ?y|from) /', '', $byline );
    $byline = preg_replace( '/staff( reports?)? and .+/', 'staff', $byline );
    $byline = preg_replace( '/.+and staff( reports?)?/', 'staff', $byline );
    $byline = preg_replace( '/^(.*\S+)\s*staff writer\s*$/', '$1', $byline );
    $byline = preg_replace( '/^(.*\S+)\s*contributing writer\s*$/', '$1', $byline );
    $byline = preg_replace( '/^(.*\S+)\s*staff report\s*$/', '$1', $byline );

    if ( strpos( $byline, ' and ' ) != false ) {
      $bylines = preg_split( '/ and /', $byline );
    } else if ( strpos( $byline, '  ' ) != false ) {
      $bylines = preg_split( '/  /', $byline );
    } else {
      $bylines = array( $byline );
    }

    foreach ( $bylines as $i => $byline ) {
      $bylines[$i] = trim($byline);
    }

    return $bylines;
  }



  /**
   *
   */
  protected function cue_url( $id )
  {
    return "https://cue.misitemgr.com/#/main?uri=https:%2F%2Fcue-webservice.misitemgr.com%2Fwebservice%2Fescenic%2Fcontent%2F{$id}&mimetype=x-ece%2Fstory";
  }



  /**
   *
   */
  protected function chartbeat_url( $story_url )
  {
    preg_match_all( '/https?:\/\/www\.(([^\.]+).+)/', $story_url, $matches, PREG_PATTERN_ORDER );

    if ( !isset( $matches[1] ) || !isset( $matches[2] ) ) {
      return false;
    }

    return "https://chartbeat.com/publishing/dashboard/{$matches[2][0]}.com/#path=" . urlencode( $matches[1][0] );
  }



  /**
   *
   */
  protected function facebook_share_url( $story_url )
  {
    return "https://www.facebook.com/sharer/sharer.php?u={$story_url}";
  }



  /**
   *
   */
  protected function twitter_share_url( $story_url, $title )
  {
    return 'https://twitter.com/share?text=' . urlencode( $title ) . "&url={$story_url}";
  }



  /**
   *
   */
  protected function reddit_share_url( $story_url )
  {
    return "https://www.reddit.com/submit?url={$story_url}";
  }



  /**
   *
   */
  protected function linkedin_share_url( $story_url, $title )
  {
    return "https://www.linkedin.com/shareArticle?mini=true&url={$story_url}&title=" . urlencode( $title );
  }



  /**
   * @see  http://stackoverflow.com/a/21491305
   */
  protected function convert_smart_quotes( $str )
  {
    $chr_map = array(
     // Windows codepage 1252
     "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
     "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
     "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
     "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
     "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
     "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
     "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
     "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark
     "\xC2\x96" => "-", // en dash
     "\xC2\x97" => "-", // em dash

     // Regular Unicode     // U+0022 quotation mark (")
                            // U+0027 apostrophe     (')
     "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
     "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
     "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
     "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
     "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
     "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
     "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
     "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
     "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
     "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
     "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
     "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
     "\xE2\x80\x90" => "-", // hyphen
     "\xE2\x80\x91" => "-", // non-breaking hyphen
     "\xE2\x80\x92" => "-", // figure dash
     "\xE2\x80\x93" => "-", // en dash
     "\xE2\x80\x94" => "-", // em dash
     "\xE2\x80\x95" => "-", // horizontal bar
     "\xEF\xB9\x98" => "-", // small en dash
     "\xEF\xB9\xA3" => "-", // small hyphen-minus
     "\xEF\xBC\x8D" => "-", // fullwidth hyphen-minus

    );

    $chr = array_keys( $chr_map );
    $rpl = array_values( $chr_map );

    return str_replace( $chr, $rpl, html_entity_decode( $str, ENT_QUOTES, "UTF-8" ) );
  }



  /**
   *
   */
  static public function upperc( $word )
  {
    $search  = array( 'tnt', 'ph-', '-mcg', 'nfl', 'mlb', 'nascar', 'wsu', 'mls' );
    $replace = array( 'TNT', 'PH-', '-McG', 'NFL', 'MLB', 'NASCAR', 'WSU', 'MLS' );

    $word = str_replace( $search, $replace, $word );
    $word = str_replace( '-', ' ', $word );
    $word = ucwords( $word );

    return $word;
  }




}


$slackbot = new WebPubSlackBot();

?>