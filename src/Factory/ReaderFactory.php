<?php
namespace DataReader\Factory;

use DataReader\Reader;
use DataReader\Resource\File;
use DataReader\Resource\File\CSV;
use DataReader\Resource\File\JSON;
use DataReader\Resource\ArrayData;
use DataReader\Output\Json as JsonOutput;

class ReaderFactory
{
    public static function createCsvReader(string $filename): Reader
    {
        return new Reader(
            new File($filename, new CSV()),
            new JsonOutput()
        );
    }

    public static function createJsonReader(string $filename): Reader
    {
        return new Reader(
            new File($filename, new JSON()),
            new JsonOutput()
        );
    }

    public static function createArrayReader(array $data): Reader
    {
        return new Reader(
            new ArrayData($data),
            new JsonOutput()
        );
    }
}