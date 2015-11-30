<?php

namespace BNS\App\FixtureBundle\Marker\ForeignTableName;

use BNS\App\FixtureBundle\Marker\MarkerManager;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class UserMarker extends AbstractForeignTableNameMarker
{
    /**
     * @var MarkerManager
     */
    private $manager;

    /**
     * @var array<String> 
     */
    private $primaryKeys;

    /**
     * @var array<Integer> 
     */
    private static $usedUsersId;

    /**
     * @param MarkerManager $manager
     */
    public function __construct(MarkerManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @return string 
     */
    public function getTableName()
    {
        return 'user';
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param DialogHelper $dialog
     * @param \ColumnMap $column
     * @param mixed $value
     *
     * @return string
     */
    public function dump(InputInterface $input, OutputInterface $output, DialogHelper $dialog, \ColumnMap $column, $value)
    {
        $isComment = $this->isComment($column);
        $choices = range(1, 3);

        $output->writeln(array(
            '',
            '   ---------------',
            '   1: the selected user will be a random teacher',
            '   2: the selected user will be a random pupil',
            '   3: the selected user will be a random parent'
        ));

        if ($isComment) {
            $choices = range(1, 7);
            $output->writeln(array(
                '   4: the selected user will be the comment object author',
                '   5: the selected user will be a random teacher and can\'t be the object comment author',
                '   6: the selected user will be a random pupil and can\'t be the object comment author',
                '   7: the selected user will be a random parent and can\'t be the object comment author',
            ));
        }

        $output->writeln(array(
            '   ---------------',
            ''
        ));

        $choice = null;
        while (!in_array($choice, $choices)) {
            $choice = $dialog->ask($output, '   > Your choice ? [2]: ', 2);
        }

        switch ($choice) {
            case 2: return 'USER(PUPIL)';
            case 3: return 'USER(PARENT)';
            case 4: return 'USER(OBJECT_COMMENT_AUTHOR)';
            case 5: return 'USER(COMMENT_TEACHER)';
            case 6: return 'USER(COMMENT_PUPIL)';
            case 7: return 'USER(COMMENT_PARENT)';
            default: return 'USER(TEACHER)';
        }
    }

    /**
     * @param \ColumnMap $column
     * @param Object $obj
     * @param mixed $value
     *
     * @return id
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \LogicException
     */
    public function load(InputInterface $input, \ColumnMap $column, $obj, $value)
    {
        if (!$matches = $this->getMarkers('USER', $value)) {
            throw new \InvalidArgumentException('Unknown marker for foreign table name ' . $this->getTableName() . ' value: ' . $value);
        }

        $isComment = $this->isComment($column);
        if ($isComment && null == $obj->getObjectId()) {
            throw new \RuntimeException('Can NOT retrieve the comment object id !');
        }

        $users = $this->manager->getGroupUsers();
        $user = null;

        switch ($matches[0]) {
            case 'TEACHER': return $this->selectUser('TEACHER', $column, $users);
            case 'PUPIL':   return $this->selectUser('PUPIL', $column, $users);
            case 'PARENT':  return $this->selectUser('PARENT', $column, $users);
        
            case 'OBJECT_COMMENT_AUTHOR':
                if (!$isComment) {
                    throw new \LogicException('The object must implement the CommentInterface to use the comment user marker !');
                }

            return $obj->getObject()->getAuthorId();
            
            case 'COMMENT_TEACHER':
                if (!$isComment) {
                    throw new \LogicException('The object must implement the CommentInterface to use the comment user marker !');
                }

                $count = count($users['TEACHER']);
                while (null == $user || $user->getId() == $obj->getObject()->getAuthorId()) {
                    $user = $users['TEACHER'][rand(0, $count - 1)];
                }

            return $user->getId();

            case 'COMMENT_PUPIL':
                if (!$isComment) {
                    throw new \LogicException('The object must implement the CommentInterface to use the comment user marker !');
                }

                $count = count($users['PUPIL']);
                while (null == $user || $user->getId() == $obj->getObject()->getAuthorId()) {
                    $user = $users['PUPIL'][rand(0, $count - 1)];
                }

            return $user->getId();

            case 'COMMENT_PARENT':
                    if (!$isComment) {
                    throw new \LogicException('The object must implement the CommentInterface to use the comment user marker !');
                }

                $count = count($users['PARENT']);
                while (null == $user || $user->getId() == $obj->getObject()->getAuthorId()) {
                    $user = $users['PARENT'][rand(0, $count - 1)];
                }

            return $user->getId();
        }
    }

    /**
     * @param \ColumnMap $column
     *
     * @return boolean
     */
    private function isComment(\ColumnMap $column)
    {
        $rClass = new \ReflectionClass($column->getTable()->getClassname());

        return $rClass->implementsInterface('BNS\App\CommentBundle\Comment\CommentInterface');
    }

    /**
     * @param array<String> $primaryKeys Not the real value but linked primary key, like "ObjectClass_7"
     */
    public function setPrimaryKeys($primaryKeys)
    {
        $this->primaryKeys = $primaryKeys;
    }

    /**
     * @param string $role
     * @param \ColumnsMap $column
     *
     * @return int
     */
    private function selectUser($role, \ColumnMap $column, array $users)
    {
        // To avoid integrity constraint error, we must use an array with key like "ObjectPrimaryKeyClass_1.OtherObjectPrimaryKeyClass_43"
        if ($column->isPrimaryKey()) {
            $key = '';

            if (count($this->primaryKeys) > 1) {
                foreach ($this->primaryKeys as $name => $value) {
                    if ($name != $column->getName()) {
                        $key = $value . '.';
                    }
                }

                $key = substr($key, 0, -1);

                if (!isset(self::$usedUsersId[$key])) {
                    self::$usedUsersId[$key] = array();
                }
            }
        }
        
        $userId = null;
        while (null == $userId || $column->isPrimaryKey() && in_array($userId, self::$usedUsersId[$key])) {
            $userId = $users[$role][rand(0, count($users[$role]) - 1)]->getId();
        }

        if ($column->isPrimaryKey()) {
            self::$usedUsersId[$key][] = $userId;
        }

        return $userId;
    }

    /**
     * Clear used users
     */
    public static function clearUsers()
    {
        self::$usedUsersId = array();
    }
}