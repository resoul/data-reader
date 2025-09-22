<?php
namespace DataReader\Resource;

use DataReader\ConfigInterface;
use DataReader\ResourceInterface;

class ArrayData extends Resource implements ResourceInterface
{
    private array $rawData;

    public function __construct(array $data = [])
    {
        $this->rawData = $data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function apply(ConfigInterface $config)
    {
        $items = [];
        $isFirst = true;

        foreach ($this->rawData as $item) {
            if ($isFirst) {
                $firstItem = $config->configureFirstItem($item);
                if ($firstItem !== false) {
                    $items[] = $firstItem;
                }
                $isFirst = false;
            } else {
                $items[] = $config->configureItem($item);
            }
        }

        $this->setData($items);
        return $this->getData();
    }

    public function getData()
    {
        return $this->data;
    }
}