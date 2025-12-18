<?php

namespace AMWhalen\ArchiveMyTweets;

class TwitterV2Test extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		require_once dirname(__FILE__) . '/../includes.php';
	}

	/**
	 * Test that the class can be instantiated
	 */
	public function testConstructor() {
		$twitter = new TwitterV2('test_key', 'test_secret');
		$this->assertInstanceOf(TwitterV2::class, $twitter);
	}

	/**
	 * Test that the bearer token can be set directly
	 */
	public function testSetBearerToken() {
		$twitter = new TwitterV2('test_key', 'test_secret');
		$twitter->setBearerToken('test_bearer_token');
		$this->assertEquals('test_bearer_token', $twitter->getBearerToken());
	}

	/**
	 * Test that the bearer token passed in constructor is used
	 */
	public function testBearerTokenInConstructor() {
		$twitter = new TwitterV2('test_key', 'test_secret', 'constructor_bearer_token');
		$this->assertEquals('constructor_bearer_token', $twitter->getBearerToken());
	}

	/**
	 * Test timeout getter and setter
	 */
	public function testTimeoutGetterSetter() {
		$twitter = new TwitterV2('test_key', 'test_secret');
		$this->assertEquals(30, $twitter->getTimeOut());
		
		$twitter->setTimeOut(60);
		$this->assertEquals(60, $twitter->getTimeOut());
	}

	/**
	 * Test user agent getter and setter
	 */
	public function testUserAgentGetterSetter() {
		$twitter = new TwitterV2('test_key', 'test_secret');
		$this->assertStringContainsString('PHP TwitterV2/', $twitter->getUserAgent());
		
		$twitter->setUserAgent('TestApp/1.0');
		$this->assertStringContainsString('TestApp/1.0', $twitter->getUserAgent());
	}

	/**
	 * Test that getLastRateLimitStatus returns an array
	 */
	public function testGetLastRateLimitStatus() {
		$twitter = new TwitterV2('test_key', 'test_secret');
		$status = $twitter->getLastRateLimitStatus();
		$this->assertIsArray($status);
	}

	/**
	 * Test that statusesUserTimeline throws exception without user info
	 */
	public function testStatusesUserTimelineRequiresUserInfo() {
		$twitter = new TwitterV2('test_key', 'test_secret', 'test_bearer');
		
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Specify a userId or a screenName.');
		
		$twitter->statusesUserTimeline();
	}
}
