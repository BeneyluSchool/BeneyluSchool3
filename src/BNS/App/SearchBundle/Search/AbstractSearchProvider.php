<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 19/01/2018
 * Time: 13:06
 */

namespace BNS\App\SearchBundle\Search;


abstract class AbstractSearchProvider
{
    /**
     * Module unique name concerned by this search
     *
     * @return string
     */
    abstract public function getName();


    abstract function search($term, $providers = array());
}
