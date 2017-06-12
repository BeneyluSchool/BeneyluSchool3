<?php

namespace BNS\App\ClassroomBundle\Twig;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class EvaluentExtension extends \Twig_Extension
{
    /** @var boolean  */
    protected $showEvaluent;

    /** @var TokenStorage  */
    protected $tokenStorage;

    /** @var  string */
    protected $userMainRole;

    /**
     * EvaluentExtension constructor.
     * @param BNSRightManager $rightManager
     * @param boolean $showEvaluent
     */
    public function __construct(TokenStorage $tokenStorage, $showEvaluent = false)
    {
        $this->tokenStorage = $tokenStorage;
        $this->showEvaluent = $showEvaluent;
    }

    /**
     * @inheritDoc
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('hasEvaluent', [$this, 'hasEvaluent']),
            new \Twig_SimpleFunction('getEvaluentLink', [$this, 'getEvaluentLink'])
        ];
    }

    public function hasEvaluent($school)
    {
        if (!$this->showEvaluent) {
            return false;
        }
        $mainRole = $this->getUserMainRole();
        if (!$mainRole) {
            return false;
        }
        if (!in_array($mainRole, ['TEACHER', 'DIRECTOR', 'PUPIL', 'PARENT'])) {
            return false;
        }
        if ($school) {
            if ($school->getType() === 'SCHOOL' && $uai = $school->getUAI()) {
                return in_array($uai, $this->getEvaluentUAIs());
            }
        }

        return false;
    }

    public function getEvaluentLink($mainRole = null)
    {
        if (!$mainRole) {
            $mainRole = $this->getUserMainRole();
        }
        switch ($mainRole) {
            case 'TEACHER':
            case 'DIRECTOR':
                return 'http://ppe.orion.education.fr/dgesco/itw/answer/s/ajxgf0pbv5/k/evaluent1d2017ens';
            case 'PUPIL':
                return 'http://ppe.orion.education.fr/dgesco/itw/answer/s/ajxgf0pbv5/k/evaluent1d2017ele';
            case 'PARENT':
                return 'http://ppe.orion.education.fr/dgesco/itw/answer/s/ajxgf0pbv5/k/evaluent1d2017par';
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'evaluent_extension';
    }

    protected function getUserMainRole()
    {
        if (null === $this->userMainRole) {
            $this->userMainRole = false;
            if ($token = $this->tokenStorage->getToken()) {
                $user = $token->getUser();
                if ($user && $user instanceof User) {
                    if ($role = GroupTypeQuery::create()->findPk($user->getHighRoleId())) {
                        $this->userMainRole = $role->getType();
                    }
                }
            }
        }

        return $this->userMainRole;
    }

    protected function getEvaluentUAIs()
    {
        return [
            '0240575V',
            '0240574U',
            '0240578Y',
            '0240300W',
            '0240306C',
            '0240656H',
            '0240651C',
            '0240655G',
            '0241065C',
            '0240647Y',
            '0240648Z',
            '0240977G',
            '0241112D',
            '0240213B',
            '0240235A',
            '0240727K',
            '0240785Y',
            '0240688T',
            '0241149U',
            '0240739Y',
            '0240564H',
            '0240609G',
            '0240632G',
            '0240636L',
            '0240640R',
            '0240583D',
            '0240786Z',
            '0241094J',
            '0240489B',
            '0240818J',
            '0241050L',
            '0240617R',
            '0240538E',
            '0240545M',
            '0240234Z',
            '0240878Z',
            '0240661N',
            '0240676E',
            '0240902A',
            '0240969Y',
            '0240166A',
            '0240172G',
            '0240445D',
            '0240590L',
            '0241028M',
            '0240372Z',
            '0240812C',
            '0240639P',
            '0240633H',
            '0240971A',
            '0240747G',
            '0240752M',
            '0240986S',
            '0240594R',
            '0240975E',
            '0240274T',
            '0240531X',
            '0240599W',
            '0240606D',
            '0330250T',
            '0332968X',
            '0330491E',
            '0332821M',
            '0331165M',
            '0331256L',
            '0331354T',
            '0333253G',
            '0330894T',
            '0331158E',
            '0330193F',
            '0330555Z',
            '0333125T',
            '0333007P',
            '0332181S',
            '0331774Z',
            '0331632V',
            '0331204E',
            '0331244Y',
            '0330525S',
            '0330828W',
            '0241086A',
            '0331108A',
            '0330646Y',
            '0330725J',
            '0332661N',
            '0331392J',
            '0332572S',
            '0330700G',
            '0332130L',
            '0330280A',
            '0332616P',
            '0332668W',
            '0333101S',
            '0332117X',
            '0332141Y',
            '0330863J',
            '0330862H',
            '0331778D',
            '0332526S',
            '0331746U',
            '0330412U',
            '0330668X',
            '0330565K',
            '0330610J',
            '0332143A',
            '0332118Y',
            '0332365S',
            '0333197W',
            '0332201N',
            '0330433S',
            '0330682M',
            '0332217F',
            '0331190P',
            '0331786M',
            '0330322W',
            '0331154A',
            '0332164Y',
            '0332621V',
            '0331083Y',
            '0332154M',
            '0330546P',
            '0331624L',
            '0330638P',
            '0332214C',
            '0332663R',
            '0332056F',
            '0331042D',
            '0331138H',
            '0332698D',
            '0332272R',
            '0331470U',
            '0330992Z',
            '0330990X',
            '0332771H',
            '0330300X',
            '0332253V',
            '0331187L',
            '0332124E',
            '0331030R',
            '0330568N',
            '0331027M',
            '0331076R',
            '0332610H',
            '0330918U',
            '0330906F',
            '0330293P',
            '0332225P',
            '0332209X',
            '0331787N',
            '0330639R',
            '0332152K',
            '0331033U',
            '0330418A',
            '0332611J',
            '0331227E',
            '0331277J',
            '0330982N',
            '0330504U',
            '0331216T',
            '0331016A',
            '0331208J',
            '0331357W',
            '0332364R',
            '0330563H',
            '0331120N',
            '0331873G',
            '0331103V',
            '0331287V',
            '0400391H',
            '0400393K',
            '0400804G',
            '0400652S',
            '0400439K',
            '0400722T',
            '0400431B',
            '0401008D',
            '0400904R',
            '0400425V',
            '0400491S',
            '0400526E',
            '0400427X',
            '0400564W',
            '0400573F',
            '0400922K',
            '0400980Y',
            '0400168R',
            '0400871E',
            '0400149V',
            '0400146S',
            '0400630T',
            '0400346J',
            '0400655V',
            '0400670L',
            '0400598H',
            '0400600K',
            '0400763M',
            '0400155B',
            '0400162J',
            '0400810N',
            '0400738K',
            '0400207H',
            '0400209K',
            '0400208J',
            '0400206G',
            '0401035H',
            '0400621H',
            '0400617D',
            '0400623K',
            '0400733E',
            '0400816V',
            '0400151X',
            '0400316B',
            '0400452Z',
            '0400496X',
            '0400413G',
            '0400417L',
            '0400255K',
            '0400764N',
            '0400335X',
            '0400450X',
            '0400471V',
            '0400486L',
            '0400487M',
            '0400455C',
            '0400294C',
            '0400298G',
            '0400254J',
            '0400814T',
            '0400880P',
            '0400274F',
            '0400263U',
            '0400261S',
            '0400771W',
            '0400605R',
            '0400735G',
            '0400234M',
            '0400524C',
            '0400527F',
            '0400215S',
            '0400214R',
            '0400981Z',
            '0400265W',
            '0400277J',
            '0400383Z',
            '0400385B',
            '0400387D',
            '0401073Z',
            '0400881R',
            '0400344G',
            '0400513R',
            '0400514S',
            '0400518W',
            '0400503E',
            '0400511N',
            '0400449W',
            '0470638N',
            '0470386P',
            '0470703J',
            '0470659L',
            '0470712U',
            '0470287G',
            '0470132N',
            '0470504T',
            '0470364R',
            '0470348Y',
            '0470458T',
            '0470450J',
            '0470300W',
            '0470134R',
            '0470131M',
            '0470761X',
            '0470612K',
            '0470155N',
            '0470544L',
            '0470316N',
            '0642066Y',
            '0641402B',
            '0640694G',
            '0640689B',
            '0641057B',
            '0641042K',
            '0641169Y',
            '0641606Y',
            '0640806D',
            '0640796T',
            '0640800X',
            '0640793P',
            '0640727T',
            '0640557H',
            '0640623E',
            '0641147Z',
            '0641372U',
            '0640997L',
            '0640723N',
            '0641153F',
            '0641221E',
            '0640547X',
            '0641070R',
            '0641120V',
            '0640307L',
            '0642067Z',
            '0641909C',
            '0641139R',
            '0641118T',
            '0640310P',
            '0641619M',
            '0641616J',
            '0640884N',
            '0641450D',
            '0641023P',
            '0640764H',
            '0640633R',
            '0641774F',
            '0640667C',
            '0640385W',
            '0640565S',
            '0640595Z',
            '0640404S',
            '0641032Z',
            '0641177G',
            '0640263N',
            '0641828P',
            '0640515M',
            '0641467X',
            '0640415D',
            '0640520T',
            '0640761E',
            '0640322C',
            '0640464G',
            '0640293W',
            '0640553D',
            '0641176F',
            '0641141T',
            '0640869X',
            '0641083E',
            '0640382T',
            '0640336T',
            '0641468Y',
            '0641719W',
            '0640574B',
            '0640957T',
            '0641369R',
            '0641736P',
            '0641709K',
            '0641217A',
            '0640277D',
            '0640473S',
            '0640472R',
            '0640319Z',
            '0641624T',
            '0640571Y',
            '0640298B',
            '0641444X',
            '0640445L',
            '0641102A',
        ];
    }
}
