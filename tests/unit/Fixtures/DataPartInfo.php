<?php

namespace PhpImap\Fixtures;

use PhpImap\DataPartInfo as Base;

class DataPartInfo extends Base
{
    public function fetch()
    {
        return $this->processFetch();
    }

    public function setData($data)
    {
        $this->data = $data;
    }
}
