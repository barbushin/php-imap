<?php

namespace PhpImap;

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
    protected $hasAttachments = false;
    protected $dataInfo = [[], []];

    public function setHeader(IncomingMailHeader $header)
    {
        foreach (get_object_vars($header) as $property => $value) {
            $this->$property = $value;
        }
    }

    public function addDataPartInfo(DataPartInfo $dataInfo, $type)
    {
        $this->dataInfo[$type][] = $dataInfo;
    }

    /**
     * __get() is utilized for reading data from inaccessible (protected
     * or private) or non-existing properties.
     *
     * @property $name Name of the property (eg. textPlain)
     *
     * @return mixed Value of the property (eg. Plain text message)
     */
    public function __get($name)
    {
        $type = false;
        if ('textPlain' == $name) {
            $type = DataPartInfo::TEXT_PLAIN;
        }
        if ('textHtml' == $name) {
            $type = DataPartInfo::TEXT_HTML;
        }
        if (false === $type) {
            trigger_error("Undefined property: IncomingMail::$name");
        }
        $this->$name = '';
        foreach ($this->dataInfo[$type] as $data) {
            $this->$name .= trim($data->fetch());
        }

        return $this->$name;
    }

    /**
     * The method __isset() is triggered by calling isset() or empty()
     * on inaccessible (protected or private) or non-existing properties.
     *
     * @property $name Name of the property (eg. textPlain)
     *
     * @return bool True, if property is set or empty
     */
    public function __isset($name)
    {
        self::__get($name);

        return isset($this->$name);
    }

    public function addAttachment(IncomingMailAttachment $attachment)
    {
        $this->attachments[$attachment->id] = $attachment;
    }

    /**
     * Sets property $hasAttachments.
     *
     * @param bool $hasAttachments True, if IncomingMail[] has one or more attachments
     */
    public function setHasAttachments($hasAttachments)
    {
        $this->hasAttachments = $hasAttachments;
    }

    /**
     * Returns, if the mail has attachments or not.
     *
     * @return bool true or false
     */
    public function hasAttachments()
    {
        return $this->hasAttachments;
    }

    /**
     * @return IncomingMailAttachment[]
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @param string $id The attachment id
     *
     * @return bool
     */
    public function removeAttachment($id)
    {
        if (!isset($this->attachments[$id])) {
            return false;
        }

        unset($this->attachments[$id]);

        return true;
    }

    /**
     * Get array of internal HTML links placeholders.
     *
     * @return array attachmentId => link placeholder
     */
    public function getInternalLinksPlaceholders()
    {
        return preg_match_all('/=["\'](ci?d:([\w\.%*@-]+))["\']/i', $this->textHtml, $matches) ? array_combine($matches[2], $matches[1]) : [];
    }

    public function replaceInternalLinks($baseUri)
    {
        $baseUri = rtrim($baseUri, '\\/').'/';
        $fetchedHtml = $this->textHtml;
        $search = [];
        $replace = [];
        foreach ($this->getInternalLinksPlaceholders() as $attachmentId => $placeholder) {
            foreach ($this->attachments as $attachment) {
                if ($attachment->contentId == $attachmentId) {
                    $search[] = $placeholder;
                    $replace[] = $baseUri.basename($this->attachments[$attachment->id]->filePath);
                }
            }
        }

        return str_replace($search, $replace, $fetchedHtml);
    }

    /**
     * Embed inline image attachments as base64 to allow for
     * email html to display inline images automatically.
     */
    public function embedImageAttachments()
    {
        preg_match_all("/\bcid:[^'\"\s]{1,256}/mi", $this->textHtml, $matches);

        if (\count($matches)) {
            foreach ($matches as $match) {
                if (!isset($match[0])) {
                    continue;
                }

                $cid = str_replace('cid:', '', $match[0]);

                foreach ($this->getAttachments() as $attachment) {
                    if ($attachment->contentId == $cid && 'inline' == $attachment->disposition) {
                        $contents = $attachment->getContents();
                        $contentType = $attachment->getMimeType();

                        if (!strstr($contentType, 'image')) {
                            continue;
                        }

                        $base64encoded = base64_encode($contents);
                        $replacement = 'data:'.$contentType.';base64, '.$base64encoded;

                        $this->textHtml = str_replace($match[0], $replacement, $this->textHtml);

                        $this->removeAttachment($attachment->id);
                    }
                }
            }
        }
    }
}
