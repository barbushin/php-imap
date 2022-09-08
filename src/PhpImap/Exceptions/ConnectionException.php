<?php

declare(strict_types=1);

namespace PhpImap\Exceptions;

use Exception;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 */
class ConnectionException extends Exception
{
    public function __construct(array $message, int $code = 0, Exception $previous = null)
    {
        parent::__construct(json_encode($message), $code, $previous);
    }

    public function getErrors(string $select = 'first')
    {
        $message = $this->getMessage();

        switch (strtolower($select)) {
            case 'all':
                return json_decode($message);
                break;
            default:
            case 'first':
                $message = json_decode($message);

                return $message[0];
                break;
            case 'last':
                $message = json_decode($message);

                return $message[\count($message) - 1];
                break;
        }
    }
}
