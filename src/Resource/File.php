<?php
namespace DataReader\Resource;

use DataReader\ConfigInterface;
use DataReader\ResourceInterface;
use DataReader\Exception\ResourceException;

class File extends Resource implements ResourceInterface
{
    private string $filename;
    private FileInterface $format;

    public function __construct(string $filename, FileInterface $format)
    {
        if (!file_exists($filename)) {
            throw new ResourceException("File does not exist: {$filename}");
        }

        if (!is_readable($filename)) {
            throw new ResourceException("File is not readable: {$filename}");
        }

        $this->filename = $filename;
        $this->format = $format;
    }

    public function apply(ConfigInterface $config): array
    {
        $handle = fopen($this->filename, 'r');

        if ($handle === false) {
            throw new ResourceException("Cannot open file: {$this->filename}");
        }

        try {
            $data = $this->format->read($handle, $config);
            $this->setData($data);
            return $this->getData();
        } finally {
            fclose($handle);
        }
    }

    public function getData(): array
    {
        return $this->data ?? [];
    }

    public function setData($data): void
    {
        if (!is_array($data)) {
            throw new ResourceException('Data must be an array');
        }
        $this->data = $data;
    }
}