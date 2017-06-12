<?php

namespace BNS\App\HomeworkBundle\Model;

use BNS\App\HomeworkBundle\Model\om\BaseHomeworkSubjectQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'homework_subject' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.HomeworkBundle.Model
 */
class HomeworkSubjectQuery extends BaseHomeworkSubjectQuery
{

    /**
     * Trouve les matieres d'un groupe et les filtre
     * pour eliminer la matiere "racine"
     * (qui sert uniquement a contenir les matieres de ce groupe) 
     * 
     * @param int $groupId
     * @return array|HomeworkSubject[] array de HomeworkSubject
     */
    public function fetchAndFilterByGroupId($groupId)
    {

        $subjects = $this->orderByTreeLeft()
                ->findByGroupId($groupId);

        $sortedSubjects = array();
        // Récupération des parents en cachant le sujet principal (Root subject), puis des enfants
        /** @var HomeworkSubject $subject */
        foreach ($subjects as $subject) {
            if ($subject->getLevel() == 1 && !$subject->isRoot()) {
                $sortedSubjects[$subject->getId()] = $subject;
            }
        }

        return $sortedSubjects;
    }

}

// HomeworkSubjectQuery
