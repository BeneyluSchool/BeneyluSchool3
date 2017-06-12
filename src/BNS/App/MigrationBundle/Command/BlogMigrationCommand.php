<?php
namespace BNS\App\MigrationBundle\Command;

use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticleCategory;
use BNS\App\CoreBundle\Model\BlogArticleComment;
use BNS\App\CoreBundle\Model\BlogCategory;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\CoreBundle\Model\BlogPeer;
use BNS\App\CoreBundle\Model\BlogQuery;
use PDO;
use PDOStatement;
use Propel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;

/**
 *
 * @author Eymeric Taelman <eymeric.taelman@pixel-cookers.com>
 */
class BlogMigrationCommand extends BaseMigrationCommand
{

    protected function configure()
    {
        parent::configure();
        $this->setName('bns:migration:iconito:blog')
            ->setDescription('Import des blogs iconito')
            ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        switch ($input->getArgument('step')) {
            case 'blog':
                $output->writeln('<info>Debut</info> migration des <info>Blogs</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'blog'), $this->importBlogs()));
                $this->end();
            case 'category':
                $output->writeln('<info>Debut</info> migration des <info>Blog categories</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'categorie'), $this->importCategories()));
                $this->end();
            case 'article':
                $output->writeln('<info>Debut</info> migration des <info>Blog articles</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'article'), $this->importArticles()));
                $this->end();
            case 'comment':
                $output->writeln('<info>Debut</info> migration des <info>Blog commentaires</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'commentaire'), $this->importComments()));
                $this->end();
            case 'link':
                $output->writeln('<info>Debut</info> migration des <info>Blog lien</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'lien'), $this->importLink()));
                $this->end();
            case 'page':
                $output->writeln('<info>Debut</info> migration des <info>Blog page</info> : ' . date('d/m/Y H:i:s'));
                $this->displayResult(array_merge(array('label' => 'page'), $this->importPage()));
                $this->end();
        }
    }

    protected function importBlogs()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = Propel::getMasterConnection('import');

         $sql = "SELECT a.* , k.*
            FROM `module_blog` a INNER JOIN `kernel_mod_enabled` k ON a.id_blog = k.module_id
            WHERE k.module_type = 'MOD_BLOG'
                AND
            k.node_type IN ('BU_CLASSE','BU_ECOLE','BU_VILLE')
            ORDER BY k.node_id
            ;";

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id_blog'], 'module_blog')) {
                $this->log('Blog deja importe ' . $row['id_blog']);
                $ignore++;
                continue;
            }
            $continue = true;

            switch($row['node_type']){
                case "BU_CLASSE":
                    if (!($importedGroup = $this->getImported($row['node_id'], 'kernel_bu_ecole_classe'))) {
                        $this->log('Erreur import blog du groupe ' . $row['id_blog'] . ' classe non importee : ' . $row['node_id']);
                        $error++;
                        $continue = false;
                    }
                break;
                case "BU_ECOLE":
                    if (!($importedGroup = $this->getImported($row['node_id'], 'kernel_bu_ecole'))) {
                        $this->log('Erreur import blog du groupe ' . $row['id_blog'] . ' ecole non importee : ' . $row['node_id']);
                        $error++;
                        $continue = false;
                    }
                break;
                case "BU_VILLE":
                    if (!($importedGroup = $this->getImported($row['node_id'], 'kernel_bu_ville'))) {
                        $this->log('Erreur import blog du groupe ' . $row['id_blog'] . ' ville non importe : ' . $row['node_id']);
                        $error++;
                        $continue = false;
                    }
                break;
                case "CLUB":
                    $continue = false; // club blog imported has minisite
                    if (!($importedGroup = $this->getImported($row['node_id'], 'module_groupe_groupe'))) {
                        $this->log('Erreur import blog du groupe ' . $row['id_blog'] . ' Club non importe : ' . $row['node_id']);
                        $error++;
                        $continue = false;
                    }
                break;
                default:
                    $this->log('Erreur, Type de noeud inconnu : ' . $row['node_type'] );
                    $error++;
                    continue;
                break;
            }

            if(!$continue){
                continue;
            }

            $blog = BlogQuery::create()
                ->filterByGroupId($importedGroup->getBnsKey())
                ->findOne();

            if ($blog) {
                $blog->setTitle($row['name_blog']);
                $blog->save();
            } else {
                BlogPeer::create(array(
                    'label' => $row['name_blog'],
                    'group_id' => $importedGroup->getBnsKey()
                ));

                $blog = BlogQuery::create()
                    ->filterByGroupId($importedGroup->getBnsKey())
                    ->findOne();
            }



            if($blog){
                $this->saveImported($row['id_blog'], 'module_blog', $blog->getId(), 'Blog');

                if (!empty($row['logo_blog'])) {
                    $this->createJoinResource($blog->getId(), 'Blog', 'logo',
                            $this->getTeacherId($this->getImported($row['id_blog'], 'module_blog'), $importedGroup->getBnsKey()), $row['logo_blog'], false);
                }
            }else{
                $this->log("Erreur : pas de blog BNS pour le groupe d'ID : " . $importedGroup->getBnsKey());
                $error++;
                continue;
            }
            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importCategories()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = Propel::getMasterConnection('import');

        $sql = "SELECT *
            FROM `module_blog_articlecategory`
            ;";

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id_bacg'], 'module_blog_articlecategory')) {
                $this->log('Categorie blog deja importee ' . $row['id_bacg']);
                $ignore++;
                continue;
            }

            if (!$importedBlog = $this->getImported($row['id_blog'], 'module_blog')) {
                $this->log('Blog non importe ' . $row['id_blog']);
                $ignore++;
                continue;
            }

            $blog = BlogQuery::create()
                ->filterById($importedBlog->getBnsKey())
                ->findOne();

            if($blog){

                $matiereRoot = BlogCategoryQuery::create()
                    ->filterByBlogId($blog->getId())
                    ->filterByLevel(0)
                    ->findOne();
                if(!$matiereRoot){
                    $matiereRoot = new BlogCategory();
                    $matiereRoot->setTitle("Root category" . $blog->getId());
                    $matiereRoot->setBlogId($blog->getId());
                    $matiereRoot->makeRoot();
                    $matiereRoot->save();
                }

                $category = new BlogCategory();
                $category->setTitle($row['name_bacg']);
                $category->insertAsFirstChildOf($matiereRoot);
                $category->save();

                $this->saveImported($row['id_bacg'], 'module_blog_articlecategory', $category->getId(), 'BlogCategory');

            }else{
                $this->log("Erreur : Le blog n existe pas");
                $error++;
                continue;
            }
            $success++;
        }
        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importArticles()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = Propel::getMasterConnection('import');

        $sql = 'SELECT * FROM `module_blog_article`';

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id_bact'], 'module_blog_article')) {
                $this->log('Article deja importe ' . $row['id_bact']);
                $ignore++;
                continue;
            }

            if (!($importedBlog = $this->getImported($row['id_blog'], 'module_blog'))) {
                $this->log('Erreur import de l article  ' . $row['id_bact'] . ' blog non importe : ' . $row['id_blog']);
                $error++;
                continue;
            }

            if (!($importedAuthor = $this->getImported($row['author_bact'], 'dbuser'))) {
                $this->log('Erreur  import de l article ' . $row['id_bact'] . ' utilisateur non importe : ' . $row['author_bact']);
                $error++;
                continue;
            }

            $article = new BlogArticle();
            $article->setBlogId($importedBlog->getBnsKey());
            $article->setTitle($row['name_bact']);
            
            if(trim($row['content_html_bact']) == "" && trim($row['sumary_html_bact']) != ""){
                $content = $row['sumary_html_bact'];
            }else{
                $content = $row['content_html_bact'];
            }
            
            $article->setContent($this->purifyHtml($content));
            $article->setAuthorId($importedAuthor->getBnsKey());

            $date = substr($row['date_bact'],0,4) . '-' . substr($row['date_bact'],4,2) . '-' . substr($row['date_bact'],6,2);
            $time = empty($row['time_bact'])? '' : substr($row['time_bact'],0,2) . ':' . substr($row['time_bact'],2,2);

            if (!empty($time)) {
                $date .= ' ' . $time;
            }

            try {
                $date = str_replace('o', '0', $date);
                $date = new \DateTime($date);
            } catch (\Exception $e) {
                $date = null;
            }
            $article->setCreatedAt($date);

            if($row['is_online'] == 1){
                $article->setPublishedAt($date);
            }
            $article->setStatus($row['is_online'] ? 'PUBLISHED' : 'DRAFT');
            $article->setIsStar($row['sticky_bact']);
            $article->save(null, true);

            $this->saveImported($row['id_bact'], 'module_blog_article', $article->getId(), 'BlogArticle');

            //Liaison avec les catégories
            $sqlLink = "SELECT *
                FROM `module_blog_article_blogarticlecategory`
                WHERE id_bact = " . $row['id_bact'] . "
            ;";

             /* @var $stmt PDOStatement*/
            $stmtLink = $con->prepare($sqlLink);
            $stmtLink->execute();

            while ($rowLink = $stmtLink->fetch(PDO::FETCH_ASSOC)) {

                if (!($importedCategory = $this->getImported($rowLink['id_bacg'], 'module_blog_articlecategory'))) {
                    $this->log('Erreur import Liaison article / categorie : ' . $rowLink['id_bacg']);
                    $error++;
                    continue;
                }

                $article_category = new BlogArticleCategory();
                $article_category->setArticleId($article->getId());
                $article_category->setCategoryId($importedCategory->getBnsKey());
                $article_category->save();

            }
            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importComments()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = Propel::getMasterConnection('import');

        $sql = 'SELECT * FROM `module_blog_articlecomment` WHERE authorid_bacc IS NOT NULL;';

         /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id_bacc'], 'module_blog_articlecomment')) {
                $this->log('Commentaire deja importe ' . $row['id_bacc']);
                $ignore++;
                continue;
            }

