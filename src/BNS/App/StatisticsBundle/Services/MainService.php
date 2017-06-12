<?php

namespace BNS\App\StatisticsBundle\Services;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use DateTimeZone;
use \BNS\App\StatisticsBundle\Model\MarkerQuery;
use \BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Annotation\Rights;

/**
 * MainService est le service de statistique principal.
 * Il doit être utilisé depuis les services secondaires.
 * Son rôle est de sauvegarder dans une base redis
 * les informations d'utilisation de la Beneylu School.
 *
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
class MainService{
    private $messagesFifo;
    private $container;
    /**
     *
     */
    public function __construct($container)
    {
        $this->messagesFifo = array();
        $this->container = $container;
    }

    /**
     * ajoute une ligne de statistique
     * @param string $text
     */
    public function addStat($text)
    {
        $this->messagesFifo[] = $text;
    }

    /**
     * incremente un marqueur de statistique
     * @param string $indicateurId
     * @param date $date
     * @param time $heure
     * @param int $groupeId
     * @param int $roleId
     */
    public function increment($indicateurId, $date, $heure, $groupeId, $roleId, $info = null)
    {
        $line = array( "INC", ($indicateurId.":".$date.":".$heure.":".$groupeId.":".$roleId));
        //s'il y a des données supplémentaires on les ajoutes à la ligne
        if($info != null) {
            $line[1] .= ":".$info;
        }

        $this->addStat($line);
    }

    /**
     * décrémente un marqueur de statistique
     * @param string $indicateurId
     * @param date $date
     * @param time $heure
     * @param int $groupeId
     * @param int $roleId
     */
    public function decrement($indicateurId, $date, $heure, $groupeId, $roleId)
    {
        $line = array( "DEC", ($indicateurId.":".$date.":".$heure.":".$groupeId.":".$roleId));
        $this->addStat($line);
    }

    /**
     * Déclenché lorsqu'une page s'affiche correctement
     * on sauvegarde dans REDIS les nouvelles lignes de stats
     * @param \Symfony\Component\HttpKernel\Event\PostResponseEvent $event
     */
    public function onKernelTerminate(PostResponseEvent $event)
    {
        while(! empty($this->messagesFifo) ) {
            //récupère le 1er élément insérer dans la file
            $line = array_shift($this->messagesFifo);

            //appel la bonne méthode en fonction de sa description
            if($line[0] == "INC") {
                //$this->container->get("snc_redis.default")->incr($line[1]);
                $this->container->get("snc_redis.default")->incr($line[1]);
            }
            else if($line[0] == "DEC") {
                //$this->container->get("snc_redis.default")->decr($line[1]);
                $this->container->get("snc_redis.default")->decr($line[1]);
            }
        }
    }

    /**
     * Méthode a utiliser pour récupérer les données avant affichage
     * filtre les statistiques selon les paramètres suivant
     *
     * @param string $indicator
     * @param array $groupTypes
     * @param boolean $aggreg additionne les données ou sort une ligne par info
     * @param string $period DAY, MONTH, HOURS
     * @param date $dateStart
     * @param date $dateEnd
     * @param string $title titre du graphique
     * @return type
     */
    public function statFilter($indicator, $groupTypes = array(), $aggreg = false, $allGroupTypesAllowed = false, $legendName = null, $period = 'DAY', $dateStart = '', $dateEnd = '', $title = '')
    {
        //initialisation des données à retourner
        $stats = array();
        //création du tableau de stat
        $stats['data'] = array();
        //DAY MONTH OU HOURS
        $stats['period'] = $period;
        //nom de l'indicateur
        $stats['name'] = array();
        $stats['name'][0] = "connection";
        $stats['size'] = 0;
        $isGet = false;
        $nameRole = '';

        //GET
        if($indicator == '')
        {
           //initialisation de période par défaut
            $indicator = "MAIN_CONNECT_PLATFORM";
            $periodStart = new \DateTime(date('Y-m-d') . ' 00:00:00');
            $periodEnd = clone $periodStart;
            $periodStart->modify('-1 month');
            $periodEnd->setTime(23, 59, 59);

            //création du titre
            $stats['title'] = $this->container->get('translator')->trans('CONNECTION', array(), 'STATISTICS');
            $isGet = true;
        }
        else {
            $dateStart->setTimezone(new DateTimeZone('Etc/GMT+2'));
            $dateEnd->setTimezone(new DateTimeZone('Etc/GMT+2'));
            $periodStart = $dateStart;
            $periodEnd = $dateEnd;
        }

        //récupération du marqueur à partir de son nom
        $marker = MarkerQuery::create('l')
                ->where('l.unique_name = ?', $indicator)
                ->findOne();

        //si le marqueur n'existe pas
        if(null == $marker) {
            throw new \Exception("Marker given ". $indicator ." does not exist in database");
        }

        //récupération du nom du module correspondant au marqueur
        $module = $marker->getModuleUniqueName();

        //récupération de la description du module
        $description = $marker->getDescription();

        //si pas de titre rempli par l'utilisateur on prend la description du marqueur
        $stats['title'] = ($title == '')? $description : $title;

        //construction de la requète à la volée à partir du nom du module
        $queryName = "\BNS\App\StatisticsBundle\Model\\".ucfirst(strtolower($module))."Query";

        //si pas de groupe type fourni et droit d'utiliser tous les groupes on prend tous les groupes
        if($allGroupTypesAllowed) {
            $roleIds = array();
            $nameRole = $this->container->get('translator')->trans('ALL_ROLE', array(), 'STATISTICS');
        }
        else {
            if(count($groupTypes) == 0) {
                $roleIds = array("NotAllRight");
            }
        }

        if(count($groupTypes) > 0) { //sinon on récupère l'id du groupe fourni
            $nameRole=$this->container->get('translator')->trans('ROLE', array(), 'STATISTICS');
            foreach($groupTypes as $id => $gt) {
                $groupType = GroupTypeQuery::create()
                    ->filterByType($gt)
                    ->findOne();
                $roleIds[] = $groupType->getId();

                $nameRole .= " " . $groupType->getLabel();
            }
        }

        // En fonction de la période on crée le format de la date
        switch ($period) {
            case 'MONTH':
                $dateFormatPropel = "DATE_FORMAT(".strtolower($module).".date, '%Y%m')";
                $dateFormatPhp = "Y-m";
                break;
            case 'HOURS': //pour l'heure il faut d'abords faire un format sur le jour puis l'heure
                $dateFormatPropel = "DATE_FORMAT(".strtolower($module).".date, '%Y%m%d %H')";
                $dateFormatPhp = "Y-m-d H:i:s";
                break;
            default: // par défaut 'DAY'
                $dateFormatPropel = "DATE_FORMAT(".strtolower($module).".date, '%Y%m%d')";
                $dateFormatPhp = "Y-m-d";
                break;
        }

        //création de la date de début et de fin
        $date = array();
        $date['min'] = $periodStart->format('Y-m-d H:i:s');
        $date['max'] = $periodEnd->format('Y-m-d H:i:s');

        $interval = $periodStart->diff($periodEnd);
        $nbDays = $interval->days;

        //récupération des IDs des groupes qui nous interessent

        $group = $this->container->get("bns.right_manager")->getCurrentGroup();
        $groupId = $group->getId();

        $stats['data'] = array();

        // Création de la requète de récupération de donnée
        $connexions = $queryName::create()
            ->withColumn('date', 'dateIn') //création d'une colonne pour la date
            ->withColumn("SUM(value)", 'count') //création d'une colonne pour la somme des valeurs
            ->filterByMarkerId($indicator) //filtre celon le marqueur
            ->filterByGroupId($groupId); //filtre celon l'id du groupe courant

        //si le role n'est pas donné on prend tous les roles
        if( count($roleIds) != 0 ) {
            $connexions = $connexions->filterByRoleId($roleIds); //filtre celon l'id du plus haut role
        }

        $connexions = $connexions
            ->filterByDate($date) //filtre celon la date de début (pas l'heure)
            ->addGroupByColumn($dateFormatPropel) //groupe celon le format de date créer plus tot
            ->orderByDate() //trie celon la date
            ->find();

       //la legende correspond au nom du groupe
        $stats['name'] = $group->getLabel();
        $stats['data'] = array();
        $hardStats = array();
        // création du tableau de données a retourner
        foreach ($connexions as $i => $connexion ) {
            $hardStats[$connexion->getDate('Y-m-d')] = $connexion->getCount();
        }
        //On parse ses données en JSON
        for($i = 0;$i <= $nbDays; $i++)
        {
            $dateToMatch = date('Y-m-d',strtotime('+' . $i . 'days',$periodStart->format('U')));
            //Attention il faut caster en int pour le bon rendu du json_encode
            $realStats[] = isset($hardStats[$dateToMatch]) ? (int) $hardStats[$dateToMatch] : 0;
        }
        $stats['data'] = \json_encode($realStats);
        $stats['day'] = $periodStart->format('d');
        $stats['month'] = $periodStart->format('m');
        $stats['year'] = $periodStart->format('Y');

        return $stats;
    }

    /**
     *
     * @return string
     */
    public static function getSubscribedEvents()
    {
        return array(KernelEvents::TERMINATE => 'onKernelTerminate');
    }
}

?>
