<?php

namespace BNS\App\MediaLibraryBundle\Command;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\MediaLibraryBundle\Adapter\LazyOpenCloud;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Gaufrette\Adapter;
use Predis\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FixSpotCopyCommand extends ContainerAwareCommand
{

    /** @var  OutputInterface */
    protected $output;

    protected function configure()
    {
        $this
            ->setName('media-library:fix-spot-copy')
            ->setDescription('Fix old copies of spot resources')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'The number of item per batch', 10000)
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', '6000M'); // YOLO

        $container = $this->getContainer();
        BNSAccess::setContainer($container);

        // init services
        $this->output = $output;
        $userManager = $container->get('bns.user_manager');
        $paasManager = $container->get('bns.paas_manager');

        $output->writeln('BENEYLU SCHOOL - Media Transfer');

        $limit = (int)$input->getOption('limit');

        \Propel::disableInstancePooling();

        $query = MediaQuery::create();
        $query->filterByStatusDeletion([1, 0]);
        $query->setFormatter('PropelOnDemandFormatter');
        $query->where($query->getModelAliasOrName().'.FromPaasId = '.$query->getModelAliasOrName().'.CopyFromId');
        $query->limit($limit);
        $copiedMedias = $query->find();
        if (!count($copiedMedias)) {
            return;
        }
        $output->writeln(sprintf('%s medias to fix', count($copiedMedias)));
        $notFixed = [];

        /** @var Media $copiedMedia */
        foreach ($copiedMedias as $copiedMedia) {
            $user = $copiedMedia->getUserRelatedByUserId();
            $userManager->setUser($user);

            $token = new OAuthToken(['OSEF'], array('ROLE_USER'));
            $token->setUser($user);
            $container->get('security.token_storage')->setToken($token);

            /** @var Media $media */
            foreach ($paasManager->getMediaLibraryResources($user) as $media) {
                if ($media->getExternalId() == $copiedMedia->getFromPaasId()) {
                    $copiedMedia->setExternalSource($media->getExternalSource());
                    $copiedMedia->setExternalId($media->getExternalId());
                    $copiedMedia->setExternalData($media->getExternalData());
                    $copiedMedia->setCopyFromId($media->getId());
                    $copiedMedia->setExpiresAt($media->getExpiresAt());
                    $copiedMedia->save();
                    $output->writeln('Fixed media '.$copiedMedia->getId());

                    continue 2;
                }
            }
            $output->writeln('Could not fix media '.$copiedMedia->getId());
            $notFixed[] = $copiedMedia->getId();
        }

        if (count($notFixed)) {
            $output->writeln(sprintf('Could not fix %s medias: %s', count($notFixed), var_export($notFixed, true)));
        } else {
            $output->writeln('Finished :)');
        }
    }

}
