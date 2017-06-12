<?php

namespace BNS\App\FixtureBundle\Command;

use BNS\App\CoreBundle\Date\ExtendedDateTime;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Utils\Console;
use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\ResourceBundle\Model\ResourceLabelGroup;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceQuery;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FixturesCreateUserCommand extends AbstractCommand
{
    /**
     * @var array<Resource>
     */
    private $avatars;

    /**
     * @var array<User>
     */
    private $users;

    /**
     * @var array
     */
    private $maleFirstnames;

    /**
     * @var int
     */
    private $countMaleFirstnames;

    /**
     * @var array
     */
    private $femaleFirstnames;

    /**
     * @var int
     */
    private $countFemaleFirstnames;

    /**
     * @var array
     */
    private $lastnames;

    /**
     * @var int
     */
    private $countLastnames;


	protected function configure()
    {
        $this
            ->setName('bns:fixtures:create-user')
            ->setDescription('Create user fixtures')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
			->addArgument('group_id', InputArgument::REQUIRED, 'The group where the users will be created')
			->addOption('teacher', null, InputOption::VALUE_OPTIONAL, 'The number of teacher that will be created')
			->addOption('pupil', null, InputOption::VALUE_OPTIONAL, 'The number of pupil that will be created')
			->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Your e-mail address, only needed if you want to created teacher')
			->addOption('email-creation-choice', null, InputOption::VALUE_OPTIONAL, <<<EOT
The email creation choice for teacher.

1: All teachers will have "youremail+[firstname]_[lastname]@yourhost.com"
2: All teachers will have "[firstname].[lastname]@beneyluschool.net"
EOT
            )
			->addOption('no-avatar', null, InputOption::VALUE_NONE, 'Disable avatar upload & setting process for users')
        ;
    }

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		list($conName, $defaultConfig) = $this->getConnection($input, $output);


        $group = GroupQuery::create('g')->findPk($input->getArgument('group_id'));
        if (null == $group) {
            return $this->writeSection($output, '  /!\ The group with id #' . $input->getArgument('group_id') . ' is NOT found !');
        }

        $this->writeSection($output, '    # Creating users for group "' . $group->getLabel() . '"');
        $classroomManager = $this->getContainer()->get('bns.classroom_manager')->setClassroom($group);

        $dialog = $this->getHelperSet()->get('dialog');
        $countTeacher = $input->getOption('teacher');
        $countPupil = $input->getOption('pupil');

        while (null == $countTeacher || !is_numeric($countTeacher)) {
            $countTeacher = $dialog->ask($output, '    > How many TEACHER you want ? : ');
        }

        while (null == $countPupil || !is_numeric($countPupil)) {
            $countPupil = $dialog->ask($output, '    > How many PUPIL you want ? : ');
        }

        // Retrieving data process
        $this->writeSection($output, '    # Retrieving data');
        $firstnames             = Yaml::parse(__DIR__ . '/../Resources/data/user/firstname.yml');
        $this->lastnames        = Yaml::parse(__DIR__ . '/../Resources/data/user/lastname.yml');
        $this->countLastnames   = count($this->lastnames);
        $this->users['TEACHER'] = array();
        $this->users['PUPIL']   = array();

        $this->maleFirstnames        = $firstnames['male'];
        $this->countMaleFirstnames   = count($this->maleFirstnames);
        $this->femaleFirstnames      = $firstnames['female'];
        $this->countFemaleFirstnames = count($this->femaleFirstnames);

        $output->writeln(' - ' . count($firstnames, COUNT_RECURSIVE) . ' firstnames loaded');
        $output->writeln(' - ' . $this->countLastnames . ' lastnames loaded');

        $userManager = $this->getContainer()->get('bns.user_manager');

        // Teacher process
        if ($countTeacher > 0) {
            $this->writeSection($output, '    # Creating teachers');

            $email = $input->getOption('email');
            if (null == $email) {
                $email = $dialog->ask($output, '    > Please, provide your e-mail address : ');
            }

            $pos        = strpos($email, '@');
            $emailFirst = substr($email, 0, $pos);
            $emailLast  = substr($email, $pos);

            $choice = $input->getOption('email-creation-choice');
            if (null == $choice) {
                $output->writeln(array(
                    '',
                    '    --------------------',
                    '    1: All teachers will have "' . $emailFirst . '+[firstname]_[lastname]' . $emailLast . '"',
                    '    2: All teachers will have "[firstname].[lastname]@beneyluschool.net"',
                    '    --------------------',
                    ''
                ));
            }

            while (null == $choice || !in_array($choice, array(1, 2))) {
                $choice = $dialog->ask($output, '    > Your choice ? [1]: ', 1);
            }

            $output->writeln('');
            Console::progress($output, $countTeacher);
            $usedData = array();

            for ($i = 0; $i < $countTeacher; $i++) {
                mt_srand();
                list($gender, $firstname, $lastname) = $this->generateUserData($usedData);

                if (1 == $choice) {
                    $userEmail = $emailFirst . '+' . strtolower(StringUtil::filterString($firstname)) . '.' . strtolower(StringUtil::filterString($lastname)) . $emailLast;
                }
                else {
                    $userEmail = strtolower(StringUtil::filterString($firstname)) . '.' . strtolower(StringUtil::filterString($lastname)) . '@beneyluschool.net';
                }

                $birthday = new ExtendedDateTime();
                $birthday->modify('-' . rand(25, 55) . 'year');
                $birthday->modify('-' . rand(1, 12) . 'month');
                $birthday->modify('-' . rand(1, 30) . 'day');

                $teacher = $userManager->createUser(array(
					'first_name' => $firstname,
					'last_name'  => $lastname,
					'email'		 => $userEmail,
					'birthday'   => $birthday,
                    'lang'       => 'fr',
                    'gender'     => $gender
				));

                $this->users['TEACHER'][] = $teacher;
                $classroomManager->assignTeacher($teacher);

                Console::progress($output, $countTeacher, $i + 1);
            }

            Console::progress($output, $countTeacher, $countTeacher, true);
        }

        // Pupil process
        if ($countPupil > 0) {
            $this->writeSection($output, '    # Creating pupils');
            Console::progress($output, $countPupil);
            $usedData = array();

            for ($i = 0; $i < $countPupil; $i++) {
                mt_srand();
                list($gender, $firstname, $lastname) = $this->generateUserData($usedData);

                $birthday = new ExtendedDateTime();
                $birthday->modify('-9 year');
                $birthday->modify('-' . rand(1, 12) . 'month');
                $birthday->modify('-' . rand(1, 30) . 'day');

                $pupil = $userManager->createUser(array(
					'first_name' => $firstname,
					'last_name'  => $lastname,
					'birthday'   => $birthday,
                    'lang'       => 'fr',
                    'gender'     => $gender
				));

                $this->users['PUPIL'][] = $pupil;
                $classroomManager->assignPupil($pupil);

                Console::progress($output, $countPupil, $i + 1);
            }

            Console::progress($output, $countPupil, $countPupil, true);
        }

        if (!$input->getOption('no-avatar')) {
            $this->writeSection($output, '    # Processing avatars');
            $this->uploadAvatars($input, $output, $group);
        }

        $this->writeSection($output, '    # List of accounts :');
        foreach ($this->users as $type => $usersType) {
            $output->writeln(' - ' . ucfirst($type) . ' :');
            foreach ($usersType as $user) {
                $output->writeln('   > Login: ' . $user->getLogin() . ' / Password: ' . $user->getPassword());
            }
            $output->writeln('');
        }

        // Clear API for current group
        $classroomManager->clearGroupCache();

        $this->writeSection($output, '    # All accounts has been created with successfully.');
    }

    /**
     * @param array $usedData
     *
     * @return array<String>
     */
    private function generateUserData(array $usedData)
    {
        $gender = rand(0, 10) <= 6 ? 'M' : 'F';
        if ('M' == $gender) {
            do {
                $firstname = $this->maleFirstnames[rand(0, $this->countMaleFirstnames - 1)];
            } while (in_array($firstname, $usedData));
            $usedData[] = $firstname;
        }
        else {
            do {
                $firstname = $this->femaleFirstnames[rand(0, $this->countFemaleFirstnames - 1)];
            } while (in_array($firstname, $usedData));
            $usedData[] = $firstname;
        }

        do {
            $lastname = $this->lastnames[rand(0, $this->countLastnames - 1)];
        } while (in_array($lastname, $usedData));
        $usedData[] = $lastname;

        return array(
            $gender,
            $firstname,
            $lastname
        );
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \BNS\App\CoreBundle\Model\Group $group
     */
    private function uploadAvatars(InputInterface $input, OutputInterface $output, Group $group)
    {
        $label = ResourceLabelGroupQuery::create('rlag')
           ->where('rlag.GroupId = ?', $group->getId())
           ->where('rlag.Label = ?', 'Avatars')
        ->findOne();

        if (null == $label) {
            $output->write(' - Creating resource avatar folder...	');
            $root = ResourceLabelGroupQuery::create('rlag')->findRoot($group->getId());
            $label = new ResourceLabelGroup();
            $label->setGroupId($group->getId());
            $label->setLabel('Avatars');
            $label->insertAsLastChildOf($root);
            $label->save();
            $output->write('Finished', true);

            $output->write(' - Retrieving avatars from folder...	');
            $finder = new Finder();
            $files  = $finder->files()->name('*')
                ->in(__DIR__ . '/../Resources/data/user/avatars')
            ;
            $count = count($files);
            $output->write($count . ' avatars.	Finished', true);

            $output->write(' - Uploading avatars...', true);
            $output->writeln('');
            Console::progress($output, $count);

            // Setting the author id, must be a teacher
            if (0 == count($this->users['TEACHER'])) {
                $classroomManager = $this->getContainer()->get('bns.classroom_manager')->setGroup($group);
                $this->users['TEACHER'] = $classroomManager->getTeachers();
            }

            $creator = $this->getContainer()->get('bns.resource_creator');
            $creator->setUser($this->users['TEACHER'][0]);
            $i = 0;

            foreach ($files as $path => $file) {
                $this->avatars[] = $creator->createResourceFromFile(
                    $path,
                    $file->getFileName(),
                    $file->getExtension(),
                    $file->getFileName(),
                    $label,
                    false
                );

                Console::progress($output, $count, ++$i);
            }

            Console::progress($output, $count, $count, true);
        }
        else {
            $output->write(' - Retrieving avatars from database...	');
            $this->avatars = ResourceQuery::create('r')
               ->join('r.ResourceLinkGroup rlg')
               ->join('rlg.ResourceLabelGroup rlag')
               ->where('rlag.Id = ?', $label->getId())
               ->where('r.TypeUniqueName = ?', 'IMAGE')
           ->find();
            $output->write(count($this->avatars) . ' avatars.	Finished', true);
        }

        $output->write(' - Setting avatars from all added users...	', true);
        $output->writeln('');
        $count = 0;
        $countAvatars = count($this->avatars);
        $i = 0;

        // Counting
        foreach ($this->users as $usersType) {
            foreach ($usersType as $user) {
                ++$count;
            }
        }

        Console::progress($output, $count);

        foreach ($this->users as $usersType) {
            foreach ($usersType as $user) {
                if (rand(0, 1) == 0) {
                    $user->getProfile()->setAvatarId($this->avatars[rand(0, $countAvatars - 1)]->getId());
                    $user->getProfile()->save();
                }

                Console::progress($output, $count, ++$i);
            }
        }

        Console::progress($output, $count, $count, true);
    }
}
