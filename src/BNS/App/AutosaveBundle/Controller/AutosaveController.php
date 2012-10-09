<?php

namespace BNS\App\AutosaveBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Utils\Crypt;

class AutosaveController extends Controller
{
	/**
	 * @Route("/", name="autosave_bundle_save")
	 */
	public function saveAction()
	{
		// On récupère la requête de l'autosave
		$autosaveQuery = $this->getRequest()->get('autosave_query', null);
		if (null == $autosaveQuery) {
			throw new InvalidArgumentException('Autosave query parameter is missing !');
		}
		
		// On décrypte le namespace de la classe
		$autosaveQuery['object_class'] = Crypt::decrypt($autosaveQuery['object_class']);
		
		// On parse les attributs à sauvegarder
		$attributesToSave = $autosaveQuery['attributes_to_save'];
		foreach ($attributesToSave as $attributeName => $value) {
			if (null == $this->getRequest()->get($attributeName, null)) {
				throw new InvalidArgumentException('Missing POST parameter : ' . $attributeName . ' ! Make sure the input exists.');
			}
			
			$attributesToSave[$attributeName] = $this->getRequest()->get($attributeName);
		}
		
		if (isset($autosaveQuery['additionnal_attributes'])) {
			foreach ($autosaveQuery['additionnal_attributes'] as $attributeName => $value) {
				$attributesToSave[$attributeName] = $value;
			}
		}
		
		// L'objet existe déjà en base, on le met à jour
		$primaryKey = $this->getRequest()->get('object_primary_key', null);
		
		if (null != $primaryKey && 'null' != $primaryKey) {
			$primaryKey = Crypt::decrypt($primaryKey);
			$objectClassPeer = $autosaveQuery['object_class'] . 'Query';
			$object = $objectClassPeer::create()->findPk($primaryKey);
			$object->autosave($attributesToSave);
		}
		// L'objet n'existe pas en base, on le créer et on notifie son existance
		else {
			$object = new $autosaveQuery['object_class']();
			$primaryKey = $object->autosave($attributesToSave);
		}
		
		$params = $object->toArray(\BasePeer::TYPE_FIELDNAME);
		$params['object_primary_key'] = Crypt::encrypt($object->getPrimaryKey());
		
		return new Response(json_encode($params));
	}
}