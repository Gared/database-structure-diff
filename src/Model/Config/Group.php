<?php
declare(strict_types=1);

namespace DatabaseDiffer\Model\Config;

class Group
{
    /**
     * @var Connection
     */
    private $fromConnection;

    /**
     * @var Connection
     */
    private $toConnection;

    /**
     * Group constructor.
     * @param Connection $fromConnection
     * @param Connection $toConnection
     */
    public function __construct(Connection $fromConnection, Connection $toConnection)
    {
        $this->fromConnection = $fromConnection;
        $this->toConnection = $toConnection;
    }

    /**
     * @return Connection
     */
    public function getFromConnection(): Connection
    {
        return $this->fromConnection;
    }

    /**
     * @return Connection
     */
    public function getToConnection(): Connection
    {
        return $this->toConnection;
    }
}