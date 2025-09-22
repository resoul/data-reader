<?php
namespace DataReader\Resource\File;

use DataReader\ConfigInterface;
use DataReader\Resource\FileInterface;
use DataReader\Exception\ResourceException;

class JSON implements FileInterface
{
    public function read($handle, ConfigInterface $config): array
    {
        $content = stream_get_contents($handle);

        if ($content === false) {
            throw new ResourceException('Failed to read JSON file');
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ResourceException('JSON decode error: ' . json_last_error_msg());
        }

        $items = [];
        $isFirst = true;

        foreach ($data as $item) {
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

        return $items;
    }
}