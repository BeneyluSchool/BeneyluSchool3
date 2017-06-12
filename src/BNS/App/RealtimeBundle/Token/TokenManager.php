<?php

namespace BNS\App\RealtimeBundle\Token;

use Namshi\JOSE\JWS;

/**
 * Class TokenManager
 *
 * @package BNS\App\RealtimeBundle\Token
 */
class TokenManager
{

    /**
     * Path to the private key file
     *
     * @var string
     */
    private $keyPath;

    /**
     * Passphrase of the private key
     *
     * @var string
     */
    private $keyPassphrase;

    public function __construct($keyPath, $keyPassphrase = '')
    {
        $this->setKeyPath($keyPath);
        $this->keyPassphrase = $keyPassphrase;
    }

    public function getToken($object = null)
    {
        // TODO clean data after refactor

        if ($object instanceof \BaseObject) {
            // propel objects
            if (!method_exists($object, 'getId')) {
                throw new \InvalidArgumentException("Cannot generate token for object without Id field");
            }
            $id = $object->getId();

            $data = array(
                'id' => $id,
                'class' => get_class($object),
                'room' => get_class($object).'('.$id.')',
            );
        } else if (is_string($object)) {
            $data = array(
                'room' => $object,
            );
        } else {
            // random stuff
            $data = array(
                'room' => '',
                'id' => '0',
                'class' => 'none'
            );
        }

        $jws  = new JWS('RS256');
        $jws->setPayload(array_merge(array(
            'exp' => time() + 24 * 60 * 60,
        ), $data));

        $privateKey = openssl_pkey_get_private($this->keyPath, $this->keyPassphrase);
        $jws->sign($privateKey);

        return $jws->getTokenString();
    }

    public function setKeyPath($path)
    {
        if (substr($path, 0, 7) !== 'file://') {
            $path = 'file://' . $path;
        }

        $this->keyPath = $path;
    }

}
