<?php
namespace BNS\App\CommandBundle\Command;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateParentChildAuthCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('bns:update-parent-child-auth')
            ->setDescription('Update all parent child relation')
            ->addArgument('start', InputArgument::OPTIONAL, 'start nb page', 1)
            ->addArgument('limit', InputArgument::OPTIONAL, 'nb parent exec', 1000)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        BNSAccess::setContainer($container);

        $bnsApi = $container->get('bns.api');

        $parents = UserQuery::create()
                ->joinPupilParentLinkRelatedByUserParentId()
                ->groupById()
                ->paginate($input->getArgument('start'), $input->getArgument('limit'))
            ;

        /** @var User $parent */
        foreach ($parents as $parent) {
            $output->writeln('update user : ' . $parent->getLogin());
            $pupils = $parent->getPupilParentLinksRelatedByUserParentId();
            $pupilIds = [];
            foreach ($pupils as $pupil) {
                $pupilIds[] = $pupil->getUserPupilId();
            }

            try {
                $result = $bnsApi->send('post_users_children', [
                    'route' => [
                        'id' => $parent->getId(),
                        'username' => $parent->getLogin(),
                    ],
                    'values' => [
                        'user_ids' => $pupilIds
                    ]
                ]);
            } catch (\Exception $e) {
                $output->writeln('<error>Error :' . $e->getMessage() . '</error>');
                throw $e;
            }
        }
    }
}
