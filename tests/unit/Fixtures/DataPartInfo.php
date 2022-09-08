<?php

declare(strict_types=1);

namespace PhpImap\Fixtures;

use PhpImap\DataPartInfo as Base;

class DataPartInfo extends Base
{
    /** @var string|null */
    protected $data;

    public function fetch(): string
    {
        return $this->decodeAfterFetch($this->data);
    }

    public function setData(string $data = null): void
    {
        $this->data = $data;
    }
}
