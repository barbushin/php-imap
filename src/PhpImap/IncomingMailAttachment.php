<?php namespace PhpImap;

/**
 * @see https://github.com/barbushin/php-imap
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 * 
 * @property-read string $filePath lazy attachment data file
 */
class IncomingMailAttachment {

	public $id;
	public $contentId;
	public $name;
	public $disposition;
	private $file_path;
	private $dataInfo;

	public function __get ($name) {
	    if($name !== 'filePath') {
	        trigger_error("Undefined property: IncomingMailAttachment::$name");
	    }
        if(!isset($this->file_path)) {
            return false;
        }
        $this->filePath = $this->file_path;
        if(@file_exists($this->file_path)) {
            return $this->filePath;
        }
        if(false === file_put_contents($this->filePath, $this->dataInfo->fetch())) {
            unset($this->filePath);
            unset($this->file_path);
            return false;
        }
        return $this->filePath;
    }

    public function setFilePath($filePath) {
        $this->file_path = $filePath;
    }

    public function addDataPartInfo(DataPartInfo $dataInfo) {
	    $this->dataInfo = $dataInfo;
	}
}
