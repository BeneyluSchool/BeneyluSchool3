<?php

namespace BNS\App\CoreBundle\Utils;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * Date : 20 juin 2012
 */
class Crypt
{
	/**
	 * @param string $string The string to encrypt
	 * @param string $secret Le mot de passe : un mot de passe par défaut est uilisé pour les actions non critiques
	 * 
	 * @return string An encrypted string
	 */
	public static function encrypt($string, $secret = 'pixel_secret_cookers')
	{
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($secret), $string, MCRYPT_MODE_CBC, md5(md5($secret))));
	}
	
	/**
	 * @param string $string The string to decrypt
	 * 
	 * @return string A decrypted string
	 */
	public static function decrypt($string, $secret = 'pixel_secret_cookers')
	{
		return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($secret), base64_decode($string), MCRYPT_MODE_CBC, md5(md5($secret))), "\0");
	}
	
	/**
	 * You should use this method only if you want to match two hashed strings
	 * 
	 * @param string $string The string to encode. This string can't be decrypted !
	 * 
	 * @return string A decrypted string
	 */
	public static function encode($string, $secret = 'pixel_secret_cookers')
	{
		return md5(base64_encode(pack("H*", sha1(utf8_encode($string)))) . $secret);
	}
	
	    
    /*
     * Génère un identifiant unique de 10 caractères
     * TODO EMO
     */
    public static function generateUID()
    {
        return strtoupper(bin2hex(openssl_random_pseudo_bytes(5)));
    }

}