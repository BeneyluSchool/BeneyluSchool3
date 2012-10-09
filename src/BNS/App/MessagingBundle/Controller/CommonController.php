<?php

/* Toujours faire attention aux use : ne pas charger inutilement des class */
namespace BNS\App\MessagingBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;


class CommonController extends Controller
{	
	/**
	 * Renvoie le type de messagerie : light ou real
	 * @return string type || real
	 */
	protected $type;
	protected function getMessagingType(){
		if(!isset($this->type)){
			$dbValue = $this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('MESSAGING_TYPE');
			if($dbValue != 'light' && $dbValue != "" && $dbValue != null){
				$this->type = $dbValue;
			}else{
				$this->type = 'light';
			}
		}
		return $this->type;
	}    
}

