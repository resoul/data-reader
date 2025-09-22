<?php
namespace DataReader\Resource\File;

use DataReader\ConfigInterface;
use DataReader\Resource\FileInterface;
use DataReader\Exception\ResourceException;

class XML implements FileInterface
{
    private string $itemTag;

    public function __construct(string $itemTag = 'item')
    {
        $this->itemTag = $itemTag;
    }

    public function read($handle, ConfigInterface $config): array
    {
        $content = stream_get_contents($handle);

        if ($content === false) {
            throw new ResourceException('Failed to read XML file');
        }

        $xml = simplexml_load_string($content);

        if ($xml === false) {
            throw new ResourceException('Failed to parse XML');
        }

        $items = [];
        $isFirst = true;

        foreach ($xml->{$this->itemTag} as $xmlItem) {
            $item = json_decode(json_encode($xmlItem), true);

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