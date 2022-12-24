<?php
namespace DataReader\Resource;

use DataReader\ConfigInterface;
use DataReader\ResourceInterface;
use Exception;

class File extends Resource implements ResourceInterface
{
    /**
     * @var mixed|null
     */
    private $filename;

    /**
     * @var FileInterface
     */
    private $format;

    /**
     * @throws Exception
     */
    public function __construct($filename, FileInterface $format)
    {
        if (!file_exists($filename)) {
            throw new Exception('File does not exist ' . $filename);
        }

        $this->filename = $filename;
        $this->format = $format;
    }

    public function apply(ConfigInterface $config)
    {
        if (($handle = fopen($this->filename, 'r')) !== false) {
            $this->setData($this->format->read($handle, $config));
            fclose($handle);
        }

        return $this->getData();
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }
}