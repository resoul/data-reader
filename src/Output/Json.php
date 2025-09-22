<?php
namespace DataReader\Output;

use DataReader\OutputInterface;

class Json extends Output implements OutputInterface
{
    private $options;

    public function __construct(int $options = JSON_PRETTY_PRINT)
    {
        $this->options = $options;
    }

    public function items($items)
    {
        $json = json_encode($items, $this->options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON encoding error: ' . json_last_error_msg());
        }

        return $json;
    }
}