<?php

namespace BNS\App\CoreBundle\Utils;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class String
{
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
	 * @param text The text to cut 
	 * @param len The maximum length of the cut string 
	 * @returns string 
	**/ 
	public static function substrws($text, $len = 180)
	{ 
		$text = strip_tags($text);
		
		if ((strlen($text) > $len)) { 
			$whitespaceposition = strpos($text, " ", $len) - 1; 

			if ($whitespaceposition > 0) {
				$text = substr($text, 0, ($whitespaceposition + 1)); 
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
		if (count($date = explode('-', $pDate)) == 3) {
			return $date[2] . '/' . $date[1] . '/' . $date[0];
		}
		elseif (count($date = explode('/', $pDate)) == 3) {
			return $date[2] . '-' . $date[1] . '-' . $date[0];
		}
		
		return false;
	}
}