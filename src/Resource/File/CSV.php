<?php
namespace DataReader\Resource\File;

use DataReader\ConfigInterface;
use DataReader\Resource\FileInterface;

class CSV implements FileInterface
{
    public function read($handle, ConfigInterface $config)
    {
        $items = [];
        $line = 0;
        while (($item = fgetcsv($handle)) !== false) {
            if ($line) {
                $items[] = $config->configureItem($item);
            } else if (($initItem = $config->configureFirstItem($item)) !== false) {
                $items[] = $config->configureItem($initItem);
            }
            $line++;
        }

        return $items;
    }
}