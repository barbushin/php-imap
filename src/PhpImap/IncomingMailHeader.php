<?php

declare(strict_types=1);

namespace PhpImap;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 */
class IncomingMailHeader
{
    /** @var int|null The IMAP message ID - not the "Message-ID:"-header of the email */
    public $id;

    /** @var string|null */
    public $imapPath;

    /** @var string|null */
    public $mailboxFolder;

    /** @var bool */
    public $isSeen = false;

    /** @var bool */
    public $isAnswered = false;

    /** @var bool */
    public $isRecent = false;

    /** @var bool */
    public $isFlagged = false;

    /** @var bool */
    public $isDeleted = false;

    /** @var bool */
    public $isDraft = false;

    /** @var string|null */
    public $date;

    /** @var string|null */
    public $headersRaw;

    /**
     * @var string[][]
     *
     * @psalm-var array<string, list<string>>
     */
    public $headersByName = [];

    /** @var object|null */
    public $headers;

    /** @var string|null */
    public $mimeVersion;

    /** @var string|null */
    public $xVirusScanned;

    /** @var string|null */
    public $organization;

    /** @var string|null */
    public $contentType;

    /** @var string|null */
    public $xMailer;

    /** @var string|null */
    public $contentLanguage;

    /** @var string|null */
    public $xSenderIp;

    /** @var string|null */
    public $priority;

    /** @var string|null */
    public $importance;

    /** @var string|null */
    public $sensitivity;

    /** @var string|null */
    public $autoSubmitted;

    /** @var string|null */
    public $precedence;

    /** @var string|null */
    public $failedRecipients;

    /** @var string|null */
    public $subject;

    /** @var string|null */
    public $fromHost;

    /** @var string|null */
    public $fromName;

    /** @var string|null */
    public $fromAddress;

    /** @var string|null */
    public $senderHost;

    /** @var string|null */
    public $senderName;

    /** @var string|null */
    public $senderAddress;

    /** @var string|null */
    public $xOriginalTo;

    /**
     * @var (string|null)[]
     *
     * @psalm-var array<string, string|null>
     */
    public $to = [];

    /** @var string|null */
    public $toString;

    /**
     * @var (string|null)[]
     *
     * @psalm-var array<string, string|null>
     */
    public $cc = [];

    /** @var string|null */
    public $ccString;

    /**
     * @var (string|null)[]
     *
     * @psalm-var array<string, string|null>
     */
    public $bcc = [];

    /**
     * @var (string|null)[]
     *
     * @psalm-var array<string, string|null>
     */
    public $replyTo = [];

    /** @var string|null */
    public $messageId;

    /** @var string|null */
    public $inReplyTo;

    /** @var string|null */
    public $references;

    public function setHeadersRaw(string $headersRaw): void
    {
        $this->headersRaw = $headersRaw;
        $this->headersByName = $this->parseHeadersRaw($headersRaw);
    }

    public function getHeader(string $headerName): ?string
    {
        $headers = $this->getHeaders($headerName);

        if ([] === $headers) {
            return null;
        }

        return $headers[0];
    }

    /**
     * @return string[]
     *
     * @psalm-return list<string>
     */
    public function getHeaders(string $headerName): array
    {
        $this->ensureParsedHeadersByName();

        return $this->headersByName[$this->normalizeHeaderName($headerName)] ?? [];
    }

    /**
     * @return string[][]
     *
     * @psalm-return array<string, list<string>>
     */
    public function getAllHeaders(): array
    {
        $this->ensureParsedHeadersByName();

        return $this->headersByName;
    }

    protected function ensureParsedHeadersByName(): void
    {
        if ([] !== $this->headersByName || null === $this->headersRaw) {
            return;
        }

        $this->headersByName = $this->parseHeadersRaw($this->headersRaw);
    }

    /**
     * @return string[][]
     *
     * @psalm-return array<string, list<string>>
     */
    protected function parseHeadersRaw(string $headersRaw): array
    {
        $parsedHeaders = [];
        $currentHeaderName = null;
        $currentHeaderValue = '';

        foreach ($this->splitHeaderLines($headersRaw) as $line) {
            if ('' === $line) {
                $this->storeParsedHeader($parsedHeaders, $currentHeaderName, $currentHeaderValue);
                $currentHeaderName = null;
                $currentHeaderValue = '';

                continue;
            }

            if (null !== $currentHeaderName && 1 === \preg_match('/^[ \t]/', $line)) {
                $currentHeaderValue .= ' '.\ltrim($line);

                continue;
            }

            $this->storeParsedHeader($parsedHeaders, $currentHeaderName, $currentHeaderValue);

            $separatorPosition = \strpos($line, ':');

            if (false === $separatorPosition) {
                $currentHeaderName = null;
                $currentHeaderValue = '';

                continue;
            }

            $currentHeaderName = \substr($line, 0, $separatorPosition);
            $currentHeaderValue = \trim(\substr($line, $separatorPosition + 1));
        }

        $this->storeParsedHeader($parsedHeaders, $currentHeaderName, $currentHeaderValue);

        return $parsedHeaders;
    }

    /**
     * @return string[]
     *
     * @psalm-return list<string>
     */
    protected function splitHeaderLines(string $headersRaw): array
    {
        /** @var list<string> */
        return \explode("\n", \str_replace(["\r\n", "\r"], "\n", $headersRaw));
    }

    /**
     * @param string[][]  $parsedHeaders
     * @param string|null $headerName
     *
     * @psalm-param array<string, list<string>> $parsedHeaders
     */
    protected function storeParsedHeader(array &$parsedHeaders, ?string $headerName, string $headerValue): void
    {
        if (null === $headerName || '' === \trim($headerName)) {
            return;
        }

        $headerName = $this->normalizeHeaderName($headerName);

        $parsedHeaders[$headerName] ??= [];
        $parsedHeaders[$headerName][] = \trim($headerValue);
    }

    protected function normalizeHeaderName(string $headerName): string
    {
        return \strtolower(\trim($headerName));
    }
}
