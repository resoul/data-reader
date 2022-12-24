<?php
namespace DataReader;

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
        $items = $this->resource->apply($this->config);
        $this->output->items($items);

        return $items;
    }

    public function getItems()
    {
    }

    public function getTotalItems()
    {
        // TODO: Implement getTotalItems() method.
    }
}