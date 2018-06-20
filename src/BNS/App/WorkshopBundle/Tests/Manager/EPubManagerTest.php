<?php

namespace BNS\App\WorkshopBundle\Tests\Manager;

use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\FileSystem\BNSFileSystemManager;
use BNS\App\WorkshopBundle\Manager\EPubManager;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;

class EPubManagerTest extends AppWebTestCase
{
    public function testEPub()
    {
        $bookManager = $this->getEPubManager();
        $document = WorkshopDocumentQuery::create()
            ->useWorkshopContentQuery()
            ->filterByAuthorId(8)
            ->endUse()
            ->findOne();

        $book = $bookManager->create($document);
        $this->assertInstanceOf('PHPePub\Core\EPub', $book);
        $this->assertEquals('Mr Test', $book->getAuthor());
    }

    public function testAddChapter()
    {
        $bookManager = $this->getEPubManager();
        $document = WorkshopDocumentQuery::create()
            ->findOne();
        $book = $bookManager->create($document);
        $chapter = '<?xml version="1.0" encoding="utf-8"?>'.
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">'.
            '<html xmlns="http://www.w3.org/1999/xhtml">'.
            '<head>'.
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'.
            '<title>Test Book</title>'.
            '<style type="text/css"> .test-style{ width: 100%}</style>'.
            '</head>'.
            '<body class="layout-column navbar-shown ng-app workshop">'.
            '<div class="workshop-document-view">'.
            '<div class="page-read">'.
            'Mon chapitre'.
            '</div>'.
            '</div>'.
            '</body>'.
            '</html>';
        $bookManager->addChapter($book, $chapter, 1);
        $bookManager->addChapter($book, $chapter, 2);
        $this->assertEquals(2, $book->getChapterCount());
        $this->assertEquals(3, count($book->getFileList()));
    }

    protected function getEPubManager()
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();
        $adapter = $container->get("bns.local.adapter");

        $fileSystem = new BNSFileSystemManager($container, $adapter);

        return new EPubManager($fileSystem);
    }

}