            if (!($importedArticle = $this->getImported($row['id_bact'], 'module_blog_article'))) {
                $this->log('Erreur import du commentaire  ' . $row['id_bacc'] . ' Article non importe : ' . $row['id_bact']);
                $error++;
                continue;
            }

            if (!($importedAuthor = $this->getImported($row['authorid_bacc'], 'dbuser'))) {
                $this->log('Erreur  import du commentaire ' . $row['id_bacc'] . ' utilisateur non importe : ' . $row['authorid_bacc']);
                $error++;
                continue;
            }

            $comment = new BlogArticleComment();
            $comment->setAuthorId($importedAuthor->getBnsKey());
            $date = substr($row['date_bacc'],0,4) . '-' . substr($row['date_bacc'],4,2) . '-' . substr($row['date_bacc'],6,2) . ' ' . substr($row['time_bacc'],0,2) . ':' . substr($row['time_bacc'],2,2);
            $comment->setDate($date);
            $comment->setObjectId($importedArticle->getBnsKey());
            $comment->setContent($this->purifyHtml($row["content_bacc"]));
            if($row['is_online']){
                $comment->setStatus('VALIDATED');
            }else{
                $comment->setStatus('PENDING_VALIDATION');
            }
            $comment->save(null, true);
            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importLink()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $rm = $this->get('bns.resource_manager');
        $rc = $this->get('bns.resource_creator');
        $con = Propel::getMasterConnection('import');
        $sql = 'SELECT * FROM `module_blog_link` l
                INNER JOIN kernel_mod_enabled m ON m.module_id = l.id_blog AND m.module_type = "MOD_BLOG"';
        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total++;
            if ($this->isImported($row['id_blnk'], 'module_blog_link')) {
                $this->log('lien deja importe  ' . $row['id_blnk']);
                $ignore++;
                continue;
            }

