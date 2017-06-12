<?php

namespace BNS\App\TranslationBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class VerificationCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('bns:translation:verification')
            ->setDescription('Verification translations')
            ->addArgument('id', InputArgument::OPTIONAL, 'The OSA id or bundle name to only check this project')
            ->addArgument('lang', InputArgument::OPTIONAL, 'Filter only for lang file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projects = $this->getAllProjects();
        if ($id = $input->getArgument('id')) {
            if (is_numeric($id)) {
                if (isset($projects[$id])) {
                    $projects = [
                        $id => $projects[$id]
                    ];
                } else {
                    $output->writeln(sprintf('<error>Id "%s" invalid look at "%s" file to add the project</error>', $id, __FILE__));
                    exit(1);
                }
            } else {
                if (!preg_match('/Bundle/', $id)) {
                    $id .= 'Bundle';
                }
                $projectIds = array_keys($projects, $id);
                if (count($projectIds) > 0) {
                    $projects = array_intersect_key($projects, array_combine($projectIds, $projectIds));
                } else {
                    $output->writeln(sprintf('<error>Name "%s" invalid look at "%s" file to add the project</error>', $id, __FILE__));
                    exit(1);
                }
            }
        }

        $verification = $this->getContainer()->get('onesky_verification');
        $lang = $input->getArgument('lang');
        foreach ($projects as $id => $project){
            $verification->verification($project, $output, $lang);
            $output->writeln($project ." terminé");
        }
        $output->writeln("Translations verification finish");
    }

    public function getAllProjects(){
        return array(
            '37351' => 'HomeworkBundle',
            '37354' => 'BlogBundle',
            '37357' => 'ClassroomBundle',
            '37360' => 'GPSBundle',
            '37363' => 'LiaisonBookBundle',
            '37366' => 'SearchBundle',
            '37468' => 'ProfileBundle',
            '37540' => 'TeamBundle',
            '37543' => 'SchoolBundle',
            '37546' => 'MiniSiteBundle',
            '38332' => 'MessagingBundle',
            '38488' => 'LunchBundle',
            '39103' => 'CommentBundle',
            '39397' => 'CalendarBundle',
            '40858' => 'ModalBundle',
            '41146' => 'UserBundle',
            '41440' => 'StatisticsBundle',
            '41446' => 'PortalBundle',
            '41449' => 'InfoBundle',
            '42175' => 'MainBundle',
            '42238' => 'GroupBundle',
            '43132' => 'MediaLibraryBundle',
            '43606' => 'CoreBundle',
            //'46805' => 'CoreBundle',
            //'46802' => 'CoreBundle',
            //'46808' => 'CoreBundle',
            //'46838' => 'CoreBundle',
            '46514' => 'DirectoryBundle',
            '39148' => 'NotificationBundle',
            '50545' => 'EventBundle',
            '42328' => 'MailerBundle',
            '46487' => 'UserDirectoryBundle',
//            '46910' => 'SecurityBundle', // auth
            '56815' => 'EventBundle', // space ops
            '62608' => 'WorkshopBundle',
        );
    }
}
