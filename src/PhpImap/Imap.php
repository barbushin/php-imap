<?php
/**
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 * @author BAPCLTD-Marv
 */
declare(strict_types=1);

namespace PhpImap;

use const CL_EXPUNGE;
use const IMAP_CLOSETIMEOUT;
use const IMAP_OPENTIMEOUT;
use const IMAP_READTIMEOUT;
use const IMAP_WRITETIMEOUT;
use InvalidArgumentException;
use function mb_detect_encoding;
use const NIL;
use function preg_match;
use const SORTARRIVAL;
use const SORTCC;
use const SORTDATE;
use const SORTFROM;
use const SORTSIZE;
use const SORTSUBJECT;
use const SORTTO;
use UnexpectedValueException;

/**
 * @psalm-type PARTSTRUCTURE_PARAM = object{attribute:string, value?:string}
 * @psalm-type PARTSTRUCTURE = object{
 *  id?:string,
 *  encoding:int|mixed,
 *  partStructure:object[],
 *  parameters:PARTSTRUCTURE_PARAM[],
 *  dparameters:object{attribute:string, value:string}[],
 *  parts:array<int, object{disposition?:string}>,
 *  type:int,
 *  subtype:string
 * }
 */
final class Imap
{
    /** @psalm-var list<int> */
    const SORT_CRITERIA = [
        SORTARRIVAL,
        SORTCC,
        SORTDATE,
        SORTFROM,
        SORTSIZE,
        SORTSUBJECT,
        SORTTO,
    ];

    /** @psalm-var list<int> */
    const TIMEOUT_TYPES = [
        IMAP_CLOSETIMEOUT,
        IMAP_OPENTIMEOUT,
        IMAP_READTIMEOUT,
        IMAP_WRITETIMEOUT,
    ];

    /** @psalm-var list<int> */
    const CLOSE_FLAGS = [
        0,
        CL_EXPUNGE,
    ];

    /**
     * @param resource|false $imap_stream
     * @param string         $message
     * @param string         $mailbox
     * @param string|null    $options
     * @param string|null    $internal_date
     *
     * @return true
     *
     * @see imap_append()
     */
    public static function append(
        $imap_stream,
        $mailbox,
        $message,
        $options = null,
        $internal_date = null
    ) {
        if (!\is_string($mailbox)) {
            throw new \InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($mailbox).' given!');
        }
        if (!\is_string($message)) {
            throw new \InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be a string, '.\gettype($message).' given!');
        }
        if (null !== $options && !\is_string($options)) {
            throw new \InvalidArgumentException('Argument 4 passed to '.__METHOD__.'() must be a string, '.\gettype($options).' given!');
        }
        if (null !== $internal_date && !\is_string($internal_date)) {
            throw new \InvalidArgumentException('Argument 5 passed to '.__METHOD__.'() must be a string, '.\gettype($internal_date).' given!');
        }

        imap_errors(); // flush errors

        $imap_stream = self::EnsureResource($imap_stream, __METHOD__, 1);

        if (null !== $options && null !== $internal_date) {
            $result = imap_append(
                $imap_stream,
                $mailbox,
                $message,
                $options,
                $internal_date
            );
        } elseif (null !== $options) {
            $result = imap_append($imap_stream, $mailbox, $message, $options);
        } else {
            $result = imap_append($imap_stream, $mailbox, $message);
        }

