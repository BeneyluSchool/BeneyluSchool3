<?php
namespace BNS\App\LsuBundle\Command;

use BNS\App\LsuBundle\Model\LsuDomain;
use BNS\App\LsuBundle\Model\LsuDomainQuery;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LsuDomainCommand extends ContainerAwareCommand
{
    /** @var  OutputInterface */
    protected $output;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('bns:lsu:domains');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
//        \Propel::disableInstancePooling();

        $data = $value = Yaml::parse(file_get_contents(__DIR__.'/../Resources/data/domains.yml'));
        if (isset($data['domains'])) {
            foreach ($data['domains'] as $key => $domains) {
                $output->writeln(sprintf('import domain : "%s"', $key));
                $this->import($domains);
            }
        }
    }

    protected function import($data)
    {
        $version = (string)$data['version'];

        $root = LsuDomainQuery::create()->findRoot($version);
        if (!$root) {
            $root = new LsuDomain();
            $root->setScopeValue($version);
            $root->setLabel($version);
            $root->setCycle($data['cycle']);
            $root->makeRoot();
            $root->save();
        }
        if (isset($data['children'])) {
            foreach ($data['children'] as $childrenData) {
                $this->importChildren($root, $childrenData);
            }
        }
    }

    protected function importChildren(LsuDomain $domain, $childrenData)
    {
        $this->output->writeLn(sprintf('Import domain : "%s"', $childrenData['label']));

        $code = isset($childrenData['code']) ? $childrenData['code'] : null;
        $cycle = isset($childrenData['cycle']) ? $childrenData['cycle'] : $domain->getCycle();

        /** @var LsuDomain $child */
        $child = LsuDomainQuery::create()
            ->filterByLabel($childrenData['label'])
            ->filterByVersion($domain->getVersion())
            ->filterByCycle($cycle)
            ->_if($code)
                ->filterByCode($code)
            ->_endif()
            ->findOneOrCreate()
        ;

        if ($child->isNew()) {
            $child->insertAsLastChildOf($domain);
        }
        $child->save();

        if (isset($childrenData['children'])) {
            foreach ($childrenData['children'] as $data) {
                $this->importChildren($child, $data);
            }
        }
    }

}
