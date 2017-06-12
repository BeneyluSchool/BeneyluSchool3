<?php

namespace BNS\App\BlogBundle\Tests\Model;

use BNS\App\CoreBundle\Model\BlogCategory;
use BNS\App\CoreBundle\Model\BlogCategoryPeer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BlogTest extends WebTestCase
{
	public function testBlogCategorySlug()
	{
		$client = static::createClient(array(
			'environment'	=> 'app_test'
		));
		$queryCountBefore = \Propel::getConnection(BlogCategoryPeer::DATABASE_NAME)->getQueryCount();
		$client->getContainer();
		
		for ($i=0; $i<100; $i++) {
			$category = new BlogCategory();
			$category->setTitle('Toto');
			$category->setBlogId(1);
			$category->save();
			$category = null;
		}
		
		$queryCount = \Propel::getConnection(BlogCategoryPeer::DATABASE_NAME)->getQueryCount() - $queryCountBefore;
		$this->assertTrue(true, "100 slugs created successfully");
		$this->assertLessThanOrEqual(300, $queryCount, 'Too much queries : ' . $queryCount . ' > 300');
	}
}