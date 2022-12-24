<?php
namespace DataReader\Resource;

abstract class Resource
{
    protected $data;

    abstract function setData($data);
}