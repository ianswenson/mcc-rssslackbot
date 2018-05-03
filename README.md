# MCC RSS to Slack

This script will check a list of McClatchy RSS feeds for new stories and send Slack notifications.

## Executing

Execute webpub/class.webpubbot.php

## Settings

In class.slackbot.php:

Enter bot information

```
protected $bots = array(
  'BOTNAME'  => array(
    'name'    => 'BOTNAME',
    'token'   => 'TOKEN',
    'channel' => 'DEFAULT_CHANNEL',
  ),
);
```

Enter channel information

```
protected $channels = array(
  'CHANNEL_NAME' => 'CHANNEL_ID',
);
```

In class.webpubbot.php add

1. List of RSS feeds in `$feeds` with name => url pair.
2. List of partial byline strings to whitelist in `$byline_whitelist`. Email domain names are recommended.
3. List of partial URL strings to blacklist in `$url_blacklist`.
4. Database credentials to `$database`.