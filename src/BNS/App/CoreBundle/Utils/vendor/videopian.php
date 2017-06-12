<?php
/**
 * Videopian
 * get everything about a video
 * 
 * -------------------------------------------------------------------
 * NEW BSD LICENSE
 * 
 * Copyright (C) 2009-2010 Upian.com and contributors
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 *
 * - Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * - Neither the name of Upian.com nor the names of its
 *   contributors may be used to endorse or promote products derived from this
 *   software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * -------------------------------------------------------------------
 * 
 * @author Denis Hovart <denis@upian.com>
 * @author Hans Lemuet <hans@upian.com>
 * @license http://opensource.org/licenses/bsd-license.php New BSD license
 * @version 0.2
 */

class Videopian_Exception extends Exception {
    # ================================================================================
    # Let's define our error messages
    # You can translate / adapt them to your project

    const GENERIC_ERROR         = 'Error while getting the video data.';
    const SERVICE_NOT_SUPPORTED = 'Please make sure the service you are trying to use is supported by Videopian';
    const API_KEY_NEEDED        = 'You need to specify an API key to grab video information from this service';
    const NOT_A_VIDEO           = 'The media you are trying to get is not a video';
    const URL_UNAVAILABLE       = 'The video you are trying to get is unavailable';
    const INFO_FILE_UNAVAILABLE = 'The information file for the video you are trying to get is unavailable';
    const WRONG_OEMBED_FORMAT   = 'Allowed formats for oEmbed are xml and json';
    const NO_HTML_PARSER        = 'You need to include simple_html_dom first to enable support of this service in Videopian';
}

class Videopian {

    # ================================================================================
    # Specify here the API keys for the services you want to use.
    # You'll need to request one for each.

    const VEOH_API_KEY      = '';
    const FLICKR_API_KEY    = '';
    const SEVENLOAD_API_KEY = '';
    const VIDDLER_API_KEY   = '';
    const REVVER_LOGIN      = '';
    const REVVER_PASSWORD   = '';


    # ================================================================================
    # Do not change anything under this line

    private static $url;
    private static $service;
    private static $id;
    private static $video;
    private static $oembed;
    private static $format;
    private static $max_width;
    private static $max_height;
    private static $html_parser;

	
	/*Fonction ajoutée Par Eymeric */
	public static function getSite(){
		return self::$service;
	}
	
	
    # ================================================================================
    # Process the URL to extract the service and the video id
    private static function processUrl() {

        self::$url = preg_replace('#\#.*$#', '', trim(self::$url));

        if(substr(self::$url,0,7) != 'http://' && substr(self::$url,0,8) != 'https://')
        {
            self::$url = 'https://' . self::$url;
        }

        $services_regexp = array(
            '#atom\.com.*/funny_videos/(?P<id>[^/]*)#i'                => 'atom',
            '#blip\.tv.*/file/(?P<id>[0-9]*)#i'                        => 'blip',
            '#collegehumor\.com.*/video:(?P<id>[0-9]*)#i'              => 'collegehumor',
            '#dailymotion\.com.*/(?:video|swf)/(?P<id>[^_]*)#i'        => 'dailymotion',
            '#flickr\.com.*/photos/[a-zA-Z0-9]*/(?P<id>[^/]*)#'        => 'flickr',
            '#video\.google\.[a-z]{0,5}/.*[\?&]docid=(?P<id>[^&]*)#i'  => 'googlevideo',
            '#metacafe\.com/watch/(?P<id>.[^/]*)#i'                    => 'metacafe',
            '#myspace\.com/.*[\?&]videoid=(?P<id>[^&]*)#i'             => 'myspace',
            '#revver\.com/video/(?P<id>[^/]*)#i'                       => 'revver',
            '#sevenload.com/.*/(?:videos|episodes)/(?P<id>[^-]*)#i'    => 'sevenload',
            '#veoh\.com/.*/(?P<id>[^?&]*)/?#i'                         => 'veoh',
            '#viddler\.com/explore/.*/videos/(?P<id>[0-9]*)/?#i'       => 'viddler',
            '#vimeo\.com\/(?P<id>[0-9]*)[\/\?]?#i'                     => 'vimeo',
            '#^(?:https?://)?(?:www\.)?(?:youtu\.be/|youtube\.com(?:/embed/|/v/|/watch\?v=|/watch\?.+&v=))([\w-]{11})(?:.+)?$#x'   => 'youtube'
        );

        foreach ($services_regexp as $pattern => $service) {
            if (preg_match($pattern, self::$url, $matches)) {
                self::$service = $service;
				//Trick Eymeric : Changement du pattern pour youtube récupéré dans une autre classe / adaptation
				if($service == "youtube"){
					self::$id = $matches[1];
				}else{
					self::$id = $matches['id'];
				}
            }
        }
    }

    # ======================================================================================================
    # Check the availability of an URL, throwing an exception if the resource is unavailable for some reason
    # (a bit tricky but working nontheless)
    private static function checkAvailability($url) {

        $headers = @get_headers($url, 1);
        if(!$headers) throw new Videopian_Exception(Videopian_Exception::INFO_FILE_UNAVAILABLE);
        for($i = 0; $i < 20; $i++) {
            if(array_key_exists($i, $headers)) $http_status = $headers[$i];
            else break; 
        }
        $http_status_code = intval(substr($http_status, 9, 3));
        if($http_status_code > 300) throw new Videopian_Exception(Videopian_Exception::INFO_FILE_UNAVAILABLE . " ($http_status)");
        return true;
    }

