<?php
declare(strict_types=1);

namespace DatabaseDiffer\Model\Config;

class Connection
{
    /**
     * @var array
     */
    private $config;

    /**
     * Connection constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return bool
     */
    public function isFile(): bool
    {
        return isset($this->config['driver']) && $this->config['driver'] === 'file';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        if ($this->isFile()) {
            return 'File: ' . $this->config['path'];
        }

        return 'Database: ' . ($this->config['dbname'] ?? '') . '@' . ($this->config['host'] ?? 'localhost');
    }
}