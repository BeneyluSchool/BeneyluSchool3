<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace BNS\App\FixtureBundle\Command;

//Chargement des écoles


use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticleCategory;
use BNS\App\CoreBundle\Model\BlogCategory;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\RegistrationBundle\Model\SchoolInformation;
use BNS\App\RegistrationBundle\Model\SchoolInformationPeer;
use BNS\App\RegistrationBundle\Model\SchoolInformationQuery;
use Criteria;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Propel;
use PropelPDO;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Migration V2 => V3
 */
class MigrationCommand extends ContainerAwareCommand
{
	/**
	 * @var PropelPDO MySQL connexion
	 */
	protected $con;
	protected $test = false;
	
	protected function configure()
    {
        $this
            ->setName('bns:migration')
            ->setDescription('V2 / V3 migration')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connexion a utiliser')
			->addOption('test',"test", InputOption::VALUE_OPTIONAL, 'En test')
			->addArgument("step")
        ;
    }
	
	

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
		$this->con = Propel::getConnection($connectionName);
		Propel::setForceMasterConnection(true);
		ini_set("memory_limit","6000M");
		
		//C'est parti
		
		BNSAccess::setContainer($this->getContainer());
		
		$args = $input->getArguments();
		$opts = $input->getOptions();
		
		if($opts['test'] == "test"){
			$this->test = true;
		}
		
