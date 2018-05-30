<?php

defined( 'DS' ) or define( 'DS', DIRECTORY_SEPARATOR );
defined( 'WEBPUB_PATH' ) or define( 'WEBPUB_PATH', dirname(__FILE__) . DS );

include_once( WEBPUB_PATH . 'class.rssscrape.php' );
include_once( WEBPUB_PATH . 'class.webpubbot.php' );



// If we're logging time spent
$log_time = false;

if ( $log_time ) {
  $time1 = microtime(true);
}



// Scrape RSS feeds
$scraper = new RSSScrape();
$scraper->set_folder( 'feeds' );
$scraper->set_feeds( array(
  'FEED_NAME' => 'FEED_URL',
));
$scraper->init();



// Slackbot
$slackbot = new WebPubSlackBot( 'feeds' );
$slackbot->init();



// If we're logging time spent
if ( $log_time ) :

  $time2 = microtime(true);

?>
<p>Completed in <?php echo round($time2 - $time1, 2); ?> seconds</p>
<?php endif; ?>
