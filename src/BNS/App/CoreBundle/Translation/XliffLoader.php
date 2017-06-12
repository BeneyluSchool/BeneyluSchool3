<?php
namespace BNS\App\CoreBundle\Translation;

use JMS\TranslationBundle\Exception\RuntimeException;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Model\FileSource;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Translation\Loader\LoaderInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class XliffLoader implements LoaderInterface
{
    public function load($resource, $locale, $domain = 'messages')
    {
        $previous = libxml_use_internal_errors(true);
        if (false === $doc = simplexml_load_file($resource)) {
            libxml_use_internal_errors($previous);
            $libxmlError = libxml_get_last_error();

            throw new RuntimeException(sprintf('Could not load XML-file "%s": %s', $resource, $libxmlError->message));
        }
        libxml_use_internal_errors($previous);

        $doc->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');
        $doc->registerXPathNamespace('jms', 'urn:jms:translation');

        $hasReferenceFiles = in_array('urn:jms:translation', $doc->getNamespaces(true));

        $catalogue = new MessageCatalogue();
        $catalogue->setLocale($locale);

        foreach ($doc->xpath('//xliff:trans-unit') as $trans) {
            $id = ($resName = (string) $trans->attributes()->resname)
                ? $resName : (string) $trans->source;

            $m = Message::create($id, $domain)
                ->setDesc((string) $trans->source)
                ->setLocaleString((string) $trans->target)
            ;
            $catalogue->add($m);

            if ($hasReferenceFiles) {
                foreach ($trans->xpath('./jms:reference-file') as $file) {
                    $line = (string) $file->attributes()->line;
                    $column = (string) $file->attributes()->column;
                    $m->addSource(new FileSource(
                        (string) $file,
                        $line ? (integer) $line : null,
                        $column ? (integer) $column : null
                    ));
                }
            }

            if ($meaning = (string) $trans->note) {
                $m->setMeaning($meaning);
            }

            if ($trans->target && (!($state = (string) $trans->target->attributes()->state) || 'new' !== $state)) {
                $m->setNew(false);
            }

        }

        return $catalogue;
    }
}