		switch($args["step"]){
			case "schools":
				$this->loadSchools($input,$output);
			break;
			case "classrooms":
				$this->loadClassrooms($input,$output);
			break;
			case "teachers":
				$this->loadTeachers($input,$output);
			break;
			case "pupils":
				$this->loadPupils($input,$output);
			break;
			case "blogCategories":
				$this->loadBlogPostCategories($input,$output);
			break;
		}
		
    }
	
	public function getLogger($name){
		// create a log channel
		$log = new Logger('migration');
		$log->pushHandler(new StreamHandler(__DIR__ . '/../Resources/Logs/'.$name.'.log', Logger::INFO));
		return $log;
	}
	
	/** 
	 * Step 1
	 * Chargement des écoles depuis schools vers school_informations
	 */
	
	public static $countriesCultures = array(
		5 => 'DE',
		12 => 'SA',
		16 => 'AU',
		25 => 'BE',
		34 => 'BR',
		42 => 'CA',
		43 => 'CV',
		45 => 'CN',
		47 => 'CO',
		53 => 'HR',
		56 => 'DJ',
		58 => 'EG',
		60 => 'EC',
		62 => 'ES',
		64 => 'US',
		66 => 'FI',
		67 => 'FR',
		78 => 'GT',
		79 => 'GN',
		83 => 'GF',
		107 => 'IN',
		108 => 'ID',
		113 => 'IL',
		114 => 'IT',
		126 => 'LB',
		128 => 'LY',
		131 => 'LU',
		133 => 'MG',
		137 => 'ML',
		140 => 'MA',
		143 => 'MU',
		152 => 'NP',
		156 => 'NE',
		157 => 'NG',
		160 => 'NC',
		170 => 'NL',
		175 => 'PT',
		180 => 'CZ',
		183 => 'GB',
		184 => 'RU',
		186 => 'SN',
		201 => 'SG',
		202 => 'SI',
		208 => 'CH',
		224 => 'TN',
		226 => 'TR',
		228 => 'UA',
		229 => 'UY'
	);
	
	public static $classroomLevels = array(
		1 => 'CP',
		2 => 'CE1',
		3 => 'CE2',
		4 => 'CM1',
		5 => 'CM2',
		6 => 'PS',
		7 => 'MS',
		8 => 'GS',
		9 => '6ème',
		10 => '5ème',
		11 => '4ème',
		12 => '3ème',
		13 => 'CLIS',
		14 => 'SEC',
		15 => 'PREM',
		16 => 'TERM'		
	);
	
	
	
	public function loadSchools(InputInterface $input, OutputInterface $output)
	{
		
		$output->writeln("Creation des ecoles statiques");
		
		if($this->test == true){
			$intest = '_test';
		}else{
			$intest = "";
		}
		
		$file = 1;
		$nbSchools = 0;
		$logger = $this->getLogger('schools');
		
		while(is_file(__DIR__ . '/../Resources/data/Migration/schools'. $intest . '_' . $file.'.yml')){
		
			$output->writeln("Importation fichier n $file");
			
			$schools			= Yaml::parse(__DIR__ . '/../Resources/data/Migration/schools'. $intest . '_' .$file.'.yml');
		
			$countSchools      	= count($schools);

			$output->writeln("Création des $countSchools écoles dans  le fichier $file");

			foreach($schools as $school){

				$output->writeln("Creation n " . $nbSchools);
				
				//Check existence

				if(isset($school['name']) && isset($school['country_id']) && isset($school['city']) && isset($school['id'])){
					
					if(!SchoolInformationQuery::create()->findPk($school['id'])){
						
						$newSchool = new SchoolInformation();
						$newSchool->setId($school['id']);
						$newSchool->setName($school['name']);
						$newSchool->setCity($school['city']);
						$newSchool->setCountry(self::$countriesCultures[$school['country_id']]);

						if(isset($school['address']))
							$newSchool->setAddress($school['address']);

						if(isset($school['roll_number']))
							$newSchool->setUai($school['roll_number']);

						if(isset($school['zip_code']))
							$newSchool->setZipCode($school['zip_code']);

						if(isset($school['phone_number']))
							$newSchool->setPhoneNumber($school['phone_number']);

						if(isset($school['fax_number']))
							$newSchool->setFaxNumber($school['fax_number']);

						if(isset($school['email']))
							$newSchool->setEmail($school['email']);
						$newSchool->setStatus(SchoolInformationPeer::STATUS_VALIDATED);
						$newSchool->save();
						$nbSchools++;
						unset($school,$newSchool);
						
					}else{
						$logger->info("Ecole " . $school['id'] . " n'a pas pu être importée, elle existe deja.");
					}
				}else{
					$logger->info("Ecole " . $school['id'] . " n'a pas pu être importée;");
				}
			}
			unset($schools);
			$file++;
		}
	}
	/*
	SELECT * 
	FROM  `classroom` 
	WHERE classroom.status >= -1
	
	SELECT  `level_id` ,  `classroom_id` 
	FROM  `classroom_level` 
	JOIN classroom
	WHERE classroom.id = classroom_level.classroom_id
	AND classroom.status >= -1
	
	
	*/
	public function loadClassrooms(InputInterface $input, OutputInterface $output)
	{
		$filestockName = __DIR__ . '/../Resources/data/Migration/Stock/classrooms.yml';
		
		$nbClassrooms = 1;
		$logger = $this->getLogger('classrooms');
		
		$filestockName = __DIR__ . '/../Resources/data/Migration/Stock/classrooms.yml';
		
		$output->writeln("Importation fichier des classes");
			
		$classrooms			= Yaml::parse(__DIR__ . '/../Resources/data/Migration/classrooms.yml');
		
		$output->writeln("Chargement des relations niveaux / classe ");
		
		$classroomsLevelsFile			= Yaml::parse(__DIR__ . '/../Resources/data/Migration/classroom_levels.yml');
		
		$classroomsLevels = array();
		
		foreach($classroomsLevelsFile as $row){
			$classroomsLevels[$row['classroom_id']][] = $row['level_id'];
		}
		
		$output->writeln("Fin du chargement des relations niveaux / classe ");
		
		$output->writeln("Chargement des classes déjà crees ");
		
		$classroomsDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/classrooms.yml');
		
		$output->writeln("Fin du chargement des classes déjà crees");

		$countClassrooms = count($classrooms);

		$output->writeln("Création des $countClassrooms classes");

		foreach($classrooms as $classroom){

			$output->writeln("Creation n " . $nbClassrooms);
			
			if(isset($classroom['name']) && isset($classroom['id']) && isset($classroom['status']) && isset($classroom['school_id'])){
				if(!isset($classroomsDone[$classroom['id']])){
					$output->writeln("Recherche de l ecole");
					//Recupération OU creation de l'école
					$schoolId = $classroom['school_id'];
					
					$schoolInfo = SchoolInformationQuery::create('si')
						->joinWith('Group', Criteria::LEFT_JOIN)
						->where('si.Id = ?', $schoolId)
					->findOne();
					
					$output->writeln("Fin Recherche de l ecole");
					
					if($schoolInfo){
						
						// Creating school if not exists
						if (null == $schoolInfo->getGroupId()) {
							$output->writeln("Creation de lecole");
							$school = $this->getContainer()->get('bns.classroom_manager')->createSchoolFromInformation($schoolInfo);
							$newSchoolId = $school->getId();
							$output->writeln("Fin Creation de lecole");
						}else{
							$newSchoolId = $schoolInfo->getGroupId();
						}
						
						$output->writeln("Creation de  la classe");
						$newClassroom = $this->getContainer()->get('bns.classroom_manager')->createClassroom(
							array(
								'label' => $classroom['name'],
								'validated' => in_array($classroom['status'],array(0,1)),
								'group_parent_id' => $newSchoolId
							)
						);
						$output->writeln("Fin creation de la classe");
						$myClassroomsDatas = array(
							$classroom['id'] => array(
								'new_id' => $newClassroom->getId(),
								'school_id' => $classroom['school_id'],
								'classroom_config_id' => @$classroom['classroom_config_id'],
								'mediatheque_info_id' => @$classroom['mediatheque_info_id'],
								'new_school_id' => $newSchoolId
							)
						);
						$output->writeln("Put dans Yamel");
						//Ecriture dans fichier stock;
						file_put_contents($filestockName, Yaml::dump($myClassroomsDatas),FILE_APPEND);
						$output->writeln("CFin put yamel");
						$myClassroomsLevels = array();

						
						$output->writeln("Mise en place des niveaux");
						if(isset($classroomsLevels[$classroom['id']])){
							foreach($classroomsLevels[$classroom['id']] as $levelId){
								$myClassroomsLevels[] = self::$classroomLevels[$levelId];
							}
						}
						
						$newClassroom->setAttribute('LEVEL',$myClassroomsLevels);
						$output->writeln("Fin des niveaux");
						unset($schoolInfo,$newClassroom,$myClassroomsLevels,$myClassroomsDatas,$classroom);
						$output->writeln("Fin");
						
					}else{
						$logger->info("Classe " . @$classroom['id'] . " non importée, Ecole " . $classroom['school_id'] . " inexistante ");
					}
				}else{
					$logger->info("Classe " . @$classroom['id'] . " non importée, Déjà créée");
				}				
			}else{
				$logger->info("Classe " . @$classroom['id'] . " non importée, données manquantes");
			}
			$nbClassrooms++;
		}
	}
	
	/**
		SELECT * 
		FROM  `sf_guard_teacher_profile` 
		JOIN sf_guard_user
		JOIN classroom
		WHERE sf_guard_teacher_profile.user_id = sf_guard_user.id
		AND sf_guard_user.is_active >=0
		AND sf_guard_teacher_profile.classroom_id = classroom.id
		AND classroom.status >= -1
	 */
	
	public function loadTeachers(InputInterface $input, OutputInterface $output)
	{
		$filestockName = __DIR__ . '/../Resources/data/Migration/Stock/teachers.yml';
		$nbTeachers = 1;
		
		$logger = $this->getLogger('users');
		
		$output->writeln("Chargement des utilisateurs déjà crees ");
		
		$usersDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/teachers.yml');
		$classroomsDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/classrooms.yml');
		
		$output->writeln("Fin du chargement des utilisateurs deja crees");
		
		$output->writeln("Chargement des enseignants ");
		
		$teachers			= Yaml::parse(__DIR__ . '/../Resources/data/Migration/teachers.yml');
		
		$output->writeln("Fin du chargement des enseignants ");
		
		$countTeachers = count($teachers);

		$output->writeln("Creation des $countTeachers enseignants");

		foreach($teachers as $teacher){
			$output->writeln("Creation n " . $nbTeachers);
			
			if(
				isset($teacher['user_id']) && 
				isset($teacher['classroom_id']) && 
				isset($teacher['first_name']) && 
				isset($teacher['last_name']) &&
				isset($teacher['gender']) && 
				isset($teacher['email']) && 
				isset($teacher['username']) && 
				isset($teacher['salt']) &&
				isset($teacher['password']) && 
				isset($teacher['is_active']))
				{
				if(!isset($usersDone[$teacher['user_id']]) && isset($classroomsDone[$teacher['classroom_id']])){
					
					$newClassroomId = $classroomsDone[$teacher['classroom_id']]['new_id'];
					$newTeacher = $this->getContainer()->get('bns.user_manager')->createUser(array(
						'first_name'	=> $teacher['first_name'],
						'last_name'		=> $teacher['last_name'],
						'gender'		=> $teacher['gender'],
						'lang'			=> 'fr',
						'email'			=> $teacher['email'],
						'salt'			=> $teacher['salt'],
						'password'		=> $teacher['password'],
						'birthday'		=> isset($teacher['birthday']) ? $teacher['birthday'] : null,
						'username'		=> $teacher['username']
					),false);
					
					$classroom = $this->getContainer()->get('bns.classroom_manager')->findGroupById($newClassroomId);
					$this->getContainer()->get('bns.classroom_manager')->setClassroom($classroom);
					$this->getContainer()->get('bns.classroom_manager')->assignTeacher($newTeacher);
					
					$myTeacherDatas = array(
						$teacher['user_id'] => array(
							'new_id' => $newTeacher->getId(),
							'old_classroom_id' => $teacher['classroom_id'],
							'new_classroom_id' => $classroom->getId()
						)
					);
					//Ecriture dans fichier stock;
					file_put_contents($filestockName, Yaml::dump($myTeacherDatas),FILE_APPEND);
					unset($teacher,$myTeacherDatas,$classroom);
				}else{
					$logger->info("Enseignant " . @$teacher['username'] . " non importée, classe non importée");
				}
			}else{
				$logger->info("Enseignant " . @$teacher['username'] . " non importée, données manquantes");
			}
			$nbTeachers++;
		}
	}
	
	/**
		SELECT * 
		FROM  `sf_guard_pupil_profile` 
		JOIN sf_guard_user
		JOIN classroom
		WHERE sf_guard_pupil_profile.user_id = sf_guard_user.id
		AND sf_guard_user.is_active >=0
		AND sf_guard_pupil_profile.classroom_id = classroom.id
		AND classroom.status >= -1
	 */
	
	
	public function loadPupils(InputInterface $input, OutputInterface $output)
	{
		$filestockName = __DIR__ . '/../Resources/data/Migration/Stock/pupils.yml';
		
		$logger = $this->getLogger('users');
		
		$output->writeln("Chargement des utilisateurs déjà crees ");
		
		$usersDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/pupils.yml');
		$classroomsDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/classrooms.yml');
		
		$output->writeln("Fin du chargement des utilisateurs deja crees");
		
		$file = 1;
		$nb = 0;
		
		while(is_file(__DIR__ . '/../Resources/data/Migration/pupils_' . $file.'.yml')){
			
			$output->writeln("Chargement des élèves du ficchier n " . $file);
		
			$pupils			= Yaml::parse(__DIR__ . '/../Resources/data/Migration/pupils_' . $file.'.yml');

			$output->writeln("Fin du chargement des élèves du fichier " . $file);

			foreach($pupils as $pupil){
				$output->writeln("Creation n " . $nb);
				if(
					isset($pupil['user_id']) && 
					isset($pupil['classroom_id']) && 
					isset($pupil['first_name']) && 
					isset($pupil['last_name']) &&
					isset($pupil['gender']) && 
					isset($pupil['username']) && 
					isset($pupil['salt']) &&
					isset($pupil['password']) && 
					isset($pupil['is_active']))
					{
					if(!isset($usersDone[$pupil['user_id']]) && isset($classroomsDone[$pupil['classroom_id']])){

						$newClassroomId = $classroomsDone[$pupil['classroom_id']]['new_id'];
						$newPupil = $this->getContainer()->get('bns.user_manager')->createUser(array(
							'first_name'	=> $pupil['first_name'],
							'last_name'		=> $pupil['last_name'],
							'gender'		=> $pupil['gender'],
							'lang'			=> 'fr',
							'salt'			=> $pupil['salt'],
							'password'		=> $pupil['password'],
							'birthday'		=> isset($pupil['birthday']) ? $pupil['birthday'] : null,
							'username'		=> $pupil['username']
						),false);

						$classroom = $this->getContainer()->get('bns.classroom_manager')->findGroupById($newClassroomId);
						$this->getContainer()->get('bns.classroom_manager')->setClassroom($classroom);
						$this->getContainer()->get('bns.classroom_manager')->assignPupil($newPupil);

						$myPupilDatas = array(
							$pupil['user_id'] => array(
								'new_id' => $newPupil->getId(),
								'old_classroom_id' => $pupil['classroom_id'],
								'new_classroom_id' => $classroom->getId()
							)
						);
						//Ecriture dans fichier stock;
						file_put_contents($filestockName, Yaml::dump($myPupilDatas),FILE_APPEND);
						unset($pupil,$myPupilDatas,$newPupil,$classroom);
					}else{
						$logger->info("Eleve " . @$pupil['username'] . " non importée, classe non importée");
					}
				}else{
					$logger->info("Eleve " . @$pupil['username'] . " non importée, données manquantes");
				}	
				$nb++;
			}
			$file++;
			unset($pupils);
		}
	}
	
	public function loadParents(InputInterface $input, OutputInterface $output)
	{
		$filestockName = __DIR__ . '/../Resources/data/Migration/Stock/parents.yml';
				
		$logger = $this->getLogger('users');
		
		$output->writeln("Chargement des utilisateurs déjà crees ");
		
		$usersDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/parents.yml');
		$classroomsDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/classrooms.yml');
		
		$output->writeln("Fin du chargement des utilisateurs deja crees");
		
		$output->writeln("Chargement des parents");
		
		$parents			= Yaml::parse(__DIR__ . '/../Resources/data/Migration/parents.yml');
		
		$output->writeln("Fin du chargement des parents ");
		
		$file = 1;
		$nb = 0;		

		while(is_file(__DIR__ . '/../Resources/data/Migration/parents_' . $file.'.yml')){
			
			$output->writeln("Chargement des parents du fichier n " . $file);
		
			$parents			= Yaml::parse(__DIR__ . '/../Resources/data/Migration/parents_' . $file.'.yml');

			$output->writeln("Fin du chargement des parents du fichier " . $file);

			foreach($parents as $parent){
				$output->writeln("Creation n " . $nb);
				if(
					isset($parent['user_id']) && 
					isset($parent['classroom_id']) && 
					isset($parent['first_name']) && 
					isset($parent['last_name']) &&
					isset($parent['gender']) && 
					isset($parent['username']) && 
					isset($parent['salt']) &&
					isset($parent['password']) && 
					isset($parent['is_active']))
					{
					if(!isset($usersDone[$parent['user_id']]) && isset($classroomsDone[$parent['classroom_id']])){

						$newClassroomId = $classroomsDone[$parent['classroom_id']]['new_id'];
						$newParent = $this->getContainer()->get('bns.user_manager')->createUser(array(
							'first_name'	=> $parent['first_name'],
							'last_name'		=> $parent['last_name'],
							'gender'		=> 0,
							'lang'			=> 'fr',
							'salt'			=> $parent['salt'],
							'password'		=> $parent['password'],
							'birthday'		=> null,
							'email'			=> @$parent['email'],
							'username'		=> $parent['username']
						),false);

						$classroom = $this->getContainer()->get('bns.classroom_manager')->findGroupById($newClassroomId);
						$this->getContainer()->get('bns.classroom_manager')->setClassroom($classroom);
						$this->getContainer()->get('bns.classroom_manager')->assignParent($newParent);

						$myParentDatas = array(
							$parent['user_id'] => array(
								'new_id' => $newParent->getId(),
								'old_classroom_id' => $parent['classroom_id'],
								'new_classroom_id' => $classroom->getId()
							)
						);
						//Ecriture dans fichier stock;
						file_put_contents($filestockName, Yaml::dump($myParentDatas),FILE_APPEND);
						unset($parent,$myParentDatas,$newParent,$classroom);
					}else{
						$logger->info("Parent " . @$pupil['username'] . " non importée, classe non importée");
					}
				}else{
					$logger->info("Eleve " . @$pupil['username'] . " non importée, données manquantes");
				}	
				$nb++;
			}
			$file++;
			unset($pupils);
		}
	}

	public function loadBlogPostCategories(InputInterface $input, OutputInterface $output)
	{
		$logger = $this->getLogger('blogCategories');
		$filestockName = __DIR__ . '/../Resources/data/Migration/Stock/categories.yml';
		$classroomsDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/classrooms.yml');
		$categoriesDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/categories.yml');
		
		$nb = 0;		

		include __DIR__ . '/../Resources/data/Migration/blog_post_category.php';
		foreach($blog_post_category as $cat){
			
			$output->writeln("Creation n " . $nb);
			if(
				isset($cat['id']) && 
				isset($cat['title']) && 
				isset($cat['classroom_id']))
				{
				if(isset($classroomsDone[$cat['classroom_id']]) && !isset($categoriesDone[$cat['id']])){

					$newCategory = new BlogCategory();
					
					$blog = BlogQuery::create()->filterByGroupId($classroomsDone[$cat['classroom_id']]['new_id'])->findOne();
					$root = BlogCategoryQuery::create()->filterByBlogId($blog->getId())->filterByLevel(0)->findOne();
					if($root){
						$newCategory->setBlogId($blog->getId());
						$newCategory->setTitle($cat['title']);
						$newCategory->save();

						$newCategory->insertAsLastChildOf($root);
						$newCategory->save();

						$catDatas = array(
							$cat['id'] => array(
								'new_id' => $newCategory->getId(),
							)
						);
						//Ecriture dans fichier stock;
						file_put_contents($filestockName, Yaml::dump($catDatas),FILE_APPEND);
						unset($cat,$catDatas,$newCategory,$root,$blog);
					}
				}else{
					$logger->info("Categorie non importée : classe manquante");
				}
			}else{
				$logger->info("Categorie non importée, données manquantes");
			}	
			$nb++;
		}
	}
	
	
	
	
	
	
	
	//Export des articles du blog
	/**
	 * 
SELECT blog_post.`id` ,  `user_id` ,  `classroom_id` ,  `blog_post_category_id` ,  `title` ,  `subtitle` ,  `content` , blog_post.`status` 
FROM  `blog_post` 
JOIN classroom
WHERE classroom.id = blog_post.classroom_id
AND classroom.status >= -1
AND blog_post.status = 1
ORDER BY blog_post.`id`
	 */
	
	public function loadBlogPosts(InputInterface $input, OutputInterface $output)
	{
		$logger = $this->getLogger('blogPosts');
		$filestockName = __DIR__ . '/../Resources/data/Migration/Stock/posts.yml';
		
		$output->writeln("Chargement des utilisateurs déjà crees ");
		
		$pupilsDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/pupils.yml');
		$teachersDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/teachers.yml');
		$classroomsDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/classrooms.yml');
		$categoriesDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/categories.yml');
		
		$output->writeln("Chargement des articles déjà faits");
		$postsDone = Yaml::parse(__DIR__ . '/../Resources/data/Migration/Stock/posts.yml');
		
		$output->writeln("Chargement des articles");
		
		$file = 1;
		$nb = 0;		

		while(is_file(__DIR__ . '/../Resources/data/Migration/blog_post _' . $file.'.php')){
			
			include __DIR__ . '/../Resources/data/Migration/blog_post _' . $file.'.php';

			foreach($blog_post as $post){
				$output->writeln("Creation n " . $nb);
				if(
					isset($post['id']) && 
					isset($post['user_id']) && 
					isset($post['classroom_id']) && 
					isset($post['blog_post_category_id']) &&
					isset($post['title']) && 
					isset($post['content']))
					{
					if(!isset($postsDone[$post['id']]) && isset($classroomsDone[$post['classroom_id']])){

						$blog = BlogQuery::create()->filterByGroupId($classroomsDone[$cat['classroom_id']]['new_id'])->findOne();						
						
						//$newClassroomId = $classroomsDone[$parent['classroom_id']]['new_id'];
						
						$newArticle = new BlogArticle();
						$newArticle->setTitle($post['title']);
						$newArticle->setContent($post['content']);
						$newArticle->setStatus(1);
						$newArticle->setBlogId($blog->getId());
						$newArticle->setPublishedAt(date('Y-m-d H:i:s',date('U')));
						
						if(isset($pupilsDone[$post['user_id']])){
							$authorId = $pupilsDone[$post['user_id']]['new_id'];
						}elseif(isset($teachersDone[$post['user_id']])){
							$authorId = $teachersDone[$post['user_id']]['new_id'];
						}
						$newArticle->setAuthorId($authorId);
						$newArticle->save();

						if($post['blog_post_category_id'] != null){
							if(isset($categoriesDone[$post['blog_post_category_id']])){
								$link = new BlogArticleCategory();
								$link->setArticleId($newArticle->getId());
								$link->setCategoryId($categoriesDone[$post['blog_post_category_id']]['new_id']);
								$link->save();			
							}
						}
						
						$myPostDatas = array(
							$post['id'] => array(
								'new_id' => $newArticle->getId()
							)
						);
						//Ecriture dans fichier stock;
						file_put_contents($filestockName, Yaml::dump($myPostDatas),FILE_APPEND);
						unset($myPostDatas,$post,$newArticle,$blog);
					}else{
						$logger->info();
					}
				}else{
					$logger->info();
				}	
				$nb++;
			}
			$file++;
			unset($blog_post);
		}
	}
	
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * 
	 * @return type
	 * 
	 * @throws InvalidArgumentException 
	 */
	protected function getConnection(InputInterface $input, OutputInterface $output)
    {
        $propelConfiguration = $this->getContainer()->get('propel.configuration');
        $name = $input->getOption('connection') ?: $this->getContainer()->getParameter('propel.dbal.default_connection');

        if (isset($propelConfiguration['datasources'][$name])) {
            $defaultConfig = $propelConfiguration['datasources'][$name];
        } else {
            throw new InvalidArgumentException(sprintf('Connection named %s doesn\'t exist', $name));
        }

        $output->writeln(sprintf('Use connection named <comment>%s</comment> in <comment>%s</comment> environment.',
            $name, $this->getApplication()->getKernel()->getEnvironment()));

        return array($name, $defaultConfig);
    }
	
	/**
	 * @param OutputInterface $output
	 * @param type $text
	 * @param type $style 
	 */
	protected function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(array(
            '',
            $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true),
            '',
        ));
    }
}