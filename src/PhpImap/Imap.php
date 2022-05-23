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
use const NIL;
use const PHP_MAJOR_VERSION;
use PhpImap\Exceptions\ConnectionException;
use const SE_FREE;
use const SORTARRIVAL;
use const SORTCC;
use const SORTDATE;
use const SORTFROM;
use const SORTSIZE;
use const SORTSUBJECT;
use const SORTTO;
use stdClass;
use Throwable;
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
    public const SORT_CRITERIA = [
        SORTARRIVAL,
        SORTCC,
        SORTDATE,
        SORTFROM,
        SORTSIZE,
        SORTSUBJECT,
        SORTTO,
    ];

    /** @psalm-var list<int> */
    public const TIMEOUT_TYPES = [
        IMAP_CLOSETIMEOUT,
        IMAP_OPENTIMEOUT,
        IMAP_READTIMEOUT,
        IMAP_WRITETIMEOUT,
    ];

    /** @psalm-var list<int> */
    public const CLOSE_FLAGS = [
        0,
        CL_EXPUNGE,
    ];

    /**
     * @param resource|false $imap_stream
     *
     * @return true
     *
     * @see imap_append()
     */
    public static function append(
        $imap_stream,
        string $mailbox,
        string $message,
        string $options = null,
        string $internal_date = null
    ): bool {
        \imap_errors(); // flush errors

        $imap_stream = self::EnsureConnection($imap_stream, __METHOD__, 1);

        if (null !== $options && null !== $internal_date) {
            $result = \imap_append(
                $imap_stream,
                $mailbox,
                $message,
                $options,
                $internal_date
            );
        } elseif (null !== $options) {
            $result = \imap_append($imap_stream, $mailbox, $message, $options);
        } else {
            $result = \imap_append($imap_stream, $mailbox, $message);
        }

        if (false === $result) {
            throw new UnexpectedValueException('Could not append message to mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_append'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     */
    public static function body(
        $imap_stream,
        int $msg_number,
        int $options = 0
    ): string {
        \imap_errors(); // flush errors

        $result = \imap_body(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            $msg_number,
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch message body from mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_body'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     */
    public static function check($imap_stream): object
    {
        \imap_errors(); // flush errors

        $result = \imap_check(self::EnsureConnection($imap_stream, __METHOD__, 1));

        if (false === $result) {
            throw new UnexpectedValueException('Could not check imap mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_check'));
        }

        /** @var object */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int|string     $sequence
     *
     * @return true
     */
    public static function clearflag_full(
        $imap_stream,
        $sequence,
        string $flag,
        int $options = 0
    ): bool {
        \imap_errors(); // flush errors

        $result = \imap_clearflag_full(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
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
            throw new UnexpectedValueException('Could not clear flag on messages!', 0, self::HandleErrors(\imap_errors(), 'imap_clearflag_full'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @psalm-param value-of<self::CLOSE_FLAGS> $flag
     *
     * @return true
     */
    public static function close($imap_stream, int $flag = 0): bool
    {
        \imap_errors(); // flush errors

        /** @var int */
        $flag = $flag;

        $result = \imap_close(self::EnsureConnection($imap_stream, __METHOD__, 1), $flag);

        if (false === $result) {
            $message = 'Could not close imap connection';

            if (CL_EXPUNGE === ($flag & CL_EXPUNGE)) {
                $message .= ', messages may not have been expunged';
            }

            $message .= '!';
            throw new UnexpectedValueException($message, 0, self::HandleErrors(\imap_errors(), 'imap_close'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return true
     */
    public static function createmailbox($imap_stream, string $mailbox): bool
    {
        \imap_errors(); // flush errors

        $result = \imap_createmailbox(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            static::encodeStringToUtf7Imap($mailbox)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not create mailbox!', 0, self::HandleErrors(\imap_errors(), 'createmailbox'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string|int     $msg_number
     *
     * @return true
     */
    public static function delete(
        $imap_stream,
        $msg_number,
        int $options = 0
    ): bool {
        /**
         * @var int
         *
         * @todo remove docblock pending resolution of https://github.com/vimeo/psalm/issues/2620
         */
        $msg_number = self::encodeStringToUtf7Imap(self::EnsureRange(
            $msg_number,
            __METHOD__,
            1
        ));

        \imap_errors(); // flush errors

        $result = \imap_delete(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            $msg_number,
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not delete message from mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_delete'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return true
     */
    public static function deletemailbox($imap_stream, string $mailbox): bool
    {
        \imap_errors(); // flush errors

        $result = \imap_deletemailbox(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            static::encodeStringToUtf7Imap($mailbox)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not delete mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_deletemailbox'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return true
     */
    public static function expunge($imap_stream): bool
    {
        \imap_errors(); // flush errors

        $result = \imap_expunge(
            self::EnsureConnection($imap_stream, __METHOD__, 1)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not expunge messages from mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_expunge'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int|string     $sequence
     *
     * @return object[]
     *
     * @psalm-return list<object>
     */
    public static function fetch_overview(
        $imap_stream,
        $sequence,
        int $options = 0
    ): array {
        \imap_errors(); // flush errors

        $result = \imap_fetch_overview(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            self::encodeStringToUtf7Imap(self::EnsureRange(
                $sequence,
                __METHOD__,
                1,
                true
            )),
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch overview for message from mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_fetch_overview'));
        }

        /** @psalm-var list<object> */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param string|int     $section
     */
    public static function fetchbody(
        $imap_stream,
        int $msg_number,
        $section,
        int $options = 0
    ): string {
        if (!\is_string($section) && !\is_int($section)) {
            throw new InvalidArgumentException('Argument 3 passed to '.__METHOD__.'() must be a string or integer, '.\gettype($section).' given!');
        }

        \imap_errors(); // flush errors

        $result = \imap_fetchbody(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            $msg_number,
            self::encodeStringToUtf7Imap((string) $section),
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch message body from mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_fetchbody'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     */
    public static function fetchheader(
        $imap_stream,
        int $msg_number,
        int $options = 0
    ): string {
        \imap_errors(); // flush errors

        $result = \imap_fetchheader(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            $msg_number,
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch message header from mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_fetchheader'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @psalm-return PARTSTRUCTURE
     */
    public static function fetchstructure(
        $imap_stream,
        int $msg_number,
        int $options = 0
    ): object {
        \imap_errors(); // flush errors

        $result = \imap_fetchstructure(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            $msg_number,
            $options
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch message structure from mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_fetchstructure'));
        }

        /** @psalm-var PARTSTRUCTURE */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @todo add return array shape pending resolution of https://github.com/vimeo/psalm/issues/2620
     */
    public static function get_quotaroot(
        $imap_stream,
        string $quota_root
    ): array {
        \imap_errors(); // flush errors

        $result = \imap_get_quotaroot(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            self::encodeStringToUtf7Imap($quota_root)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not quota for mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_get_quotaroot'));
        }

        return $result;
    }

    /**
     * @param resource|false $imap_stream
     *
     * @return object[]
     *
     * @psalm-return list<object>
     */
    public static function getmailboxes(
        $imap_stream,
        string $ref,
        string $pattern
    ): array {
        \imap_errors(); // flush errors

        $result = \imap_getmailboxes(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            $ref,
            $pattern
        );

        if (false === $result) {
            $errors = \imap_errors();

            if (false === $errors) {
                /*
                * if there were no errors then there were no mailboxes,
                *  rather than a failure to get mailboxes.
                */
                return [];
            }

            throw new UnexpectedValueException('Call to imap_getmailboxes() with supplied arguments returned false, not array!', 0, self::HandleErrors(\imap_errors(), 'imap_getmailboxes'));
        }

        /** @psalm-var list<object> */
        return $result;
    }

    /**
     * @param resource|false $imap_stream
     *
     * @return object[]
     *
     * @psalm-return list<object>
     */
    public static function getsubscribed(
        $imap_stream,
        string $ref,
        string $pattern
    ): array {
        \imap_errors(); // flush errors

        $result = \imap_getsubscribed(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            $ref,
            $pattern
        );

        if (false === $result) {
            throw new UnexpectedValueException('Call to imap_getsubscribed() with supplied arguments returned false, not array!', 0, self::HandleErrors(\imap_errors(), 'imap_getsubscribed'));
        }

        /** @psalm-var list<object> */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     */
    public static function headers($imap_stream): array
    {
        \imap_errors(); // flush errors

        $result = \imap_headers(
            self::EnsureConnection($imap_stream, __METHOD__, 1)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch headers from mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_headers'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return string[]
     *
     * @psalm-return list<string>
     */
    public static function listOfMailboxes($imap_stream, string $ref, string $pattern): array
    {
        \imap_errors(); // flush errors

        $result = \imap_list(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
            static::encodeStringToUtf7Imap($ref),
            static::encodeStringToUtf7Imap($pattern)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not list folders mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_list'));
        }

        return \array_values(\array_map(
            static function (string $folder): string {
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
     * @todo flesh out array shape pending resolution of https://github.com/vimeo/psalm/issues/1518
     *
     * @psalm-pure
     */
    public static function mail_compose(array $envelope, array $body): string
    {
        return \imap_mail_compose($envelope, $body);
    }

    /**
     * @param false|resource $imap_stream
     * @param int|string     $msglist
     *
     * @return true
     */
    public static function mail_copy(
        $imap_stream,
        $msglist,
        string $mailbox,
        int $options = 0
    ): bool {
        \imap_errors(); // flush errors

        $result = \imap_mail_copy(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
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
            throw new UnexpectedValueException('Could not copy messages!', 0, self::HandleErrors(\imap_errors(), 'imap_mail_copy'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     * @param int|string     $msglist
     *
     * @return true
     */
    public static function mail_move(
        $imap_stream,
        $msglist,
        string $mailbox,
        int $options = 0
    ): bool {
        \imap_errors(); // flush errors

        $result = \imap_mail_move(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
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
            throw new UnexpectedValueException('Could not move messages!', 0, self::HandleErrors(\imap_errors(), 'imap_mail_move'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     */
    public static function mailboxmsginfo($imap_stream): stdClass
    {
        \imap_errors(); // flush errors

        $result = \imap_mailboxmsginfo(
            self::EnsureConnection($imap_stream, __METHOD__, 1)
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not fetch mailboxmsginfo from mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_mailboxmsginfo'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     */
    public static function num_msg($imap_stream): int
    {
        \imap_errors(); // flush errors

        $result = \imap_num_msg(self::EnsureConnection($imap_stream, __METHOD__, 1));

        if (false === $result) {
            throw new UnexpectedValueException('Could not get the number of messages in the mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_num_msg'));
        }

        return $result;
    }

    /**
     * @psalm-param array{DISABLE_AUTHENTICATOR:string}|array<empty, empty> $params
     *
     * @return resource
     */
    public static function open(
        string $mailbox,
        string $username,
        string $password,
        int $options = 0,
        int $n_retries = 0,
        array $params = []
    ) {
        if (\preg_match("/^\{.*\}(.*)$/", $mailbox, $matches)) {
            $mailbox_name = $matches[1] ?? '';

            if (!\mb_detect_encoding($mailbox_name, 'ASCII', true)) {
                $mailbox = static::encodeStringToUtf7Imap($mailbox);
            }
        }

        \imap_errors(); // flush errors

        $result = @\imap_open($mailbox, $username, $password, $options, $n_retries, $params);

        if (!$result) {
            throw new ConnectionException(\imap_errors() ?: []);
        }

        return $result;
    }

    /**
     * @param resource|false $imap_stream
     *
     * @psalm-pure
     */
    public static function ping($imap_stream): bool
    {
        return (\is_resource($imap_stream) || $imap_stream instanceof \IMAP\Connection) && \imap_ping($imap_stream);
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return true
     */
    public static function renamemailbox(
        $imap_stream,
        string $old_mbox,
        string $new_mbox
    ): bool {
        $imap_stream = self::EnsureConnection($imap_stream, __METHOD__, 1);

        $old_mbox = static::encodeStringToUtf7Imap($old_mbox);
        $new_mbox = static::encodeStringToUtf7Imap($new_mbox);

        \imap_errors(); // flush errors

        $result = \imap_renamemailbox($imap_stream, $old_mbox, $new_mbox);

        if (!$result) {
            throw new UnexpectedValueException('Could not rename mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_renamemailbox'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return true
     */
    public static function reopen(
        $imap_stream,
        string $mailbox,
        int $options = 0,
        int $n_retries = 0
    ): bool {
        $imap_stream = self::EnsureConnection($imap_stream, __METHOD__, 1);

        $mailbox = static::encodeStringToUtf7Imap($mailbox);

        \imap_errors(); // flush errors

        $result = \imap_reopen($imap_stream, $mailbox, $options, $n_retries);

        if (!$result) {
            throw new UnexpectedValueException('Could not reopen mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_reopen'));
        }

        return $result;
    }

    /**
     * @param false|resource        $imap_stream
     * @param string|false|resource $file
     *
     * @return true
     */
    public static function savebody(
        $imap_stream,
        $file,
        int $msg_number,
        string $part_number = '',
        int $options = 0
    ): bool {
        $imap_stream = self::EnsureConnection($imap_stream, __METHOD__, 1);
        $file = \is_string($file) ? $file : self::EnsureResource($file, __METHOD__, 2);
        $part_number = self::encodeStringToUtf7Imap($part_number);

        \imap_errors(); // flush errors

        $result = \imap_savebody($imap_stream, $file, $msg_number, $part_number, $options);

        if (!$result) {
            throw new UnexpectedValueException('Could not reopen mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_savebody'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @return int[]
     *
     * @psalm-return list<int>
     */
    public static function search(
        $imap_stream,
        string $criteria,
        int $options = SE_FREE,
        string $charset = null,
        bool $encodeCriteriaAsUtf7Imap = false
    ): array {
        \imap_errors(); // flush errors

        $imap_stream = static::EnsureConnection($imap_stream, __METHOD__, 1);

        if ($encodeCriteriaAsUtf7Imap) {
            $criteria = static::encodeStringToUtf7Imap($criteria);
        }

        if (\is_string($charset)) {
            $result = \imap_search(
                $imap_stream,
                $criteria,
                $options,
                static::encodeStringToUtf7Imap($charset)
            );
        } else {
            $result = \imap_search($imap_stream, $criteria, $options);
        }

        if (!$result) {
            $errors = \imap_errors();

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
     *
     * @return true
     */
    public static function setflag_full(
        $imap_stream,
        $sequence,
        string $flag,
        int $options = NIL
    ): bool {
        \imap_errors(); // flush errors

        $result = \imap_setflag_full(
            self::EnsureConnection($imap_stream, __METHOD__, 1),
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
            throw new UnexpectedValueException('Could not set flag on messages!', 0, self::HandleErrors(\imap_errors(), 'imap_setflag_full'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @psalm-param value-of<self::SORT_CRITERIA> $criteria
     * @psalm-suppress InvalidArgument
     *
     * @todo InvalidArgument, although it's correct: Argument 3 of imap_sort expects int, bool provided https://www.php.net/manual/de/function.imap-sort.php
     *
     * @return int[]
     *
     * @psalm-return list<int>
     */
    public static function sort(
        $imap_stream,
        int $criteria,
        bool $reverse,
        int $options,
        string $search_criteria = null,
        string $charset = null
    ): array {
        \imap_errors(); // flush errors

        $imap_stream = self::EnsureConnection($imap_stream, __METHOD__, 1);

        /** @var int */
        $criteria = $criteria;

        if (PHP_MAJOR_VERSION < 8) {
            /** @var int */
            $reverse = (int) $reverse;
        } else {
            /** @var bool */
            $reverse = $reverse;
        }

        if (null !== $search_criteria && null !== $charset) {
            $result = \imap_sort(
                $imap_stream,
                $criteria,
                $reverse,
                $options,
                self::encodeStringToUtf7Imap($search_criteria),
                self::encodeStringToUtf7Imap($charset)
            );
        } elseif (null !== $search_criteria) {
            $result = \imap_sort(
                $imap_stream,
                $criteria,
                $reverse,
                $options,
                self::encodeStringToUtf7Imap($search_criteria)
            );
        } else {
            $result = \imap_sort(
                $imap_stream,
                $criteria,
                $reverse,
                $options
            );
        }

        if (false === $result) {
            throw new UnexpectedValueException('Could not sort messages!', 0, self::HandleErrors(\imap_errors(), 'imap_sort'));
        }

        /** @psalm-var list<int> */
        return $result;
    }

    /**
     * @param false|resource $imap_stream
     *
     * @psalm-param SA_MESSAGES|SA_RECENT|SA_UNSEEN|SA_UIDNEXT|SA_UIDVALIDITY|SA_ALL $flags
     */
    public static function status($imap_stream, string $mailbox, int $options): stdClass
    {
        $imap_stream = self::EnsureConnection($imap_stream, __METHOD__, 1);

        $mailbox = static::encodeStringToUtf7Imap($mailbox);

        \imap_errors(); // flush errors

        $result = \imap_status($imap_stream, $mailbox, $options);

        if (!$result) {
            throw new UnexpectedValueException('Could not get status of mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_status'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     */
    public static function subscribe($imap_stream, string $mailbox): void
    {
        $imap_stream = self::EnsureConnection($imap_stream, __METHOD__, 1);

        $mailbox = static::encodeStringToUtf7Imap($mailbox);

        \imap_errors(); // flush errors

        $result = \imap_subscribe($imap_stream, $mailbox);

        if (false === $result) {
            throw new UnexpectedValueException('Could not subscribe to mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_subscribe'));
        }
    }

    /**
     * @psalm-param value-of<self::TIMEOUT_TYPES> $timeout_type
     *
     * @return true|int
     */
    public static function timeout(
        int $timeout_type,
        int $timeout = -1
    ) {
        \imap_errors(); // flush errors

        /** @var int */
        $timeout_type = $timeout_type;

        $result = \imap_timeout(
            $timeout_type,
            $timeout
        );

        if (false === $result) {
            throw new UnexpectedValueException('Could not get/set connection timeout!', 0, self::HandleErrors(\imap_errors(), 'imap_timeout'));
        }

        return $result;
    }

    /**
     * @param false|resource $imap_stream
     */
    public static function unsubscribe(
        $imap_stream,
        string $mailbox
    ): void {
        $imap_stream = self::EnsureConnection($imap_stream, __METHOD__, 1);

        $mailbox = static::encodeStringToUtf7Imap($mailbox);

        \imap_errors(); // flush errors

        $result = \imap_unsubscribe($imap_stream, $mailbox);

        if (false === $result) {
            throw new UnexpectedValueException('Could not unsubscribe from mailbox!', 0, self::HandleErrors(\imap_errors(), 'imap_unsubscribe'));
        }
    }

    /**
     * Returns the provided string in UTF7-IMAP encoded format.
     *
     * @return string $str UTF-7 encoded string
     *
     * @psalm-pure
     */
    public static function encodeStringToUtf7Imap(string $str): string
    {
        $out = \mb_convert_encoding($str, 'UTF7-IMAP', \mb_detect_encoding($str, 'UTF-8, ISO-8859-1, ISO-8859-15', true));

        if (!\is_string($out)) {
            throw new UnexpectedValueException('mb_convert_encoding($str, \'UTF-8\', {detected}) could not convert $str');
        }

        return $out;
    }

    /**
     * Returns the provided string in UTF-8 encoded format.
     *
     * @return string $str, but UTF-8 encoded
     *
     * @psalm-pure
     */
    public static function decodeStringFromUtf7ImapToUtf8(string $str): string
    {
        $out = \mb_convert_encoding($str, 'UTF-8', 'UTF7-IMAP');

        if (!\is_string($out)) {
            throw new UnexpectedValueException('mb_convert_encoding($str, \'UTF-8\', \'UTF7-IMAP\') could not convert $str');
        }

        return $out;
    }

    /**
     * @param false|resource $maybe
     *
     * @throws InvalidArgumentException if $maybe is not a valid resource
     *
     * @return resource
     *
     * @psalm-pure
     */
    private static function EnsureResource($maybe, string $method, int $argument)
    {
        if (!$maybe || (!\is_resource($maybe) && !$maybe instanceof \IMAP\Connection)) {
            throw new InvalidArgumentException('Argument '.(string) $argument.' passed to '.$method.' must be a valid resource!');
        }

        /** @var resource */
        return $maybe;
    }

    /**
     * @param false|resource $maybe
     *
     * @throws Exceptions\ConnectionException if $maybe is not a valid resource
     *
     * @return resource
     */
    private static function EnsureConnection($maybe, string $method, int $argument)
    {
        try {
            return self::EnsureResource($maybe, $method, $argument);
        } catch (Throwable $e) {
            throw new Exceptions\ConnectionException('Argument '.(string) $argument.' passed to '.$method.' must be valid resource!', 0, $e);
        }
    }

    /**
     * @param array|false $errors
     *
     * @psalm-pure
     */
    private static function HandleErrors($errors, string $method): UnexpectedValueException
    {
        if ($errors) {
            return new UnexpectedValueException('IMAP method '.$method.'() failed with error: '.\implode('. ', $errors));
        }

        return new UnexpectedValueException('IMAP method '.$method.'() failed!');
    }

    /**
     * @param scalar $msg_number
     *
     * @psalm-pure
     */
    private static function EnsureRange(
        $msg_number,
        string $method,
        int $argument,
        bool $allow_sequence = false
    ): string {
        if (!\is_int($msg_number) && !\is_string($msg_number)) {
            throw new InvalidArgumentException('Argument 1 passed to '.__METHOD__.'() must be an integer or a string!');
        }

        $regex = '/^\d+:\d+$/';
        $suffix = '() did not appear to be a valid message id range!';

        if ($allow_sequence) {
            $regex = '/^\d+(?:(?:,\d+)+|:\d+)$/';
            $suffix = '() did not appear to be a valid message id range or sequence!';
        }

        if (\is_int($msg_number) || \preg_match('/^\d+$/', $msg_number)) {
            return \sprintf('%1$s:%1$s', $msg_number);
        } elseif (1 !== \preg_match($regex, $msg_number)) {
            throw new InvalidArgumentException('Argument '.(string) $argument.' passed to '.$method.$suffix);
        }

        return $msg_number;
    }
}
