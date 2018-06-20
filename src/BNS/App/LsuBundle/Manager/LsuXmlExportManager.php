<?php

namespace BNS\App\LsuBundle\Manager;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\LsuBundle\Model\Lsu;
use BNS\App\LsuBundle\Model\LsuComment;
use BNS\App\LsuBundle\Model\LsuCommentQuery;
use BNS\App\LsuBundle\Model\LsuDomain;
use BNS\App\LsuBundle\Model\LsuDomainQuery;
use BNS\App\LsuBundle\Model\LsuPosition;
use BNS\App\LsuBundle\Model\LsuPositionQuery;
use BNS\App\LsuBundle\Model\LsuTemplate;
use BNS\App\LsuBundle\Model\LsuTemplateDomainDetail;
use BNS\App\LsuBundle\Model\LsuTemplateDomainDetailQuery;
use BNS\App\LsuBundle\Model\LsuTemplateQuery;

/**
 * Class LsuXmlExportManager
 *
 * @package BNS\App\LsuBundle\Manager
 */
class LsuXmlExportManager
{
    const LSU_VERSION = 'v2016';
    const LSU_COURSES = ['PAR_CIT', 'PAR_ART', 'PAR_SAN'];

    const LSU_PREFIX_USER = 'U_';
    const LSU_PREFIX_DIRECTOR = 'D_';
    const LSU_PREFIX_GROUP = 'G_';
    const LSU_PREFIX_TEMPLATE = 'P_';
    const LSU_PREFIX_DOMAIN_DETAIL = 'EP_';

    const XML_VERSION = '1.0';
    const XML_ENCODING = 'UTF-8';
    const XMLNS = 'urn:fr:edu:scolarite:lsun:bilans:import';
    const XMLNS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    const XSI_LOCATION = 'urn:fr:edu:scolarite:lsun:bilans:import import-bilan-1d.xsd';
    const SCHEMA_VERSION = '1.0';

    /**
     * The document being built
     *
     * @var \DOMDocument
     */
    protected $document;

    /**
     * The main document node holding our data
     *
     * @var \DOMElement
     */
    protected $data;

    /**
     * @var Lsu[]
     */
    protected $lsus;

    /**
     * @var Group[]
     */
    protected $classrooms;

    /**
     * @var Group
     */
    protected $school;

    /**
     * @var User[]
     */
    protected $pupils;

    /**
     * @var LsuTemplate[]
     */
    protected $templates;

    /**
     * @var array
     */
    protected $pupilIdsByTemplateId;

    /**
     * Teacher ids grouped by LSU template id
     *
     * @var array
     */
    protected $teacherIdsByTemplateId;

    /**
     * @var User[]
     */
    protected $teachers;

    /**
     * @var User[]
     */
    protected $directors;

    /**
     * @var LsuTemplateDomainDetail[]
     */
    protected $details;

    /**
     * LsuDetail ids by LsuTemplate id by Lsu id
     *
     * @var array
     */
    protected $detailIdsByTemplateAndDomainId;

    /**
     * LsuPosition by LsuDomain id by Lsu id
     *
     * @var array
     */
    protected $positionByLsuAndDomainId;

    /**
     * LsuComment by LsuDomain id by Lsu id
     *
     * @var array
     */
    protected $commentByLsuAndDomainId;

    /**
     * @var BNSGroupManager
     */
    protected $groupManager;

    /**
     * @var BNSUserManager
     */
    protected $userManager;

    public function __construct(BNSGroupManager $groupManager, BNSUserManager $userManager)
    {
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
    }

    /**
     * @param Lsu|Lsu[] $lsus
     * @return string
     */
    public function export($lsus)
    {
        if (!(is_array($lsus) || $lsus instanceof \Traversable)) {
            $lsus = [$lsus];
        }

        $this->loadModels($lsus);

        $this->initDocument();
        $this->writeDirectors();
        $this->writeClassrooms();
        $this->writePupils();
        $this->writePeriods();
        $this->writeTeachers();
        $this->writeDetails();
        $this->writeCommonCourses();
        $this->writePeriodicAssessments();
        $this->writeCycleAssessments();

        return $this->document->saveXML();
    }

