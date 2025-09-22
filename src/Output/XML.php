<?php
namespace DataReader\Output;

use DataReader\OutputInterface;

class XML extends Output implements OutputInterface
{
    private string $rootElement;
    private string $itemElement;

    public function __construct(string $rootElement = 'data', string $itemElement = 'item')
    {
        $this->rootElement = $rootElement;
        $this->itemElement = $itemElement;
    }

    public function items($items): string
    {
        $xml = new \SimpleXMLElement("<{$this->rootElement}></{$this->rootElement}>");

        foreach ($items as $item) {
            $itemNode = $xml->addChild($this->itemElement);
            $this->arrayToXml($item, $itemNode);
        }

        return $xml->asXML();
    }

    private function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXml($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }
}