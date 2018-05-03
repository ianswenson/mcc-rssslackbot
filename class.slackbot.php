<?php

defined( 'DS' ) or define( 'DS', DIRECTORY_SEPARATOR );
defined( 'THIS_PATH' ) or define( 'THIS_PATH', dirname(__FILE__) . DS );

include_once( THIS_PATH . 'class.basic.php' );


/**
 * DO NOT URLENCODE text/attachment
 *
 *
 * Attachment schema
 *
 * Should have:
 * fallback
 * title
 * text
 * fields array
 *   title
 *   value
 *   short (boolean)
 *
 * Can have:
 * pretext
 * color
 * title_link
 *
 */
class SlackBot extends Basic
{
  /**
   * @var array
   */
  protected $bots = array(
    'BOTNAME'  => array(
      'name'    => 'BOTNAME',
      'token'   => 'TOKEN',
      'channel' => 'DEFAULT_CHANNEL',
    ),
  );

  /**
   * @var array
   */
  protected $channels   = array(
    'CHANNEL_NAME' => 'CHANNEL_ID',
  );

  /**
   * @var string
   */
  protected $attachment = '';

  protected $bot;
  protected $channel;



  /**
   *
   */
  function __construct( $bot, $channel = null )
  {
    $this->set_bot( $bot );
    $this->set_channel( $channel );
  }


  /**
   * Sets Slack bot ID
   *
   * @param   string $bot
   */
  public function set_bot( $bot )
  {
    if ( is_array($bot) ) {
      if ( isset( $bot['name'] ) && isset( $bot['token'] ) ) {
        $this->bot = $bot;
      } else {
        $this->error( 'Malformed bot.' );
      }
    }

    else if ( is_string($bot) && isset( $this->bots[$bot] ) ) {
      $this->bot = $this->bots[$bot];
    } else {
      $this->error( 'No bot found.' );
    }
  }



  /**
   * Sets Slack channel
   *
   * @param   string $channel
   */
  public function set_channel( $channel )
  {
    if ( preg_match( '/^[A-Z0-9]{9}$/', $channel ) ) {
      $this->channel = $channel;
    }

    else if ( isset( $this->channels[$channel] ) ) {
      $this->channel = $this->channels[$channel];
    }

    else if ( isset( $this->bot['channel'] ) ) {
      $this->channel = $this->channels[$this->bot['channel']];
    }

    else {
      $this->error( 'Channel not found.' );
    }
  }



  /**
   * Puts text into a Slack API ready URL
   *
   * @param   string $text            Text to be put into notification
   * @param   boolean $is_attachment  Whether to use attachment formatting
   * @return  string
   */
  protected function build_url( $text, $is_attachment = false )
  {
    if ( !$this->validate() ) {
      return false;
    }

    $url = 'https://slack.com/api/chat.postMessage';

    // Token
    $url .= "?token={$this->bot['token']}";
    $url .= "&channel={$this->channel}";
    $url .= "&username={$this->bot['name']}";
    $url .= "&as_user=true";

    if ( !$is_attachment ) {
      $url .= "&text=" . urlencode($text);
    }

    else {
      if ( is_array($text) ) {
        $text = '[' . json_encode( $text ) . ']';
      }

      $text = $this->compress_attachment( $text );
      $url .= "&attachments={$text}";
    }

    return $url;
  }



  /**
   * Makes attachment URL-safe
   *
   * @param   string $attachment
   * @return  string
   */
  protected function compress_attachment( $attachment )
  {
    $attachment = str_replace( ': ', ':', $attachment );
    $attachment = str_replace( "\n", '', $attachment );
    $attachment = preg_replace( '/ (\{|\[)/', '$1', $attachment );

    return urlencode( $attachment );
  }



  /**
   * Validate bot and channel are set
   *
   * @return  boolean
   */
  protected function validate()
  {
    return ( is_array( $this->bot ) && !empty( $this->channel ) );
  }


}

?>