<?php

namespace AMWhalen\ArchiveMyTweets;

/**
 * X API v2 Client (formerly Twitter API v2)
 * 
 * Implements X API v2 for the free tier as documented at:
 * https://docs.x.com/x-api/getting-started/getting-access
 * 
 * The free tier supports:
 * - User posts (tweets) lookup (up to 1,500 posts per month)
 * - OAuth 2.0 Bearer Token authentication
 */
class TwitterV2
{
    // API v2 URL
    const API_URL = 'https://api.x.com/2';
    
    // OAuth 2.0 token URL
    const OAUTH2_TOKEN_URL = 'https://api.x.com/oauth2/token';
    
    // Current version
    const VERSION = '1.0.0';
    
    /**
     * A cURL instance
     *
     * @var resource
     */
    private $curl;
    
    /**
     * The consumer key (API Key)
     *
     * @var string
     */
    private $consumerKey;
    
    /**
     * The consumer secret (API Secret)
     *
     * @var string
     */
    private $consumerSecret;
    
    /**
     * The Bearer Token for OAuth 2.0
     *
     * @var string
     */
    private $bearerToken;
    
    /**
     * The timeout in seconds
     *
     * @var int
     */
    private $timeOut = 30;
    
    /**
     * The user agent
     *
     * @var string
     */
    private $userAgent;
    
    /**
     * The rate limit status for the last executed call
     *
     * @var array
     */
    private $lastRateLimitStatus;
    