    # ================================================================================
    # Fetch and return the video data
    public static function get($url, $oembed = false, $format = 'xml', $max_width = 640, $max_height = 385) {

        self::$url = $url;
        self::$oembed = $oembed;
        if($format != 'xml' && $format != 'json') throw new Videopian_Exception(Videopian_Exception::WRONG_OEMBED_FORMAT);
        else self::$format = $format;
        self::$max_width = $max_width;
        self::$max_height = $max_height;

        // Detecting PHP Simple HTML DOM Parser
        if (class_exists('simple_html_dom')) self::$html_parser = new simple_html_dom();

        self::processUrl();

        self::$video = new stdClass;
        self::$video->url = self::$url;
        self::$video->site = self::$service;

        if(null === self::$service) return self::getPageMetadata();

        $method = sprintf('get%s', ucfirst(self::$service));
		
        if (!is_callable(array(__CLASS__, $method))) {
            throw new Videopian_Exception(Videopian_Exception::SERVICE_NOT_SUPPORTED);
        }
		
        return call_user_func(array(__CLASS__, $method));

    }

    # --------------------------------------------------------------------------------
    # Check if the <head> section of the page countains the information of the video 
    public static function getPageMetadata() {

        if (null === self::$html_parser) throw new Videopian_Exception(Videopian_Exception::NO_HTML_PARSER);
        
        self::$html_parser->load_file(self::$url);
        
        if(self::$oembed) {
            switch(self::$format) {
                case 'xml' :
                $oembed_url_query = self::$html_parser->find('link[type="text/xml+oembed"]', 0);
                if($oembed_url_query) $oembed_url = $oembed_url_query->href;
                else break;
                if(self::checkAvailability($oembed_url)) return @file_get_contents($oembed_url);
                break;
        
                case 'json' :
                $oembed_url_query = self::$html_parser->find('link[type="text/json+oembed"]', 0);
                if($oembed_url_query)  $oembed_url = $oembed_url_query->href;
                else break;
                if(self::checkAvailability($oembed_url)) return @file_get_contents($oembed_url);
                break;
            }
        }
        
        // we're checking if there's a <link rel="video_src" /> on the page we loaded; otherwise we throw an exception
        // http://wiki.developers.facebook.com/index.php/Facebook_Share/Specifying_Meta_Tags
        $player_url_query = self::$html_parser->find('link[rel="video_src"]', 0);
        self::$video->player_url = $player_url_query ? $player_url_query->href : null;
        if(null === self::$video->player_url) throw new Videopian_Exception(Videopian_Exception::NOT_A_VIDEO);
        
        # Title
        $title_query = self::$html_parser->find('meta[name="title"]', 0);
        self::$video->title = $title_query ? $title_query->content : null;
        
        # Description
        $description_query = self::$html_parser->find('meta[name="description"]', 0);
        self::$video->description = $description_query ? $description_query->content : null;
        
        # Dimensions
        $width_query = self::$html_parser->find('meta[name="video_width"]', 0);
        $height_query = self::$html_parser->find('meta[name="video_height"]', 0);
        self::$video->width = $width_query ? intval($width_query->content) : null;
        self::$video->height = $height_query ? intval($height_query->content) : null;
        
        # Thumbnails
        $thumbnail_query = self::$html_parser->find('link[rel="image_src"]', 0);
        if($thumbnail_query) {
            $thumbnail = new stdClass;
            $thumbnail->url = strval($thumbnail_query->href);
            list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
            self::$video->thumbnails[] = $thumbnail;
        }
        return self::$oembed ? self::generateOEmbed() : self::$video;
    }


    public static function getAtom() {
        
        # since there is no API for AtomFilms, we have to parse the (pretty rich) page metadata
        if (null === self::$html_parser) throw new Videopian_Exception(Videopian_Exception::NO_HTML_PARSER);
        self::$html_parser->load_file(self::$url);
        
        # Title
        $title_query = self::$html_parser->find('meta[name="title"]', 0);
        self::$video->title = $title_query ? $title_query->content : null;

        # Description
        $description_query = self::$html_parser->find('meta[name="description"]', 0);
        self::$video->description = $description_query ? $description_query->content : null;

        # Tags
        $tags_query = self::$html_parser->find('meta[name="keywords"]', 0);
        if($tags_query) {
            $tags = explode(',', $tags_query->content);
            foreach($tags as $t) self::$video->tags[] = trim($t);
        }

        # Duration
        $duration_query = self::$html_parser->find('meta[name="duration"]', 0);
        $duration_raw = $duration_query ? $duration_query->content : null;
        preg_match('#(?:(?:(?P<hr>[0-9]*):)?(?P<min>[0-5]?[0-9]):)?(?P<sec>[0-5]?[0-9])#', $duration_raw, $matches);
        $hours = $matches['hr'] ? intval($matches['hr']) : 0;
        $minutes = $matches['min'] ? intval($matches['min']) : 0;
        $seconds = $matches['sec'] ? intval($matches['sec']) : 0;
        self::$video->duration = ($hours * 60 * 60) + ($minutes * 60) + $seconds;

        # Dimensions
        $width_query = self::$html_parser->find('meta[name="video_width"]', 0);
        self::$video->width = $width_query ? $width_query->content : null;
        $height_query = self::$html_parser->find('meta[name="video_height"]', 0);
        self::$video->height_query = $height_query ? $height_query->content : null;

        # Author & author URL
        $author_query = self::$html_parser->find('meta[name="created_by"]', 0);
        self::$video->author = $author_query ? $author_query->content : null;
        $author_url_query = self::$html_parser->find('meta[name="created_by_url"]', 0);
        self::$video->author_url = $author_url_query ? $author_url_query->content : null;

        # Publication date
        $date_published_query = self::$html_parser->find('meta[name="date_added"]', 0);
        self::$video->date_published = $date_published_query ? new DateTime(date(DATE_RSS, strtotime(strval($date_published_query->content)))) : null;

        # Thumbnails
        $thumbnail_query = self::$html_parser->find('meta[name="thumbnail_url"]', 0);
        if($thumbnail_query) {
            $thumbnail = new stdClass;
            $thumbnail->url = $thumbnail_query->content;
            list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
            self::$video->thumbnails[] = $thumbnail;
        }

        # Player URL
        $player_url_query = self::$html_parser->find('link[rel="video_src"]', 0);
        self::$video->player_url = $player_url_query ? $player_url_query->href : null;

        return self::$oembed ? self::generateOEmbed() : self::$video;
    }