        if (false === $result) {
            throw new UnexpectedValueException('Could not append message to mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_append'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int            $msg_number
     * @param int            $options
     *
     * @return string
     */
    public static function body(
        $imap_stream,
        $msg_number,
        $options = 0
    ) {
        if (!\is_int($msg_number)) {
            throw new \InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be an integer, '.\gettype($msg_number).' given!');
        }
        if (!\is_int($options)) {
            throw new \InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_body(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            $msg_number,
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch message body from mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_body'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return object
     */
    public static function check($imap_stream)
    {
        imap_errors(); // flush errors

        $result = imap_check(self::EnsureResource($imap_stream, __METHOD__, 1));

        if (false === $result) {
            throw new UnexpectedValueException('Could not check imap mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_check'));
        }

        /** @var object */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int|string     $sequence
     * @param string         $flag
     * @param int            $options
     *
     * @return true
     */
    public static function clearflag_full(
        $imap_stream,
        $sequence,
        $flag,
        $options = 0
    ) {
        if (!\is_string($flag)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be a string, '.\gettype($flag).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 4 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_clearflag_full(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            self::encodeStringToUtf7Imap(static::EnsureRange(
                $sequence,
                __METHOD__,
                2,
                true
            )),
            self::encodeStringToUtf7Imap($flag),
            $options
        );

        if (!$result) {
            throw new UnexpectedValueException('Could not clear flag on messages!', 0, self::HandleErrors(imap_errors(), 'imap_clearflag_full'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int            $flag
     *
     * @psalm-param value-of<self::CLOSE_FLAGS> $flag
     * @psalm-param 0|32768 $flag
     *
     * @return true
     */
    public static function close($imap_stream, $flag = 0)
    {
        if (!\is_int($flag)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be an integer, '.\gettype($flag).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_close(self::EnsureResource($imap_stream, __METHOD__, 1), $flag);

        if (false === $result) {
            $message = 'Could not close imap connection';

            if (CL_EXPUNGE === ($flag & CL_EXPUNGE)) {
                $message .= ', messages may not have been expunged';
            }

            $message .= '!';
            throw new UnexpectedValueException($message, 0, self::HandleErrors(imap_errors(), 'imap_close'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string         $mailbox
     *
     * @return true
     */
    public static function createmailbox($imap_stream, $mailbox)
    {
        if (!\is_string($mailbox)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($mailbox).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_createmailbox(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            static::encodeStringToUtf7Imap($mailbox)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not create mailbox!', 0, self::HandleErrors(imap_errors(), 'createmailbox'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string|int     $msg_number
     * @param int            $options
     *
     * @return true
     */
    public static function delete(
        $imap_stream,
        $msg_number,
        $options = 0
    ) {
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_delete(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            self::encodeStringToUtf7Imap(self::EnsureRange(
                $msg_number,
                __METHOD__,
                1
            )),
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not delete message from mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_delete'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string         $mailbox
     *
     * @return true
     */
    public static function deletemailbox($imap_stream, $mailbox)
    {
        if (!\is_string($mailbox)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($mailbox).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_deletemailbox(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            static::encodeStringToUtf7Imap($mailbox)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not delete mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_deletemailbox'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return true
     */
    public static function expunge($imap_stream)
    {
        imap_errors(); // flush errors

        $result = imap_expunge(
            self::EnsureResource($imap_stream, __METHOD__, 1)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not expunge messages from mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_expunge'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int|string     $sequence
     * @param int            $options
     *
     * @return object[]
     *
     * @psalm-return list<object>
     */
    public static function fetch_overview(
        $imap_stream,
        $sequence,
        $options = 0
    ) {
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_fetch_overview(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            self::encodeStringToUtf7Imap(self::EnsureRange(
                $sequence,
                __METHOD__,
                1,
                true
            )),
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch overview for message from mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_fetch_overview'));
        }

        /** @psalm-var list<object> */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int            $msg_number
     * @param string|int     $section
     * @param int            $options
     *
     * @return string
     */
    public static function fetchbody(
        $imap_stream,
        $msg_number,
        $section,
        $options = 0
    ) {
        if (!\is_int($msg_number)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be an integer, '.\gettype($msg_number).' given!');
        }
        if (!\is_string($section)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be a string, '.\gettype($section).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 4 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_fetchbody(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            $msg_number,
            self::encodeStringToUtf7Imap((string) $section),
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch message body from mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_fetchbody'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int            $msg_number
     * @param int            $options
     *
     * @return string
     */
    public static function fetchheader(
        $imap_stream,
        $msg_number,
        $options = 0
    ) {
        if (!\is_int($msg_number)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be an integer, '.\gettype($msg_number).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_fetchheader(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            $msg_number,
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch message header from mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_fetchheader'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int            $msg_number
     * @param int            $options
     *
     * @return object
     *
     * @psalm-return PARTSTRUCTURE
     */
    public static function fetchstructure(
        $imap_stream,
        $msg_number,
        $options = 0
    ) {
        if (!\is_int($msg_number)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be an integer, '.\gettype($msg_number).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_fetchstructure(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            $msg_number,
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch message structure from mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_fetchstructure'));
        }

        /** @psalm-var PARTSTRUCTURE */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string         $quota_root
     *
     * @return array[]
     */
    public static function get_quotaroot(
        $imap_stream,
        $quota_root
    ) {
        if (!\is_string($quota_root)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($quota_root).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_get_quotaroot(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            self::encodeStringToUtf7Imap($quota_root)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not quota for mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_get_quotaroot'));
        }

        return $result;
    }

    /**
     * @param resource|false $imap_stream
     * @param string         $ref
     * @param string         $pattern
     *
     * @return object[]
     *
     * @psalm-return list<object>
     */
    public static function getmailboxes(
        $imap_stream,
        $ref,
        $pattern
    ) {
        if (!\is_string($ref)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($ref).' given!');
        }
        if (!\is_string($pattern)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be a string, '.\gettype($pattern).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_getmailboxes(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            $ref,
            $pattern
        );

        if (false === $result) {
            throw new UnexpectedValueException('Call to imap_getmailboxes() with supplied arguments returned false, not array!', 0, self::HandleErrors(imap_errors(), 'imap_headers'));
        }

        /** @psalm-var list<object> */
        return $result;
    }

    /**
     * @param resource|false $imap_stream
     * @param string         $ref
     * @param string         $pattern
     *
     * @return object[]
     *
     * @psalm-return list<object>
     */
    public static function getsubscribed(
        $imap_stream,
        $ref,
        $pattern
    ) {
        if (!\is_string($ref)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($ref).' given!');
        }
        if (!\is_string($pattern)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be a string, '.\gettype($pattern).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_getsubscribed(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            $ref,
            $pattern
        );

        if (false === $result) {
            throw new UnexpectedValueException('Call to imap_getsubscribed() with supplied arguments returned false, not array!', 0, self::HandleErrors(imap_errors(), 'imap_headers'));
        }

        /** @psalm-var list<object> */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return array
     */
    public static function headers($imap_stream)
    {
        imap_errors(); // flush errors

        $result = imap_headers(
            self::EnsureResource($imap_stream, __METHOD__, 1)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch headers from mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_headers'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string         $ref
     * @param string         $pattern
     *
     * @return string[]
     *
     * @psalm-return list<string>
     */
    public static function listOfMailboxes($imap_stream, $ref, $pattern)
    {
        if (!\is_string($ref)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($ref).' given!');
        }
        if (!\is_string($pattern)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be a string, '.\gettype($pattern).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_list(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            static::encodeStringToUtf7Imap($ref),
            static::encodeStringToUtf7Imap($pattern)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not list folders mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_list'));
        }

        return array_values(array_map(
            /**
             * @param string $folder
             *
             * @return string
             */
            static function ($folder) {
                return static::decodeStringFromUtf7ImapToUtf8($folder);
            },
            $result
        ));
    }

    /**
     * @param mixed[] An associative array of headers fields
     * @param mixed[] An indexed array of bodies
     *
     * @psalm-param array{
     *	subject?:string
     * } $envelope An associative array of headers fields (docblock is not complete)
     * @psalm-param list<array{
     *	type?:int,
     *	encoding?:int,
     *	charset?:string,
     *	subtype?:string,
     *	description?:string,
     *	disposition?:array{filename:string}
     * }> $body An indexed array of bodies (docblock is not complete)
     *
     * @return string
     *
     * @todo flesh out array shape pending resolution of https://github.com/vimeo/psalm/issues/1518
     */
    public static function mail_compose(array $envelope, array $body)
    {
        return imap_mail_compose($envelope, $body);
    }

    /**
     * @param false|resource $imap_stream
     * @param int|string     $msglist
     * @param string         $mailbox
     * @param int            $options
     *
     * @return true
     */
    public static function mail_copy(
        $imap_stream,
        $msglist,
        $mailbox,
        $options = 0
    ) {
        if (!\is_string($mailbox)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be a string, '.\gettype($mailbox).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 4 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_mail_copy(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            static::encodeStringToUtf7Imap(self::EnsureRange(
                $msglist,
                __METHOD__,
                2,
                true
            )),
            static::encodeStringToUtf7Imap($mailbox),
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not copy messages!', 0, self::HandleErrors(imap_errors(), 'imap_mail_copy'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int|string     $msglist
     * @param string         $mailbox
     * @param int            $options
     *
     * @return true
     */
    public static function mail_move(
        $imap_stream,
        $msglist,
        $mailbox,
        $options = 0
    ) {
        if (!\is_string($mailbox)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be a string, '.\gettype($mailbox).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 4 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_mail_move(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            static::encodeStringToUtf7Imap(self::EnsureRange(
                $msglist,
                __METHOD__,
                2,
                true
            )),
            static::encodeStringToUtf7Imap($mailbox),
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not move messages!', 0, self::HandleErrors(imap_errors(), 'imap_mail_move'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return object
     */
    public static function mailboxmsginfo($imap_stream)
    {
        imap_errors(); // flush errors

        $result = imap_mailboxmsginfo(
            self::EnsureResource($imap_stream, __METHOD__, 1)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch mailboxmsginfo from mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_mailboxmsginfo'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return int
     */
    public static function num_msg($imap_stream)
    {
        imap_errors(); // flush errors

        $result = imap_num_msg(self::EnsureResource($imap_stream, __METHOD__, 1));

        if (false === $result) {
            throw new UnexpectedValueException('Could not get the number of messages in the mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_num_msg'));
        }

        return $result;
    }

    /**
     * @param string $mailbox
     * @param string $username
     * @param string $password
     * @param int    $options
     * @param int    $n_retries
     *
     * @psalm-param array{DISABLE_AUTHENTICATOR:string}|array<empty, empty> $params
     *
     * @return resource
     */
    public static function open(
        $mailbox,
        $username,
        $password,
        $options = 0,
        $n_retries = 0,
        array $params = []
    ) {
        if (!\is_string($mailbox)) {
            throw new InvalidArgumentException('Argument 1 passed to '.__METHOD__.' must be a string, '.\gettype($mailbox).' given!');
        }
        if (!\is_string($username)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be a string, '.\gettype($username).' given!');
        }
        if (!\is_string($password)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.' must be a string, '.\gettype($password).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 4 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }
        if (!\is_int($n_retries)) {
            throw new InvalidArgumentException('Argument 5 passed to '.__METHOD__.'() must be an integer, '.\gettype($n_retries).' given!');
        }

        if (preg_match("/^\{.*\}(.*)$/", $mailbox, $matches)) {
            $mailbox_name = $matches[1];

            if (!mb_detect_encoding($mailbox_name, 'ASCII', true)) {
                $mailbox = static::encodeStringToUtf7Imap($mailbox_name);
            }
        }

        imap_errors(); // flush errors

        $result = imap_open($mailbox, $username, $password, $options, $n_retries, $params);

        if (!$result) {
            $lastError = imap_last_error();

            if ('' !== trim($lastError)) {
                throw new UnexpectedValueException('IMAP error:'.$lastError);
            }

            throw new UnexpectedValueException('Could not open mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_open'));
        }

        return $result;
    }

    /**
     * @param resource|false $imap_stream
     *
     * @return bool
     */
    public static function ping($imap_stream)
    {
        return \is_resource($imap_stream) && imap_ping($imap_stream);
    }

    /**
     * @param false|resource $imap_stream
     * @param string         $old_mbox
     * @param string         $new_mbox
     *
     * @return true
     */
    public static function renamemailbox(
        $imap_stream,
        $old_mbox,
        $new_mbox
    ) {
        if (!\is_string($old_mbox)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be a string, '.\gettype($old_mbox).' given!');
        }
        if (!\is_string($new_mbox)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.' must be a string, '.\gettype($new_mbox).' given!');
        }

        $imap_stream = self::EnsureResource($imap_stream, __METHOD__, 1);

        $old_mbox = static::encodeStringToUtf7Imap($old_mbox);
        $new_mbox = static::encodeStringToUtf7Imap($new_mbox);

        imap_errors(); // flush errors

        $result = imap_renamemailbox($imap_stream, $old_mbox, $new_mbox);

        if (!$result) {
            throw new UnexpectedValueException('Could not rename mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_renamemailbox'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string         $mailbox
     * @param int            $options
     * @param int            $n_retries
     *
     * @return true
     */
    public static function reopen(
        $imap_stream,
        $mailbox,
        $options = 0,
        $n_retries = 0
    ) {
        if (!\is_string($mailbox)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be a string, '.\gettype($mailbox).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.' must be an integer, '.\gettype($options).' given!');
        }
        if (!\is_int($n_retries)) {
            throw new InvalidArgumentException('Argument 4 passed to '.__METHOD__.' must be an integer, '.\gettype($n_retries).' given!');
        }

        $imap_stream = self::EnsureResource($imap_stream, __METHOD__, 1);

        $mailbox = static::encodeStringToUtf7Imap($mailbox);

        imap_errors(); // flush errors

        $result = imap_reopen($imap_stream, $mailbox, $options, $n_retries);

        if (!$result) {
            throw new UnexpectedValueException('Could not reopen mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_reopen'));
        }

        return $result;
    }

    /**
     * @param false|resource        $imap_stream
     * @param string|false|resource $file
     * @param int                   $msg_number
     * @param string                $part_number
     * @param int                   $options
     *
     * @return true
     */
    public static function savebody(
        $imap_stream,
        $file,
        $msg_number,
        $part_number = '',
        $options = 0
    ) {
        if (!\is_int($msg_number)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be an integer, '.\gettype($msg_number).' given!');
        }
        if (!\is_string($part_number)) {
            throw new InvalidArgumentException('Argument 4 passed to '.__METHOD__.'() must be an integer, '.\gettype($part_number).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 5 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        $imap_stream = self::EnsureResource($imap_stream, __METHOD__, 1);
        $file = \is_string($file) ? $file : self::EnsureResource($file, __METHOD__, 2);
        $part_number = self::encodeStringToUtf7Imap($part_number);

        imap_errors(); // flush errors

        $result = imap_savebody($imap_stream, $file, $msg_number, $part_number, $options);

        if (!$result) {
            throw new UnexpectedValueException('Could not reopen mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_savebody'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string         $criteria
     * @param int            $options
     * @param string         $charset
     *
     * @return int[]
     *
     * @psalm-return list<int>
     */
    public static function search(
        $imap_stream,
        $criteria,
        $options = SE_FREE,
        $charset = null
    ) {
        if (!\is_string($criteria)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be a string, '.\gettype($criteria).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.' must be a string, '.\gettype($options).' given!');
        }
        if (null !== $charset && !\is_string($charset)) {
            throw new InvalidArgumentException('Argument 4 passed to '.__METHOD__.' must be a string or null, '.\gettype($charset).' given!');
        }

        imap_errors(); // flush errors

        $imap_stream = static::EnsureResource($imap_stream, __METHOD__, 1);
        $criteria = static::encodeStringToUtf7Imap($criteria);

        if (\is_string($charset)) {
            $result = imap_search(
                $imap_stream,
                $criteria,
                $options,
                static::encodeStringToUtf7Imap($charset)
            );
        } else {
            $result = imap_search($imap_stream, $criteria, $options);
        }

        if (!$result) {
            $errors = imap_errors();

            if (false === $errors) {
                /*
                * if there were no errors then there were no matches,
                *  rather than a failure to parse criteria.
                */
                return [];
            }

            throw new UnexpectedValueException('Could not search mailbox!', 0, self::HandleErrors($errors, 'imap_search'));
        }

        /** @psalm-var list<int> */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int|string     $sequence
     * @param string         $flag
     * @param int            $options
     *
     * @return true
     */
    public static function setflag_full(
        $imap_stream,
        $sequence,
        $flag,
        $options = NIL
    ) {
        if (!\is_string($flag)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.' must be a string, '.\gettype($flag).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 4 passed to '.__METHOD__.' must be an integer, '.\gettype($options).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_setflag_full(
            self::EnsureResource($imap_stream, __METHOD__, 1),
            self::encodeStringToUtf7Imap(static::EnsureRange(
                $sequence,
                __METHOD__,
                2,
                true
            )),
            self::encodeStringToUtf7Imap($flag),
            $options
        );

        if (!$result) {
            throw new UnexpectedValueException('Could not set flag on messages!', 0, self::HandleErrors(imap_errors(), 'imap_setflag_full'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int            $criteria
     * @param bool           $reverse
     * @param int            $options
     * @param string|null    $search_criteria
     * @param string|null    $charset
     *
     * @psalm-param value-of<self::SORT_CRITERIA> $criteria
     * @psalm-param 1|5|0|2|6|3|4 $criteria
     *
     * @return int[]
     *
     * @psalm-return list<int>
     */
    public static function sort(
        $imap_stream,
        $criteria,
        $reverse,
        $options,
        $search_criteria = null,
        $charset = null
    ) {
        if (!\is_int($criteria)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be an integer, '.\gettype($criteria).' given!');
        }
        if (!\is_bool($reverse)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.' must be a boolean, '.\gettype($reverse).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 4 passed to '.__METHOD__.' must be an integer, '.\gettype($options).' given!');
        }
        if (null !== $search_criteria && !\is_string($search_criteria)) {
            throw new InvalidArgumentException('Argument 5 passed to '.__METHOD__.' must be a string or null, '.\gettype($search_criteria).' given!');
        }
        if (null !== $charset && !\is_string($charset)) {
            throw new InvalidArgumentException('Argument 6 passed to '.__METHOD__.' must be a string or null, '.\gettype($charset).' given!');
        }

        imap_errors(); // flush errors

        $imap_stream = self::EnsureResource($imap_stream, __METHOD__, 1);
        $reverse = (int) $reverse;

        if (null !== $search_criteria && null !== $charset) {
            $result = imap_sort(
                $imap_stream,
                $criteria,
                $reverse,
                $options,
                self::encodeStringToUtf7Imap($search_criteria),
                self::encodeStringToUtf7Imap($charset)
            );
        } elseif (null !== $search_criteria) {
            $result = imap_sort(
                $imap_stream,
                $criteria,
                $reverse,
                $options,
                self::encodeStringToUtf7Imap($search_criteria)
            );
        } else {
            $result = imap_sort(
                $imap_stream,
                $criteria,
                $reverse,
                $options
            );
        }

        if (!$result) {
            throw new UnexpectedValueException('Could not sort messages!', 0, self::HandleErrors(imap_errors(), 'imap_sort'));
        }

        /** @psalm-var list<int> */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string         $mailbox
     * @param int            $options
     *
     * @psalm-param SA_MESSAGES|SA_RECENT|SA_UNSEEN|SA_UIDNEXT|SA_UIDVALIDITY|SA_ALL $flags
     *
     * @return object
     */
    public static function status(
        $imap_stream,
        $mailbox,
        $options
    ) {
        if (!\is_string($mailbox)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($mailbox).' given!');
        }
        if (!\is_int($options)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be an integer, '.\gettype($options).' given!');
        }

        $imap_stream = self::EnsureResource($imap_stream, __METHOD__, 1);

        $mailbox = static::encodeStringToUtf7Imap($mailbox);

        imap_errors(); // flush errors

        $result = imap_status($imap_stream, $mailbox, $options);

        if (!$result) {
            throw new UnexpectedValueException('Could not get status of mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_status'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string         $mailbox
     *
     * @return void
     */
    public static function subscribe(
        $imap_stream,
        $mailbox
    ) {
        if (!\is_string($mailbox)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($mailbox).' given!');
        }

        $imap_stream = self::EnsureResource($imap_stream, __METHOD__, 1);

        $mailbox = static::encodeStringToUtf7Imap($mailbox);

        imap_errors(); // flush errors

        $result = imap_subscribe($imap_stream, $mailbox);

        if (false === $result) {
            throw new UnexpectedValueException('Could not subscribe to mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_subscribe'));
        }
    }

    /**
     * @param int $timeout_type
     * @param int $timeout
     *
     * @psalm-param value-of<self::TIMEOUT_TYPES> $timeout_type
     * @psalm-param 4|1|2|3 $timeout_type
     *
     * @return true|int
     */
    public static function timeout(
        $timeout_type,
        $timeout = -1
    ) {
        if (!\is_int($timeout_type)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be an integer, '.\gettype($timeout_type).' given!');
        }
        if (!\is_int($timeout)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be an integer, '.\gettype($timeout).' given!');
        }

        imap_errors(); // flush errors

        $result = imap_timeout(
            $timeout_type,
            $timeout
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not get/set connection timeout!', 0, self::HandleErrors(imap_errors(), 'imap_timeout'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string         $mailbox
     *
     * @return void
     */
    public static function unsubscribe(
        $imap_stream,
        $mailbox
    ) {
        if (!\is_string($mailbox)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($mailbox).' given!');
        }

        $imap_stream = self::EnsureResource($imap_stream, __METHOD__, 1);

        $mailbox = static::encodeStringToUtf7Imap($mailbox);

        imap_errors(); // flush errors

        $result = imap_unsubscribe($imap_stream, $mailbox);

        if (false === $result) {
            throw new UnexpectedValueException('Could not unsubscribe from mailbox!', 0, self::HandleErrors(imap_errors(), 'imap_unsubscribe'));
        }
    }

    /**
     * Returns the provided string in UTF7-IMAP encoded format.
     *
     * @param string $str
     *
     * @return string $str UTF-7 encoded string
     */
    public static function encodeStringToUtf7Imap($str)
    {
        if (!\is_string($str)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($str).' given!');
        }

        $out = mb_convert_encoding($str, 'UTF7-IMAP', mb_detect_encoding($str, 'UTF-8, ISO-8859-1, ISO-8859-15', true));

        if (!\is_string($out)) {
            throw new UnexpectedValueException('mb_convert_encoding($str, \'UTF-8\', {detected}) could not convert $str');
        }

        return $out;
    }

    /**
     * Returns the provided string in UTF-8 encoded format.
     *
     * @param string $str
     *
     * @return string $str, but UTF-8 encoded
     */
    public static function decodeStringFromUtf7ImapToUtf8($str)
    {
        if (!\is_string($str)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must be a string, '.\gettype($str).' given!');
        }

        $out = mb_convert_encoding($str, 'UTF-8', 'UTF7-IMAP');

        if (!\is_string($out)) {
            throw new UnexpectedValueException('mb_convert_encoding($str, \'UTF-8\', \'UTF7-IMAP\') could not convert $str');
        }

        return $out;
    }

    /**
     * @param false|resource $maybe
     * @param string         $method
     * @param int            $argument
     *
     * @throws InvalidArgumentException if $maybe is not a valid resource
     *
     * @return resource
     */
    private static function EnsureResource($maybe, $method, $argument)
    {
        if (!\is_string($method)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be a string, '.\gettype($method).' given!');
        }
        if (!\is_int($argument)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be an integer, '.\gettype($argument).' given!');
        }

        if (!$maybe || !\is_resource($maybe)) {
            throw new InvalidArgumentException('Argument '.(string) $argument.' passed to '.$method.' must be a valid resource!');
        }

        /** @var resource */
        return $maybe;
    }

    /**
     * @param array|false $errors
     * @param string      $method
     *
     * @return UnexpectedValueException
     */
    private static function HandleErrors($errors, $method)
    {
        if (!\is_string($method)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be a string, '.\gettype($method).' given!');
        }

        if ($errors) {
            return new UnexpectedValueException('IMAP method '.$method.'() failed with error: '.implode('. ', $errors));
        }

        return new UnexpectedValueException('IMAP method '.$method.'() failed!');
    }

    /**
     * @param scalar $msg_number
     * @param string $method
     * @param int    $argument
     * @param bool   $allow_sequence
     *
     * @return string
     */
    private static function EnsureRange(
        $msg_number,
        $method,
        $argument,
        $allow_sequence = false
    ) {
        if (!\is_int($msg_number) && !\is_string($msg_number)) {
            throw new InvalidArgumentException('Argument 1 passed to '.__METHOD__.'() must be an integer or a string!');
        }
        if (!\is_string($method)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be a string, '.\gettype($method).' given!');
        }
        if (!\is_int($argument)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.' must be an integer, '.\gettype($argument).' given!');
        }
        if (!\is_bool($allow_sequence)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.' must be a boolean, '.\gettype($allow_sequence).' given!');
        }

        if (\is_int($msg_number) || preg_match('/^\d+$/', $msg_number)) {
            return sprintf('%1$s:%1$s', $msg_number);
        } elseif (
            $allow_sequence &&
            1 !== preg_match('/^\d+(?:(?:,\d+)+|:\d+)$/', $msg_number)
        ) {
            throw new InvalidArgumentException('Argument '.(string) $argument.' passed to '.$method.'() did not appear to be a valid message id range or sequence!');
        } elseif (1 !== preg_match('/^\d+:\d+$/', $msg_number)) {
            throw new InvalidArgumentException('Argument '.(string) $argument.' passed to '.$method.'() did not appear to be a valid message id range!');
        }

        return $msg_number;
    }
}
