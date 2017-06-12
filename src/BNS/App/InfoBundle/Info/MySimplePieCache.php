<?php

namespace BNS\App\InfoBundle\Info;


use BNS\App\InfoBundle\Model\AnnouncementQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

class MySimplePieCache extends \SimplePie_Cache implements \SimplePie_Cache_Base
{

    /**
     * Classe de surchage du cache Simple Pie pour pouvoir stocker en base les announcements
     */

    protected $cacheFile;
    protected $container;

    public function __construct($location, $filename, $extension)
    {
        $this->cacheFile = new \SimplePie_Cache_File($location, $filename, $extension);
    }


    public function create($location, $filename, $extension)
    {
        return new MySimplePieCache($location, $filename, $extension);
    }

    /**
     * @param array|\SimplePie $data
     * @return bool
     */
    public function save($data)
    {

        /*
         * Nous sommes à une reconstruction de cache, c'est à ce moment que nous vérifions / réajustons les ANNOUNCEMENTS
         */
        $container = BNSAccess::getContainer();
        if($data->feed_url == $container->getParameter('bns_app_info_feeds_blog'))
        {
            /* @var $item \SimplePie_Item  */
            foreach($data->get_items(0,$container->getParameter('bns_app_info_nb_announcements_index_blog')) as $item)
            {
                $announcement = AnnouncementQuery::create()
                    ->filterByLabel($item->get_link())
                    ->filterByIsActive(true)
                    ->filterByType('BLOG')
                    ->findOneOrCreate();
                $found[] = $item->get_link();
                if($announcement->isNew())
                {
                    //On récupère l'image
                    $html = \simple_html_dom::file_get_html($item->get_link());
                    $image = $html->find('img');
                    $announcement->setImageUrl($image[0]->src);
                    $announcement->setCreatedAt($item->get_date('Y-m-d h:i:s'));
                    $announcement->save();
                }
            }
            AnnouncementQuery::create()->filterByLabel($found,\Criteria::NOT_IN)->filterByType('BLOG')->delete();
        }
        if($data->feed_url == $container->getParameter('bns_app_info_feeds_updates'))
        {
            /* @var $item \SimplePie_Item  */
            foreach($data->get_items(0,$container->getParameter('bns_app_info_nb_announcements_index_forum')) as $item)
            {
                $query = AnnouncementQuery::create()
                    ->filterByLabel($item->get_link())
                    ->filterByType('UPDATE')
                    ->filterByIsActive(true)
                    ->findOneOrCreate()->save();
                $found[] = $item->get_link();
            }
            AnnouncementQuery::create()->filterByLabel($found,\Criteria::NOT_IN)->filterByType('UPDATE')->delete();
        }
        return $this->cacheFile->save($data);
    }

    public function load()
    {
        return $this->cacheFile->load();
    }

    public function mtime()
    {
        return $this->cacheFile->mtime();
    }

    public function unlink()
    {
        return $this->cacheFile->unlink();
    }

    public function touch()
    {
        return $this->cacheFile->touch();
    }
}