    /**
     * @param LSU[] $lsus
     */
    protected function loadModels($lsus)
    {
        $pupilIds = [];
        $templateIds = [];
        $groupIds = [];
        $pupilIdsByTemplateId = [];

        $this->lsus = [];

        // get all templates, all users and all users grouped by template
        foreach ($lsus as $lsu) {
            $this->lsus[$lsu->getId()] = $lsu;
            $templateIds[] = $lsu->getTemplateId();
            $pupilIds[] = $lsu->getUserId();
            $pupilIdsByTemplateId[$lsu->getTemplateId()][] = $lsu->getUserId();
        }
        foreach ($pupilIdsByTemplateId as $tplId => $ids) {
            $pupilIdsByTemplateId[$tplId] = array_unique($ids);
        }
        $this->pupilIdsByTemplateId = $pupilIdsByTemplateId;
        $this->templates = LsuTemplateQuery::create()->joinWith('LsuConfig')->findPks($templateIds)->getArrayCopy('Id');

        // get all groups and group by template
        foreach ($this->templates as $template) {
            $groupIds[] = $template->getLsuConfig()->getGroupId();
        }

        // get all classrooms
        $this->classrooms = GroupQuery::create()
            ->filterById($groupIds)
            ->useGroupTypeQuery()
                ->filterByType('CLASSROOM')
            ->endUse()
            ->find()
            ->getArrayCopy('Id');

        if (count($this->classrooms)) {
            $this->school = $this->groupManager->setGroup(reset($this->classrooms))->getParent();
        }

        // get all pupils
        $this->pupils = UserQuery::create()->findPks($pupilIds)->getArrayCopy('Id');

        // get all teachers and teachers by template
        $this->teacherIdsByTemplateId = [];
        $teacherIds = [];
        foreach ($this->templates as $template) {
            $classroom = $this->classrooms[$template->getLsuConfig()->getGroupId()];
            $classroomTeacherIds = $this->groupManager->setGroup($classroom)->getUsersByRoleUniqueNameIds('TEACHER');
            $this->teacherIdsByTemplateId[$template->getId()] = $classroomTeacherIds;
            $teacherIds = array_merge($teacherIds, $classroomTeacherIds);
        }
        $this->teachers = UserQuery::create()->findPks($teacherIds)->getArrayCopy('Id');

        if ($this->school) {
            $this->directors = $this->groupManager->setGroup($this->school)->getUsersByRoleUniqueName('DIRECTOR', true);
            if (!count($this->directors)) {
                $this->directors = $this->groupManager->setGroup($this->school)->getUsersByRoleUniqueName('ENT_REFERENT', true);
            }
        }
        if (!count($this->directors) && count($this->teachers)) {
            $this->directors = [reset($this->teachers)];
        }

        $this->details = LsuTemplateDomainDetailQuery::create()
            ->filterByTemplateId($templateIds)
            ->find()
            ->getArrayCopy('Id');
        foreach ($this->details as $detail) {
            $this->detailIdsByTemplateAndDomainId[$detail->getTemplateId()][$detail->getDomainId()][] = $detail->getId();
        }

        /** @var LsuPosition[] $positions */
        $positions = LsuPositionQuery::create()
            ->filterByLsuId(array_keys($this->lsus))
            ->find()
            ->getArrayCopy();
        foreach ($positions as $position) {
            $this->positionByLsuAndDomainId[$position->getLsuId()][$position->getDomainId()] = $position;
        }

        /** @var LsuComment[] $comments */
        $comments = LsuCommentQuery::create()
            ->filterByLsuId(array_keys($this->lsus))
            ->find()
            ->getArrayCopy('Id');

        foreach ($comments as $comment) {
            $this->commentByLsuAndDomainId[$comment->getLsuId()][$comment->getDomainId()] = $comment;
        }
    }

