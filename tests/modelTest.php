<?php

namespace AMWhalen\ArchiveMyTweets;
use PDO;

// see: http://stackoverflow.com/questions/3138946/mocking-the-pdo-object-using-phpunit
class MockPDO extends PDO {
	public function __construct() {}
}

class ModelTest extends \PHPUnit\Framework\TestCase {

	protected $db;

	public function setUp(): void {
		$this->db = $this->createMock(MockPDO::class);
	}

	public function testTableName() {
		$model = new Model($this->db, 'amt_');
		$this->assertEquals('amt_tweets', $model->getTableName());
	}

	public function testAddNoTweets() {
		$model = new Model($this->db, 'amt_');
		$this->assertEquals(0, $model->addTweets(array()));
	}

}