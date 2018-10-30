<?php namespace PhpImap;

/**
 * @see https://github.com/barbushin/php-imap
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 */
class IncomingMailAttachment extends AbstractMailAttachment {
    /**
     * Function from save file
     *
     * @param $content
     * @return mixed
     */
    public function save ($content) {
        file_put_contents($this->filePath, $content);
    }
}
