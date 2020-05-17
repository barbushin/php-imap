<?php

declare(strict_types=1);

namespace PhpImap;

use InvalidArgumentException;

/**
 * The PhpImap IncomingMail class.
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 * @see https://github.com/barbushin/php-imap
 *
 * @property string $textPlain lazy plain message body
 * @property string $textHtml  lazy html message body
 */
class IncomingMail extends IncomingMailHeader
{
    /**
     * @var IncomingMailAttachment[]
     */
    protected $attachments = [];

    /** @var bool */
    protected $hasAttachments = false;

    /**
     * @var DataPartInfo[][]
     *
     * @psalm-var array{0:list<DataPartInfo>, 1:list<DataPartInfo>}
     */
    protected $dataInfo = [[], []];

    /** @var string|null */
    private $textPlain;

    /** @var string|null */
    private $textHtml;

    /**
     * __get() is utilized for reading data from inaccessible (protected
     * or private) or non-existing properties.
     *
     * @param string $name Name of the property (eg. textPlain)
     *
     * @return string Value of the property (eg. Plain text message)
     */
    public function __get(string $name): string
    {
        $type = false;
        if ('textPlain' == $name) {
            $type = DataPartInfo::TEXT_PLAIN;
        }
        if ('textHtml' == $name) {
            $type = DataPartInfo::TEXT_HTML;
        }
        if (false === $type) {
            \trigger_error("Undefined property: IncomingMail::$name");
        }
        $this->$name = '';
        foreach ($this->dataInfo[$type] as $data) {
            $this->$name .= \trim($data->fetch());
        }

        /** @var string */
        return $this->$name;
    }

    /**
     * The method __isset() is triggered by calling isset() or empty()
     * on inaccessible (protected or private) or non-existing properties.
     *
     * @param string $name Name of the property (eg. textPlain)
     *
     * @return bool True, if property is set or empty
     */
    public function __isset(string $name): bool
    {
        self::__get($name);

        return isset($this->$name);
    }

    public function setHeader(IncomingMailHeader $header): void
    {
        /** @psalm-var array<string, scalar|array|object|null> */
        $array = \get_object_vars($header);
        foreach ($array as $property => $value) {
            $this->$property = $value;
        }
    }

    /**
     * @param DataPartInfo::TEXT_PLAIN|DataPartInfo::TEXT_HTML $type
     */
    public function addDataPartInfo(DataPartInfo $dataInfo, int $type): void
    {
        $this->dataInfo[$type][] = $dataInfo;
    }

    public function addAttachment(IncomingMailAttachment $attachment): void
    {
        if (!\is_string($attachment->id)) {
            throw new InvalidArgumentException('Argument 1 passed to '.__METHOD__.'() does not have an id specified!');
        }
        $this->attachments[$attachment->id] = $attachment;

        $this->setHasAttachments(true);
    }

    /**
     * Sets property $hasAttachments.
     *
     * @param bool $hasAttachments True, if IncomingMail[] has one or more attachments
     */
    public function setHasAttachments(bool $hasAttachments): void
    {
        $this->hasAttachments = $hasAttachments;
    }

    /**
     * Returns, if the mail has attachments or not.
     *
     * @return bool true or false
     */
    public function hasAttachments(): bool
    {
        return $this->hasAttachments;
    }

    /**
     * @return IncomingMailAttachment[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param string $id The attachment id
     */
    public function removeAttachment(string $id): bool
    {
        if (!isset($this->attachments[$id])) {
            return false;
        }

        unset($this->attachments[$id]);

        $this->setHasAttachments([] !== $this->attachments);

        return true;
    }

    /**
     * Get array of internal HTML links placeholders.
     *
     * @return array attachmentId => link placeholder
     *
     * @psalm-return array<string, string>
     */
    public function getInternalLinksPlaceholders(): array
    {
        $match = \preg_match_all('/=["\'](ci?d:([\w\.%*@-]+))["\']/i', $this->textHtml, $matches);

        /** @psalm-var array{1:list<string>, 2:list<string>} */
        $matches = $matches;

        return $match ? \array_combine($matches[2], $matches[1]) : [];
    }

    public function replaceInternalLinks(string $baseUri): string
    {
        $baseUri = \rtrim($baseUri, '\\/').'/';
        $fetchedHtml = $this->textHtml;
        $search = [];
        $replace = [];
        foreach ($this->getInternalLinksPlaceholders() as $attachmentId => $placeholder) {
            foreach ($this->attachments as $attachment) {
                if ($attachment->contentId == $attachmentId) {
                    if (!\is_string($attachment->id)) {
                        throw new InvalidArgumentException('Argument 1 passed to '.__METHOD__.'() does not have an id specified!');
                    }
                    $search[] = $placeholder;
                    $replace[] = $baseUri.\basename($this->attachments[$attachment->id]->filePath);
                }
            }
        }

        /** @psalm-var string */
        return \str_replace($search, $replace, $fetchedHtml);
    }

    /**
     * Embed inline image attachments as base64 to allow for
     * email html to display inline images automatically.
     */
    public function embedImageAttachments(): void
    {
        \preg_match_all("/\bcid:[^'\"\s]{1,256}/mi", $this->textHtml, $matches);

        /** @psalm-var list<list<string>> */
        $matches = $matches;

        if (\count($matches)) {
            foreach ($matches as $match) {
                if (!isset($match[0])) {
                    continue;
                }

                $cid = \str_replace('cid:', '', $match[0]);

                foreach ($this->getAttachments() as $attachment) {
                    if ($attachment->contentId == $cid && 'inline' == $attachment->disposition) {
                        $contents = $attachment->getContents();
                        $contentType = $attachment->getFileInfo(FILEINFO_MIME);

                        if (!\strstr($contentType, 'image')) {
                            continue;
                        } elseif (!\is_string($attachment->id)) {
                            throw new InvalidArgumentException('Argument 1 passed to '.__METHOD__.'() does not have an id specified!');
                        }

                        $base64encoded = \base64_encode($contents);
                        $replacement = 'data:'.$contentType.';base64, '.$base64encoded;

                        $this->textHtml = \str_replace($match[0], $replacement, $this->textHtml);

                        $this->removeAttachment($attachment->id);
                    }
                }
            }
        }
    }
}
