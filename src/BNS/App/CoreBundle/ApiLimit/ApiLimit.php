<?php

namespace BNS\App\CoreBundle\ApiLimit;

use BNS\App\CoreBundle\Model\ApiLimitQuery;

/**
 * @author Eymeric Taelman
 */
class ApiLimit
{

    /**
     * Actions traitées
     */

    CONST HOME_SUBSCRIPTION = 'HOME_SUBSCRIPTION';
    private $configuration;


    public function __construct($configuration)
    {
        $this->configuration = $configuration;
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function check($ip, $action)
    {
        $line = ApiLimitQuery::create()
            ->filterByIp($ip)
            ->filterByAction($action)
            ->filterByCreatedAt(time() - 3600, \Criteria::GREATER_EQUAL)
            ->findOneOrCreate();
        $value = $line->getValue();
        $conf = $this->getConfiguration();

        if ($value >= $conf[strtolower($action)]) {
            //TODO Envoyer alerte Email
            return false;
        } else {
            if($line->isNew())
            {
                $line->setCreatedAt(time());
            }
            $line->setValue($value + 1);
            $line->save();
            return true;
        }
    }


}