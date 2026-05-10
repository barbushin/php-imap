<?php

declare(strict_types=1);

namespace PhpImap\Fixtures;

use PhpImap\Mailbox as Base;

class Mailbox extends Base
{
    public function decodeRFC2231ForTests(string $string): string
    {
        return $this->decodeRFC2231($string);
    }

    public function getMailHeaderFieldValueForTests(string $headersRaw, string $headerFieldName): string
    {
        return $this->getMailHeaderFieldValue($headersRaw, $headerFieldName);
    }

    /**
     * @return (string|null)[]|null
     */
    public function possiblyGetEmailAndNameFromRecipientForTests(object $recipient): ?array
    {
        return $this->possiblyGetEmailAndNameFromRecipient($recipient);
    }

    /**
     * @psalm-return array{messageId:null|string, inReplyTo:null|string, references:null|string}
     */
    public function getThreadingHeadersForTests(object $head, string $headersRaw): array
    {
        return $this->getThreadingHeaders($head, $headersRaw);
    }

    /**
     * @param array<int, object> $t
     *
     * @return array{0:string|null, 1:string|null, 2:string}
     */
    public function possiblyGetHostNameAndAddressForTests(array $t): array
    {
        return $this->possiblyGetHostNameAndAddress($t);
    }

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

    public function hasAttachmentDispositionForTests(object $partStructure): bool
    {
        return $this->hasAttachmentDisposition($partStructure);
    }
}
