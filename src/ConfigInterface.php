<?php
namespace DataReader;

interface ConfigInterface
{
    public function configureItem($item);

    public function configureFirstItem($item);
}