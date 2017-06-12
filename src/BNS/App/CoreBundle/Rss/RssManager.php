<?php

namespace BNS\App\CoreBundle\Rss;

use \Predis\Client;

class RssManager
{
    /**
	 * @var Predis\Client 
	 */
	protected $redis;

	/**
	 * @param \Predis\Client $redis
	 */
	public function __construct(Client $redis)
	{
		$this->redis = $redis;
	}

	/**
	 * @param string $url
	 * @param int    $maxItem
	 * 
	 * @return array Items
	 */
	public function getRss($url, $maxItem = 5, $useCache = false)
	{
		$rssKey = 'board_rss_' . md5($url);
		$cleaned = array();

		// Recherche en cache du flux
		if (!$useCache || !$this->redis->exists($rssKey .':datas')) {
			try {
				$rss = new \rss_php();
				$rss->load($url);
                $items = array_slice($rss->getItems(true), 0, $maxItem);
				$i = 0;
				foreach ($items as $item) {
					$cleanedItem = array();
					$cleanedItem['title'] = $this->convertText($item['title']['value']);
					$cleanedItem['link'] = $item['link']['value'];
					$cleanedItem['description'] = $this->convertText($item['description']['value']);
	//                     $cleanedItem['description'] = str_replace('&#8217;',"'",html_entity_decode($item['description']['value']));
	//                     $html =  file_get_html($item['link']['value']);
	//                     $image = $redis->get('bns_blog:image:' . $cleaned_item['link']);
	//                     if(!$image){
	//                         $image = $html->find('img');
	//                         $image = $image[0]->src;
	//                         $redis->set('bns_blog:image:' . $cleaned_item['link'],$image);
	//                     }
	//                     $cleaned_item['image'] = $image;
					$this->redis->set($rssKey .':feed_' . $i,  json_encode($cleanedItem));
					$cleaned[] = $cleanedItem;
					$i++;
				}
			} catch (\Exception $e) {
                throw $e;
				/** TODO: log error */
			}

			$this->redis->set($rssKey .':datas', 1);
			$this->redis->expire($rssKey .':datas', 12 * 3600);
		} else {
			$i = 0;
			$data = $this->redis->get($rssKey .':feed_' . $i);
			while ($data && $i < $maxItem) {
				$cleaned[] = json_decode($data);
				$i ++;
				$data = $this->redis->get($rssKey .':feed_' . $i);
			}
		}

		return $cleaned;
	}

	protected function convertText($text)
	{
		$text = mb_convert_encoding($text, 'UTF8', mb_detect_encoding($text));

		return $text;
	}
}