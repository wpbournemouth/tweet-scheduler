<?php
require_once 'vendor/autoload.php';
require_once 'class-buffer-tweets.php';

$dotenv = new Dotenv\Dotenv( __DIR__ );
$dotenv->load();

$speaker_handle = '@tweetingsherry';
$talk_title     = 'marketing WordPress products';

$talks = array(
	array(
		'handle' => 'Ben Levy',
		'title' => 'marketing WordPress products',
		'lightning' => false,
	),
	array(
		'handle' => '@polevaultweb',
		'title' => 'the awesome @wpcli',
		'lightning' => true,
	),
);

$tweets = include 'tweets.php';

$scheduler = new WPBournemouth\TweetBuffer\scheduleMeetupTweets( $talks, $tweets );
$scheduler->run();
