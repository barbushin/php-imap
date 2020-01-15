<?php

namespace PhpImap\Fixtures;

use PhpImap\DataPartInfo as Base;

class DataPartInfo extends Base
{
    public function fetch(): string
    {
        return $this->decodeAfterFetch();
    }

    public function setData(string $data = null)
    {
        $this->data = $data;
    }
}
