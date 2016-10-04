# tweet-scheduler

Automatically buffer tweets to market a meetup event

## Installation

1. Make sure you have [composer](https://getcomposer.org) installed.
1. Clone the repo and run `composer install`
1. Copy the `.env.sample` to `.env` and fill it out
1. Edit the tweet text in `tweets.php` and configure the schedule. E.g. `meetup-20` is 20 days before the meetup date.
1. Customise tweet scheduled time in `.env`
1. For each new month edit `$speaker_handle` and `$talk_title`
1. Hit `index.php` and tweets will be scheduled in Buffer

## Credits

This was put together hastily by [Iain Poulson](https://github.com/polevaultweb)

## License
MIT