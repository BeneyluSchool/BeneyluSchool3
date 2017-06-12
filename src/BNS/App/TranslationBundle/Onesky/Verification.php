<?php
namespace BNS\App\TranslationBundle\Onesky;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Output\OutputInterface;


class Verification
{
    /** @var OutputInterface */
    private $output;

    /**
     * @return $this
     */
    public function verification($name, OutputInterface $output, $lang = null)
    {
        $this->output = $output;

        $finder = new Finder();
        $path = 'src/BNS/App/'.$name.'/Resources/translations/';
        $finder->files()->in($path);
        if ($lang) {
            $finder->name('/\.' . preg_quote($lang) .'\./');
        }
        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealpath());
            $xml = simplexml_load_string($content);
            if($xml == false){
                $this->output->writeln('Problème avec le fichier : ' . $file->getfileName());
                continue;
            }
            $this->parse($xml->file->body, $name.' : '.$file->getfileName());
        }
        return $this;
    }

    /**
     * @return $array
     */
    public function xml2array($xmlObject, $out = [])
    {

        foreach($xmlObject->attributes() as $attr => $val){
            $out[$attr] = (string)$val;
        }
        $has_childs = false;
        foreach($xmlObject as $index => $node) {
            $has_childs = true;
            $out[$index][] = $this->xml2array($node);
        }
        if (!$has_childs && $val = (string)$xmlObject){
            $out[] = $val;
        }
        foreach ($out as $key => $vals) {
            if (is_array($vals) && count($vals) === 1 && array_key_exists(0, $vals)){
                $out[$key] = $vals[0];
            }
        }
        return $out;
    }

    public function parse($xml, $name)
    {
        $values = $this->xml2array($xml);
        $values = $values['body'];
        foreach($values as $element){
            foreach($element as $value){
                if(!isset($value['id']) && isset($element['id'])){
                    $value = $element;
                }
                if(!isset($value['id'])){
                    $this->output->writeln($name . ' Erreur : Pas id');
                    continue;
                }
                if(!isset($value['resname']) || empty($value['resname'])){
                    $this->output->writeln($name . ' Erreur : le resname n\'existe pas pour id : ' .  $value['id']);
                } elseif(!preg_match('/^[a-zA-Z0-9_\.]*$/', $value['resname'])){
                    $this->output->writeln(sprintf('%s Erreur : le resname "%s" (%s) est invalide mauvais format ', $name, $value['resname'], $value['id']));
                } elseif(sha1($value['resname']) != $value['id']){
                    $this->output->writeln($name . ' Erreur : le resname ne correspond pas pour id : ' .  $value['id']);
                }
                if(!isset($value['source']) || empty($value['source'][0])){
                    $this->output->writeln($name . ' Erreur de source pour id : ' .  $value['id']);
                }
                if(!isset($value['target']) || empty($value['target'][0])){
                    $this->output->writeln($name . ' Erreur de target pour id : ' .  $value['id']);
                } elseif (isset($value['resname']) && $value['target'][0] === $value['resname']) {
                    $this->output->writeln(sprintf('%s Erreur target "%s" non traduite pour id : %s', $name, $value['target'][0], $value['id']));
                }
                if($value == $element){
                    break;
                }
            }
        }
    }
}
