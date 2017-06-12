<?php

namespace BNS\App\CoreBundle\Purifier;

/**
 * Class BNSHTMLPurifier
 *
 * @package BNS\App\CoreBundle\Purifier
 */
class BNSHTMLPurifier extends \HTMLPurifier
{

    public function __construct($config = null)
    {
        parent::__construct($config);

        $mediaElements = ['img', 'source', 'a'];
        $mediaAttrs = ['data-slug', 'data-uid', 'data-id'];

        $tags = [
            "*[style],*[id]",
            "h1,h2,h3,h4,h5,h6,b,i,u,s,ul,ol,li,strong,em,br,p,div,span",
            "a[href|target|class|".implode('|', $mediaAttrs)."]",
            "img[src|height|width|".implode('|', $mediaAttrs)."]",
            "table[border|cellpadding|cellspacing|width|height]",
            "colgroup,col,thead,tbody,tfoot,caption,tr",
            "th[colspan|rowspan],td[colspan|rowspan]",
            "section,article,aside,header,footer,address,hgroup,figure,figcaption",
            "source[src|type|class|".implode('|', $mediaAttrs)."]",
            "video[src|type|preload|autoplay|muted|loop|controls|width|height|poster]",
            "audio[src|type|preload|autoplay|muted|loop|controls]",
            "iframe[src|allowfullscreen|width|height|frameborder]",
        ];

        $this->config->set('HTML.DefinitionID', 'html5');
        $this->config->set('HTML.DefinitionRev', 1);
        $this->config->set('HTML.Allowed', implode(',', $tags));
        $this->config->set('Attr.AllowedFrameTargets', array('_blank'));

        if ($def = $this->config->maybeGetRawHTMLDefinition()) {
            // http://developers.whatwg.org/sections.html
            $def->addElement('section', 'Block', 'Flow', 'Common');
            $def->addElement('nav',     'Block', 'Flow', 'Common');
            $def->addElement('article', 'Block', 'Flow', 'Common');
            $def->addElement('aside',   'Block', 'Flow', 'Common');
            $def->addElement('header',  'Block', 'Flow', 'Common');
            $def->addElement('footer',  'Block', 'Flow', 'Common');
            // Content model actually excludes several tags, not modelled here
            $def->addElement('address', 'Block', 'Flow', 'Common');
            $def->addElement('hgroup', 'Block', 'Required: h1 | h2 | h3 | h4 | h5 | h6', 'Common');
            // http://developers.whatwg.org/grouping-content.html
            $def->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
            $def->addElement('figcaption', 'Inline', 'Flow', 'Common');
            // http://developers.whatwg.org/the-video-element.html#the-video-element
            $def->addElement('video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', array(
                'src' => 'URI',
                'type' => 'Text',
                'width' => 'Length',
                'height' => 'Length',
                'poster' => 'URI',
                'preload' => 'Enum#auto,metadata,none',
                'autoplay' => 'Bool',
                'loop' => 'Bool',
                'muted' => 'Bool',
                'controls' => 'Bool',
            ));
            $def->addElement('audio', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', array(
                'src' => 'URI',
                'type' => 'Text',
                'preload' => 'Enum#auto,metadata,none',
                'autoplay' => 'Bool',
                'loop' => 'Bool',
                'muted' => 'Bool',
                'controls' => 'Bool',
            ));
            $def->addElement('source', 'Block', 'Flow', 'Common', array(
                'src' => 'URI',
                'type' => 'Text',
            ));
            // http://developers.whatwg.org/text-level-semantics.html
            $def->addElement('s',    'Inline', 'Inline', 'Common');
            $def->addElement('var',  'Inline', 'Inline', 'Common');
            $def->addElement('sub',  'Inline', 'Inline', 'Common');
            $def->addElement('sup',  'Inline', 'Inline', 'Common');
            $def->addElement('mark', 'Inline', 'Inline', 'Common');
            $def->addElement('wbr',  'Inline', 'Empty', 'Core');
            // http://developers.whatwg.org/edits.html
            $def->addElement('ins', 'Block', 'Flow', 'Common', array('cite' => 'URI', 'datetime' => 'CDATA'));
            $def->addElement('del', 'Block', 'Flow', 'Common', array('cite' => 'URI', 'datetime' => 'CDATA'));
            // TinyMCE
            $def->addAttribute('img', 'data-mce-src', 'Text');
            $def->addAttribute('img', 'data-mce-json', 'Text');
            // Others
            $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
            $def->addAttribute('table', 'height', 'Text');
            $def->addAttribute('td', 'border', 'Text');
            $def->addAttribute('th', 'border', 'Text');
            $def->addAttribute('tr', 'width', 'Text');
            $def->addAttribute('tr', 'height', 'Text');
            $def->addAttribute('tr', 'border', 'Text');

            // BNS
            foreach ($mediaElements as $tag) {
                foreach ($mediaAttrs as $attr) {
                    $def->addAttribute($tag, $attr, 'Text');
                }
            }

        }
    }

}
