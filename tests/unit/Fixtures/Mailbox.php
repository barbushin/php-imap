<?php

declare(strict_types=1);

namespace PhpImap\Fixtures;

use PhpImap\Mailbox as Base;

class Mailbox extends Base
{
    public function getImapPassword(): string
    {
        return $this->imapPassword;
    }

    public function getImapOptions(): int
    {
        return $this->imapOptions;
    }
}
