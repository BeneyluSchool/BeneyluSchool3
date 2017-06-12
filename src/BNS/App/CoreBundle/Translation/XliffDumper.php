<?php
namespace BNS\App\CoreBundle\Translation;

use JMS\TranslationBundle\JMSTranslationBundle;
use JMS\TranslationBundle\Model\FileSource;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\Dumper\DumperInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class XliffDumper implements DumperInterface
{
    /**
     * @var string
     */
    private $sourceLanguage = 'en';

    /**
     * @var bool
     */
    private $addDate = true;

    /**
     * @var bool
     */
    private $addReference = false;

    /**
     * @var bool
     */
    private $addReferencePosition = true;

    /**
     * @param $bool
     */
    public function setAddDate($bool)
    {
        $this->addDate = (bool) $bool;
    }

    /**
     * @param $lang
     */
    public function setSourceLanguage($lang)
    {
        $this->sourceLanguage = $lang;
    }

    /**
     * @param $bool
     */
    public function setAddReference($bool)
    {
        $this->addReference = $bool;
    }

    /**
     * @param $bool
     */
    public function setAddReferencePosition($bool)
    {
        $this->addReferencePosition = $bool;
    }

    /**
     * @param MessageCatalogue $catalogue
     * @param MessageCatalogue|string $domain
     * @return string
     */
    public function dump(MessageCatalogue $catalogue, $domain = 'messages')
    {
        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = true;

        $doc->appendChild($root = $doc->createElement('xliff'));
        $root->setAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');
        $root->setAttribute('xmlns:jms', 'urn:jms:translation');
        $root->setAttribute('version', '1.2');

        $root->appendChild($file = $doc->createElement('file'));

        if ($this->addDate) {
            $date = new \DateTime();
            $file->setAttribute('date', $date->format('Y-m-d\TH:i:s\Z'));
        }

        $file->setAttribute('source-language', $this->sourceLanguage);
        $file->setAttribute('target-language', $catalogue->getLocale());
        $file->setAttribute('datatype', 'plaintext');
        $file->setAttribute('original', 'not.available');

        $file->appendChild($header = $doc->createElement('header'));

        $header->appendChild($tool = $doc->createElement('tool'));
        $tool->setAttribute('tool-id', 'JMSTranslationBundle');
        $tool->setAttribute('tool-name', 'JMSTranslationBundle');
        $tool->setAttribute('tool-version', JMSTranslationBundle::VERSION);


        $header->appendChild($note = $doc->createElement('note'));
        $note->appendChild($doc->createTextNode('The source node in most cases contains the sample message as written by the developer. If it looks like a dot-delimitted string such as "form.label.firstname", then the developer has not provided a default message.'));

        $file->appendChild($body = $doc->createElement('body'));

        foreach ($catalogue->getDomain($domain)->all() as $id => $message) {
            if ((empty($id)  && !$message->getSourceString()) || (' ' === $id && ' ' === $message->getSourceString())) {
                //skip empty id or empty message
                continue;
            }
            $body->appendChild($unit = $doc->createElement('trans-unit'));
            $unit->setAttribute('id', hash('sha1', $id));
            $unit->setAttribute('resname', $id);

            $unit->appendChild($source = $doc->createElement('source'));
            if (preg_match('/[<>&]/', $message->getSourceString())) {
                $source->appendChild($doc->createCDATASection($message->getSourceString()));
            } else {
                $source->appendChild($doc->createTextNode($message->getSourceString()));
            }

            $unit->appendChild($target = $doc->createElement('target'));
            if (preg_match('/[<>&]/', $message->getLocaleString())) {
                $target->appendChild($doc->createCDATASection($message->getLocaleString()));
            } else {
                $target->appendChild($doc->createTextNode($message->getLocaleString()));
            }

            if ($message->isNew()) {
                $target->setAttribute('state', 'new');
            }

            if ($this->addReference) {
            // As per the OASIS XLIFF 1.2 non-XLIFF elements must be at the end of the <trans-unit>
            if ($sources = $message->getSources()) {
                foreach ($sources as $source) {
                    if ($source instanceof FileSource) {
                        $unit->appendChild($refFile = $doc->createElement('jms:reference-file', $source->getPath()));

                            if ($this->addReferencePosition) {
                        if ($source->getLine()) {
                            $refFile->setAttribute('line', $source->getLine());
                        }

                        if ($source->getColumn()) {
                            $refFile->setAttribute('column', $source->getColumn());
                        }
                            }

                        continue;
                    }

                    $unit->appendChild($doc->createElementNS('jms:reference', (string) $source));
                }
            }
            }

            if ($meaning = $message->getMeaning()) {
                $unit->appendChild($note = $doc->createElement('note'));
                $note->appendChild($doc->createCDATASection($meaning));
            } else {
                // no meaning append note auto
                $targetMessage = $message->getLocaleString();
                $noteMessage = '';
                $placeHolders = array();
                if (preg_match_all('/(%[a-zA-Z]*%)/', $targetMessage, $matches)) {
                    foreach ($matches[0] as $placeHolder) {
                        if (isset($placeHolder)) {
                            $placeHolders[$placeHolder] = sprintf("%s is a placeholder\n", $placeHolder);
                        }
                    }
                    foreach ($placeHolders as $placeHolder) {
                        $noteMessage .= $placeHolder;
                    }
                }

                if (preg_match('/({[0-9]*}|%count%)/', $targetMessage)) {
                    $noteMessage .= "This text has plural translations based on %count% variable\nhttp://symfony.com/doc/current/components/translation/usage.html#pluralization";
                }

                if (preg_match('/[<>&]/', $targetMessage)) {
                    $noteMessage .= "This text contains HTML tags, please try to keep those tags\n";
                }

                if (!empty($noteMessage)) {
                    $unit->appendChild($note = $doc->createElement('note'));
                    $note->appendChild($doc->createCDATASection($noteMessage));
                }
            }

        }

        return $doc->saveXML();
    }
}
