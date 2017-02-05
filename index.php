<?php
require_once 'vendor/autoload.php';
require_once 'class-buffer-tweets.php';

$dotenv = new Dotenv\Dotenv( __DIR__ );
$dotenv->load();

$talks = array(
	array(
		'handle' => '@ifingers',
		'title' => 'accessibility with #WordPress',
		'main' => true,
	),
	array(
		'handle' => '@tweetingsherry',
		'title' => 'giving talks (#meta)',
		'lightning' => true,
	),
	array(
		'handle' => '@moblimic',
		'title' => '@docker & Bedrock with #WordPress',
		'lightning_2' => true,
	),
);

$tweets = include 'tweets.php';

$scheduler = new WPBournemouth\TweetBuffer\scheduleMeetupTweets( $talks, $tweets );
$scheduler->run();
