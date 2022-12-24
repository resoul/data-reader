<?php
namespace DataReader\Resource;

use DataReader\ConfigInterface;

interface FileInterface
{
    public function read($handle, ConfigInterface $config);
}