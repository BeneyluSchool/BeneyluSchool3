<?php
namespace BNS\App\MigrationBundle\Command;

use BNS\App\MiniSiteBundle\Model\MiniSitePageNews;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePagePeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePageQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageText;
use BNS\App\MiniSiteBundle\Model\MiniSitePageTextPeer;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use BNS\App\MiniSiteBundle\Model\MiniSiteWidget;
use BNS\App\MiniSiteBundle\Model\MiniSiteWidgetExtraProperty;
use BNS\App\MiniSiteBundle\Model\MiniSiteWidgetTemplateQuery;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use BNS\App\CoreBundle\Model\UserPeer;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\PupilParentLinkQuery;

/**
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class MinisiteMigrationCommand extends BaseMigrationCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:minisite')
            ->setDescription('Import des page ecoles iconito')
            ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        \Propel::disableInstancePooling();

        switch ($this->input->getArgument('step')) {
            default:
            case 'minisite':
                $output->writeln('<info>Debut</info> migration des <info>fiches ecole</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'fiche ecole'), $this->importMiniSite()));
                $this->end();
            case 'club':
                $output->writeln('<info>Debut</info> migration des <info>blog de club</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'blog de club'), $this->importMiniSiteClub()));
                $this->end();
            case 'clubPage':
                $output->writeln('<info>Debut</info> migration des <info>blog de club page</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'blog de club page'), $this->importMiniSiteClubPage()));
                $this->end();

        }
    }


    protected function importMiniSite()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $userManager = $this->get('bns.user_manager');

        $con = \Propel::getMasterConnection('import');

        //query get school
        $sql = 'SELECT * FROM module_fiches_ecoles';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si la fiche est déjà importé
            if ($this->isImported($row['id'], 'module_fiches_ecoles')) {
                $this->log('fiche deja importee : ' . $row['id']);
                $ignore++;
                continue;
            }

            if (!($importedSchool = $this->getImported($row['id'], 'kernel_bu_ecole'))) {
                $this->log('Erreur ecole non importe ' . $row['id']);
                if(in_array($row['id'], array(661057,661031,12,660946,661049,661057)))
                {
                    var_dump($row['id']);
                }
                $error++;
                continue;
            }

            if (!($teacherId = $this->getTeacherId($importedSchool, $importedSchool->getBnsKey()))) {
                $this->log('Erreur ecole non importe, aucun enseignant' . $row['id']);
                $error++;
                continue;
            }

            $miniSite = MiniSiteQuery::create()->filterByGroupId($importedSchool->getBnsKey())->findOneOrCreate();

            if ($miniSite->isNew()) {
                $miniSite->setTitle('Fiche école');
                $miniSite->save();
            }
            if (!empty($row['photo'])) {
                $this->createJoinResource($miniSite->getId(), 'MiniSite', 'logo', $teacherId, $row['photo']);
            }

            $homePage = MiniSitePageQuery::create()
                ->filterByIsHome(true)
                ->filterByMiniSite($miniSite)
                ->findOneOrCreate();

            if ($homePage->isNew()) {
                $homePage->setTitle('Accueil');
                $homePage->setIsActivated(true);
                $homePage->save();
            }

            $miniSitePageText = $homePage->getMiniSitePageText();

            if (!$miniSitePageText) {
                $miniSitePageText = new MiniSitePageText();
                $homePage->setMiniSitePageText($miniSitePageText);
            }
            $miniSitePageText->setPublishedTitle('Accueil');
            $miniSitePageText->setAuthorId($teacherId);
            $miniSitePageText->setLastModificationAuthorId($teacherId);
            $miniSitePageText->setStatus(MiniSitePageTextPeer::STATUS_PUBLISHED);
            $miniSitePageText->setPublishedAt('now');

            $content = '<h3>Horaires</h3>' . PHP_EOL;
            $content .= $this->purifyHtml($row['horaires']) . PHP_EOL;

            for ($i = 1; $i < 5; $i++) {
                $title = "zone{$i}_titre";
                $text = "zone{$i}_texte";
                if (!empty($row[$title]) || !empty($row[$text])) {
                    $content .= '<h3>' . $row[$title] . '<h3>' . PHP_EOL;
                    $content .= $this->purifyHtml($row[$text]) . PHP_EOL;
                }
            }
            $miniSitePageText->setPublishedContent($content);
            $miniSitePageText->save();

            if ($miniSite) {
                if ($this->saveImported($row['id'], 'module_fiches_ecoles', $miniSite->getId(), 'MiniSite')) {
                    $this->log('import fiche ecole : ' . $row['id']);

                    $success++;
                } else {
                    throw new \Exception('Erreur lors de la sauvegarde de migrationIconito pour la fiche ecole ' . $row['id']);
                }
            } else {
                $this->log('Erreur fiche ecole non importe : ' . $row['id'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();
        unset($userIds);

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importMiniSiteClub()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $sql = "SELECT b.name_blog, k.*, a.*
            FROM `module_blog` b
            INNER JOIN `kernel_mod_enabled` k ON b.id_blog = k.module_id
            INNER JOIN `module_blog_article` a ON a.id_blog = b.id_blog
            WHERE k.module_type = 'MOD_BLOG' AND k.node_type IN ('CLUB')
            ORDER BY k.node_id
            ;";

        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            // vérif si l'article est déjà importé
            if ($this->isImported($row['id_bact'], 'module_blog_article')) {
                $this->log('article deja importee : ' . $row['id_bact']);
                $ignore++;
                continue;
            }

            if (!($importedClub = $this->getImported($row['node_id'], 'module_groupe_groupe'))) {
                $this->log('Erreur club non importe ' . $row['node_id']);
                $error++;
                continue;
            }

            if (!($importedAuthor = $this->getImported($row['author_bact'], 'dbuser'))) {
                $this->log('Erreur  import de l article ' . $row['id_bact'] . ' utilisateur non importe : ' . $row['author_bact']);
                $error++;
                continue;
            }

            $miniSite = MiniSiteQuery::create()->filterByGroupId($importedClub->getBnsKey())->findOneOrCreate();

            if ($miniSite->isNew()) {
                $miniSite->setTitle($row['name_blog']);
                $miniSite->save();
            }

            /** @var $newsPage \BNS\App\MiniSiteBundle\Model\MiniSitePage */
            $newsPage = MiniSitePageQuery::create()
                ->filterByIsHome(false)
                ->filterByTitle($row['name_blog'])
                ->filterByType(MiniSitePagePeer::TYPE_NEWS)
                ->filterByMiniSite($miniSite)
                ->findOneOrCreate();

            if ($newsPage->isNew()) {
                $newsPage->setType(MiniSitePagePeer::TYPE_NEWS);
                $newsPage->setIsActivated(true);
                $newsPage->save();
            }

            $miniSitePageNews = new MiniSitePageNews();
            $miniSitePageNews->setTitle($row['name_bact']);

            $date = $this->parseDateTime($row['date_bact'], $row['time_bact']);
            $miniSitePageNews->setCreatedAt($date);
            if ($row['is_online'] == 1) {
                $miniSitePageNews->setPublishedAt($date);
                $miniSitePageNews->setStatus(MiniSitePageNewsPeer::STATUS_PUBLISHED);
            } else {
                $miniSitePageNews->setStatus(MiniSitePageNewsPeer::STATUS_DRAFT);
            }

            //MAJ pour prendre le résumé en contenu au lieu du vrai contenu si ce dernier est vide
            $tmpContent = $this->purifyHtml($row['content_html_bact']) != "" ? $this->purifyHtml($row['content_html_bact']) : $this->purifyHtml($row['sumary_html_bact']);

            $miniSitePageNews->setContent($tmpContent);
            $miniSitePageNews->setAuthorId($importedAuthor->getBnsKey());

            $miniSitePageNews->setMiniSitePage($newsPage);
            $miniSitePageNews->save();

            if ($miniSitePageNews) {
                if ($this->saveImported($row['id_bact'], 'module_blog_article', $miniSitePageNews->getId(), 'MiniSitePageNews')) {
                    $this->log('import article club : ' . $row['id_bact']);

                    $success++;
                } else {
                    throw new \Exception('Erreur lors de la sauvegarde de migrationIconito pour article club ' . $row['id']);
                }
            } else {
                $this->log('Erreur article club non importe : ' . $row['id'] . ' erreur lors de la creation');
                $error++;
            }
        }
        $stmt->closeCursor();

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importMiniSiteClubPage()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = \Propel::getMasterConnection('import');

        $sql = "SELECT b.name_blog, k.*, p.*
            FROM `module_blog` b
            INNER JOIN `kernel_mod_enabled` k ON b.id_blog = k.module_id
            INNER JOIN `module_blog_page` p ON p.id_blog = b.id_blog
            WHERE k.module_type = 'MOD_BLOG' AND k.node_type IN ('CLUB')
            ORDER BY k.node_id
            ;";

        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id_bpge'], 'module_blog_page')) {
                $this->log('Page deja importe ' . $row['id_bpge']);
                $ignore++;
                continue;
            }

            if (!($importedClub = $this->getImported($row['node_id'], 'module_groupe_groupe'))) {
                $this->log('Erreur club non importe ' . $row['node_id']);
                $error++;
                continue;
            }

            if (!($importedAuthor = $this->getImported($row['author_bpge'], 'dbuser'))) {
                $this->log('Erreur import de la page ' . $row['id_bpge'] . ' utilisateur non importe : ' . $row['author_bpge']);
                $error++;
                continue;
            }

            $miniSite = MiniSiteQuery::create()->filterByGroupId($importedClub->getBnsKey())->findOneOrCreate();

            if ($miniSite->isNew()) {
                $miniSite->setTitle('Fiche école');
                $miniSite->save();
            }

            $clubPage = MiniSitePageQuery::create()
                ->filterByIsHome(false)
                ->filterBySlug($row['url_bpge'])
                ->filterByMiniSite($miniSite)
                ->findOneOrCreate();

            if ($clubPage->isNew()) {
                $clubPage->setTitle($row['name_bpge']);
                $clubPage->setType(MiniSitePagePeer::TYPE_TEXT);
                $clubPage->setIsActivated(true);
                $clubPage->save();
            }

            $miniSitePageText = $clubPage->getMiniSitePageText();

            if (!$miniSitePageText) {
                $miniSitePageText = new MiniSitePageText();
                $clubPage->setMiniSitePageText($miniSitePageText);
            }
            $miniSitePageText->setAuthorId($importedAuthor->getBnsKey());
            $miniSitePageText->setLastModificationAuthorId($importedAuthor->getBnsKey());

            $date = $this->parseDateTime($row['date_bpge'], '1337');
            $miniSitePageText->setCreatedAt($date);
            if ($row['is_online'] == 1) {
                $miniSitePageText->setPublishedAt($date);
                $miniSitePageText->setStatus(MiniSitePageTextPeer::STATUS_PUBLISHED);
                $miniSitePageText->setPublishedTitle($row['name_bpge']);
                $miniSitePageText->setPublishedContent($this->purifyHtml($row['content_html_bpge']));
            } else {
                $miniSitePageText->setStatus(MiniSitePageTextPeer::STATUS_DRAFT);
                $miniSitePageText->setDraftTitle($row['name_bpge']);
                $miniSitePageText->setDraftContent($this->purifyHtml($row['content_html_bpge']));
            }
            $miniSitePageText->save();

            $this->saveImported($row['id_bpge'], 'module_blog_page', $miniSitePageText->getPageId(), 'MiniSitePageText');

            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }
}
