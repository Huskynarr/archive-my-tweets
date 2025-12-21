<?php

namespace AMWhalen\ArchiveMyTweets;

require_once 'tweet.php';

/**
 * Interacts with the X API v2 (formerly Twitter API v2) to archive posts for an account.
 */
class Archiver {

    protected $username;
    protected $twitter;
    protected $model;

    /**
     * Constructor
     * 
     * @param string $username X username
     * @param TwitterV2|\TijsVerkoyen\Twitter\Twitter $twitter X API client (formerly Twitter API)
     * @param Model $model Database model
     */
    public function __construct($username, $twitter, Model $model) {

        $this->username = $username;
        $this->twitter  = $twitter;
        $this->model    = $model;

    }

    /**
     * Grabs all the latest posts (tweets) and puts them into the database.
     * Note: API v2 free tier is limited to 1,500 posts (tweets) per month.
     *
     * @return string Returns a string with informational output.
     */
    public function archive() {

        // API v2 params
        $paginationToken    = null;
        $sinceId            = null;
        $userId             = null; // not needed if using screen name
        $screenName         = $this->username;
        $count              = 100; // API v2 max is 100 per request
        $trimUser           = null;
        $excludeReplies     = false;
        $contributorDetails = true;
        $includeRts         = true;

        $latest = $this->model->getLatestTweet();
        if ($latest && isset($latest['id'])) {
            $sinceId = (string)$latest['id'];
        }

        // loop variables
        $str            = '';
        $page           = 1;
        $gotResults     = true;
        $apiCalls       = 0;
        $tweetsFound    = 0;
        $numAdded       = 0;
        $exceptionCount = 0;
        $numTweetsAdded = 0;
        $numExceptions  = 0;
        $maxExceptions  = 25; // don't get stuck in the loop if X is down

        while ($gotResults) {

            $str .= "page token: " . (($paginationToken === null) ? 'null' : $paginationToken) . "\n";

            try {

                $page = $this->twitter->statusesUserTimelinePage(
                    $userId,
                    $screenName,
                    $sinceId,
                    $count,
                    null,
                    $trimUser,
                    $excludeReplies,
                    $contributorDetails,
                    $includeRts,
                    $paginationToken
                );
                $tweetResults = $page['tweets'];
                $paginationToken = $page['next_token'];
                $apiCalls++;

                $numResults = count($tweetResults);
                $tweetsFound += $numResults;

                if ($numResults == 0) {
                    $str .= 'NO posts (tweets) on page ' . $page . ", exiting.\n";
                    $gotResults = false;
                } else {

                    $newestTweet = $tweetResults[0];
                    $oldestTweet = end($tweetResults);

                    $str .= $numResults . ' posts (tweets) on page ' . $page . " (oldest: ".$oldestTweet['id'].", newest: ".$newestTweet['id'].")\n";

                    $page++;

                    // add these tweets to the database
                    $tweets = array();
                    foreach ($tweetResults as $t) {

                        $tweet = new Tweet();
                        $tweet->load_array($t);
                        $tweets[] = $tweet;

                    }
                    $result = $this->model->addTweets($tweets);

                    if ($result === false) {
                        $str .= 'ERROR INSERTING POSTS (TWEETS) INTO DATABASE: ' . $this->model->getLastErrorMessage() . "\n";
                    } else if ( $result == 0 ) {
                        $str .= 'Zero posts (tweets) added.' . "\n";
                    } else {
                        $str .= $result . ' posts (tweets) added.' . "\n";
                        $numAdded += $result;
                    }

                    // If there's no next token, we're at the end of the timeline.
                    if (empty($paginationToken)) {
                        $gotResults = false;
                    }

                }

                // check if we've reached the rate limit
                $rate = $this->twitter->getLastRateLimitStatus();
                if (isset($rate['remaining']) && isset($rate['limit'])) {
                    $str .= $rate['remaining'] . '/' . $rate['limit'] . "\n";
                    if ($rate['remaining'] <= 0) {
                        $str .= 'API limit reached for this hour. Try again later.' . "\n";
                        $gotResults = false;
                    }
                } else {
                    $str .= 'Rate limit headers missing from response. X may be having problems. Try again later.' . "\n";
                    $gotResults = false;
                }

            } catch (\Exception $e) {

                $str .= 'Exception: ' . $e->getMessage() . "\n";

                if ($e->getCode() === 429) {
                    $str .= 'API limit reached. Try again later.' . "\n";
                    $gotResults = false;
                    continue;
                }

                $numExceptions++;

                // break out to avoid infinite looping while X is down
                if ($numExceptions >= $maxExceptions) {
                    $str .= 'Too many connection errors. X may be down. Try again later.' . "\n";
                    $gotResults = false;
                }

            }

        }

        $str .= $apiCalls . ' API calls, ' . $tweetsFound . ' posts (tweets) found, '.$numAdded.' posts (tweets) saved' . "\n";

        return $str;

    }

    /**
     * Subtracts 1 from the given integer, with support for 32 bit systems.
     * Note the return value is a string and not an int.
     *
     *
     * @param string $int A positive, non-zero integer represented as a string.
     * @return string
     */
    public function decrement64BitInteger($int) {

        if (PHP_INT_SIZE == 8) {
            return (string)((int)$int - 1);
        } else {

            $str = (string)$int;

            // 1 and 0 are special cases with this method
            if ($str == 1 || $str == 0) {
                return (string)($str - 1);
            }

            // Determine if number is negative
            $negative = $str[0] == '-';

            // Strip sign and leading zeros
            $str = ltrim($str, '0-+');

            // Loop characters backwards
            for ($i = strlen($str) - 1; $i >= 0; $i--) {

                if ($negative) { // Handle negative numbers

                    if ($str[$i] < 9) {
                        $str[$i] = $str[$i] + 1;
                        break;
                    } else {
                        $str[$i] = 0;
                    }

                } else { // Handle positive numbers

                    if ($str[$i]) {
                        $str[$i] = $str[$i] - 1;
                        break;
                    } else {
                        $str[$i] = 9;
                    }

                }

            }

            return ($negative ? '-' : '').ltrim($str, '0');

        }

    }

}