    # --------------------------------------------------------------------------------
    public static function getBlip() {

        if(self::$oembed) {
            $oembed_url = 'http://blip.tv/oembed/?url='.urlencode(self::$url).'&format='.self::$format;
            if(self::checkAvailability($oembed_url)) return @file_get_contents($oembed_url);
        }

        # XML data URL
        $file_data = "http://blip.tv/file/".self::$id."?skin=rss";
        self::checkAvailability($file_data);
        self::$video->xml_url = $file_data;
        
        # XML
        $xml = new SimpleXMLElement(file_get_contents($file_data));
        
        # Title
        $title_query = $xml->xpath('/rss/channel/item/title');
        self::$video->title = $title_query ? strval($title_query[0]) : null;
        
        # Description
        $description_query = $xml->xpath('/rss/channel/item/blip:puredescription');
        self::$video->description = $description_query ? strval(trim($description_query[0])) : null;
        
        # Tags
        $tags_query = $xml->xpath('/rss/channel/item/media:keywords');
        self::$video->tags = $tags_query ? explode(', ',strval(trim($tags_query[0]))) : null;
        
        # Duration
        $duration_query = $xml->xpath('/rss/channel/item/blip:runtime');
        self::$video->duration = $duration_query ? intval($duration_query[0]) : null;
        
        # Dimensions
        $dimensions_query = $xml->xpath('/rss/channel/item/media:group/media:content[@type="video/x-flv"]');
        $dimensions_query = $dimensions_query[0]->attributes();
        self::$video->height = $dimensions_query ? intval($dimensions_query['height']) : null;
        self::$video->width = $dimensions_query ? intval($dimensions_query['width']) : null;
        
        # Author & author URL
        $author_query = $xml->xpath('/rss/channel/item/blip:user');
        self::$video->author = $author_query ? strval($author_query[0]) : null;
        $author_safe_query = $xml->xpath('/rss/channel/item/blip:safeusername');
        self::$video->author_url = 'http://'.strval($author_safe_query[0]).'.blip.tv';
        
        # Publication date
        $date_published_query = $xml->xpath('/rss/channel/item/blip:datestamp');
        self::$video->date_published = $date_published_query ? new DateTime($date_published_query[0]) : null;
        
        # Last update date
        self::$video->date_updated = null;
        
        # Thumbnails
        $thumbnail_small_query = $xml->xpath('/rss/channel/item/blip:smallThumbnail');
        if($thumbnail_small_query) {
            $thumbnail = new stdClass;
            $thumbnail->url = strval($thumbnail_small_query[0]);
            list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
            self::$video->thumbnails[] = $thumbnail;
        }
        $thumbnail_query = $xml->xpath('/rss/channel/item/media:thumbnail/@url');
        if($thumbnail_query) {
            $thumbnail = new stdClass;
            $thumbnail->url = strval($thumbnail_query[0]);
            list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
            self::$video->thumbnails[] = $thumbnail;
        }
        
        # Player URL
        $player_url_query = $xml->xpath('/rss/channel/item/blip:embedUrl');
        self::$video->player_url = $player_url_query ? strval($player_url_query[0]) : null;
        
        # FLV file URL
        $flv_url_query = $xml->xpath('/rss/channel/item/media:group/media:content[@type="video/x-flv"]/@url');
        self::$video->files['video/x-flv'] = $flv_url_query ? strval($flv_url_query[0]) : null;
        
        # MOV file URL
        $mov_url_query = $xml->xpath('/rss/channel/item/media:group/media:content[@type="video/quicktime"]/@url');
        self::$video->files['video/quicktime'] = $mov_url_query ? strval($mov_url_query[0]) : null;
        
        return self::$video;
    }
        

