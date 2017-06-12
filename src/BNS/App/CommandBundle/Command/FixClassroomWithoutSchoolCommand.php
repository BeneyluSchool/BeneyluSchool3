<?php
namespace BNS\App\CommandBundle\Command;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class FixClassroomWithoutSchoolCommand extends ContainerAwareCommand
{
    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this
            ->setName('bns:fix:classroom-without-school')
            ->setDescription('Create school for classroom attached to group 1')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'the number of classroom to fix', null)
            ->addOption('offset', null, InputOption::VALUE_OPTIONAL, 'the starting offset', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $groupManager = $container->get('bns.group_manager');
        $logger = $container->get('logger');
        \Propel::disableInstancePooling();

        $groupOne = GroupQuery::create()->findPk(1);

        $classroomType = GroupTypeQuery::create()->filterByType('CLASSROOM')->findOne();

        $groupManager->setGroup($groupOne);
        $groups = $groupManager->getSubgroups(false);

        $limit = $input->getOption('limit');
        $offset = $input->getOption('offset');

        $count = count($groups);
        $output->writeln(sprintf('Nb child groups : <info>%s</info> groups', $count));

        $i = 0;
        $success = 0;
        $error = 0;
        foreach ($groups as $group) {
            if ($limit && ($i > $limit)) {
                break;
            }
            $i++;
            if ($i < $offset) {
                continue;
            }

            if (!(isset($group['group_type_id']) && $group['group_type_id'] == $classroomType->getId())) {
                continue;
            }
            $output->write(sprintf('fix classroom %s <info>#%s</info> :', $group['label'], $group['id']));
            try {
                $classroom = GroupQuery::create()
                    ->joinWith('GroupType')
                    ->findPk($group['id'])
                ;
                $groupManager->createSchool($classroom, $groupOne);
                $output->writeln('<info>OK</info>');
                $success++;
            } catch (\Exception $e) {
                $msg = sprintf('<error>Error</error> %s', $e->getMessage());
                $output->writeln($msg);
                $logger->error('[fix:classroom-without-school] ' . $msg, $group);
                $error++;
            }
        }

        $output->writeln(sprintf('Done : <info>%s success</info> <error>%s error</error>', $success, $error));
    }
}
