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

    public function getImapOAuthToken(): ?string
    {
        return $this->imapOAuthToken;
    }

    public function getImapOptions(): int
    {
        return $this->imapOptions;
    }

    public function getImapOpenSecretForTests(): string
    {
        return $this->getImapOpenSecret();
    }

    public function getImapOpenOptionsForTests(): int
    {
        return $this->getImapOpenOptions();
    }
}
