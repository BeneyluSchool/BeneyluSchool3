<?php

namespace BNS\App\CoreBundle\LiaisonBook;

use BNS\App\CoreBundle\Model\LiaisonBook;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;
use BNS\App\CoreBundle\Model\LiaisonBookSignature;
use BNS\App\CoreBundle\Model\LiaisonBookSignatureQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @author ROUAYS Pierre-Luc
 * Classe permettant la gestion des carnets de liaison / signatures
 */
class BNSLiaisonBookManager
{	

        protected $api;
    
	public function __construct($api)
	{
		$this->api = $api;
	}
        
        /**
	 * @param array $liaisonBookInfos contient tous les paramètres nécessaires à la création d'un carnet de liaison
	 * La clé d'un paramètre doit être le nom du paramètre; Les paramètres obligatoires sont title, content, group_id
	 * @throws Exception lève une exception si les champs obligatoire (title, content et group_id) ne sont pas renseigné
	 */
	public function createLiaisonBook(array $liaisonBookInfos)
	{
            // tableau qui contient les attributs que l'utilisateur doit obligatoirement fournir
            $obligatoryProperties = array('title', 'content', 'group_id');
            // On boucle sur le tableau $liaisonBookInfos fourni en paramètre pour s'assurer de la présence de ces attributs
            foreach ($obligatoryProperties as $obligatoryProperty)
            {
                    if (!array_key_exists($obligatoryProperty, $liaisonBookInfos))
                    {				
                            throw new Exception($obligatoryProperty.' argument is missing in array passing as parameter of createLiaisonBook() method.');
                    }
            }

            //Création du carnet de liaison avec ces paramètres passés
            $liaisonBook = new LiaisonBook();
            $liaisonBook->setTitle($liaisonBookInfos['title']);
            $liaisonBook->setContent($liaisonBookInfos['content']);
            $liaisonBook->setGroupId($liaisonBookInfos['group_id']);
            $liaisonBook->setDate(new \DateTime());
            $liaisonBook->save();
	}
        
        /**
	 * @param $user : utilisateur qui signe
         * @param $liaisonBook : la news du carnet de liaison qu'il signe
	 */
	public function signLiaisonBook($user, $liaisonBook)
	{
            //Si la signature n'existe pas déjà
            $liaisonBookSignature = LiaisonBookSignatureQuery::create()->findByLiaisonbookIdAndUserId($liaisonBook->getId(), $user->getId());
            
            if($liaisonBookSignature == null)
            {
                //Ajout de la signature de l'utilisateur pour le liaison book
                $liaisonBookSignature = new LiaisonBookSignature();
                $liaisonBookSignature->setUser($user);
                $liaisonBookSignature->setLiaisonBook($liaisonBook);
                $liaisonBookSignature->save();
            }
	}
             
	/**
	 * @param $group_id : groupe en cours
	 */
	public function getLiaisonBooksByGroupId($group_id)
	{
            $liaisonBooks = LiaisonBookQuery::create()->filterByGroupId($group_id)->orderByDate(\Criteria::DESC)->find();
            return $liaisonBooks;
	}
        
        /**
	 * @param $id : groupe du liaisonBook
	 */
	public function getLiaisonBooksById($id)
	{
            $liaisonBook = LiaisonBookQuery::create()->findOneById($id);
            return $liaisonBook;
	}
        
        /**
	 * @param $group_id : groupe en cours
         * @param $month : mois du post
         * @param $year : année du post
	 */
	public function getLiaisonBooksByGroupIdAndDate($group_id, $month, $year)
	{
            $liaisonBooks = LiaisonBookQuery::create()->orderByDate()->findByGroupIdAndDate($group_id, $month, $year);
            return $liaisonBooks;
	}
        
        /**
	 * @param $group_id : groupe en cours
         * @param $month : mois du post
         * @param $year : année du post
	 */
	public function getLiaisonBooksByGroupIdAndLessOneYear($group_id)
	{
            $liaisonBooks = LiaisonBookQuery::create()->orderByDate()->findByGroupIdAndLessOneYear($group_id);
            return $liaisonBooks;
	}
        
	public function getUsersThatHaveThePermissionInGroup($permission_unique_name, $group_id)
	{
		$users = $this->api->send('group_get_users_with_permission', array(
			'route' =>  array(
				'group_id' => $group_id,
				'permission_unique_name' => $permission_unique_name
			))
		);
		
		if (null == $users) {
			return array();
		}

		return $users;
	}
        
}