<?php namespace PhpImap;

/**
 * @see https://github.com/barbushin/php-imap
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 */
class IncomingMail extends IncomingMailHeader {

	public $textPlain;
	public $textHtml;
	/** @var IncomingMailAttachment[] */
	protected $attachments = array();

	public function setHeader(IncomingMailHeader $header) {
		foreach(get_object_vars($header) as $property => $value) {
			$this->$property = $value;
		}
	}

	public function addAttachment(IncomingMailAttachment $attachment) {
		$this->attachments[$attachment->id] = $attachment;
	}

	/**
	 * @return IncomingMailAttachment[]
	 */
	public function getAttachments() {
		return $this->attachments;
	}

	/**
	 * Get array of internal HTML links placeholders
	 * @return array attachmentId => link placeholder
	 */
	public function getInternalLinksPlaceholders() {
		return preg_match_all('/=["\'](ci?d:([\w\.%*@-]+))["\']/i', $this->textHtml, $matches) ? array_combine($matches[2], $matches[1]) : array();

	}

	public function replaceInternalLinks($baseUri) {
		$baseUri = rtrim($baseUri, '\\/') . '/';
		$fetchedHtml = $this->textHtml;
		$search = array();
		$replace = array();
		foreach($this->getInternalLinksPlaceholders() as $attachmentId => $placeholder) {
			foreach($this->attachments as $attachment) {
				if($attachment->contentId == $attachmentId) {
					$search[] = $placeholder;
					$replace[] = $baseUri . basename($this->attachments[$attachment->id]->filePath);
				}
			}
		}
		return str_replace($search, $replace, $fetchedHtml);
	}
}