            if (!($importedGroup = $this->getImportedGroup($row['node_id'], $row['node_type']))) {
                $this->log('Erreur import du lien  ' . $row['id_blnk'] . ' group non importe : ' . $row['node_id']);
                $error++;
                continue;
            }
            if (!($teacherId = $this->getTeacherId($importedGroup, $importedGroup->getBnsKey()))) {
                $this->log('Erreur import du lien  ' . $row['id_blnk'] . ' aucun enseignant');
                $error++;
                continue;
            }

            $labelGroup = ResourceLabelGroupQuery::create()
                ->filterByGroupId($importedGroup->getBnsKey())
                ->filterByTreeLevel(0)
                ->findOne();

            if ($labelGroup) {
                $datas = array();

                $datas['url'] = $row['url_blnk'];
                $datas['title'] = $row['name_blnk'];
                $datas['description'] = '';
                $datas['destination'] = 'group_' . $importedGroup->getBnsKey() . '_' . $labelGroup->getId();
                $datas['type'] = 'LINK';
                $datas['skip_validation_error'] = true;
                $rm->setUser(UserQuery::create()->findPk($teacherId));
                $rc->createFromUrl($datas);
                try {
                    $object = $rc->getObject();
                    $this->saveImported($row['id_blnk'], 'module_blog_link', $object->getId(), 'Resource');
                    $success++;
                } catch (\Exception $e) {
                    $this->log('Erreur  import du lien ' . $row['id_blnk'] . ' creation de la ressource');
                    $error++;
                    continue;
                }
            } else {
                $this->log('Erreur  import du lien ' . $row['id_blnk']);
                $error++;
                continue;
            }
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }

    protected function importPage()
    {
        $success   = 0;
        $ignore    = 0;
        $error     = 0;
        $total     = 0;

        $con = Propel::getMasterConnection('import');

        $sql = 'SELECT * FROM `module_blog_page`';

        /* @var $stmt PDOStatement*/
        $stmt = $con->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total++;

            if ($this->isImported($row['id_bpge'], 'module_blog_page')) {
                $this->log('Page deja importe ' . $row['id_bpge']);
                $ignore++;
                continue;
            }

            if (!($importedBlog = $this->getImported($row['id_blog'], 'module_blog'))) {
                $this->log('Erreur import de la page ' . $row['id_bpge'] . ' blog non importe : ' . $row['id_blog']);
                $error++;
                continue;
            }

            if (!($importedAuthor = $this->getImported($row['author_bpge'], 'dbuser'))) {
                $this->log('Erreur import de la page ' . $row['id_bpge'] . ' utilisateur non importe : ' . $row['author_bpge']);
                $error++;
                continue;
            }

            $article = new BlogArticle();
            $article->setBlogId($importedBlog->getBnsKey());
            $article->setTitle($row['name_bpge']);
            $article->setContent($this->purifyHtml($row['content_html_bpge']));
            $article->setAuthorId($importedAuthor->getBnsKey());

            $date = substr($row['date_bpge'],0,4) . '-' . substr($row['date_bpge'],4,2) . '-' . substr($row['date_bpge'],6,2) . ' 13:37';
            $article->setCreatedAt($date);

            if ($row['is_online'] == 1) {
                $article->setPublishedAt($date);
            }
            $article->setStatus($row['is_online'] ? 'PUBLISHED' : 'DRAFT');
            $article->setIsStar(true);
            $article->save(null, true);

            $this->saveImported($row['id_bpge'], 'module_blog_page', $article->getId(), 'BlogArticle');

            $success++;
        }

        return array('success' => $success, 'ignore' => $ignore, 'error' => $error, 'total' => $total);
    }
}
