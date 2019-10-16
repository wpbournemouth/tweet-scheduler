<?php
require_once 'vendor/autoload.php';
require_once 'class-buffer-tweets.php';

$dotenv = new Dotenv\Dotenv( __DIR__ );
$dotenv->load();

$talks = array(
//	array(
//		'handle' => '@grahamarmfield',
//		'title'  => 'designing for accessibility',
//		'main'   => true,
//	),
//	array(
//		'handle' => '@tnash',
//		'title'  => '#WordPress site security',
//		'lightning'   => true,
//	),
);

$tweets = include 'tweets.php';

$scheduler = new WPBournemouth\TweetBuffer\scheduleMeetupTweets( $talks, $tweets );
$scheduler->run();
