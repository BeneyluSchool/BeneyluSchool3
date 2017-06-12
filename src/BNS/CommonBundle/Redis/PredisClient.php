<?php
namespace BNS\CommonBundle\Redis;

use Predis\Client;
use Predis\Command\CommandInterface;
use Predis\Connection\Aggregate\ReplicationInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class PredisClient extends Client
{
    /**
     * @inheritDoc
     */
    public function executeRaw(array $arguments, &$error = null)
    {
        // force connection to use master
        $this->switchToMaster();

        return parent::executeRaw($arguments, $error);
    }

    /**
     * @inheritDoc
     */
    public function executeCommand(CommandInterface $command)
    {
        // force connection to use master
        $this->switchToMaster();

        return parent::executeCommand($command);
    }

    /**
     *  force the redis connection to use master
     */
    public function switchToMaster()
    {
        if ($this->connection instanceof ReplicationInterface) {
            $this->connection->switchTo('master');
        }
    }
}
