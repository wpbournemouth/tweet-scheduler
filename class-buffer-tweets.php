<?php
namespace WPBournemouth\TweetBuffer;

use Buffer\Client;
use DMS\Service\Meetup\MeetupKeyAuthClient;

class scheduleMeetupTweets {

	protected $meetup;
	protected $meetup_group;
	protected $talks;
	protected $tweets;

	protected $meetup_client;

	/**
	 * scheduleMeetupTweets constructor.

	 * @param array $talks
	 * @param array  $tweets
	 */
	public function __construct( $talks, $tweets ) {
		$this->talks = $talks;
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

	/**
	 * Get speaker handle
	 *
	 * @param bool $lightning
	 *
	 * @return mixed
	 */
	protected function get_speaker_handle( $lightning = false ) {
		foreach ( $this->talks as $talk ) {
			if ( $lightning && isset( $talk['lightning'] ) && $talk['lightning'] ) {
				return $talk['handle'];
			}

			if ( ! $lightning && ( ! isset( $talk['lightning'] ) || ! $talk['lightning'] ) ) {
				return $talk['handle'];
			}
		}

		return false;
	}

	/**
	 * Get speaker handle
	 *
	 * @param bool $lightning
	 *
	 * @return mixed
	 */
	protected function get_talk_title( $lightning = false ) {
		foreach ( $this->talks as $talk ) {
			if ( $lightning && isset( $talk['lightning'] ) && $talk['lightning'] ) {
				return $talk['title'];
			}

			if ( ! $lightning && ( ! isset( $talk['lightning'] ) || ! $talk['lightning'] ) ) {
				return $talk['title'];
			}
		}

		return false;
	}

	/**
	 * Does the tweet string contain any tags that haven't been replaced?
	 *
	 * @param array $replacements
	 * @param string $tweet
	 *
	 * @return bool
	 */
	protected function tweet_contains_unreplaced_tags( $replacements, $tweet ) {
		foreach ( $replacements as $search => $replace ) {
			if ( false !== $replace ) {
				// Replacement has happened on this tag
				continue;
			}

			if ( false !== strpos( $tweet, $search ) ) {
				// Tag exists in tweet
				return true;
			}
		}

		// No unreplaced tags in tweet
		return false;
	}

	/**
	 * Replace data in tweets
	 *
	 * @return array
	 */
	protected function replaceTweetData() {
		$replacements = array(
			'[speaker_handle]'           => $this->get_speaker_handle(),
			'[talk_title]'               => $this->get_talk_title(),
			'[lightning_speaker_handle]' => $this->get_speaker_handle( true ),
			'[lightning_talk_title]'     => $this->get_talk_title( true ),
			'[meetup_date]'              => $this->meetup['date']->format( 'D jS M' ),
			'[meetup_link]'              => $this->meetup['link'],
			'[meetup_group_link]'        => 'http://www.meetup.com/' . $this->meetup_group,
		);

		$replaced_tweets = array();
		foreach ( $this->tweets as $schedule => $tweet ) {
			if ( $this->tweet_contains_unreplaced_tags( $replacements, $tweet ) ) {
				continue;
			}

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
		$tweets = $this->replaceTweetData();

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

	/**
	 * Get datetime for a schedule string
	 * 
	 * @param string $schedule
	 *
	 * @return bool|\DateTime
	 */
	protected function getSchedule( $schedule ) {
		if ( 'now' === $schedule ) {
			$now = new \DateTime();
			$now->modify( '+1 hours' );

			return $now;
		}

		$parts = explode( '-', $schedule );
		array_shift( $parts );

		$days = $parts[0];
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
			$time_str = explode( ':', getenv( 'BUFFER_TWEET_TIME' ) );
			$hour = $time_str[0];
			$min  = $time_str[1];
		}

		if ( isset( $parts[1] ) ) {
			// Allow default time to be overridden
			$time = explode( ':', $parts[1] );
			$hour = $time[0];
			$min  = $time[1];
		}

		$date->setTime( $hour, $min );

		return $date;
	}

}
