<?php
require_once 'vendor/autoload.php';
require_once 'class-buffer-tweets.php';

$dotenv = new Dotenv\Dotenv( __DIR__ );
$dotenv->load();

$speaker_handle = '@mheap';
$talk_title     = 'WordPress as a 12 Factor App';

$tweets = include 'tweets.php';

$scheduler = new WPBournemouth\TweetBuffer\scheduleMeetupTweets( $speaker_handle, $talk_title, $tweets );
$scheduler->run();
