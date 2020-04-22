<?php

declare(strict_types=1);

namespace PhpImap\Fixtures;

use PhpImap\DataPartInfo as Base;

class DataPartInfo extends Base
{
    public function fetch(): string
    {
        return $this->decodeAfterFetch();
    }

    public function setData(string $data = null): void
    {
        $this->data = $data;
    }
}
