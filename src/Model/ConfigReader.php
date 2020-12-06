<?php
declare(strict_types=1);

namespace DatabaseDiffer\Model;

use DatabaseDiffer\Exception\FileNotFoundException;
use DatabaseDiffer\Model\Config\Connection;
use DatabaseDiffer\Model\Config\Group;

class ConfigReader
{
    /**
     * @var string
     */
    private $filePath;

    /**
     * @var Group[]
     */
    private $groups;

    /**
     * Config constructor.
     * @param string $filePath
     * @throws FileNotFoundException
     */
    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException('File not found: ' . $filePath);
        }

        $this->filePath = $filePath;
        $this->loadConfig();
    }

    /**
     * Loading config into Group models
     */
    private function loadConfig(): void
    {
        $config = include $this->filePath;

        $this->groups = [];
        foreach ($config as $group) {
            if (count($group) === 2) {
                $connections = [];
                foreach ($group as $dbConfig) {
                    $connections[] = new Connection($dbConfig);
                }
                $this->groups[] = new Group($connections[0], $connections[1]);
            }
        }
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}