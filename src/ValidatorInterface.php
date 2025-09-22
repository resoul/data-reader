<?php

namespace DataReader;

interface ValidatorInterface
{
    public function validate($item): bool;
}