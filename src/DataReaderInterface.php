<?php
namespace DataReader;

interface DataReaderInterface
{
    public function getItems(): array;
    public function getTotalItems(): int;
}