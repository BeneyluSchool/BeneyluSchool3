<?php

namespace BNS\App\PaasBundle\Tests\Manager;

use BNS\App\PaasBundle\Manager\LicenceManager as BaseLicenceManager;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LicenceManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider licenceData
     *
     * @param $a
     * @param $b
     * @param $result
     */
    public function testCompareLicence($a, $b, $result)
    {
        $this->assertEquals($result, $this->getLicenceManager()->compareLicence($a, $b));
    }

    /**
     * @dataProvider licenceOrderData
     *
     * @param $list
     * @param $result
     */
    public function testCompareLicenceOrder($list, $result)
    {
        $licences = [];
        $resultLicences = [];
        foreach ($list as $licence) {
            $licences[] = [
                'licence' => $licence,
                'life_time' => true,
                'end' => null
            ];
        }

        foreach ($result as $licence) {
            $resultLicences[] = [
                'licence' => $licence,
                'life_time' => true,
                'end' => null
            ];
        }

        $manager = $this->getLicenceManager();

        usort($licences, [$manager, 'compareLicence']);

        $this->assertEquals($resultLicences, $licences);
    }


    public function licenceOrderData()
    {
        return [
            [[1 => 'EXPRESS', 2 => 'CLASSIC', 3 => 'SCHOOL', 4 => 'INFINITY'], ['INFINITY', 'SCHOOL', 'CLASSIC', 'EXPRESS']],
            [['EXPRESS', 'CLASSIC', 'EXPRESS', 'SCHOOL', 'SCHOOL'], ['SCHOOL', 'SCHOOL', 'CLASSIC', 'EXPRESS', 'EXPRESS']],
            [['SCHOOL', 'EXPRESS', 'CLASSIC', 'EXPRESS', 'SCHOOL', ], ['SCHOOL', 'SCHOOL', 'CLASSIC', 'EXPRESS', 'EXPRESS']],
            [['a', 'EXPRESS', 'CLASSIC', 'EXPRESS', 'SCHOOL', ], ['SCHOOL', 'CLASSIC', 'EXPRESS', 'EXPRESS', 'a']],
        ];
    }

    public function licenceData()
    {
        return [
            [
                [
                    'licence' => 'SCHOOL',
                    'life_time' => true,
                    'end' => null
                ],
                [
                    'licence' => 'EXPRESS',
                    'life_time' => true,
                    'end' => null
                ],
                -1
            ],
            [
                [
                    'licence' => 'EXPRESS',
                    'life_time' => true,
                    'end' => null
                ],
                [
                    'licence' => 'SCHOOL',
                    'life_time' => true,
                    'end' => null
                ],
                1
            ],
            [
                [
                    'licence' => 'EXPRESS',
                    'life_time' => true,
                    'end' => null
                ],
                [
                    'licence' => 'EXPRESS',
                    'life_time' => true,
                    'end' => null
                ],
                0
            ],
            [
                [
                    'licence' => 'EXPRESS',
                    'life_time' => false,
                    'end' => null
                ],
                [
                    'licence' => 'EXPRESS',
                    'life_time' => true,
                    'end' => null
                ],
                1
            ],
            [
                [
                    'licence' => 'EXPRESS',
                    'life_time' => true,
                    'end' => null
                ],
                [
                    'licence' => 'EXPRESS',
                    'life_time' => false,
                    'end' => null
                ],
                -1
            ]
        ];
    }

    protected function getLicenceManager($globalLicence = null)
    {
        $cache = new ArrayAdapter();

        $apiMock = $this->getMockBuilder('BNS\App\CoreBundle\Api\BNSApi')
            ->disableOriginalConstructor()
            ->getMock();

        $paasManagerMock = $this->getMockBuilder('BNS\App\PaasBundle\Manager\PaasSubscriptionManager')
            ->disableOriginalConstructor()
            ->getMock();

        return new LicenceManager($apiMock, $paasManagerMock, $cache, $globalLicence);
    }
}

class LicenceManager extends BaseLicenceManager
{
    public function compareLicence($a, $b)
    {
        return parent::compareLicence($a, $b);
    }
}
