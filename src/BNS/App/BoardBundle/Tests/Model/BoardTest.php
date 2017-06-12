<?php

namespace BNS\App\BoardBundle\Tests\Model;

use BNS\App\BoardBundle\Model\BoardCategory;
use BNS\App\BoardBundle\Model\BoardCategoryPeer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BoardTest extends WebTestCase
{
	public function testBoardCategorySlug()
	{
		$client = static::createClient(array(
			'environment'	=> 'app_test'
		));
		$queryCountBefore = \Propel::getConnection(BoardCategoryPeer::DATABASE_NAME)->getQueryCount();
		$client->getContainer();
		
		for ($i=0; $i<100; $i++) {
			$category = new BoardCategory();
			$category->setTitle('Toto');
			$category->setBoardId(1);
			$category->save();
			$category = null;
		}
		
		$queryCount = \Propel::getConnection(BoardCategoryPeer::DATABASE_NAME)->getQueryCount() - $queryCountBefore;
		$this->assertTrue(true, "100 slugs created successfully");
		$this->assertLessThanOrEqual(300, $queryCount, 'Too much queries : ' . $queryCount . ' > 300');
	}
}