    # --------------------------------------------------------------------------------
    public static function getCollegehumor() {

        # XML data URL
        $file_data = 'http://www.collegehumor.com/moogaloop/video:'.self::$id;
        self::checkAvailability($file_data);
        self::$video->xml_url = $file_data;
        
        # XML
        $xml = new SimpleXMLElement(file_get_contents($file_data));
        
        # Title
        $title_query = $xml->xpath('/videoplayer/video/caption');
        self::$video->title = $title_query ? strval($title_query[0]) : false;
        
        # Description
        $description_query = $xml->xpath('/videoplayer/video/description');
        self::$video->description = $description_query ? strval(trim($description_query[0])) : false;
        
        # Tags
        $tags_query = $xml->xpath('/videoplayer/video/tags');
        if($tags_query) {
            $tags = explode(',', strval($tags_query[0]));
            foreach($tags as $t) self::$video->tags[] = trim($t);
        }
        
        # Author & author URL
        self::$video->author = null;
        self::$video->author_url = null;
        
        # Publication date
        self::$video->date_published = null;
        
        # Last update date
        self::$video->date_updated = null;
        
        # Duration
        $duration_query = $xml->xpath('/videoplayer/video/duration');
        self::$video->duration = $duration_query ? intval($duration_query[0]) : false;
        
        # Thumbnails
        $thumbnails_query = $xml->xpath('/videoplayer/video/thumbnail');
        $thumbnail = new stdClass;
        $thumbnail->url = strval($thumbnails_query[0]);
        list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
        self::$video->thumbnails[] = $thumbnail;
        
        # Player URL
        self::$video->player_url = 'http://www.collegehumor.com/moogaloop/moogaloop.swf?clip_id='.self::$id;
        
        # FLV file URL
        $flv_url_query = $xml->xpath('/videoplayer/video/file');
        self::$video->files['video/x-flv'] = $flv_url_query ? strval($flv_url_query[0]) : null;
        
        return self::$oembed ? self::generateOEmbed() : self::$video;
    }

        
    # --------------------------------------------------------------------------------
    public static function getDailymotion() {
        
        if(self::$oembed) {
            $oembed_url = 'http://www.dailymotion.com/api/oembed/?url='.urlencode(self::$url).'&format='.self::$format;
            if(self::checkAvailability($oembed_url)) return @file_get_contents($oembed_url);
        }

        # XML data URL
        $file_data = 'http://www.dailymotion.com/rss/video/'.self::$id;
        self::checkAvailability($file_data);
        self::$video->xml_url = $file_data;
        
        # XML
        $xml = new SimpleXMLElement(file_get_contents($file_data));
        
        # Title
        $title_query = $xml->xpath('/rss/channel/item/title');
        self::$video->title = $title_query ? strval($title_query[0]) : null;
        
        # Description
        $description_query = $xml->xpath('/rss/channel/item/itunes:summary');
        self::$video->description = $description_query ? strval(trim($description_query[0])) : null;
        
        # Tags
        $tags_query = $xml->xpath('/rss/channel/item/itunes:keywords');
        if($tags_query) {
            $tags = explode(',', strval($tags_query[0]));
            foreach($tags as $t) self::$video->tags[] = trim($t);
        }
        
        # Duration
        $duration_query = $xml->xpath('/rss/channel/item/media:group/media:content/@duration');
        self::$video->duration = $duration_query ? intval($duration_query[0]) : null;
        
        # Dimensions
        $dimensions_query = $xml->xpath('/rss/channel/item/media:player');
        $dimensions_query = $dimensions_query[0]->attributes();
        self::$video->height = $dimensions_query ? intval($dimensions_query['height']) : null;
        self::$video->width = $dimensions_query ? intval($dimensions_query['width']) : null;
        
        # Author & author URL
        $author_query = $xml->xpath('/rss/channel/item/dm:author');
        self::$video->author = $author_query ? strval($author_query[0]) : null;
        self::$video->author_url = 'http://www.dailymotion.com/'.self::$video->author;
        
        # Publication date
        $date_published_query = $xml->xpath('/rss/channel/item/pubDate');
        self::$video->date_published = $date_published_query ? new DateTime($date_published_query[0]) : null;
        
        # Last update date
        self::$video->date_updated = null;
        
        # Thumbnails
        $thumbnail = new stdClass;
        $thumbnail->url = 'http://www.dailymotion.com/thumbnail/320x240/video/'.self::$id;
        $thumbnail->width = 320;
        $thumbnail->height = 240;
        self::$video->thumbnails[] = $thumbnail;

        $thumbnail = new stdClass;
        $thumbnail->url = 'http://www.dailymotion.com/thumbnail/160x120/video/'.self::$id;
        $thumbnail->width = 160;
        $thumbnail->height = 120;
        self::$video->thumbnails[] = $thumbnail;

        $thumbnail = new stdClass;
        $thumbnail->url = 'http://www.dailymotion.com/thumbnail/80x60/video/'.self::$id;
        $thumbnail->width = 80;
        $thumbnail->height = 60;
        self::$video->thumbnails[] = $thumbnail;

        # Player URL
        self::$video->player_url = 'http://www.dailymotion.com/swf/'.self::$id;
        
        # FLV file URL
        $flv_url_query = $xml->xpath('/rss/channel/item/media:group/media:content[@type="video/x-flv"]/@url');
        self::$video->files['video/x-flv'] = $flv_url_query ? strval($flv_url_query[0]) : null;
        
        # MP4 file URL
        // TODO: Récupération de l'URL du fichier mp4
        //$mp4_query = $xml->xpath('/rss/channel/item/media:group/media:content[@type="video/mp4"]/@url');
        //self::$mp4 = $mp4_query ? $mp4_query[0] : null;
        //Trick Eymeric pour récupération de l'ID de la vidéo
		self::$video->id = self::$id;
		
        return self::$video;
    }        

