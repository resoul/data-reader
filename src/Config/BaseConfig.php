<?php
namespace DataReader\Config;

use DataReader\ConfigInterface;

abstract class BaseConfig implements ConfigInterface
{
    protected array $fieldMapping = [];
    protected array $validators = [];

    public function setFieldMapping(array $mapping): self
    {
        $this->fieldMapping = $mapping;
        return $this;
    }

    public function addValidator(callable $validator): self
    {
        $this->validators[] = $validator;
        return $this;
    }

    protected function validateItem($item): bool
    {
        foreach ($this->validators as $validator) {
            if (!$validator($item)) {
                return false;
            }
        }
        return true;
    }

    protected function mapFields($item): array
    {
        if (empty($this->fieldMapping)) {
            return $item;
        }

        $mapped = [];
        foreach ($this->fieldMapping as $from => $to) {
            $mapped[$to] = $item[$from] ?? null;
        }

        return $mapped;
    }
}