    /**
     * Constructor
     *
     * @param string $consumerKey    The API Key (Consumer Key)
     * @param string $consumerSecret The API Secret (Consumer Secret)
     * @param string|null $bearerToken Optional pre-generated Bearer Token
     */
    public function __construct(string $consumerKey, string $consumerSecret, ?string $bearerToken = null)
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->bearerToken = $bearerToken;
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->curl !== null) {
            curl_close($this->curl);
        }
    }
    
    /**
     * Get the Bearer Token for OAuth 2.0 authentication
     * If not already set, obtain one using the Application-only authentication flow
     *
     * @return string
     * @throws \Exception
     */
    public function getBearerToken(): string
    {
        if ($this->bearerToken !== null) {
            return $this->bearerToken;
        }
        
        // Generate Bearer Token using Application-only authentication
        $credentials = base64_encode(
            urlencode($this->consumerKey) . ':' . urlencode($this->consumerSecret)
        );
        
        $options = [
            CURLOPT_URL => self::OAUTH2_TOKEN_URL,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeOut,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ];
        
        // Use a dedicated curl handle for OAuth token requests
        $curlOAuth = curl_init();
        curl_setopt_array($curlOAuth, $options);
        
        $response = curl_exec($curlOAuth);
        $httpCode = curl_getinfo($curlOAuth, CURLINFO_HTTP_CODE);
        $errorNumber = curl_errno($curlOAuth);
        $errorMessage = curl_error($curlOAuth);
        curl_close($curlOAuth);
        
        if ($errorNumber !== 0) {
            throw new \Exception('cURL error: ' . $errorMessage, $errorNumber);
        }
        
        $json = json_decode($response, true);
        
        if ($httpCode !== 200 || !isset($json['access_token'])) {
            $error = 'Failed to obtain Bearer Token';
            if (isset($json['error'])) {
                $error = $json['error'];
            } elseif (isset($json['errors']) && is_array($json['errors']) && !empty($json['errors'])) {
                $error = $json['errors'][0]['message'] ?? $error;
            }
            throw new \Exception($error);
        }
        
        $this->bearerToken = $json['access_token'];
        return $this->bearerToken;
    }
    
    /**
     * Set the Bearer Token directly
     *
     * @param string $token
     */
    public function setBearerToken(string $token): void
    {
        $this->bearerToken = $token;
    }
    
    /**
     * Make an API call
     *
     * @param string $endpoint The API endpoint
     * @param array|null $parameters Query parameters
     * @return array
     * @throws \Exception
     */
    private function doCall(string $endpoint, ?array $parameters = null): array
    {
        $url = self::API_URL . '/' . $endpoint;
        
        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }
        
        $bearerToken = $this->getBearerToken();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $bearerToken,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeOut,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => true
        ];
        
        if ($this->curl === null) {
            $this->curl = curl_init();
        }
        
        curl_setopt_array($this->curl, $options);
        
        $response = curl_exec($this->curl);
        $headerSize = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        $errorNumber = curl_errno($this->curl);
        $errorMessage = curl_error($this->curl);
        
        if ($errorNumber !== 0) {
            throw new \Exception('cURL error: ' . $errorMessage, $errorNumber);
        }
        
        // Split headers and body
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        
        // Parse rate limit headers
        $this->parseRateLimitHeaders($responseHeaders);
        
        // Parse JSON response
        $json = json_decode($responseBody, true);
        
        if ($json === null) {
            throw new \Exception('Invalid JSON response from API');
        }
        
        if ($httpCode >= 400) {
            $error = $json['detail'] ?? $json['title'] ?? ('HTTP error ' . $httpCode);
            throw new \Exception($error, $httpCode);
        }

        // Check for API errors
        if (isset($json['errors']) && is_array($json['errors']) && !empty($json['errors'])) {
            $errorMsg = $json['errors'][0]['message'] ?? 'Unknown API error';
            throw new \Exception($errorMsg);
        }
        
        return $json;
    }
    
    /**
     * Parse rate limit headers from response
     *
     * @param string $headers
     */
    private function parseRateLimitHeaders(string $headers): void
    {
        $rateLimitStatus = [];
        
        foreach (explode("\r\n", $headers) as $line) {
            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $key = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);
                    
                    switch ($key) {
                        case 'x-rate-limit-limit':
                            $rateLimitStatus['limit'] = (int)$value;
                            break;
                        case 'x-rate-limit-remaining':
                            $rateLimitStatus['remaining'] = (int)$value;
                            break;
                        case 'x-rate-limit-reset':
                            $rateLimitStatus['reset'] = (int)$value;
                            break;
                    }
                }
            }
        }
        
        $this->lastRateLimitStatus = $rateLimitStatus;
    }
    
    /**
     * Get the rate limit status from the last API call
     *
     * @return array
     */
    public function getLastRateLimitStatus(): array
    {
        return $this->lastRateLimitStatus ?? [];
    }
    
    /**
     * Get user ID by username
     *
     * @param string $username
     * @return string User ID
     * @throws \Exception
     */
    public function getUserIdByUsername(string $username): string
    {
        $response = $this->doCall('users/by/username/' . urlencode($username));
        
        if (!isset($response['data']['id'])) {
            throw new \Exception('User not found: ' . $username);
        }
        
        return $response['data']['id'];
    }
    
    /**
     * Get posts for a user (API v2 equivalent of statusesUserTimeline)
     * 
     * Note: The free tier has a monthly limit of 1,500 posts (tweets).
     *
     * @param string|null $userId The ID of the user
     * @param string|null $screenName The screen name of the user (will be converted to ID)
     * @param string|null $sinceId Returns results with an ID greater than the specified ID
     * @param int|null $count Number of posts to retrieve (max 100 for v2)
     * @param string|null $maxId Returns results with an ID less than the specified ID (pagination_token in v2)
     * @param bool|null $trimUser Not used in v2, kept for compatibility
     * @param bool|null $excludeReplies Exclude reply tweets
     * @param bool|null $contributorDetails Not used in v2, kept for compatibility
     * @param bool|null $includeRts Include retweets
     * @return array Array of post data in v1.1-compatible format
     * @throws \Exception
     */
    public function statusesUserTimeline(
        ?string $userId = null,
        ?string $screenName = null,
        ?string $sinceId = null,
        ?int $count = null,
        ?string $maxId = null,
        ?bool $trimUser = null,
        ?bool $excludeReplies = null,
        ?bool $contributorDetails = null,
        ?bool $includeRts = null
    ): array {
        $page = $this->statusesUserTimelinePage(
            $userId,
            $screenName,
            $sinceId,
            $count,
            $maxId,
            $trimUser,
            $excludeReplies,
            $contributorDetails,
            $includeRts,
            null
        );
        return $page['tweets'];
    }

    /**
     * Get a single page of posts for a user, including pagination token.
     *
     * @return array{tweets: array, next_token: string|null}
     */
    public function statusesUserTimelinePage(
        ?string $userId = null,
        ?string $screenName = null,
        ?string $sinceId = null,
        ?int $count = null,
        ?string $maxId = null,
        ?bool $trimUser = null,
        ?bool $excludeReplies = null,
        ?bool $contributorDetails = null,
        ?bool $includeRts = null,
        ?string $paginationToken = null
    ): array {
        // Validate that we have either userId or screenName
        if (empty($userId) && empty($screenName)) {
            throw new \Exception('Specify a userId or a screenName.');
        }
        
        // If we only have screenName, get the user ID
        if (empty($userId) && !empty($screenName)) {
            $userId = $this->getUserIdByUsername($screenName);
        }
        
        // Build parameters for v2 API
        $parameters = [
            'tweet.fields' => 'id,text,created_at,author_id,in_reply_to_user_id,referenced_tweets,source',
            'user.fields' => 'id,username,name',
            'expansions' => 'author_id,in_reply_to_user_id,referenced_tweets.id'
        ];
        
        // Max results (v2 API supports max 100 per request)
        if ($count !== null) {
            $parameters['max_results'] = min($count, 100);
        } else {
            $parameters['max_results'] = 100;
        }
        
        // Since ID for newer tweets
        if ($sinceId !== null) {
            $parameters['since_id'] = $sinceId;
        }
        
        // Pagination or older posts
        if ($paginationToken !== null) {
            $parameters['pagination_token'] = $paginationToken;
        } elseif ($maxId !== null) {
            $parameters['until_id'] = $maxId;
        }
        
        // Exclude replies
        if ($excludeReplies === true) {
            $parameters['exclude'] = 'replies';
        }
        
        // Exclude retweets
        if ($includeRts === false) {
            if (isset($parameters['exclude'])) {
                $parameters['exclude'] .= ',retweets';
            } else {
                $parameters['exclude'] = 'retweets';
            }
        }
        
        // Make the API call
        $response = $this->doCall('users/' . $userId . '/tweets', $parameters);
        
        // Check if we have data
        if (!isset($response['data']) || empty($response['data'])) {
            return [
                'tweets' => [],
                'next_token' => null
            ];
        }
        
        // Build user lookup from includes
        $users = [];
        if (isset($response['includes']['users'])) {
            foreach ($response['includes']['users'] as $user) {
                $users[$user['id']] = $user;
            }
        }
        
        // Convert v2 response to v1.1-compatible format
        $tweets = $this->convertToV1Format($response['data'], $users);
        $nextToken = $response['meta']['next_token'] ?? null;

        return [
            'tweets' => $tweets,
            'next_token' => $nextToken
        ];
    }
    
    /**
     * Convert X API v2 response to v1.1-compatible format (formerly Twitter API v2)
     *
     * @param array $tweets Array of tweets from v2 API
     * @param array $users Array of user data keyed by user ID
     * @return array Array of tweets in v1.1 format
     */
    private function convertToV1Format(array $tweets, array $users): array
    {
        $v1Tweets = [];
        
        foreach ($tweets as $tweet) {
            $authorId = $tweet['author_id'] ?? null;
            $user = $users[$authorId] ?? ['id' => $authorId, 'username' => '', 'name' => ''];
            
            // Determine if this is a retweet
            $retweetedStatusId = null;
            $retweetedStatusUserId = null;
            if (isset($tweet['referenced_tweets'])) {
                foreach ($tweet['referenced_tweets'] as $ref) {
                    if ($ref['type'] === 'retweeted') {
                        $retweetedStatusId = $ref['id'];
                        // Note: v2 doesn't provide the original author ID in the same way
                        break;
                    }
                }
            }
            
            // Determine in_reply_to fields
            $inReplyToStatusId = null;
            if (isset($tweet['referenced_tweets'])) {
                foreach ($tweet['referenced_tweets'] as $ref) {
                    if ($ref['type'] === 'replied_to') {
                        $inReplyToStatusId = $ref['id'];
                        break;
                    }
                }
            }
            
            $v1Tweet = [
                'id' => $tweet['id'],
                'text' => $tweet['text'],
                'created_at' => $tweet['created_at'] ?? null,
                'source' => $tweet['source'] ?? 'X',
                'truncated' => false,
                'favorited' => false,
                'in_reply_to_status_id' => $inReplyToStatusId,
                'in_reply_to_user_id' => $tweet['in_reply_to_user_id'] ?? null,
                'in_reply_to_screen_name' => null, // v2 doesn't provide this directly
                'user' => [
                    'id' => $user['id'],
                    'screen_name' => $user['username'] ?? '',
                    'name' => $user['name'] ?? ''
                ]
            ];
            
            // Add retweet info if applicable
            if ($retweetedStatusId !== null) {
                $v1Tweet['retweeted_status'] = [
                    'id' => $retweetedStatusId,
                    'user' => ['id' => $retweetedStatusUserId]
                ];
            }
            
            $v1Tweets[] = $v1Tweet;
        }
        
        return $v1Tweets;
    }
    
    /**
     * Set the timeout for API calls
     *
     * @param int $seconds
     */
    public function setTimeOut(int $seconds): void
    {
        $this->timeOut = $seconds;
    }
    
    /**
     * Get the timeout
     *
     * @return int
     */
    public function getTimeOut(): int
    {
        return $this->timeOut;
    }
    
    /**
     * Set the user agent
     *
     * @param string $userAgent
     */
    public function setUserAgent(string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }
    
    /**
     * Get the user agent
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return 'PHP XAPIv2/' . self::VERSION . ' ' . ($this->userAgent ?? '');
    }
}