    # --------------------------------------------------------------------------------
    public static function getFlickr() {
        
        if(self::$oembed) {
            $oembed_url = 'http://www.flickr.com/services/oembed/?url='.urlencode(self::$url).'&format='.self::$format;
            if(self::checkAvailability($oembed_url)) $oembed = @file_get_contents($oembed_url);
            /*
                $xml = new SimpleXMLElement($oembed);
                # Media type check
                $type_query = $xml->xpath('/oembed/type');
                if($type_query[0] != 'video') throw new Videopian_Exception(Videopian_Exception::NOT_A_VIDEO);
            */
            return $oembed;
        }

        # API key check
        if (self::FLICKR_API_KEY == '') throw new Videopian_Exception(Videopian_Exception::API_KEY_NEEDED);
        
        # XML data URL
        $file_data = 'http://api.flickr.com/services/rest/?method=flickr.photos.getInfo&api_key=' . self::FLICKR_API_KEY . '&photo_id=' . self::$id;
        self::checkAvailability($file_data);
        self::$video->xml_url = $file_data;
        
        # XML
        $xml = new SimpleXMLElement(file_get_contents($file_data));
        
        # Media type check
        $media_query = $xml->xpath('/rsp/photo/@media');
        if($media_query[0] != 'video') throw new Videopian_Exception(Videopian_Exception::NOT_A_VIDEO);
        
        # Title
        $title_query = $xml->xpath('/rsp/photo/title');
        self::$video->title = $title_query ? strval($title_query[0]) : null;
        
        # Description
        $description_query = $xml->xpath('/rsp/photo/description');
        self::$video->description = empty($description_query) ? strval(trim($description_query[0])) : null;
        
        # Tags
        $tags_query = $xml->xpath('/rsp/photo/tags/tag');
        $tags = array();
        foreach ($tags_query as $tag_query) {
            $tag = (array) $tag_query;
            $tags[] = $tag[0];
        }
        self::$video->tags = $tags_query ? $tags : null;
        
        # Duration
        $duration_query = $xml->xpath('/rsp/photo/video/@duration');
        self::$video->duration = empty($duration_query) ? intval($duration_query[0]) : null;
        
        # Author & author URL
        $author_query = $xml->xpath('/rsp/photo/owner/@username');
        self::$video->author = $author_query ? strval($author_query[0]) : null;
        $author_id_query = $xml->xpath('/rsp/photo/owner/@nsid');
        self::$video->author_url = $author_id_query ? 'http://www.flickr.com/photos/'.strval($author_query[0]) : null;
        
        # Publication date
        $date_published_query = $xml->xpath('/rsp/photo/dates/@posted');
        self::$video->date_published = $date_published_query ? new DateTime(date(DATE_RSS, intval($date_published_query[0]))) : null;
        
        # Last update date
        $date_updated_query = $xml->xpath('/rsp/photo/dates/@lastupdate');
        self::$video->date_updated = $date_updated_query ? new DateTime(date(DATE_RSS, intval($date_updated_query[0]))) : null;
        
        # XML for files data URL
        $file_sizes_data = 'http://api.flickr.com/services/rest/?method=flickr.photos.getSizes&api_key=' . self::FLICKR_API_KEY . '&photo_id=' . self::$id;
        
        # XML
        $xml_sizes = new SimpleXMLElement(file_get_contents($file_sizes_data));

        # Thumbnails
        $photo_url_query = $xml_sizes->xpath('/rsp/sizes/size[@media="photo"]');
        foreach ($photo_url_query as $p) {
            $thumbnail = new stdClass;
            $thumbnail->url = strval($p['source']);
            $thumbnail->width = intval($p['width']);
            $thumbnail->height = intval($p['height']);
            self::$video->thumbnails[] = $thumbnail;
        }
    
        # Player & files URL
        $files_url_query = $xml_sizes->xpath('/rsp/sizes/size[@media="video"]');
        foreach ($files_url_query as $p) {
            switch (strval($p['label'])) {
                case 'Video Player': self::$video->player_url = $files_url_query ? strval($p['source']) : null; break;
                case 'Site MP4': self::$video->files['video/mp4'] = $files_url_query ? strval($p['source']) : null; break;
            }
        }
        
        return self::$video;
    }
        
        
    # --------------------------------------------------------------------------------
    public static function getGooglevideo() {

        # XML data URL
        $file_data = 'http://video.google.com/videofeed?docid='.self::$id;
        self::checkAvailability($file_data);
        self::$video->xml_url = $file_data;
        
        # XML
        $xml = new SimpleXMLElement(utf8_encode(file_get_contents($file_data)));
        $xml->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');
        
        # Title
        $title_query = $xml->xpath('/rss/channel/item/title');
        self::$video->title = $title_query ? strval($title_query[0]) : null;
        
        # Description
        $description_query = $xml->xpath('/rss/channel/item/media:group/media:description');
        self::$video->description = $description_query ? strval(trim($description_query[0])) : null;
        
        # Tags
        self::$video->tags = null;
        
        # Duration
        $duration_query = $xml->xpath('/rss/channel/item/media:group/media:content/@duration');
        self::$video->duration = $duration_query ? intval($duration_query[0]) : null;
        
        # Author & author URL
        // TODO: WTF?
        // $author_query = $xml->xpath('/rss/channel/item/author');
        // self::$video->author = $author_query ? strval($author_query[0]) : false;
        self::$video->author = null;
        self::$video->author_url = null;
        
        # Publication date
        $date_published_query = $xml->xpath('/rss/channel/item/pubDate');
        self::$video->date_published = $date_published_query ? new DateTime($date_published_query[0]) : null;
        
        # Last update date
        self::$video->date_updated = null;
        
        # Thumbnails
        $thumbnails_query = $xml->xpath('/rss/channel/item/media:group/media:thumbnail');
        $thumbnails_query = $thumbnails_query[0]->attributes();
        $thumbnail = new stdClass;
        $thumbnail->url = strval(preg_replace('#&amp;#', '&', $thumbnails_query['url']));
        $thumbnail->width = intval($thumbnails_query['width']);
        $thumbnail->height = intval($thumbnails_query['height']);
        self::$video->thumbnails[] = $thumbnail;
        
        # Player URL
        $player_url_query = $xml->xpath('/rss/channel/item/media:group/media:content[@type="application/x-shockwave-flash"]/@url');
        self::$video->player_url = $player_url_query ? strval($player_url_query[0]) : null;
        
        # AVI file URL
        $avi_url_query = $xml->xpath('/rss/channel/item/media:group/media:content[@type="video/x-msvideo"]/@url');
        self::$video->files['video/x-msvideo'] = $avi_url_query ? preg_replace('#&amp;#', '&', $avi_url_query[0]) : null;
        
        # FLV file URL
        $flv_url_query = $xml->xpath('/rss/channel/item/media:group/media:content[@type="video/x-flv"]/@url');
        self::$video->files['video/x-flv'] = $flv_url_query ? strval($flv_url_query[0]) : null;
        
        # MP4 file URL
        $mp4_url_query = $xml->xpath('/rss/channel/item/media:group/media:content[@type="video/mp4"]/@url');
        self::$video->files['video/mp4'] = $mp4_url_query ? preg_replace('#&amp;#', '&', $mp4_url_query[0]) : null;
        
        return self::$oembed ? self::generateOEmbed() : self::$video;
    }
        
        
    # --------------------------------------------------------------------------------
    public static function getMetacafe() {

        # XML data URL
        $file_data = "http://www.metacafe.com/api/item/".self::$id;
        self::checkAvailability($file_data);
        self::$video->xml_url = $file_data;
        
        # XML
        $xml = new SimpleXMLElement(file_get_contents($file_data));
        
        # Title
        $title_query = $xml->xpath('/rss/channel/item/title');
        self::$video->title = $title_query ? strval($title_query[0]) : null;
        
        # Description
        $description_query = $xml->xpath('/rss/channel/item/media:description');
        self::$video->description = $description_query ? strval($description_query[0]) : null;
        
        # Tags
        $tags_query = $xml->xpath('/rss/channel/item/media:keywords');
        if($tags_query) {
            $tags = explode(',', strval($tags_query[0]));
            foreach($tags as $t) self::$video->tags[] = trim($t);
        }
        
        # Duration
        self::$video->duration = null;
        
        # Author & author URL
        $author_query = $xml->xpath('/rss/channel/item/author');
        self::$video->author = $author_query ? strval($author_query[0]) : null;
        self::$video->author_url = "http://www.metacafe.com/".self::$video->author;
        
        # Publication date
        $date_published_query = $xml->xpath('/rss/channel/item/pubDate');
        self::$video->date_published = $date_published_query ? new DateTime($date_published_query[0]) : null;
        
        # Last update date
        self::$video->date_updated = null;
        
        # Thumbnails
        $thumbnails_query = $xml->xpath('/rss/channel/item/media:thumbnail/@url');
        $thumbnail = new stdClass;
        $thumbnail->url = strval($thumbnails_query[0]);
        list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
        self::$video->thumbnails[] = $thumbnail;
        
        # Player URL
        $player_url_query = $xml->xpath('/rss/channel/item/media:player/@url');
        self::$video->player_url = $player_url_query ? substr(str_replace('watch','fplayer',strval($player_url_query[0])),0,-1).'.swf' : null;
        
        # Files URL
        self::$video->files = array();
        
        return self::$oembed ? self::generateOembed() : self::$video;
    }
        
        
    # --------------------------------------------------------------------------------
    public static function getMyspace() {
        
        # XML data URL
        $file_data = "http://mediaservices.myspace.com/services/rss.ashx?type=video&videoID=".self::$id;
        self::checkAvailability($file_data);
        self::$video->xml_url = $file_data;
        
        # XML
        $xml = new SimpleXMLElement(file_get_contents($file_data));
        
        # Title
        $title_query = $xml->xpath('/rss/channel/item/title');
        self::$video->title = $title_query ? strval($title_query[0]) : null;

        # Description
        $description_query = $xml->xpath('/rss/channel/item/myspace:videodescription');
        self::$video->description = $description_query ? strval($description_query[0]) : null;
        
        # Tags
        self::$video->tags = null;
        
        # Duration
        $duration_query = $xml->xpath('/rss/channel/item/media:content[@type="video/x-flv"]/@duration');
        self::$video->duration = $duration_query ? intval($duration_query[0]) : null;
        
        # Author & author URL
        $author_query = $xml->xpath('/rss/channel/item/myspace:artistName');
        self::$video->author = $author_query ? strval($author_query[0]) : null;
        $author_url_query = $xml->xpath('/rss/channel/item/myspace:vanityURL');
        self::$video->author_url = $author_url_query ? preg_replace('#&amp;#','&', strval($author_url_query[0])) : null;
        
        # Publication date
        $date_published_query = $xml->xpath('/rss/channel/item/pubDate');
        self::$video->date_published = $date_published_query ? new DateTime($date_published_query[0]) : null;
        
        # Last update date
        self::$video->date_updated = null;
        
        # Thumbnails
        $thumbnails_query = $xml->xpath('/rss/channel/item/media:thumbnail/@url');
        $thumbnail = new stdClass;
        $thumbnail->url = strval($thumbnails_query[0]);
        list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
        self::$video->thumbnails[] = $thumbnail;
        $thumbnails_query = $xml->xpath('/rss/channel/item/media:still/@url');
        $thumbnail = new stdClass;
        $thumbnail->url = strval($thumbnails_query[0]);
        list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
        self::$video->thumbnails[] = $thumbnail;
        
        # Player URL
        self::$video->player_url = "http://lads.myspace.com/videos/vplayer.swf?m=" . self::$id;
        
        return self::$oembed ? self::generateOembed() : self::$video;
    }
        
        
    # --------------------------------------------------------------------------------
    public static function getRevver() {
        
        # Account check
        # if (self::REVVER_LOGIN == '' || self::REVVER_PASSWORD == '') throw new Videopian_Exception(Videopian_Exception::API_KEY_NEEDED);
        
        throw new Videopian_Exception(Videopian_Exception::SERVICE_NOT_SUPPORTED);
        
        return self::$oembed ? self::generateOembed() : self::$video;
    }
        
        
    # --------------------------------------------------------------------------------
    public static function getVeoh() {
        
        # API key check
        if (self::VEOH_API_KEY == '') throw new Videopian_Exception(Videopian_Exception::API_KEY_NEEDED);
        
        # XML data URL
        $file_data = "http://www.veoh.com/rest/v2/execute.xml?method=veoh.video.findByPermalink&permalink=" . self::$id . "&apiKey=" . self::VEOH_API_KEY;
        self::checkAvailability($file_data);
        self::$video->xml_url = $file_data;
        
        # XML
        $xml = new SimpleXMLElement(file_get_contents($file_data));
        
        # Title
        $title_query = $xml->xpath('/rsp/videoList/video/@title');
        self::$video->title = $title_query ? strval($title_query[0]) : null;
        
        # Description
        $description_query = $xml->xpath('/rsp/videoList/video/@description');
        self::$video->description = $description_query ? strval($description_query[0]) : null;
        
        # Tags
        $tags_query = $xml->xpath('/rsp/videoList/video/tagList/tag/@tagName');
        foreach($tags_query as $tag) self::$video->tags[] = strval($tag[0]);
        
        # Duration
        $duration_query = $xml->xpath('/rsp/videoList/video/@length');
        $duration_raw = $duration_query ? strval($duration_query[0]) : null;
        preg_match('#(?:(?P<hr>[0-9]*) hr )?(?:(?P<min>[0-5]?[0-9]) min )?(?P<sec>[0-5]?[0-9]) sec#', $duration_raw, $matches);
        $hours = $matches['hr'] ? intval($matches['hr']) : 0;
        $minutes = $matches['min'] ? intval($matches['min']) : 0;
        $seconds = $matches['sec'] ? intval($matches['sec']) : 0;
        self::$video->duration = ($hours * 60 * 60) + ($minutes * 60) + $seconds;
        
        # Author & author URL
        $author_query = $xml->xpath('/rsp/videoList/video/@username');
        self::$video->author = $author_query ? strval($author_query[0]) : null;
        self::$video->author_url = "http://www.veoh.com/users/".self::$video->author;
        
        # Publication date
        $date_published_query = $xml->xpath('/rsp/videoList/video/@dateAdded');
        self::$video->date_published = $date_published_query ? new DateTime($date_published_query[0]) : null;
        
        # Last update date
        self::$video->date_updated = null;
        
        # Thumbnails
        $thumbnail_medres_query = $xml->xpath('/rsp/videoList/video/@medResImage');
        $thumbnail = new stdClass;
        $thumbnail->url = strval($thumbnail_medres_query[0]);
        list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
        self::$video->thumbnails[] = $thumbnail;
        $thumbnail_highres_query = $xml->xpath('/rsp/videoList/video/@highResImage');
        $thumbnail = new stdClass;
        $thumbnail->url = strval($thumbnail_highres_query[0]);
        list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
        self::$video->thumbnails[] = $thumbnail;
        
        # Player URL
        self::$video->player_url = "http://www.veoh.com/veohplayer.swf?permalinkId=" . self::$id;
        
        # FLV file URL
        $flv_url_query = $xml->xpath('/rsp/videoList/video/@previewUrl');
        self::$video->files['video/x-flv'] = $flv_url_query ? strval($flv_url_query[0]) : null;
        
        return self::$oembed ? self::generateOembed() : self::$video;
    }
        
        
    # --------------------------------------------------------------------------------
    public static function getViddler() {
        
        if(self::$oembed) {
            $oembed_url = 'http://lab.viddler.com/services/oembed/?url='.urlencode(self::$url).'&format='.self::$format;
            if(self::checkAvailability($oembed_url)) return @file_get_contents($oembed_url);
        }

        # API key check
        # if (self::VIDDLER_API_KEY == '') throw new Videopian_Exception(Videopian_Exception::API_KEY_NEEDED);
        
        throw new Videopian_Exception(Videopian_Exception::SERVICE_NOT_SUPPORTED);
        
        return self::$video;
    }
        
        
    # --------------------------------------------------------------------------------
    public static function getVimeo() {
			
	//ATTENTION : API intégralement mise à jour pour passage à la V2 + XML
        if(self::$oembed) {
            $oembed_url = 'http://www.vimeo.com/api/oembed.'.self::$format.'?url='.urlencode(self::$url);
            if(self::checkAvailability($oembed_url)) return @file_get_contents($oembed_url);
        }
        
        # PHP serialized data URL
        $file_data = 'http://vimeo.com/api/v2/video/'.self::$id.'.xml';
        
        # Data
        $xml = new SimpleXMLElement(file_get_contents($file_data));
        $xml = $xml->video;
        # Title
        self::$video->title = $xml->title;
        
        # Description
        self::$video->description = strip_tags($xml->description);
        
        /*# Tags
        self::$video->tags = explode(', ',$data[0]['tags']);
        */
        # Duration
        self::$video->duration = $xml->duration;
        
        # Author & author URL
        self::$video->author = $xml->user_name;
        self::$video->author_url = $xml->user_url;
        
        # Publication date
        self::$video->date_published = new DateTime($xml->upload_date);

        # Last update date
        self::$video->date_updated = null;
        
        # Thumbnails
        $thumbnail = new stdClass;
        $thumbnail->url = $xml->thumbnail_small;
        list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
        self::$video->thumbnails[] = $thumbnail;
        $thumbnail = new stdClass;
        $thumbnail->url = $xml->thumbnail_medium;
        list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
        self::$video->thumbnails[] = $thumbnail;
        $thumbnail = new stdClass;
        $thumbnail->url = $xml->thumbnail_large;
        list($thumbnail->width, $thumbnail->height) = getimagesize($thumbnail->url);
        self::$video->thumbnails[] = $thumbnail;
        
        # Player URL
        self::$video->player_url = 'http://vimeo.com/moogaloop.swf?clip_id='.self::$id;
        
        # Files URL
        self::$video->files = array();
		
		//Trick Eymeric pour récupération de l'ID de la vidéo
		self::$video->id = self::$id;
        
        return self::$video;
    }
        
        
    # --------------------------------------------------------------------------------
    public static function getSevenload() {
        
        # API key check
        # if (self::SEVENLOAD_API_KEY == '') throw new Videopian_Exception(Videopian_Exception::API_KEY_NEEDED);
        
        throw new Videopian_Exception(Videopian_Exception::SERVICE_NOT_SUPPORTED);
        
        return self::$oembed ? self::generateOembed() : self::$video;
    }
        
        
    # --------------------------------------------------------------------------------
    public static function getYoutube() {
        if(self::$oembed) {
            $oembed_url = 'https://www.youtube.com/oembed?url='.urlencode(self::$url).'&format='.self::$format;
            if(self::checkAvailability($oembed_url)) return @file_get_contents($oembed_url);
        }

        // fallback to oembed, since YouTube API V2 is now disabled
        $oembed_url = 'https://www.youtube.com/oembed?url='.urlencode(self::$url).'&format=json';
        if(self::checkAvailability($oembed_url)) {
            $data = json_decode(@file_get_contents($oembed_url));

            $data->description = '';
            $data->tags = array();
            $data->duration = false;
            $data->author = $data->author_name;
            $data->date_published = false;
            $data->date_updated = false;

            $thumbnail = new stdClass;
            $thumbnail->url = $data->thumbnail_url;
            $thumbnail->width = $data->thumbnail_width;
            $thumbnail->height = $data->thumbnail_height;
            $data->thumbnails = array($thumbnail);

            $data->player_url = 'https://www.youtube.com/v/'.self::$id;
            $data->files = array();
            $data->id = self::$id;

            return $data;
        }
        
        # XML data URL
        $file_data = 'http://gdata.youtube.com/feeds/api/videos/'.self::$id;
        
		self::checkAvailability($file_data);
        self::$video->xml_url = $file_data;
        
        # XML
        $xml = new SimpleXMLElement(file_get_contents($file_data));
        $xml->registerXPathNamespace('a', 'http://www.w3.org/2005/Atom');
        $xml->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');
        $xml->registerXPathNamespace('yt', 'http://gdata.youtube.com/schemas/2007');
        
        # Title
        $title_query = $xml->xpath('/a:entry/a:title');
        self::$video->title = $title_query ? strval($title_query[0]) : false;
        
        # Description
        $description_query = $xml->xpath('/a:entry/a:content');
        self::$video->description = $description_query ? strval(trim($description_query[0])) : false;
        
        # Tags
        $tags_query = $xml->xpath('/a:entry/media:group/media:keywords');
        if($tags_query) {
            $tags = explode(',', strval($tags_query[0]));
            foreach($tags as $t) self::$video->tags[] = trim($t);
        }
        
        # Duration
        $duration_query = $xml->xpath('/a:entry/media:group/yt:duration/@seconds');
        self::$video->duration = $duration_query ? intval($duration_query[0]) : false;
        
        # Author & author URL
        $author_query = $xml->xpath('/a:entry/a:author/a:name');
        self::$video->author = $author_query ? strval($author_query[0]) : false;
        self::$video->author_url = 'https://www.youtube.com/'.self::$video->author;
        
        # Publication date
        $date_published_query = $xml->xpath('/a:entry/a:published');
        self::$video->date_published = $date_published_query ? new DateTime($date_published_query[0]) : false;
        
        # Last update date
        $date_updated_query = $xml->xpath('/a:entry/a:updated');
        self::$video->date_updated = $date_updated_query ? new DateTime($date_updated_query[0]) : false;
        
        # Thumbnails
        $thumbnail_query = $xml->xpath('/a:entry/media:group/media:thumbnail');
        foreach ($thumbnail_query as $t) {
            $thumbnail = new stdClass;
            $thumbnail_query = $t->attributes();
            $thumbnail->url = strval($thumbnail_query['url']);
            $thumbnail->width = intval($thumbnail_query['width']);
            $thumbnail->height = intval($thumbnail_query['height']);
            self::$video->thumbnails[] = $thumbnail;
        }
        
        # Player URL
        self::$video->player_url = 'https://www.youtube.com/v/'.self::$id;
        
        # Files URL
        self::$video->files = array();

        $files_data = 'https://www.youtube.com/get_video_info?&video_id='.self::$id;
        $files = file_get_contents($files_data);
        preg_match('#&token=([^&]*)#', $files, $matches);
        $token = $matches[1];

        # FLV file URL
        self::$video->files['video/x-flv'] = 'https://www.youtube.com/get_video?video_id='.self::$id.'&t='.$token;
        self::$video->files['video/mp4'] = 'https://www.youtube.com/get_video?video_id='.self::$id.'&t='.$token.'&fmt=18';
        
		//Trick Eymeric pour récupération de l'ID de la vidéo
		self::$video->id = self::$id;
		
        return self::$video;
    }

