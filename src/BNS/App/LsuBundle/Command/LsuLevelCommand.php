<?php
namespace BNS\App\LsuBundle\Command;

use BNS\App\LsuBundle\Model\LsuDomain;
use BNS\App\LsuBundle\Model\LsuDomainQuery;
use BNS\App\LsuBundle\Model\LsuLevelQuery;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class LsuLevelCommand extends ContainerAwareCommand
{
    /** @var  OutputInterface */
    protected $output;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('bns:lsu:levels');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $data = $value = Yaml::parse(file_get_contents(__DIR__.'/../Resources/data/levels.yml'));
        if (isset($data['levels'])) {
            $i = 0;
            foreach ($data['levels'] as $key => $levelData) {
                if (!isset($levelData['code'])) {
                    $output->writeln('Missing code');
                    continue;
                }
                if (!isset($levelData['cycle'])) {
                    $output->writeln('Missing cycle');
                    continue;
                }
                $level = LsuLevelQuery::create()
                    ->filterByCode($levelData['code'])
                    ->filterByCycle($levelData['cycle'])
                    ->findOneOrCreate()
                ;
                $level->setSortableRank($i);
                if ($level->isNew()) {
                    $level->save();
                    $output->writeln(sprintf('Level "%s" created', $level->getCode()));
                } else {
                    if ($level->isModified()) {
                        $level->save();
                    }
                    $output->writeln(sprintf('Level "%s" already here', $level->getCode()));
                }
                $i++;
            }
        }
    }

}
