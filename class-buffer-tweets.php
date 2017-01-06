<?php
namespace WPBournemouth\TweetBuffer;

use Buffer\Client;
use DMS\Service\Meetup\MeetupKeyAuthClient;

class scheduleMeetupTweets {

	protected $meetup;
	protected $meetup_group;
	protected $speaker_twitter_handle;
	protected $talk_title;
	protected $tweets;

	protected $meetup_client;

	/**
	 * scheduleMeetupTweets constructor.
	 *
	 * @param string $speaker_twitter_handle
	 * @param string $talk_title
	 * @param array  $tweets
	 */
	public function __construct( $speaker_twitter_handle, $talk_title, $tweets ) {
		$this->speaker_twitter_handle = $speaker_twitter_handle;
		$this->talk_title             = $talk_title;
		$this->tweets                 = $tweets;
		$this->meetup_group           = getenv( 'MEETUP_GROUP_URL' );

		$this->meetup_client = MeetupKeyAuthClient::factory( array( 'key' => getenv( 'MEETUP_API_KEY' ) ) );

		if ( false === ( $meetup = $this->getUpcomingMeetup() ) ) {
			return;
		}

		$this->meetup = $meetup;

		// Convert the meetup millisecond time to a timestamp
		$meetup_date = new \DateTime();
		$meetup_date->setTimestamp( $meetup['time'] / 1000 );
		$this->meetup['date'] = $meetup_date;
	}

	protected function replaceTweetData( $tweets ) {
		$replacements = array(
			'[speaker_handle]'    => $this->speaker_twitter_handle,
			'[talk_title]'        => $this->talk_title,
			'[meetup_date]'       => $this->meetup['date']->format( 'D jS M' ),
			'[meetup_link]'       => $this->meetup['link'],
			'[meetup_group_link]' => 'http://www.meetup.com/' . $this->meetup_group,
		);

		$replaced_tweets = array();
		foreach ( $tweets as $schedule => $tweet ) {
			$replaced_tweets[ $schedule ] = str_replace( array_keys( $replacements ), array_values( $replacements ), $tweet );
		}

		return $replaced_tweets;
	}

	protected function getUpcomingMeetup() {
		$response = $this->meetup_client->getGroupEvents( array( 'urlname' => $this->meetup_group ) );

		$events = $response->getData();

		if ( ! isset ( $events[0] ) ) {
			return false;
		}

		return $events[0];
	}

	public function run() {
		$tweets = $this->replaceTweetData( $this->tweets );

		$buffer = new Client( getenv( 'BUFFER_ACCESS_TOKEN' ), array() );

		$count = 0;
		foreach ( $tweets as $schedule => $tweet ) {
			if ( false === ( $date = $this->getSchedule( $schedule ) ) ) {
				// Don't buffer tweets scheduled in the past.
				continue;
			}

			$options['body']['scheduled_at'] = $date->format( \DateTime::ISO8601 );

			$response = $buffer->user()->createUpdate( $tweet, array(
				getenv( 'BUFFER_ACCOUNT_ID' ),
			), $options );

			if ( 200 === (int) $response->code ) {
				$count++;
			}
		}

		printf( '%d/%d tweets buffered!', $count, count( $tweets ) );
	}

	protected function getSchedule( $schedule ) {
		if ( 'now' === $schedule ) {
			$now = new \DateTime();
			$now->modify( '+1 hours' );

			return $now;
		}

		$days = substr( $schedule, strpos( $schedule, '-' ) + 1 );
		$date = clone $this->meetup['date'];
		$date->modify( '-' . $days . ' days' );

		$today = new \DateTime();
		$today->setTime( 23, 59 );
		if ( $date < $today ) {
			// Schedule in past, ignore.
			return false;
		}

		$hour = 11;
		$min  = 00;
		if ( getenv( 'BUFFER_TWEET_TIME' ) ) {
			$time = explode( ':', getenv( 'BUFFER_TWEET_TIME' ) );
			$hour = $time[0];
			$min  = $time[1];
		}
		$date->setTime( $hour, $min );

		return $date;
	}

}