    # --------------------------------------------------------------------------------
    public static function generateOEmbed() {

        $player_width  = self::$max_width;
        $player_height = self::$max_height;
        $player_html =  '<object width="' . $player_width . '" height="'. $player_height .'">' .
                        '<param name="movie" value="' . self::$video->player_url . '"></param>' .
                        '<param name="allowFullScreen" value="true"></param>' .
                        '<param name="allowscriptaccess" value="always"></param>' .
                        '<embed src="'. self::$video->player_url .'" type="application/x-shockwave-flash"' .
                        'width="'.  $player_width .'" height="'.  $player_height .'" allowscriptaccess="always"'.
                        'allowfullscreen="true"></embed>' .
                        '</object>';

        switch(self::$format) {
            case 'json':
                $json = '{'.
                        '"version": "1.0",'.
                        '"type": "video",'.
                        '"provider_name": "'. self::$video->site .'",'.
                        '"provider_url": "'. self::$video->url .'",'.
                        '"width": '. $player_width .','.
                        '"height": '. $player_height .','.
                        '"title": "'. htmlentities(self::$video->title) .'",'.
                        '"author_name": "'. htmlentities(self::$video->author) .'",'.
                        '"author_url": "'.self::$video->author_url  .'",'.
                        '"html": "'. addslashes($player_html) .'"'.
                        '}';
                return $json;
                break;

            case 'xml':
                $xml = new SimpleXMLElement('<oembed></oembed>');
                $xml->addChild('version', '1.0');
                $xml->addChild('type', 'video');
                $xml->addChild('provider_name', self::$video->site);
                $xml->addChild('provider_url', self::$video->url);
                $xml->addChild('width', $player_width);
                $xml->addChild('height', $player_height);
                $xml->addChild('title', self::$video->title);
                $xml->addChild('author_name', self::$video->author);
                $xml->addChild('author_url', self::$video->url);
                $xml->addChild('html', htmlentities($player_html));
                return $xml->asXML();
                break;
        }
    }
}

