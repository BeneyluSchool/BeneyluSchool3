<?php

namespace BNS\App\CoreBundle\Utils;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class StringUtil
{

    public static function stripAccents($str, $charset='utf-8')
    {
        $str = htmlentities($str, ENT_NOQUOTES, $charset);

        $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // pour les ligatures e.g. '&oelig;'
        $str = preg_replace('#&[^;]+;#', '', $str); // supprime les autres caractères

        return $str;
    }

	public static function filterString($text, $for_file = false)
	{
		$bad = array(
			'À','à','Á','á','Â','â','Ã','ã','Ä','ä','Å','å','Ă','ă','Ą','ą',
			'Ć','ć','Č','č','Ç','ç',
			'Ď','ď','Đ','đ',
			'È','è','É','é','Ê','ê','Ë','ë','Ě','ě','Ę','ę',
			'Ğ','ğ',
			'Ì','ì','Í','í','Î','î','Ï','ï',
			'Ĺ','ĺ','Ľ','ľ','Ł','ł',
			'Ñ','ñ','Ň','ň','Ń','ń',
			'Ò','ò','Ó','ó','Ô','ô','Õ','õ','Ö','ö','Ø','ø','ő',
			'Ř','ř','Ŕ','ŕ',
			'Š','š','Ş','ş','Ś','ś',
			'Ť','ť','Ť','ť','Ţ','ţ',
			'Ù','ù','Ú','ú','Û','û','Ü','ü','Ů','ů',
			'Ÿ','ÿ','ý','Ý',
			'Ž','ž','Ź','ź','Ż','ż',
			'Þ','þ','Ð','ð','ß','Œ','œ','Æ','æ','µ',
			' ','-','\'','"',
			'&','<','>',
		);

		$good = array(
			'A','a','A','a','A','a','A','a','Ae','ae','A','a','A','a','A','a',
			'C','c','C','c','C','c',
			'D','d','D','d',
			'E','e','E','e','E','e','E','e','E','e','E','e',
			'G','g',
			'I','i','I','i','I','i','I','i',
			'L','l','L','l','L','l',
			'N','n','N','n','N','n',
			'O','o','O','o','O','o','O','o','Oe','oe','O','o','o',
			'R','r','R','r',
			'S','s','S','s','S','s',
			'T','t','T','t','T','t',
			'U','u','U','u','U','u','Ue','ue','U','u',
			'Y','y','Y','y',
			'Z','z','Z','z','Z','z',
			'TH','th','DH','dh','ss','OE','oe','AE','ae','u',
			'','','',''
		);

		// convert special characters

		return $for_file ? preg_replace('([^a-zA-Z0-9.])', '', str_replace($bad, $good, $text)) : preg_replace('([^a-zA-Z0-9])', '', str_replace($bad, $good, $text));
	}
	
	/** 
	 * Word-sensitive substring function with html tags awareness
	 * 
	 * @param string $text The text to cut
	 * @param int $len The maximum length of the cut string
	 * @param bool $force Whether to force truncation if no whitespace found. Defaults to false
	 * @returns string 
	**/ 
	public static function substrws($text, $len = 180, $force = false)
	{ 
		$text = strip_tags($text);
		
		if ((strlen($text) > $len)) { 
			$whitespaceposition = strpos($text, " ", $len) - 1; 

			if ($whitespaceposition > 0) {
				$text = mb_substr($text, 0, ($whitespaceposition + 1));
			} else if ($force) {
				$text = mb_substr($text, 0, $len);
			}
		} 

		return $text; 
	}
	
	/**
	 * Conversion d'un string de date d'un format j/m/a à a-m-j et vice versa
	 * 
	 * @param $pDate
	 * 
	 * @return mixed
	 */
	public static function convertDateFormat($pDate)
	{
		// TODO critical fix : if year is at the begining
		if (count($date = explode('-', $pDate)) == 3) {
			return $date[2] . '-' . $date[1] . '-' . $date[0];
		}
		elseif (count($date = explode('/', $pDate)) == 3) {
			return $date[2] . '-' . $date[1] . '-' . $date[0];
		}
		
		return false;
	}
	
	/**
	 * @param array   $array
	 * @param boolean $showKey
	 * 
	 * @return string
	 */
	public static function arrayToString($array, $showKey = false)
	{
		$string = '';
		if (count($array) > 0) {
			foreach ($array as $key => $item) {
				if (!$showKey) {
					$string .= $item . ', ';
				}
				else {
					$string .= '[' . $key . '] => ' . $item . ', ';
				}
			}
			
			$string = substr($string, 0, -2);
		}
		
		return $string;
	}

	/**
	 * Generate random string
	 * 
	 * @param int $length 10 by default
	 * @param boolean $useSpecialChars False by default
	 *
	 * @return string
	 */
	public static function generateRandomString($length = 10, $useSpecialChars = false)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		if ($useSpecialChars) {
			$characters .= '-_.+=';
		}
		
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
		}
		
		return $randomString;
	}
}
