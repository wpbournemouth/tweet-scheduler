<?php
namespace WPBournemouth\TweetBuffer;

use Buffer\Client;
use SimplePie;
use duzun\hQuery;

class scheduleMeetupTweets {

	protected $meetup;
	protected $meetup_group;
	protected $talks;
	protected $tweets;

	/**
	 * scheduleMeetupTweets constructor.

	 * @param array $talks
	 * @param array  $tweets
	 */
	public function __construct( $talks, $tweets ) {
		$this->talks = $talks;
		$this->tweets                 = $tweets;
		$this->meetup_group           = getenv( 'MEETUP_GROUP_URL' );

		if ( false === ( $meetup = $this->getUpcomingMeetup() ) ) {
			return;
		}

		$this->meetup = $meetup;
	}

	/**
	 * Get speaker handle
	 *
	 * @param string $type
	 *
	 * @return mixed
	 */
	protected function get_speaker_handle( $type = 'main' ) {
		foreach ( $this->talks as $talk ) {
			if ( isset( $talk[ $type ] ) && $talk[ $type ] ) {
				return $talk['handle'];
			}
		}

		return false;
	}

	/**
	 * Get speaker title
	 *
	 * @param string $type
	 *
	 * @return mixed
	 */
	protected function get_talk_title( $type = 'main' ) {
		foreach ( $this->talks as $talk ) {
			if ( isset( $talk[ $type ] ) && $talk[ $type ] ) {
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
			'[speaker_handle]'             => $this->get_speaker_handle( 'main' ),
			'[talk_title]'                 => $this->get_talk_title( 'main' ),
			'[lightning_speaker_handle]'   => $this->get_speaker_handle( 'lightning' ),
			'[lightning_talk_title]'       => $this->get_talk_title( 'lightning' ),
			'[lightning_2_speaker_handle]' => $this->get_speaker_handle( 'lightning_2' ),
			'[lightning_2_talk_title]'     => $this->get_talk_title( 'lightning_2' ),
			'[meetup_date]'                => $this->meetup->date->format( 'D jS M' ),
			'[meetup_link]'                => $this->meetup->get_permalink(),
			'[meetup_group_link]'          => 'http://www.meetup.com/' . $this->meetup_group,
		);

		$replaced_tweets = array();
		foreach ( $this->tweets as $schedule => $tweet ) {
			if ( $this->tweet_contains_unreplaced_tags( $replacements, $tweet ) ) {
				continue;
			}

			$tweet = str_replace( array_keys( $replacements ), array_values( $replacements ), $tweet );

			$replaced_tweets[ $schedule ] = $tweet;
		}

		return $replaced_tweets;
	}

	protected function getUpcomingMeetup() {
		$feed = new SimplePie();

		$url = sprintf( 'https://www.meetup.com/%s/events/atom/', $this->meetup_group );
		$feed->set_feed_url($url);
		$feed->init();
		$events = $feed->get_items();

		if ( ! isset ( $events[0] ) ) {
			return false;
		}

		$meetup = $events[0];

		$html        = file_get_contents( $meetup->get_permalink() );
		$meetupDoc   = hQuery::fromHTML( $html );
		$meetup_date = $meetupDoc->find( 'time.eventStatusLabel' );

		$meetup_time = $meetup_date->attr( 'datetime' );
		$meetup_date = new \DateTime();
		$meetup_date->setTimestamp( $meetup_time / 1000 );
		$meetup->date = $meetup_date;

		return $meetup;
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

//			print( $date->format( \DateTime::ISO8601 ) . ' -  '.  $tweet . ' - ' . strlen( $tweet ) . '<br>' );
//			continue;

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
		$date = clone $this->meetup->date;
		$date->modify( '-' . $days . ' days' );

		$today = new \DateTime();
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
