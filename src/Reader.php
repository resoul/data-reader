<?php
namespace DataReader;

use DataReader\Exception\ConfigurationException;
use DataReader\Exception\DataReaderException;
use DataReader\Exception\OutputException;
use DataReader\Exception\ResourceException;

class Reader implements DataReaderInterface
{
    /**
     * @var ConfigInterface|null
     */
    private $config;

    /**
     * @var OutputInterface|null
     */
    private $output;

    /**
     * @var ResourceInterface|null
     */
    private $resource;

    public function __construct(ResourceInterface $resource = null, OutputInterface $output = null, ConfigInterface $config = null)
    {
        $this->resource = $resource;
        $this->output = $output;
        $this->config = $config;
    }

    public function resource(ResourceInterface $resource)
    {
        $this->resource = $resource;
    }

    public function config(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function output(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function run()
    {
        if (!$this->resource) {
            throw new ResourceException('Resource is required');
        }

        if (!$this->config) {
            throw new ConfigurationException('Configuration is required');
        }

        if (!$this->output) {
            throw new OutputException('Output handler is required');
        }

        try {
            $items = $this->resource->apply($this->config);
            return $this->output->items($items);
        } catch (\Exception $e) {
            throw new DataReaderException('Error during data processing: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getItems(): array
    {
        if (!$this->resource) {
            throw new \RuntimeException('Resource not set');
        }

        return $this->resource->apply($this->config);
    }

    public function getTotalItems(): int
    {
        $items = $this->getItems();
        return is_array($items) ? count($items) : 0;
    }
}