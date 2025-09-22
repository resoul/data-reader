<?php

namespace DataReader;

interface FilterInterface
{
    public function filter(array $items): array;
}