<?php

namespace BNS\App\InstallBundle\Command;

use BNS\App\CoreBundle\Model\EmailTemplate;
use BNS\App\CoreBundle\Model\EmailTemplateI18n;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeData;
use BNS\App\CoreBundle\Model\GroupTypeDataChoice;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplate;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplatePeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\RankQuery;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\HomeworkBundle\Model\HomeworkGroup;
use BNS\App\HomeworkBundle\Model\HomeworkPeer;
use BNS\App\ScolomBundle\Model\ScolomDataTemplate;
use BNS\App\ScolomBundle\Model\ScolomDataTemplateI18n;
use BNS\App\ScolomBundle\Model\ScolomTemplate;
use BNS\App\ScolomBundle\Model\ScolomTemplateI18n;
use BNS\App\PupilMonitoringBundle\Model\PupilLpc;
use BNS\App\PupilMonitoringBundle\Model\PupilLpcQuery;
use Propel\PropelBundle\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Exception\Exception;
use Symfony\Component\Yaml\Yaml;

/**
 * Create and load fixtures for BNS
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LoadCommand extends AbstractCommand
{
    /**
     * @var array<String>
     */
    private $fixturesData;

    /**
     * @var array<Group>
     */
    private $environments = array();

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('bns:load')
            ->setDescription('Load Initial Data')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connection to use')
            ->addOption('app-only', null, InputOption::VALUE_NONE, 'Only app will be initialized')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Initialization type: fast, normal, full', 'normal')
            ->addOption('firstname', null, InputOption::VALUE_NONE, 'Administrator first name')
            ->addOption('lastname', null, InputOption::VALUE_NONE, 'Administrator last name')
            ->addOption('email', null, InputOption::VALUE_NONE, 'Administrator email adress')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
        $con = \Propel::getConnection($connectionName);
        \Propel::setForceMasterConnection(true);

        try {
            //$con->beginTransaction();

            $this->flush($output);
            $this->generateModules($input, $output);
            $this->generateGroupTypeDataTemplates($input, $output);
            $this->generateGroupTypes($input, $output);

            if ($input->getOption('app-only')) {
                $this->generateEnvironmentsFromAuth($input, $output);
            } else {
                $this->generateEnvironments($input, $output);
            }

            $this->generateEmailTemplates($input, $output);
            $this->generateRules($input, $output);

            $this->generateScolom($input, $output);
            $this->generateLpc($input, $output);
            $this->generateUsers($input, $output);


            //$con->commit();
        } catch (Exception $e) {
            $con->rollBack();

            throw $e;
        }
    }

    /**
     * Flush REDIS cache
     *
     * @param OutputInterface $output
     */
    protected function flush(OutputInterface $output)
    {
        $this->writeSection($output, '# Deleting REDIS cache');
        $this->getContainer()->get('bns.api')->resetAll();
        $output->write(' - Finished', true);
    }

    /**
     * Module
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function generateModules(InputInterface $input, OutputInterface $output)
    {
        $this->writeSection($output, '# Installing modules');

        $modulesByType = array(
            'fast' => array('ADMIN', 'HELLOWORLD'),
            'normal' => array('ADMIN', 'CLASSROOM', 'HELLOWORLD','GROUP'),
            'full' => 'ALL'
        );

        /* @var $installManager \BNS\App\InstallBundle\Install\InstallManager */
        $installManager = $this->getContainer()->get('install_manager');
        $notInstalledModules = $installManager->getNotInstalledModules();
        $environment = $input->getParameterOption(array('--env', '-e'), getenv('SYMFONY_ENV') ? : 'app_dev');

        foreach ($notInstalledModules as $module) {
            if ('ALL' == $modulesByType[$input->getOption('type')] || in_array($module['unique_name'], $modulesByType[$input->getOption('type')])) {
                if ($module['unique_name'] == 'HELLOWORLD' && $environment != 'app_dev') {
                    continue;
                }

                $output->write('	> "' . $module['name'] . '" (' . $module['unique_name'] . ')...		');
                $installManager->install($module['unique_name'], true);
                $output->write("Finished", true);
            }
        }
    }

    /**
     * GroupTypeDataTemplate
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function generateGroupTypeDataTemplates(InputInterface $input, OutputInterface $output)
    {
        $groupTypeDataTemplates = $this->getFixturesData('group_type_data_templates');
        $count = count($groupTypeDataTemplates);
        $i = 0;
        $this->writeSection($output, '# Installing ' . $count . ' group type data template' . ($count > 1 ? 's' : ''));
        $this->progress($output, $count);

        foreach ($groupTypeDataTemplates as $groupTypeDataTemplateInfo) {
            // Creating main class
            $groupTypeDataTemplate = new GroupTypeDataTemplate();
            $groupTypeDataTemplate->setUniqueName($groupTypeDataTemplateInfo['unique_name']);
            $groupTypeDataTemplate->setType($groupTypeDataTemplateInfo['type']);

            if (isset($groupTypeDataTemplateInfo['default_value'])
                    && $groupTypeDataTemplateInfo['type'] == GroupTypeDataTemplatePeer::TYPE_SINGLE) {
                $groupTypeDataTemplate->setDefaultValue($groupTypeDataTemplateInfo['default_value']);
            }

            $groupTypeDataTemplate->save();

            // i18n process
//            foreach ($groupTypeDataTemplateInfo['i18n'] as $lang => $groupTypeDataTemplateI18nInfo) {
//                $groupTypeDataI18n = new GroupTypeDataTemplateI18n();
//                $groupTypeDataI18n->setLang($lang);
//                $groupTypeDataI18n->setUniqueName($groupTypeDataTemplate->getUniqueName());
//                $groupTypeDataI18n->setLabel($groupTypeDataTemplateI18nInfo['label']);
//
//                $groupTypeDataI18n->save();
//                $groupTypeDataTemplate->addGroupTypeDataTemplateI18n($groupTypeDataI18n);
//
//                // Cleaning memory
//                unset($groupTypeDataI18n);
//            }

            // Choices process
            if (!in_array($groupTypeDataTemplateInfo['type'], [
                GroupTypeDataTemplatePeer::TYPE_SINGLE,
                GroupTypeDataTemplatePeer::TYPE_TEXT,
                GroupTypeDataTemplatePeer::TYPE_BOOLEAN,
            ])) {
                foreach ($groupTypeDataTemplateInfo['choices'] as $groupTypeDataTemplateChoiceInfo) {
                    // Creating main class
                    $groupTypeDataTemplateChoice = new GroupTypeDataChoice();
                    $groupTypeDataTemplateChoice->setGroupTypeDataTemplateUniqueName($groupTypeDataTemplate->getUniqueName());
                    $groupTypeDataTemplateChoice->setValue($groupTypeDataTemplateChoiceInfo['value']);

                    $groupTypeDataTemplateChoice->save();

                    if (isset($groupTypeDataTemplateInfo['default_value']) && $groupTypeDataTemplateChoiceInfo['value'] == $groupTypeDataTemplateInfo['default_value']) {
                        $groupTypeDataTemplate->setDefaultValue($groupTypeDataTemplateChoice->getId());
                        $groupTypeDataTemplate->save();
                    }

                    // i18n choices process
//                    foreach ($groupTypeDataTemplateChoiceInfo['i18n']
//                    as $lang => $groupTypeDataTemplateChoiceI18nInfo) {
//                        $groupTypeDataTemplateChoiceI18n = new GroupTypeDataChoiceI18n();
//                        $groupTypeDataTemplateChoiceI18n->setId($groupTypeDataTemplateChoice->getId());
//                        $groupTypeDataTemplateChoiceI18n->setLang($lang);
//                        $groupTypeDataTemplateChoiceI18n->setLabel($groupTypeDataTemplateChoiceI18nInfo['value']);
//
//                        $groupTypeDataTemplateChoiceI18n->save();
//                        $groupTypeDataTemplateChoice->addGroupTypeDataChoiceI18n($groupTypeDataTemplateChoiceI18n);
//
//                        // Cleaning memory
//                        unset($groupTypeDataTemplateChoiceI18n);
//                    }

                    // Cleaning memory
                    unset($groupTypeDataTemplateChoice);
                }
            }

            // Cleaning memory
            unset($groupTypeDataTemplate);

            // Update progress bar
            $this->progress($output, $count, ++$i);
        }

        $this->progress($output, $count, $count, true);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function generateGroupTypes(InputInterface $input, OutputInterface $output)
    {
        $groupTypes = $this->getFixturesData('group_types');
        $count = count($groupTypes);
        $i = 0;
        $this->writeSection($output, '# Installing ' . $count . ' group type' . ($count > 1 ? 's' : ''));
        $this->progress($output, $count);

        foreach ($groupTypes as $groupTypeInfo) {
            // Creating main class
            $groupTypeParams = array(
                'type' => $groupTypeInfo['type'],
                'centralize' => $groupTypeInfo['centralize'],
                'label' => isset($groupTypeInfo['i18n']['fr']['label']) ? $groupTypeInfo['i18n']['fr']['label'] : $groupTypeInfo['type'],
                'simulate_role' => $groupTypeInfo['simulate_role']
            );

            if (isset($groupTypeInfo['is_recursive'])) {
                $groupTypeParams['is_recursive'] = $groupTypeInfo['is_recursive'];
            }

            $groupType = $this->getContainer()->get('bns.group_manager')->createGroupType($groupTypeParams, !$input->getOption('app-only'));
            foreach ($groupTypeInfo['data'] as $groupTypeDataTemplateUniqueName) {
                $groupTypeData = new GroupTypeData();
                $groupTypeData->setGroupTypeId($groupType->getId());
                $groupTypeData->setGroupTypeDataTemplateUniqueName($groupTypeDataTemplateUniqueName);
                $groupTypeData->save();

                // Adding GroupTypeData into main class
                $groupType->addGroupTypeData($groupTypeData);

                // Cleaning memory
                unset($groupTypeData);
            }

            // Cleaning memory
            unset($groupType);

            // Update progress bar
            $this->progress($output, $count, ++$i);
        }

        $this->progress($output, $count, $count, true);
    }

    /**
     * Environment
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function generateEnvironments(InputInterface $input, OutputInterface $output)
    {
        $environments = $this->getFixturesData('environments');
        $count = count($environments);
        $this->writeSection($output, '# Installing ' . $count . ' environment' . ($count > 1 ? 's' : ''));
        $i = 0;
        $this->progress($output, $count);

        foreach ($environments as $environment) {
            $environment = $this->getContainer()->get('bns.group_manager')->createEnvironment(array(
                'label' => $environment,
            ));

            // Saving into object to avoid db access
            $this->environments[] = $environment;

            // Cleaning memory
            unset($environment);

            // Update progress bar
            $this->progress($output, $count, ++$i);
        }

        $this->progress($output, $count, $count, true);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function generateEnvironmentsFromAuth(InputInterface $input, OutputInterface $output)
    {
        $this->writeSection($output, '# Retrieving environment(s) from auth');
        $environments = array();
        $finalEnvs = array();

        do {
            $environment = $this->getHelperSet()->get('dialog')->ask($output, '	> Please, provide an AUTH environment label to copy in APP: ');
        } while ('' == $environment);

        $environments[] = $environment;
        $output->writeln('');
        $output->writeln('');
        $output->write(' - Retrieving "' . $environment . '" environment from AUTH...');

        $authEnvs = $this->getContainer()->get('bns.api')->send('group_read_by_label', array(
            'route' => array(
                'label' => $environment,
                'type' => 'ENVIRONMENT'
            )
        ));

        $authCount = count($authEnvs);

        // There is more than one environment, select by #ID process
        if ($authCount > 1) {
            do {
                $output->writeln('');
                $output->write('	> /!\ There is more than one environment with this label (#ID: ');

                foreach ($authEnvs as $i => $authEnv) {
                    $output->write($authEnv['id'] . ($i + 1 < $authCount ? ',' : ')'));
                }

                $id = $this->getHelperSet()->get('dialog')->ask($output, '. Please, select the right #ID: ');
                $found = false;

                foreach ($authEnvs as $authEnv) {
                    if ($authEnv['id'] == $id) {
                        $finalEnvs[] = $authEnv;
                        $found = true;
                        break;
                    }
                }
            } while (!$found);
        } elseif ($authCount == 0) {
            throw new \InvalidArgumentException('There is no environment with name : ' . $environment . ' !');
        } else {
            $finalEnvs[] = $authEnvs[0];
        }

        $output->write('	Finished', true);

        // Install process
        $count = count($finalEnvs);
        $this->writeSection($output, '# Installing ' . $count . ' environment' . ($count > 1 ? 's' : ''));
        $i = 0;
        $this->progress($output, $count);

        foreach ($finalEnvs as $env) {
            $this->environments[] = GroupPeer::createGroup($env);

            $this->progress($output, $count, ++$i);
        }

        $this->progress($output, $count, $count, true);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function generateEmailTemplates(InputInterface $input, OutputInterface $output)
    {
        $emailTemplates = $this->getFixturesData('email_templates');
        $count = count($emailTemplates);
        $this->writeSection($output, '# Installing ' . $count . ' email template' . ($count > 1 ? 's' : ''));
        $i = 0;
        $this->progress($output, $count);

        foreach ($emailTemplates as $uniqueName => $emailTemplateInfo) {
            // Creating main class
            $emailTemplate = new EmailTemplate();
            $emailTemplate->setUniqueName($uniqueName);
            $emailTemplate->setVars($emailTemplateInfo['vars']);
            $emailTemplate->save();

            // i18n process
            foreach ($emailTemplateInfo['i18n'] as $lang => $emailTemplateI18nInfo) {
                $emailTemplateI18n = new EmailTemplateI18n();
                $emailTemplateI18n->setUniqueName($emailTemplate->getUniqueName());
                $emailTemplateI18n->setLang($lang);
                $emailTemplateI18n->setSubject($emailTemplateI18nInfo['subject']);
                $emailTemplateI18n->setLabel($emailTemplateI18nInfo['label']);
                $emailTemplateI18n->setHtmlBody(file_get_contents(__DIR__ . '/../Resources/install/email_templates/htmls/' . $emailTemplate->getUniqueName() . '_' . $lang . '.html'));
                $emailTemplateI18n->setPlainBody(file_get_contents(__DIR__ . '/../Resources/install/email_templates/plains/' . $emailTemplate->getUniqueName() . '_' . $lang . '.txt'));

                // Finally
                $emailTemplateI18n->save();

                // Adding i18n into main class
                $emailTemplate->addEmailTemplateI18n($emailTemplateI18n);

                // Cleaning memory
                unset($emailTemplateI18n);
            }

            $emailTemplate->save();

            // Cleaning memory
            unset($emailTemplate);

            // Update progress bar
            $this->progress($output, $count, ++$i);
        }

        $this->progress($output, $count, $count, true);
    }

    /**
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function generateRules(InputInterface $input, OutputInterface $output)
    {
        $rules = $this->getFixturesData('rules');
        $i = 0;
        $availableRanks = RankQuery::create()->find();
        $rulesByType = array(
            'fast' => array('ADMIN_IN_ENV'),
            'normal' => array('ADMIN_IN_ENV', 'PUPIL_IN_CLASSROOM', 'PARENT_IN_CLASSROOM', 'TEACHER_IN_CLASSROOM'),
            'full' => 'ALL'
        );

        $count = count($input->getOption('type') == 'full' ? $rules : $rulesByType[$input->getOption('type')]);
        $this->writeSection($output, '# Installing ' . $count . ' rule' . ($count > 1 ? 's' : ''));
        $this->progress($output, $count);


        foreach ($rules as $name => $ruleInfo) {
            if ('ALL' == $rulesByType[$input->getOption('type')] || in_array($name, $rulesByType[$input->getOption('type')])) {
                $whoGroupTypeRole = GroupTypeQuery::create()->findOneByType($ruleInfo['who']['group_type']);
                $whereGroupType = GroupTypeQuery::create()->findOneByType($ruleInfo['where']['group_type']);

                if ($whoGroupTypeRole == null || $whereGroupType == null) {
                    throw new \RuntimeException('Some group types are missing !');
                }

                foreach ($this->environments as $environment) {
                    $values = array(
                        'who_group' => array(
                            'domain_id' => $this->getContainer()->getParameter('domain_id'),
                            'group_type_id' => $whoGroupTypeRole->getId(),
                            'group_parent_id' => $environment->getId()
                        ),
                        'rule_where' => array(
                            'group_id' => $environment->getId(),
                            'group_type_id' => $whereGroupType->getId(),
                            'belongs' => $ruleInfo['where']['belong']
                        ),
                        'state' => $ruleInfo['state']
                    );

                    foreach ($ruleInfo['rank_unique_name']['value'] as $rankUniqueName) {
                        $found = false;
                        foreach ($availableRanks as $rank) {
                            if ($rank->getUniqueName() == $rankUniqueName) {
                                $found = true;
                                break;
                            }
                        }

                        if (!$found) {
                            continue;
                        }

                        $values['rank_unique_name'] = $rankUniqueName;

                        $this->getContainer()->get('bns.api')->send('rule_create', array(
                            'values' => $values
                        ));
                    }
                }

                // Update progress bar
                $this->progress($output, $count, ++$i);
            }
        }

        $this->progress($output, $count, $count, true);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function generateLpc(InputInterface $input, OutputInterface $output)
    {
        $lpcData = \Spyc::YAMLLoad(__DIR__ . '/../Resources/install/lpc/lpc_data.yml');
        $this->writeSection($output, '# Installing LPC data');
        PupilLpcQuery::create()->deleteAll();
        $root = new PupilLpc();
        $root->makeRoot();
        $root->setLabel('root');
        $root->save();
        foreach($lpcData as $key => $competences)
        {
            $palier = new PupilLpc();
            $palier->setLabel($key);
            $palier->setType('PALIER');
            $palier->insertAsLastChildOf($root);
            $palier->save();
            foreach($competences as $key => $domains)
            {
                $competence = new PupilLpc();
                $competence->setLabel($key);
                $competence->setType('COMPETENCE');
                $competence->insertAsLastChildOf($palier);
                $competence->save();
                foreach($domains as $key => $items)
                {
                    $domain = new PupilLpc();
                    $domain->setLabel($key);
                    $domain->setType('DOMAINE');
                    $domain->insertAsLastChildOf($competence);
                    $domain->save();
                    foreach($items as $itemLabel)
                    {
                        $item = new PupilLpc();
                        $item->setLabel($itemLabel);
                        $item->setType('ITEM');
                        $item->insertAsLastChildOf($domain);
                        $item->save();
                    }
                }
            }
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function generateScolom(InputInterface $input, OutputInterface $output)
    {
        $scolomData = \Spyc::YAMLLoad(__DIR__ . '/../Resources/install/scolom/scolom_data.yml');
        $count = count($scolomData);
        $i = 0;

        $this->writeSection($output, '# Installing ' . $count . ' ScoLOM data');
        $this->progress($output, $count);

        foreach ($scolomData as $uniqueName => $data) {
            $scolom = new ScolomTemplate();
            $scolom->setUniqueName($uniqueName);
            $scolom->setType($data['type']);

            if (isset($data['parent_scolom'])) {
                $scolom->setParentScolomUniqueName($data['parent_scolom']);
            }

            $scolom->save();

            // i18n process
            foreach ($data['i18n'] as $lang => $i18n) {
                $scolomI18n = new ScolomTemplateI18n();
                $scolomI18n->setUniqueName($scolom->getUniqueName());
                $scolomI18n->setLang($lang);
                $scolomI18n->setLabel($i18n['label']);
                $scolomI18n->setDescription($i18n['description']);
                $scolomI18n->save();
            }

            // Choice process
            if (isset($data['choices'])) {
                foreach ($data['choices'] as $choiceUniqueName => $choice) {
                    $scolomChoice = new ScolomDataTemplate();
                    $scolomChoice->setScolomUniqueName($scolom->getUniqueName());
                    $scolomChoice->setUniqueName($choiceUniqueName);
                    $scolomChoice->save();

                    // i18n process
                    foreach ($choice['i18n'] as $lang => $i18n) {
                        $scolomChoiceI18n = new ScolomDataTemplateI18n();
                        $scolomChoiceI18n->setId($scolomChoice->getId());
                        $scolomChoiceI18n->setLang($lang);
                        $scolomChoiceI18n->setLabel($i18n['label']);

                        if (isset($i18n['description'])) {
                            $scolomChoiceI18n->setDescription($i18n['description']);
                        }

                        $scolomChoiceI18n->save();
                    }
                }
            }

            $this->progress($output, $count, ++$i);
        }

        $this->progress($output, $count, $count, true);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function generateUsers(InputInterface $input, OutputInterface $output)
    {
        /* @var $userManager \BNS\App\CoreBundle\User\BNSUserManager */
        $userManager = $this->getContainer()->get('bns.user_manager');
        /* @var $roleManager \BNS\App\CoreBundle\Role\BNSRoleManager */
        $roleManager = $this->getContainer()->get('bns.role_manager');
        $environment = $input->getParameterOption(array('--env', '-e'), getenv('SYMFONY_ENV') ? : 'app_dev');
        $users = $this->getFixturesData('users');

        $this->writeSection($output, '# Generating initial users & groups');

        // Questions part
        $firstName = $input->getOption('firstname');
        if (null == $firstName) {
            $firstName = $this->getHelperSet()->get('dialog')->ask($output, '	> Please, provide your first name : ');
        }

        $lastName = $input->getOption('lastname');
        if ('app_prod' == $environment && null == $lastName) {
            $lastName = $this->getHelperSet()->get('dialog')->ask($output, '	> Please, provide your last name : ');
        }

        $email = $input->getOption('email');
        if (null == $email) {
            $email = $this->getHelperSet()->get('dialog')->ask($output, '	> Please, provide your email : ');
        }

        $output->writeln('');
        $output->write(' - Creating administrator...	');
        if ($input->getOption('app-only')) {
            $createAuth = true;
            $administrator = null;

            try {
                $administrator = $userManager->getUserFromCentral('administrateur');
            } catch (NotFoundHttpException $e) {
                // Nothing
            }

            if (is_array($administrator)) {
                $output->write('already exists in AUTH, skipped. Creating APP only...	');
                $createAuth = false;
            }

            // Finally
            $admin = $userManager->createUser($users['ADMINISTRATOR'] + array(
                'email' => $email,
                'birthday' => new \DateTime()
            ), true, $createAuth);

            if ($createAuth) {
                $output->write('Login: ' . $admin->getLogin() . ' / Password: ' . $admin->getPassword() . '	');
            }
        } else {
            $admin = $userManager->createUser($users['ADMINISTRATOR'] + array(
                'email' => $email,
                'birthday' => new \DateTime()
            ), true);
        }
        $output->write('Finished', true);

        // Assign role to admin
        $roleManager
            ->setGroupTypeRole(GroupTypeQuery::create()->findOneByType('ADMIN'))
            ->assignRole($admin, $this->environments[0]->getId())
        ;

        // certify admin
        $userManager->updateUserLogin($admin, 'admin', true);

        if ($input->getOption('type') != 'fast') {
            $groupManager = $this->getContainer()->get('bns.group_manager');
            $classroomManager = $this->getContainer()->get('bns.classroom_manager');
            $groupsInfo = $this->getFixturesData('groups');

            // Creating city
            $output->write(' - Creating cities...		');
            $city1Info = $groupsInfo['CITY_1'];
            $params['label'] = str_replace('%first_name%', $firstName, $city1Info['name']);
            $params['type'] = 'CITY';
            $params['validated'] = true;
            $city1 = $groupManager->createGroup($params);
            $city1->setAttribute('NAME', str_replace('%first_name%', $firstName, $city1Info['name']));
            $city1->setAttribute('INSEE_ID', $city1Info['insee_id']);
            $groupManager->linkGroupWithSubgroup($this->environments[0]->getId(), $city1->getId());
            $output->write('Finished', true);

            $city2Info = $groupsInfo['CITY_2'];
            $params['label'] = str_replace('%first_name%', $firstName, $city2Info['name']);
            $params['type'] = 'CITY';
            $params['validated'] = true;
            $city2 = $groupManager->createGroup($params);
            $city2->setAttribute('NAME', str_replace('%first_name%', $firstName, $city2Info['name']));
            $city2->setAttribute('INSEE_ID', $city2Info['insee_id']);
            $groupManager->linkGroupWithSubgroup($this->environments[0]->getId(), $city2->getId());
            $output->write('Finished', true);

            // Creating school
            $output->write(' - Creating school...		');
            $school1Info = $groupsInfo['SCHOOL_1'];
            $params['label'] = str_replace('%first_name%', $firstName, $school1Info['name']);
            $params['type'] = 'SCHOOL';
            $params['validated'] = true;
            $school1 = $groupManager->createGroup($params);
            $school1->setAttribute('NAME', str_replace('%first_name%', $firstName, $school1Info['name']));
            $school1->setAttribute('UAI', $school1Info['uai']);
            $school1->setAttribute('ADDRESS', $school1Info['address']);
            $school1->setAttribute('CITY', $school1Info['city']);
            $school1->setAttribute('EMAIL', $email);
            $groupManager->linkGroupWithSubgroup($this->environments[0]->getId(), $school1->getId());
            $output->write('Finished', true);

            $output->write(' - Creating school...		');
            $school2Info = $groupsInfo['SCHOOL_2'];
            $params['label'] = str_replace('%first_name%', $firstName, $school2Info['name']);
            $params['type'] = 'SCHOOL';
            $params['validated'] = true;
            $school2 = $groupManager->createGroup($params);
            $school2->setAttribute('NAME', str_replace('%first_name%', $firstName, $school2Info['name']));
            $school2->setAttribute('UAI', $school2Info['uai']);
            $school2->setAttribute('ADDRESS', $school2Info['address']);
            $school2->setAttribute('CITY', $school2Info['city']);
            $school2->setAttribute('EMAIL', $email);
            $groupManager->linkGroupWithSubgroup($this->environments[0]->getId(), $school2->getId());
            $output->write('Finished', true);

            // Creating classroom
            $output->write(' - Creating classroom...	');
            $classroom1Info = $groupsInfo['CLASSROOM_1'];
            $params['label'] = str_replace('%first_name%', $firstName, $classroom1Info['name']);
            $params['attributes']['LEVEL'] = $classroom1Info['levels'];
            $params['attributes']['LANGUAGE'] = $classroom1Info['lang'];
            $params['attributes']['STRUCTURE_ID'] = $classroom1Info['structure_id'];
            $params['validated'] = true;
            $params['group_parent_id'] = $school1->getId();
            $classroom1 = $classroomManager->createClassroom($params);
            $groupManager->linkGroupWithSubgroup($school1->getId(), $classroom1->getId());
            $output->write('Finished', true);

            $classroom2Info = $groupsInfo['CLASSROOM_2'];
            $params['label'] = str_replace('%first_name%', $firstName, $classroom2Info['name']);
            $params['attributes']['LEVEL'] = $classroom2Info['levels'];
            $params['attributes']['LANGUAGE'] = $classroom2Info['lang'];
            $params['attributes']['STRUCTURE_ID'] = $classroom2Info['structure_id'];
            $params['validated'] = true;
            $params['group_parent_id'] = $school2->getId();
            $classroom2 = $classroomManager->createClassroom($params);
            $groupManager->linkGroupWithSubgroup($school2->getId(), $classroom2->getId());
            $output->write('Finished', true);

            // Creating teacher
            $output->write(' - Creating teacher...		');
            if ('app_prod' == $environment) {
                $teacher = $userManager->createUser(array_merge($users['TEACHER'], array(
                    'username' => 'temporary',
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'birthday' => new \DateTime()
                )), true);

                $output->write('Login: ' . $teacher->getLogin() . ' / Password: ' . $teacher->getPassword() . '	');
            } else {
                $teacher = $userManager->createUser($users['TEACHER'] + array(
                    'first_name' => $firstName,
                    'email' => $this->customizeEmail($email, isset($users['TEACHER']['username'])? $users['TEACHER']['username'] : 'teacher'),
                    'birthday' => new \DateTime()
                ), true);
            }
            $classroomManager->assignTeacher($teacher);
            $output->write('Finished', true);

            // Creating director
            $output->write(' - Creating director...		');
            if ('app_prod' == $environment) {
                $director = $userManager->createUser(array_merge($users['DIRECTOR'], array(
                    'username' => 'temporary',
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'birthday' => new \DateTime()
                )), true);

                $output->write('Login: ' . $director->getLogin() . ' / Password: ' . $director->getPassword() . '	');
            } else {
                $director = $userManager->createUser($users['DIRECTOR'] + array(
                    'first_name' => $firstName,
                    'email' => $this->customizeEmail($email, isset($users['DIRECTOR']['username'])? $users['DIRECTOR']['username'] : 'director'),
                    'birthday' => new \DateTime()
                ), true);
            }
            $classroomManager->assignTeacher($director);
            $output->write('Finished', true);

            // Creating pupil & parent
            $output->write(' - Creating pupil & parent...	');
            if ('app_prod' == $environment) {
                $pupil = $userManager->createUser(array_merge($users['PUPIL'], array(
                    'username' => 'temporary',
                    'first_name' => $firstName,
                    'birthday' => new \DateTime()
                )), true);

                $output->write('Login: ' . $pupil->getLogin() . ' / Password: ' . $pupil->getPassword() . '	');
            } else {
                $pupil = $userManager->createUser($users['PUPIL'] + array(
                    'first_name' => $firstName,
                    'birthday' => new \DateTime()
                ), true);
            }

            $classroomManager->assignPupil($pupil);
            $output->write('Finished', true);

            // second hierarchy + automatic users
            if ($input->getOption('type') === 'full') {
                $output->write(' - Creating second set of groups and users...		');
                $city = null;
                $school = null;
                $classroom = null;
                $teamManager = $this->getContainer()->get('bns.team_manager');
                $groupsAutoInfo = $this->getFixturesData('groups_auto');
                foreach ($groupsAutoInfo as $groupInfo) {
                    $params = [
                        'type' => $groupInfo['type'],
                        'label' => 'Test - ' . ucfirst(strtolower($groupInfo['type'])),
                    ];
                    if (isset($groupInfo['id'])) {
                        $params['id'] = $groupInfo['id'];
                    }
                    if (isset($groupInfo['lang']) && $groupInfo['type'] !== 'TEAM') {
                        $params['attributes']['LANGUAGE'] = $groupInfo['lang'];
                    }
                    $group = $groupManager->createGroup($params);
                    $parentIds = $groupInfo['parent'];
                    if (!is_array($parentIds)) {
                        $parentIds = [$parentIds];
                    }
                    foreach ($parentIds as $parentId) {
                        $groupManager->linkGroupWithSubgroup($parentId, $group->getId());
                    }

                    if ('CITY' === $group->getType()) {
                        $city = $group;
                    } else if ('SCHOOL' === $group->getType()) {
                        $group->togglePremium();
                        $school = $group;
                    } else if ('CLASSROOM' === $group->getType()) {
                        $classroom = $group;
                        $classroomManager->setClassroom($classroom);
                    } else if ('TEAM' === $group->getType()) {
                        $teamManager->setTeam($group);
                    }
                }

                $defaultData = [
                    'last_name' => 'Test',
                    'lang' => 'fr',
                ];


                $cityRef = $userManager->createUser($defaultData + [
                        'username' => 'referentville2',
                        'first_name' => $firstName,
                        'email' => $this->customizeEmail($email, 'referentville2'),
                        'birthday' => new \DateTime()
                    ], true);
                $roleManager->setGroupTypeRoleFromType('CITY_REFERENT')
                    ->assignRole($cityRef, $city->getId());

                $director = $userManager->createUser($defaultData + [
                    'username' => 'directeur2',
                    'first_name' => $firstName,
                    'email' => $this->customizeEmail($email, 'directeur2'),
                    'birthday' => new \DateTime()
                ], true);
                $roleManager->setGroupTypeRoleFromType('DIRECTOR')
                    ->assignRole($director, $school->getId());

                $teacher = $userManager->createUser($defaultData + [
                    'username' => 'enseignant2',
                    'first_name' => $firstName,
                    'email' => $this->customizeEmail($email, 'enseignant2'),
                    'birthday' => new \DateTime()
                ], true);
                $classroomManager->assignTeacher($teacher);
                $teamManager->assignTeacher($teacher);

                for ($i = 1; $i <= 5; $i++) {
                    $pupil = $userManager->createUser($defaultData + [
                        'username' => 'eleve'.$i,
                        'first_name' => 'Eleve '.$i,
                        'birthday' => new \DateTime()
                    ], true);
                    $classroomManager->assignPupil($pupil);
                    if ($i <= 3) {
                        $teamManager->assignPupil($pupil);
                        foreach ($pupil->getParents() as $parent) {
                            $teamManager->assignParent($parent);
                        }
                    }
                }

                $output->write('Finished', true);
            }
        }
    }

    protected function installApplications(InputInterface $input, OutputInterface $output)
    {
        $apps = $this->getFixturesData('applications');
        $this->writeSection($output, '# Installing ' . count($apps) . ' applications');
        foreach ($apps as $app => $groupTypes) {
            $output->write(' - Installing ' . $app . '	');
            $groups = GroupQuery::create()
                ->useGroupTypeQuery()
                    ->filterByType($groupTypes)
                ->endUse()
                ->find()
            ;
            $this->installAndOpenApplicationInGroups($app, $groups);
            $output->write(' Finished', true);
        }
    }

    protected function generateHomeworks(InputInterface $input, OutputInterface $output)
    {
        $homeworkManager = $this->getContainer()->get('bns.homework_manager');
        $homeworkDatas = $this->getFixturesData('homeworks');
        $count = count($homeworkDatas);
        $this->writeSection($output, '# Installing ' . $count . ' homework' . ($count > 1 ? 's' : ''));
        $i = 0;
        $this->progress($output, $count);

        foreach ($homeworkDatas as $data) {
            $homework = new Homework();
            $homework->fromArray($data, \BasePeer::TYPE_FIELDNAME);
            if (isset($data['groups'])) {
                foreach ($data['groups'] as $id) {
                    $hg = new HomeworkGroup();
                    $hg->setGroupId($id);
                    $homework->addHomeworkGroup($hg);
                }
            }
            $homeworkManager->processHomework($homework);
            $homework->save();

            // Update progress bar
            $this->progress($output, $count, ++$i);
        }

        $this->progress($output, $count, $count, true);
    }

    /**
     * @param string $key
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function getFixturesData($key = null)
    {
        if (!isset($this->fixturesData)) {
            $this->fixturesData = Yaml::parse(__DIR__ . '/../Resources/install/fixtures_data.yml');
        }

        if (null == $key) {
            return $this->fixturesData;
        }

        if (!isset($this->fixturesData[$key])) {
            throw new \InvalidArgumentException('The key "' . $key . '" for the install fixtures data does NOT exist !');
        }

        return $this->fixturesData[$key];
    }

    /**
     * @param OutputInterface $output
     * @param int	  $size
     * @param int	  $progress
     * @param bool    $newLine
     */
    protected function progress(OutputInterface $output, $size, $progress = 0, $newLine = false)
    {
        if (0 == $progress) {
            $output->write('	> ');
        }

        if ($progress > 0) {
            $output->write(str_repeat("\x08", $size + 2));
        }

        $progressBar = '[';
        for ($i = 0; $i < $size; $i++) {
            $progressBar .= $i < $progress ? '=' : ' ';
        }
        $progressBar .= ']';

        $output->write($progressBar, $newLine);
    }

    protected function customizeEmail($email, $custom)
    {
        if (false === strpos($email, '+')) {
            $email = str_replace('@', '+' . $custom . '@', $email);
        }

        return $email;
    }

    protected function installAndOpenApplicationInGroups($name, $classrooms)
    {
        foreach ($classrooms as $classroom) {
            $this->getContainer()->get('bns_core.application_manager')->installApplication($name, $classroom);
        }
    }
}