    protected function initDocument()
    {
        $this->document = new \DOMDocument(self::XML_VERSION, self::XML_ENCODING);
        $this->document->formatOutput = true;

        $root = $this->document->createElementNS(self::XMLNS, 'lsun-bilans');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XMLNS_XSI);
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', self::XSI_LOCATION);
        $root->setAttribute('schemaVersion', self::SCHEMA_VERSION);
        $this->document->appendChild($root);

        $header = $this->document->createElement('entete');
        $header->appendChild($this->document->createElement('editeur', 'Beneylu'));
        $header->appendChild($this->document->createElement('application', 'Beneylu School'));
        if ($this->school) {
            $header->appendChild($this->document->createElement('etablissement', $this->school->getUAI()));
        }
        $root->appendChild($header);

        $this->data = $this->document->createElement('donnees');
        $root->appendChild($this->data);
    }

    protected function writeDirectors()
    {
        $this->dumpCollection($this->directors, 'directeurs', 'directeur', [
            'civilite' => 'getGender',
            'nom' => 'getLastName',
            'prenom' => 'getFirstName',
        ]);
    }

    protected function writeClassrooms()
    {
        $this->dumpCollection($this->classrooms, 'classes', 'classe', [
            'libelle' => 'getLabel',
            'id-be' => 'getOndeId',
        ]);
    }

    protected function writePupils()
    {
        if (!count($this->pupils)) {
            return;
        }

        $node = $this->document->createElement('eleves');
        $done = [];
        foreach ($this->pupilIdsByTemplateId as $templateId => $userIds) {
            $template = $this->templates[$templateId];
            $classroom = $this->classrooms[$template->getLsuConfig()->getGroupId()];
            foreach ($userIds as $userId) {
                if (isset($done[$userId])) {
                    continue;
                }
                $user = $this->pupils[$userId];
                $subnode = $this->document->createElement('eleve');
                $subnode->setAttribute('id', $this->formatId($user));
                $subnode->setAttribute('ine', $user->getIne());
                $subnode->setAttribute('classe-ref', $this->formatId($classroom));
                $subnode->setAttribute('nom', $user->getLastName());
                $subnode->setAttribute('prenom', $user->getFirstName());
                $node->appendChild($subnode);
                $done[$userId] = true;
            }
        }

        if ($node->hasChildNodes()) {
            $this->data->appendChild($node);
        }
    }

    protected function writePeriods()
    {
        $this->dumpCollection($this->templates, 'periodes', 'periode', [
            'millesime' => 'getYear',
            'indice' => 'getIndex',
            'nb-periodes' => 'getTotal',
            'date-debut' => ['getStartedAt', 'Y-m-d'],
            'date-fin' => ['getEndedAt', 'Y-m-d'],
        ]);
    }

    protected function writeTeachers()
    {
        $this->dumpCollection($this->teachers, 'enseignants', 'enseignant', [
            'civilite' => 'getGender',
            'nom' => 'getLastName',
            'prenom' => 'getFirstName',
        ]);
    }

    protected function writeDetails()
    {
        $this->dumpCollection($this->details, 'elements-programme', 'element-programme', [
            'libelle' => 'getLabel',
        ]);
    }

    protected function writeCommonCourses()
    {
        if (!count($this->templates)) {
            return;
        }

        $coursesNode = $this->document->createElement('parcours-communs');
        foreach ($this->templates as $template) {
            $group = $this->classrooms[$template->getLsuConfig()->getGroupId()];
            $periodCoursesNode = $this->document->createElement('parcours-commun');
            $periodCoursesNode->setAttribute('periode-ref', $this->formatId($template));
            $periodCoursesNode->setAttribute('classe-ref', $this->formatId($group));

            $data = $template->getData();
            foreach (self::LSU_COURSES as $course) {
                if (isset($data[$course]) && $data[$course]) {
                    $courseNode = $this->document->createElement('parcours');
                    $courseNode->setAttribute('code', $course);
                    $courseNode->appendChild($this->document->createCDATASection($data[$course]));
                    $periodCoursesNode->appendChild($courseNode);
                }
            }

            if ($periodCoursesNode->hasChildNodes()) {
                $coursesNode->appendChild($periodCoursesNode);
            }
        }

        if ($coursesNode->hasChildNodes()) {
            $this->data->appendChild($coursesNode);
        }
    }

    protected function dumpCollection($items, $collectionName, $elementName, $attributes, $withId = true)
    {
        if (!count($items)) {
            return;
        }

        $node = $this->document->createElement($collectionName);
        foreach ($items as $item) {
            $subnode = $this->document->createElement($elementName);
            if ($withId) {
                $subnode->setAttribute('id', $this->formatId($item, '', 'getId', $collectionName));
            }
            foreach ($attributes as $name => $getter) {
                if (is_array($getter)) {
                    $param = isset($getter[1]) ? $getter[1] : null;
                    $actualGetter = $getter[0];
                    $subnode->setAttribute($name, $item->$actualGetter($param));
                } else if ('civilite' === $name) {
                    $subnode->setAttribute('civilite', $this->formatGender($item->$getter()));
                } else {
                    $subnode->setAttribute($name, $item->$getter());
                }
            }
            $node->appendChild($subnode);
        }
        $this->data->appendChild($node);
    }

    /**
     * @param mixed $input
     * @param string $prefix
     * @param string $getter
     * @return string
     */
    protected function formatId($input, $prefix = '', $getter = 'getId', $name = null)
    {
        if (!(is_array($input) || $input instanceof \Traversable)) {
            $input = [$input];
        }

        $formatted = [];
        foreach ($input as $object) {
            $p = $prefix ?: '';
            if (!$prefix) {
                if ($object instanceof User) {
                    if ($name === 'directeurs') {
                        $p = self::LSU_PREFIX_DIRECTOR;
                    } else {
                        $p = self::LSU_PREFIX_USER;
                    }
                } else if ($object instanceof Group) {
                    $p = self::LSU_PREFIX_GROUP;
                } else if ($object instanceof LsuTemplate) {
                    $p = self::LSU_PREFIX_TEMPLATE;
                } else if ($object instanceof LsuTemplateDomainDetail) {
                    $p = self::LSU_PREFIX_DOMAIN_DETAIL;
                }
            }
            if (is_object($object)) {
                $formatted[] = $p . $object->$getter();
            } else {
                $formatted[] = $p . $object;
            }
        }

        return join(' ', $formatted);
    }

    protected function formatGender($gender)
    {
        switch ($gender) {
            case 'M':
                return 'M';
            case 'F':
                return 'MME';
            default:
                return $gender;
        }
    }

    protected function writePeriodicAssessments()
    {
        $this->addAssessments('bilans-periodiques', false);
    }

    protected function writeCycleAssessments()
    {
        $this->addAssessments('bilans-cycle', true);
    }

    protected function addAssessments($collectionName, $forCycleEnd = false)
    {
        if (!count($this->lsus)) {
            return;
        }

        $node = $this->document->createElement($collectionName);
        foreach($this->lsus as $lsu) {
            $this->addAssessment($node, $lsu, $forCycleEnd);
        }

        if ($node->hasChildNodes()) {
            $this->data->appendChild($node);
        }
    }

    protected function addAssessment(\DOMElement $parent, Lsu $lsu, $forCycleEnd = false)
    {
        $template = $this->templates[$lsu->getTemplateId()];
        if ($template->getIsCycleEnd() !== $forCycleEnd) {
            return;
        }
        $pupil = $this->pupils[$lsu->getUserId()];

        if ($forCycleEnd) {
            $nodeName = 'bilan-cycle';
        } else {
            $nodeName = 'bilan-periodique';
        }

        $startDate = $template->getStartedAt('Y-m-d');
        $node = $this->document->createElement($nodeName);
        $node->setAttribute('eleve-ref', $this->formatId($pupil));
        $node->setAttribute('enseignant-refs', $this->formatId($this->teacherIdsByTemplateId[$template->getId()], self::LSU_PREFIX_USER));
        $node->setAttribute('date-creation', $startDate);
        $node->setAttribute('date-verrou', $lsu->getUpdatedAt('Y-m-d\TH:i:s'));
        $node->setAttribute('directeur-ref', $this->formatId($this->directors[0]));
        if ($forCycleEnd) {
            $node->setAttribute('cycle', $template->getLsuConfig()->getLsuLevel()->getCycleRaw());
            $node->setAttribute('millesime', $template->getYear());
        } else {
            $pupilDate = $pupil->getCreatedAt('Y-m-d');
            if ($pupilDate > $startDate) {
                $scolarshipDate = $pupilDate;
            } else {
                $scolarshipDate = $startDate;
            }
            $node->setAttribute('periode-ref', $this->formatId($template));
            $node->setAttribute('date-scolarite', $scolarshipDate);
        }

        if ($forCycleEnd) {
            $this->addCommonGround($node, $lsu);
            $this->addGlobalEvaluation($node, $lsu, 'synthese');
            $this->addParents($node, $lsu);
        } else {
            $this->addAchievements($node, $lsu);
            $this->addCourses($node, $lsu);
            $this->addAccompanyingConditions($node, $lsu);
            $this->addGlobalEvaluation($node, $lsu);
            $this->addCommonGround($node, $lsu);
            $this->addParents($node, $lsu);
        }

        if ($node->hasChildNodes()) {
            $parent->appendChild($node);
        }
    }

    protected function addCommonGround(\DOMElement $parent, Lsu $lsu)
    {
        $root = LsuDomainQuery::create()->findRoot(self::LSU_VERSION);
        /** @var LsuDomain $cycle */
        $cycle = null;
        /** @var LsuDomain $cycleRoot */
        foreach ($root->getChildren() as $cycleRoot) {
            if ($cycleRoot->getCycle() === 'socle') {
                $cycle = $cycleRoot;
                break;
            }
        }

        $this->addAchievementsFromRoot($parent, $lsu, $cycle, 'socle', 'domaine', 'code');
    }

    protected function addAchievements(\DOMElement $parent, Lsu $lsu)
    {
        $template = $this->templates[$lsu->getTemplateId()];
        $root = LsuDomainQuery::create()->findRoot(self::LSU_VERSION);
        /** @var LsuDomain $cycle */
        $cycle = null;
        /** @var LsuDomain $cycleRoot */
        foreach ($root->getChildren() as $cycleRoot) {
            if ($cycleRoot->getCycle() === $template->getLsuConfig()->getLsuLevel()->getCycle()) {
                $cycle = $cycleRoot;
            }
        }

        $this->addAchievementsFromRoot($parent, $lsu, $cycle, 'liste-acquis', 'acquis');
    }

    protected function addAchievementsFromRoot(\DOMElement $parent, Lsu $lsu, LsuDomain $root, $collectionName, $elementName, $codeAttr = 'code-domaine')
    {
        $template = $this->templates[$lsu->getTemplateId()];
        $collectionNode = $this->document->createElement($collectionName);

        /** @var LsuDomain $domain */
        foreach ($root->getChildren() as $domain) {
            if (!$domain->getCode()) {
                continue;
            }
            $domainNode = $this->document->createElement($elementName);
            $domainNode->setAttribute($codeAttr, $domain->getCode());
            if (isset($this->positionByLsuAndDomainId[$lsu->getId()][$domain->getId()])) {
                /** @var LsuPosition $position */
                $position = $this->positionByLsuAndDomainId[$lsu->getId()][$domain->getId()];
                $domainNode->setAttribute('positionnement', $position->getAchievementRaw());
            }
            if (isset($this->detailIdsByTemplateAndDomainId[$template->getId()][$domain->getId()])) {
                $domainNode->setAttribute('element-programme-refs', $this->formatId($this->detailIdsByTemplateAndDomainId[$template->getId()][$domain->getId()], self::LSU_PREFIX_DOMAIN_DETAIL));
            }

            if (isset($this->commentByLsuAndDomainId[$lsu->getId()][$domain->getId()])) {
                /** @var LsuComment $comment */
                $comment = $this->commentByLsuAndDomainId[$lsu->getId()][$domain->getId()];
                $commentNode = $this->document->createElement('appreciation');
                $commentNode->appendChild($this->document->createCDATASection($comment->getComment()));
                $domainNode->appendChild($commentNode);
            }

            // add achievements for the domain's subdomains
            // recursion FTW!
            $this->addAchievementsFromRoot($domainNode, $lsu, $domain, 'sous-domaines', 'sous-domaine', $codeAttr);

            if ($domainNode->hasChildNodes() || $domainNode->hasAttribute('positionnement')) {
                $collectionNode->appendChild($domainNode);
            }
        }

        if ($collectionNode->hasChildNodes()) {
            $parent->appendChild($collectionNode);
        }
    }

    protected function addCourses(\DOMElement $parent, Lsu $lsu)
    {
        $node = $this->document->createElement('liste-parcours');

        $data = $lsu->getData();
        foreach (self::LSU_COURSES as $course) {
            if (isset($data[$course]) && $data[$course]) {
                $subnode = $this->document->createElement('parcours');
                $subnode->appendChild($this->document->createCDATASection($data[$course]));
                $subnode->setAttribute('code', $course);
                $node->appendChild($subnode);
            }
        }

        if ($node->hasChildNodes()) {
            $parent->appendChild($node);
        }
    }

    protected function addGlobalEvaluation(\DOMElement $parent, Lsu $lsu, $nodeName = 'appreciation-generale')
    {
        if ($lsu->getGlobalEvaluation()) {
            $node = $this->document->createElement($nodeName);
            $node->appendChild($this->document->createCDATASection($lsu->getGlobalEvaluation()));
            $parent->appendChild($node);
        }
    }

    protected function addAccompanyingConditions(\DOMElement $parent, Lsu $lsu)
    {
        $node = $this->document->createElement('modalites-accompagnement');
        foreach ($lsu->getAccompanyingCondition() as $condition) {
            $subnode = $this->document->createElement('modalite-accompagnement');
            $subnode->setAttribute('code', $condition);
            if ('PPRE' === $condition && $lsu->getAccompanyingConditionOther()) {
                $ppreSubnode = $this->document->createElement('complement-ppre');
                $ppreSubnode->appendChild($this->document->createCDATASection($lsu->getAccompanyingConditionOther()));
                $subnode->appendChild($ppreSubnode);
            }
            $node->appendChild($subnode);
        }

        if ($node->hasChildNodes()) {
            $parent->appendChild($node);
        }
    }

    protected function addParents(\DOMElement $parent, Lsu $lsu)
    {
        $node = $this->document->createElement('responsables');
        $pupil = $this->pupils[$lsu->getUserId()];
        /** @var User $parentUser */
        foreach ($this->userManager->getUserParent($pupil) as $parentUser) {
            $subnode = $this->document->createElement('responsable');
            $subnode->setAttribute('civilite', $this->formatGender($parentUser->getGender()));
            $subnode->setAttribute('nom', $parentUser->getLastName());
            $subnode->setAttribute('prenom', $parentUser->getFirstName());
            $node->appendChild($subnode);
        }

        if ($node->hasChildNodes()) {
            $parent->appendChild($node);
        }
    }

}
