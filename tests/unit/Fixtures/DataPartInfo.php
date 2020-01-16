<?php

namespace PhpImap\Fixtures;

use PhpImap\DataPartInfo as Base;

class DataPartInfo extends Base
{
    public function fetch()
    {
        return $this->decodeAfterFetch();
    }

    /** @param string|null $data */
    public function setData($data)
    {
        $this->data = $data;
    }
}
