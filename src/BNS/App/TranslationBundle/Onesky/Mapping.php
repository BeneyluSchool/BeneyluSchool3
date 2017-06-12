<?php
namespace BNS\App\TranslationBundle\Onesky;
class Mapping
{
    /** @var array */
    private $sources = array();
    /** @var array */
    private $locales = array();
    /** @var string */
    private $output;
    /**
     * @param array  $sources
     * @param array  $locales
     * @param string $output
     */
    public function __construct(array $sources, array $locales, $output)
    {
        $this->sources = $sources;
        $this->locales = $locales;
        $this->output  = $output;
    }
    /**
     * @param string $source
     *
     * @return bool
     */
    public function useSource($source)
    {
        return empty($this->sources) || in_array($source, $this->sources);
    }
    /**
     * @param string $locale
     *
     * @return bool
     */
    public function useLocale($locale)
    {
        return empty($this->locales) || in_array($locale, $this->locales);
    }
    /**
     * @param string $source
     * @param string $locale
     *
     * @return string
     */
    public function getOutputFilename($source, $locale, $name)
    {
        $filename = explode('.', pathinfo($source, PATHINFO_FILENAME));
        if($name == 'NotificationBundle'){
            $dirname = $this->getPath($filename[0], $name);
        } else if ($name == 'SecurityBundle') {
            $dirname = 'src/BNS/Central/' . $name . '/Resources/translations/';
        } else {
            $dirname = 'src/BNS/App/' . $name .'/Resources/translations/';
        }
        return strtr($this->output, array(
            '[dirname]'   => $dirname,
            '[filename]'  => $filename[0],
            '[locale]'    => $locale,
            '[extension]' => pathinfo($source, PATHINFO_EXTENSION),
            '[ext]'       => pathinfo($source, PATHINFO_EXTENSION),
        ));
    }

    public function getPath($filename, $name)
    {
        $dirname = 'src/BNS/App/' . $name .'/Resources/translations/';
        if(strpos($filename,'BLOG') !== false){
            $dirname .= 'BlogBundle/';
        } elseif(strpos($filename,'BUILDERS') !== false) {
            $dirname .= 'BuildersBundle/';
        } elseif(strpos($filename,'CALENDAR') !== false) {
            $dirname .= 'CalendarBundle/';
        }elseif(strpos($filename,'FORUM') !== false) {
            $dirname .= 'ForumBundle/';
        }elseif(strpos($filename,'HELLO_WORLD') !== false) {
            $dirname .= 'HelloWorldBundle/';
        }elseif(strpos($filename,'LIAISON_BOOK') !== false) {
            $dirname .= 'LiaisonBookBundle/';
        }elseif(strpos($filename,'MEDIA_LIBRARY') !== false) {
            $dirname .= 'MediaLibraryBundle/';
        }elseif(strpos($filename,'MESSAGING') !== false) {
            $dirname .= 'MessagingBundle/';
        }elseif(strpos($filename,'MINISITE') !== false) {
            $dirname .= 'MiniSiteBundle/';
        }elseif(strpos($filename,'PROFILE') !== false) {
            $dirname .= 'ProfileBundle/';
        }elseif(strpos($filename,'WORKSHOP') !== false) {
            $dirname .= 'WorkshopBundle/';
        }
        return $dirname;
    }
}
