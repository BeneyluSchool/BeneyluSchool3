<?php

namespace BNS\App\WorkshopBundle\Manager;

use BNS\App\MediaLibraryBundle\FileSystem\BNSFileSystemManager;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use Gaufrette\Adapter;
use Symfony\Component\HttpFoundation\Response;
use PHPePub\Core\EPub;

class EPubManager
{
    /** @var  Adapter */
    protected $adapter;

    public function __construct(BNSFileSystemManager $filesystemManager)
    {
        $this->adapter = $filesystemManager->getAdapter();
    }

    public function create(WorkshopDocument $workshopDocument)
    {
        $author = $workshopDocument->getAuthor();
        $title = $workshopDocument->getMedia()->getLabel();
        $description = $workshopDocument->getMedia()->getDescription();
        $book = new EPub(EPub::BOOK_VERSION_EPUB3);
        $book->setTitle($title);
        $book->setAuthor($author->getFullName(), 'firstname');
        $book->setDescription($description);
        $book->setIdentifier('beneylu-'.$workshopDocument->getId(), EPub::IDENTIFIER_URI);

        return $book;
    }

    public function addChapter(EPub $book, $content, $index)
    {
        $fileName = "Chapter".$index.".html";
        $content = $this->processResources($content, $book);
        $book->addChapter(
            "Page " . $index,
            $fileName,
            $content,
            false
        );
    }

    public function processResources($chapter, EPub $book) {

        $html = new \simple_html_dom($chapter);
        $crawler = $html->find('img[data-slug="*"],source[data-slug="*"],a[data-slug="*"],link[rel="stylesheet"], style[type="text/css"]');
        $internalPath = "media/";
        foreach ($crawler as $domEl) {


            if (null != $domEl->attr &&
                isset($domEl->attr['data-slug']) &&
                isset($domEl->attr['data-id'])
            ) {
                $dataId = $domEl->attr['data-id'];
                $resource = MediaQuery::create()
                    ->filterByStatusDeletion(MediaManager::STATUS_ACTIVE)
                    ->findOneById($dataId);

                if($resource) {
                    $extension = pathinfo($resource->getFilename(), PATHINFO_EXTENSION);
                    $filename = md5($resource->getId());
                    $path = $internalPath . $filename . '.' . $extension;
                    $mimetype = $resource->getFileMimeType();
                    $key = $resource->getFilePath();
                    if (!$resContent = $this->adapter->read($key)) {
                        return false;
                    }
                    if ($resContent) {
                        $book->addFile($path, $filename, $resContent, $mimetype);

                        if(isset($domEl->attr['data-slug']) && $domEl->tag == 'a') {
                            $domEl->attr['href'] = $path;
                        } else {
                            $domEl->attr['src'] = $path;
                        }
                    }
                }
            }
//            if(null != $domEl->attr && isset($domEl->attr['rel'])) {
//                $filename = md5($domEl->attr['href']);
//                $path = $internalPath . $filename . '.css';
//                $book->addFile($path, $filename, file_get_contents($domEl->attr['href']), 'css');
//                $domEl->attr['href'] = $path;
//            }
            if(null != $domEl->attr && isset($domEl->attr['type']) && $domEl->attr['type'] == 'text/css' && !isset($domEl->attr['rel'])) {
                $filename = md5('customstyle');
                $path = $internalPath . $filename . '.css';
                $book->addFile($path, $filename, $domEl->innertext, 'css');
                $domEl->outertext = '<link rel="stylesheet" type="text/css" href="'.$path.'" />';
            }

        }

        $html = $html->save();
        return $html;
    }

    public function download(EPub $book)
    {
        $book->sendBook($book->getTitle().'.epub');
        return new Response('ok');
    }

}
