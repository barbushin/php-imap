<?php

declare(strict_types=1);

namespace PhpImap;

use const CL_EXPUNGE;
use function count;
use const CP_UID;
use const DATE_RFC3339;
use DateTime;
use const DIRECTORY_SEPARATOR;
use Exception;
use const FILEINFO_EXTENSION;
use const FILEINFO_MIME;
use const FILEINFO_MIME_ENCODING;
use const FILEINFO_MIME_TYPE;
use const FILEINFO_NONE;
use const FILEINFO_RAW;
use const FT_PEEK;
use const FT_PREFETCHTEXT;
use const FT_UID;
use const IMAP_CLOSETIMEOUT;
use const IMAP_OPENTIMEOUT;
use const IMAP_READTIMEOUT;
use const IMAP_WRITETIMEOUT;
use InvalidArgumentException;
use const OP_ANONYMOUS;
use const OP_DEBUG;
use const OP_HALFOPEN;
use const OP_PROTOTYPE;
use const OP_READONLY;
use const OP_SECURE;
use const OP_SHORTCACHE;
use const OP_SILENT;
use const PATHINFO_EXTENSION;
use PhpImap\Exceptions\ConnectionException;
use PhpImap\Exceptions\InvalidParameterException;
use const SA_ALL;
use const SE_FREE;
use const SE_UID;
use const SORT_NUMERIC;
use const SORTARRIVAL;
use const ST_UID;
use stdClass;
use const TYPEMESSAGE;
use const TYPEMULTIPART;
use const TYPETEXT;
use UnexpectedValueException;

/**
 * @see https://github.com/barbushin/php-imap
 *
 * @author Barbushin Sergey http://linkedin.com/in/barbushin
 *
 * @psalm-type PARTSTRUCTURE_PARAM = object{attribute:string, value?:string}
 *
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
 * @psalm-type HOSTNAMEANDADDRESS_ENTRY = object{host?:string, personal?:string, mailbox:string}
 * @psalm-type HOSTNAMEANDADDRESS = array{0:HOSTNAMEANDADDRESS_ENTRY, 1?:HOSTNAMEANDADDRESS_ENTRY}
 * @psalm-type COMPOSE_ENVELOPE = array{
 *	subject?:string
 * }
 * @psalm-type COMPOSE_BODY = list<array{
 *	type?:int,
 *	encoding?:int,
 *	charset?:string,
 *	subtype?:string,
 *	description?:string,
 *	disposition?:array{filename:string}
 * }>
 *
 * @todo see @todo of Imap::mail_compose()
 */
class Mailbox
{
    public const EXPECTED_SIZE_OF_MESSAGE_AS_ARRAY = 2;

    public const MAX_LENGTH_FILEPATH = 255;

    public const PART_TYPE_TWO = 2;

    public const IMAP_OPTIONS_SUPPORTED_VALUES =
        OP_READONLY // 2
            | OP_ANONYMOUS // 4
            | OP_HALFOPEN // 64
            | CL_EXPUNGE // 32768
            | OP_DEBUG // 1
            | OP_SHORTCACHE // 8
            | OP_SILENT // 16
            | OP_PROTOTYPE // 32
            | OP_SECURE // 256
    ;

    /** @var string */
    public $decodeMimeStrDefaultCharset = 'default';

    /** @var string */
    protected $imapPath;

    /** @var string */
    protected $imapLogin;

    /** @var string */
    protected $imapPassword;

    /** @var int */
    protected $imapSearchOption = SE_UID;

    /** @var int */
    protected $connectionRetry = 0;

    /** @var int */
    protected $connectionRetryDelay = 100;

    /** @var int */
    protected $imapOptions = 0;

    /** @var int */
    protected $imapRetriesNum = 0;

    /** @psalm-var array{DISABLE_AUTHENTICATOR?:string} */
    protected $imapParams = [];

    /** @var string */
    protected $serverEncoding = 'UTF-8';

    /** @var string|null */
    protected $attachmentsDir = null;

    /** @var bool */
    protected $expungeOnDisconnect = true;

    /**
     * @var int[]
     *
     * @psalm-var array{1?:int, 2?:int, 3?:int, 4?:int}
     */
    protected $timeouts = [];

    /** @var bool */
    protected $attachmentsIgnore = false;

    /** @var string */
    protected $pathDelimiter = '.';

    /** @var string */
    protected $mailboxFolder;

    /** @var bool|false */
    protected $attachmentFilenameMode = false;

    /** @var resource|null */
    private $imapStream;

    /**
     * @throws InvalidParameterException
     */
    public function __construct(string $imapPath, string $login, string $password, string $attachmentsDir = null, string $serverEncoding = 'UTF-8', bool $trimImapPath = true, bool $attachmentFilenameMode = false)
    {
        $this->imapPath = (true == $trimImapPath) ? \trim($imapPath) : $imapPath;
        $this->imapLogin = \trim($login);
        $this->imapPassword = $password;
        $this->setServerEncoding($serverEncoding);
        if (null != $attachmentsDir) {
            $this->setAttachmentsDir($attachmentsDir);
        }
        $this->setAttachmentFilenameMode($attachmentFilenameMode);

        $this->setMailboxFolder();
    }

    /**
     * Disconnects from the IMAP server / mailbox.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Sets / Changes the path delimiter character (Supported values: '.', '/').
     *
     * @param string $delimiter Path delimiter
     *
     * @throws InvalidParameterException
     */
    public function setPathDelimiter(string $delimiter): void
    {
        if (!$this->validatePathDelimiter($delimiter)) {
            throw new InvalidParameterException('setPathDelimiter() can only set the delimiter to these characters: ".", "/"');
        }

        $this->pathDelimiter = $delimiter;
    }

    /**
     * Returns the current set path delimiter character.
     *
     * @return string Path delimiter
     */
    public function getPathDelimiter(): string
    {
        return $this->pathDelimiter;
    }

    /**
     * Validates the given path delimiter character.
     *
     * @param string $delimiter Path delimiter
     *
     * @return bool true (supported) or false (unsupported)
     *
     * @psalm-pure
     */
    public function validatePathDelimiter(string $delimiter): bool
    {
        $supported_delimiters = ['.', '/'];

        if (!\in_array($delimiter, $supported_delimiters)) {
            return false;
        }

        return true;
    }

    /**
     * Returns the current set server encoding.
     *
     * @return string Server encoding (eg. 'UTF-8')
     */
    public function getServerEncoding(): string
    {
        return $this->serverEncoding;
    }

    /**
     * Sets / Changes the server encoding.
     *
     * @param string $serverEncoding Server encoding (eg. 'UTF-8')
     *
     * @throws InvalidParameterException
     */
    public function setServerEncoding(string $serverEncoding): void
    {
        $serverEncoding = \strtoupper(\trim($serverEncoding));

        $supported_encodings = \array_map('strtoupper', \mb_list_encodings());

        if (!\in_array($serverEncoding, $supported_encodings) && 'US-ASCII' != $serverEncoding) {
            throw new InvalidParameterException('"'.$serverEncoding.'" is not supported by setServerEncoding(). Your system only supports these encodings: US-ASCII, '.\implode(', ', $supported_encodings));
        }

        $this->serverEncoding = $serverEncoding;
    }

    /**
     * Returns the current set attachment filename mode.
     *
     * @return bool Attachment filename mode (e.g. true)
     */
    public function getAttachmentFilenameMode(): bool
    {
        return $this->attachmentFilenameMode;
    }

    /**
     * Sets / Changes the attachment filename mode.
     *
     * @param bool $attachmentFilenameMode Attachment filename mode (e.g. false)
     *
     * @throws InvalidParameterException
     */
    public function setAttachmentFilenameMode(bool $attachmentFilenameMode): void
    {
        if (!\is_bool($attachmentFilenameMode)) {
            throw new InvalidParameterException('"'.$attachmentFilenameMode.'" is not supported by setOriginalAttachmentFilename(). Only boolean values are allowed: true (use original filename), false (use random generated filename)');
        }

        $this->attachmentFilenameMode = $attachmentFilenameMode;
    }

    /**
     * Returns the current set IMAP search option.
     *
     * @return int IMAP search option (eg. 'SE_UID')
     */
    public function getImapSearchOption(): int
    {
        return $this->imapSearchOption;
    }

    /**
     * Sets / Changes the IMAP search option.
     *
     * @param int $imapSearchOption IMAP search option (eg. 'SE_UID')
     *
     * @psalm-param 1|2 $imapSearchOption
     *
     * @throws InvalidParameterException
     */
    public function setImapSearchOption(int $imapSearchOption): void
    {
        $supported_options = [SE_FREE, SE_UID];

        if (!\in_array($imapSearchOption, $supported_options, true)) {
            throw new InvalidParameterException('"'.$imapSearchOption.'" is not supported by setImapSearchOption(). Supported options are SE_FREE and SE_UID.');
        }

        $this->imapSearchOption = $imapSearchOption;
    }

    /**
     * Set $this->attachmentsIgnore param. Allow to ignore attachments when they are not required and boost performance.
     */
    public function setAttachmentsIgnore(bool $attachmentsIgnore): void
    {
        $this->attachmentsIgnore = $attachmentsIgnore;
    }

    /**
     * Get $this->attachmentsIgnore param.
     *
     * @return bool $attachmentsIgnore
     */
    public function getAttachmentsIgnore(): bool
    {
        return $this->attachmentsIgnore;
    }

    /**
     * Sets the timeout of all or one specific type.
     *
     * @param int   $timeout Timeout in seconds
     * @param array $types   One of the following: IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT
     *
     * @psalm-param list<1|2|3|4> $types
     *
     * @throws InvalidParameterException
     */
    public function setTimeouts(int $timeout, array $types = [IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT]): void
    {
        $supported_types = [IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT];

        $found_types = \array_intersect($types, $supported_types);

        if (\count($types) != \count($found_types)) {
            throw new InvalidParameterException('You have provided at least one unsupported timeout type. Supported types are: IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT');
        }

        /** @var array{1?:int, 2?:int, 3?:int, 4?:int} */
        $this->timeouts = \array_fill_keys($types, $timeout);
    }

    /**
     * Returns the IMAP login (usually an email address).
     *
     * @return string IMAP login
     */
    public function getLogin(): string
    {
        return $this->imapLogin;
    }

    /**
     * Set custom connection arguments of imap_open method. See http://php.net/imap_open.
     *
     * @param string[]|null $params
     *
     * @psalm-param array{DISABLE_AUTHENTICATOR?:string}|array<empty, empty>|null $params
     *
     * @throws InvalidParameterException
     */
    public function setConnectionArgs(int $options = 0, int $retriesNum = 0, array $params = null): void
    {
        if (0 !== $options) {
            if (($options & self::IMAP_OPTIONS_SUPPORTED_VALUES) !== $options) {
                throw new InvalidParameterException('Please check your option for setConnectionArgs()! Unsupported option "'.$options.'". Available options: https://www.php.net/manual/de/function.imap-open.php');
            }
            $this->imapOptions = $options;
        }

        if (0 != $retriesNum) {
            if ($retriesNum < 0) {
                throw new InvalidParameterException('Invalid number of retries provided for setConnectionArgs()! It must be a positive integer. (eg. 1 or 3)');
            }
            $this->imapRetriesNum = $retriesNum;
        }

        if (\is_array($params) && \count($params) > 0) {
            $supported_params = ['DISABLE_AUTHENTICATOR'];

            foreach (\array_keys($params) as $key) {
                if (!\in_array($key, $supported_params, true)) {
                    throw new InvalidParameterException('Invalid array key of params provided for setConnectionArgs()! Only DISABLE_AUTHENTICATOR is currently valid.');
                }
            }

            $this->imapParams = $params;
        }
    }

    /**
     * Set custom folder for attachments in case you want to have tree of folders for each email
     * i.e. a/1 b/1 c/1 where a,b,c - senders, i.e. john@smith.com.
     *
     * @param string $attachmentsDir Folder where to save attachments
     *
     * @throws InvalidParameterException
     */
    public function setAttachmentsDir(string $attachmentsDir): void
    {
        if (empty(\trim($attachmentsDir))) {
            throw new InvalidParameterException('setAttachmentsDir() expects a string as first parameter!');
        }
        if (!\is_dir($attachmentsDir)) {
            throw new InvalidParameterException('Directory "'.$attachmentsDir.'" not found');
        }
        $this->attachmentsDir = \rtrim(\realpath($attachmentsDir), '\\/');
    }

    /**
     * Get current saving folder for attachments.
     *
     * @return string|null Attachments dir
     */
    public function getAttachmentsDir(): ?string
    {
        return $this->attachmentsDir;
    }

    /**
     * Sets / Changes the attempts / retries to connect.
     */
    public function setConnectionRetry(int $maxAttempts): void
    {
        $this->connectionRetry = $maxAttempts;
    }

    /**
     * Sets / Changes the delay between each attempt / retry to connect.
     */
    public function setConnectionRetryDelay(int $milliseconds): void
    {
        $this->connectionRetryDelay = $milliseconds;
    }

    /**
     * Get IMAP mailbox connection stream.
     *
     * @param bool $forceConnection Initialize connection if it's not initialized
     *
     * @return resource
     */
    public function getImapStream(bool $forceConnection = true)
    {
        if ($forceConnection) {
            $this->pingOrDisconnect();
            if (!$this->imapStream) {
                $this->imapStream = $this->initImapStreamWithRetry();
            }
        }

        /** @var resource */
        return $this->imapStream;
    }

    public function hasImapStream(): bool
    {
        try {
            return (\is_resource($this->imapStream) || $this->imapStream instanceof \IMAP\Connection) && \imap_ping($this->imapStream);
        } catch (\Error $exception) {
            // From PHP 8.1.10 imap_ping() on a closed stream throws a ValueError. See #680.
            $valueError = '\ValueError';
            if (class_exists($valueError) && $exception instanceof $valueError) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * Returns the provided string in UTF7-IMAP encoded format.
     *
     * @return string $str UTF-7 encoded string
     *
     * @psalm-pure
     */
    public function encodeStringToUtf7Imap(string $str): string
    {
        return imap_utf7_encode($str);
    }

    /**
     * Returns the provided string in UTF-8 encoded format.
     *
     * @return string $str UTF-7 encoded string or same as before, when it's no string
     *
     * @psalm-pure
     */
    public function decodeStringFromUtf7ImapToUtf8(string $str): string
    {
        $out = imap_utf7_decode($str);

        if (!\is_string($out)) {
            throw new UnexpectedValueException('mb_convert_encoding($str, \'UTF-8\', \'UTF7-IMAP\') could not convert $str');
        }

        return $out;
    }

    /**
     * Sets the folder of the current mailbox.
     */
    public function setMailboxFolder(): void
    {
        $imapPathParts = \explode('}', $this->imapPath);
        $this->mailboxFolder = (!empty($imapPathParts[1])) ? $imapPathParts[1] : 'INBOX';
    }

    /**
     * Switch mailbox without opening a new connection.
     *
     * @throws Exception
     */
    public function switchMailbox(string $imapPath, bool $absolute = true): void
    {
        if (\strpos($imapPath, '}') > 0) {
            $this->imapPath = $imapPath;
        } else {
            $this->imapPath = $this->getCombinedPath($imapPath, $absolute);
        }

        $this->setMailboxFolder();

        Imap::reopen($this->getImapStream(), $this->imapPath);
    }

    /**
     * Disconnects from IMAP server / mailbox.
     */
    public function disconnect(): void
    {
        if ($this->hasImapStream()) {
            Imap::close($this->getImapStream(false), $this->expungeOnDisconnect ? CL_EXPUNGE : 0);
        }
    }

    /**
     * Sets 'expunge on disconnect' parameter.
     */
    public function setExpungeOnDisconnect(bool $isEnabled): void
    {
        $this->expungeOnDisconnect = $isEnabled;
    }

    /**
     * Get information about the current mailbox.
     *
     * Returns the information in an object with following properties:
     *  Date - current system time formatted according to RFC2822
     *  Driver - protocol used to access this mailbox: POP3, IMAP, NNTP
     *  Mailbox - the mailbox name
     *  Nmsgs - number of mails in the mailbox
     *  Recent - number of recent mails in the mailbox
     *
     * @see imap_check
     */
    public function checkMailbox(): object
    {
        return Imap::check($this->getImapStream());
    }

    /**
     * Creates a new mailbox.
     *
     * @param string $name Name of new mailbox (eg. 'PhpImap')
     *
     * @see imap_createmailbox()
     */
    public function createMailbox(string $name): void
    {
        Imap::createmailbox($this->getImapStream(), $this->getCombinedPath($name));
    }

    /**
     * Deletes a specific mailbox.
     *
     * @param string $name Name of mailbox, which you want to delete (eg. 'PhpImap')
     *
     * @see imap_deletemailbox()
     */
    public function deleteMailbox(string $name, bool $absolute = false): bool
    {
        return Imap::deletemailbox($this->getImapStream(), $this->getCombinedPath($name, $absolute));
    }

    /**
     * Rename an existing mailbox from $oldName to $newName.
     *
     * @param string $oldName Current name of mailbox, which you want to rename (eg. 'PhpImap')
     * @param string $newName New name of mailbox, to which you want to rename it (eg. 'PhpImapTests')
     */
    public function renameMailbox(string $oldName, string $newName): void
    {
        Imap::renamemailbox($this->getImapStream(), $this->getCombinedPath($oldName), $this->getCombinedPath($newName));
    }

    /**
     * Gets status information about the given mailbox.
     *
     * This function returns an object containing status information.
     * The object has the following properties: messages, recent, unseen, uidnext, and uidvalidity.
     */
    public function statusMailbox(): stdClass
    {
        return Imap::status($this->getImapStream(), $this->imapPath, SA_ALL);
    }

    /**
     * Gets listing the folders.
     *
     * This function returns an object containing listing the folders.
     * The object has the following properties: messages, recent, unseen, uidnext, and uidvalidity.
     *
     * @return string[] listing the folders
     *
     * @psalm-return list<string>
     */
    public function getListingFolders(string $pattern = '*'): array
    {
        return Imap::listOfMailboxes($this->getImapStream(), $this->imapPath, $pattern);
    }

    /**
     * This function uses imap_search() to perform a search on the mailbox currently opened in the given IMAP stream.
     * For example, to match all unanswered mails sent by Mom, you'd use: "UNANSWERED FROM mom".
     *
     * @param string $criteria              See http://php.net/imap_search for a complete list of available criteria
     * @param bool   $disableServerEncoding Disables server encoding while searching for mails (can be useful on Exchange servers)
     *
     * @return int[] mailsIds (or empty array)
     *
     * @psalm-return list<int>
     */
    public function searchMailbox(string $criteria = 'ALL', bool $disableServerEncoding = false): array
    {
        if ($disableServerEncoding) {
            /** @psalm-var list<int> */
            return Imap::search($this->getImapStream(), $criteria, $this->imapSearchOption);
        }

        /** @psalm-var list<int> */
        return Imap::search($this->getImapStream(), $criteria, $this->imapSearchOption, $this->getServerEncoding());
    }

    /**
     * Search the mailbox for emails from multiple, specific senders.
     *
     * @see Mailbox::searchMailboxFromWithOrWithoutDisablingServerEncoding()
     *
     * @return int[]
     *
     * @psalm-return list<int>
     */
    public function searchMailboxFrom(string $criteria, string $sender, string ...$senders): array
    {
        return $this->searchMailboxFromWithOrWithoutDisablingServerEncoding($criteria, false, $sender, ...$senders);
    }

    /**
     * Search the mailbox for emails from multiple, specific senders whilst not using server encoding.
     *
     * @see Mailbox::searchMailboxFromWithOrWithoutDisablingServerEncoding()
     *
     * @return int[]
     *
     * @psalm-return list<int>
     */
    public function searchMailboxFromDisableServerEncoding(string $criteria, string $sender, string ...$senders): array
    {
        return $this->searchMailboxFromWithOrWithoutDisablingServerEncoding($criteria, true, $sender, ...$senders);
    }

    /**
     * Search the mailbox using multiple criteria merging the results.
     *
     * @param string $single_criteria
     * @param string ...$criteria
     *
     * @return int[]
     *
     * @psalm-return list<int>
     */
    public function searchMailboxMergeResults($single_criteria, ...$criteria)
    {
        return $this->searchMailboxMergeResultsWithOrWithoutDisablingServerEncoding(false, $single_criteria, ...$criteria);
    }

    /**
     * Search the mailbox using multiple criteria merging the results.
     *
     * @param string $single_criteria
     * @param string ...$criteria
     *
     * @return int[]
     *
     * @psalm-return list<int>
     */
    public function searchMailboxMergeResultsDisableServerEncoding($single_criteria, ...$criteria)
    {
        return $this->searchMailboxMergeResultsWithOrWithoutDisablingServerEncoding(false, $single_criteria, ...$criteria);
    }

    /**
     * Save a specific body section to a file.
     *
     * @param int $mailId message number
     *
     * @see imap_savebody()
     */
    public function saveMail(int $mailId, string $filename = 'email.eml'): void
    {
        Imap::savebody($this->getImapStream(), $filename, $mailId, '', (SE_UID === $this->imapSearchOption) ? FT_UID : 0);
    }

    /**
     * Marks mails listed in mailId for deletion.
     *
     * @param int $mailId message number
     *
     * @see imap_delete()
     */
    public function deleteMail(int $mailId): void
    {
        Imap::delete($this->getImapStream(), $mailId, (SE_UID === $this->imapSearchOption) ? FT_UID : 0);
    }

    /**
     * Moves mails listed in mailId into new mailbox.
     *
     * @param string|int $mailId  a range or message number
     * @param string     $mailBox Mailbox name
     *
     * @see imap_mail_move()
     */
    public function moveMail($mailId, string $mailBox): void
    {
        Imap::mail_move($this->getImapStream(), $mailId, $mailBox, CP_UID);
        $this->expungeDeletedMails();
    }

    /**
     * Copies mails listed in mailId into new mailbox.
     *
     * @param string|int $mailId  a range or message number
     * @param string     $mailBox Mailbox name
     *
     * @see imap_mail_copy()
     */
    public function copyMail($mailId, string $mailBox): void
    {
        Imap::mail_copy($this->getImapStream(), $mailId, $mailBox, CP_UID);
        $this->expungeDeletedMails();
    }

    /**
     * Deletes all the mails marked for deletion by imap_delete(), imap_mail_move(), or imap_setflag_full().
     *
     * @see imap_expunge()
     */
    public function expungeDeletedMails(): void
    {
        Imap::expunge($this->getImapStream());
    }

    /**
     * Add the flag \Seen to a mail.
     */
    public function markMailAsRead(int $mailId): void
    {
        $this->setFlag([$mailId], '\\Seen');
    }

    /**
     * Remove the flag \Seen from a mail.
     */
    public function markMailAsUnread(int $mailId): void
    {
        $this->clearFlag([$mailId], '\\Seen');
    }

    /**
     * Add the flag \Flagged to a mail.
     */
    public function markMailAsImportant(int $mailId): void
    {
        $this->setFlag([$mailId], '\\Flagged');
    }

    /**
     * Add the flag \Seen to a mails.
     *
     * @param int[] $mailId
     *
     * @psalm-param list<int> $mailId
     */
    public function markMailsAsRead(array $mailId): void
    {
        $this->setFlag($mailId, '\\Seen');
    }

    /**
     * Remove the flag \Seen from some mails.
     *
     * @param int[] $mailId
     *
     * @psalm-param list<int> $mailId
     */
    public function markMailsAsUnread(array $mailId): void
    {
        $this->clearFlag($mailId, '\\Seen');
    }

    /**
     * Add the flag \Flagged to some mails.
     *
     * @param int[] $mailId
     *
     * @psalm-param list<int> $mailId
     */
    public function markMailsAsImportant(array $mailId): void
    {
        $this->setFlag($mailId, '\\Flagged');
    }

    /**
     * Check, if the specified flag for the mail is set or not.
     *
     * @param int    $mailId A single mail ID
     * @param string $flag   Which you can get are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060
     *
     * @return bool True, when the flag is set, false when not
     *
     * @psalm-param int $mailId
     */
    public function flagIsSet(int $mailId, string $flag): bool
    {
        $flag = str_replace('\\', '', strtolower($flag));

        $overview = Imap::fetch_overview($this->getImapStream(), $mailId, ST_UID);

        if ($overview[0]->$flag == 1) {
            return true;
        }

        return false;
    }

    /**
     * Causes a store to add the specified flag to the flags set for the mails in the specified sequence.
     *
     * @param array  $mailsIds Array of mail IDs
     * @param string $flag     Which you can set are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060
     *
     * @psalm-param list<int> $mailsIds
     */
    public function setFlag(array $mailsIds, string $flag): void
    {
        Imap::setflag_full($this->getImapStream(), \implode(',', $mailsIds), $flag, ST_UID);
    }

    /**
     * Causes a store to delete the specified flag to the flags set for the mails in the specified sequence.
     *
     * @param array  $mailsIds Array of mail IDs
     * @param string $flag     Which you can delete are \Seen, \Answered, \Flagged, \Deleted, and \Draft as defined by RFC2060
     */
    public function clearFlag(array $mailsIds, string $flag): void
    {
        Imap::clearflag_full($this->getImapStream(), \implode(',', $mailsIds), $flag, ST_UID);
    }

    /**
     * Fetch mail headers for listed mails ids.
     *
     * Returns an array of objects describing one mail header each. The object will only define a property if it exists. The possible properties are:
     *  subject - the mails subject
     *  from - who sent it
     *  sender - who sent it
     *  to - recipient
     *  date - when was it sent
     *  message_id - Mail-ID
     *  references - is a reference to this mail id
     *  in_reply_to - is a reply to this mail id
     *  size - size in bytes
     *  uid - UID the mail has in the mailbox
     *  msgno - mail sequence number in the mailbox
     *  recent - this mail is flagged as recent
     *  flagged - this mail is flagged
     *  answered - this mail is flagged as answered
     *  deleted - this mail is flagged for deletion
     *  seen - this mail is flagged as already read
     *  draft - this mail is flagged as being a draft
     *
     * @return array $mailsIds Array of mail IDs
     *
     * @psalm-return list<object>
     *
     * @todo adjust types & conditionals pending resolution of https://github.com/vimeo/psalm/issues/2619
     */
    public function getMailsInfo(array $mailsIds): array
    {
        $mails = Imap::fetch_overview(
            $this->getImapStream(),
            \implode(',', $mailsIds),
            (SE_UID === $this->imapSearchOption) ? FT_UID : 0
        );
        if (\count($mails)) {
            foreach ($mails as $index => &$mail) {
                if (isset($mail->subject) && !\is_string($mail->subject)) {
                    throw new UnexpectedValueException('subject property at index '.(string) $index.' of argument 1 passed to '.__METHOD__.'() was not a string!');
                }
                if (isset($mail->from) && !\is_string($mail->from)) {
                    throw new UnexpectedValueException('from property at index '.(string) $index.' of argument 1 passed to '.__METHOD__.'() was not a string!');
                }
                if (isset($mail->sender) && !\is_string($mail->sender)) {
                    throw new UnexpectedValueException('sender property at index '.(string) $index.' of argument 1 passed to '.__METHOD__.'() was not a string!');
                }
                if (isset($mail->to) && !\is_string($mail->to)) {
                    throw new UnexpectedValueException('to property at index '.(string) $index.' of argument 1 passed to '.__METHOD__.'() was not a string!');
                }

                if (isset($mail->subject) && !empty(\trim($mail->subject))) {
                    $mail->subject = $this->decodeMimeStr($mail->subject);
                }
                if (isset($mail->from) && !empty(\trim($mail->from))) {
                    $mail->from = $this->decodeMimeStr($mail->from);
                }
                if (isset($mail->sender) && !empty(\trim($mail->sender))) {
                    $mail->sender = $this->decodeMimeStr($mail->sender);
                }
                if (isset($mail->to) && !empty(\trim($mail->to))) {
                    $mail->to = $this->decodeMimeStr($mail->to);
                }
            }
        }

        /** @var list<object> */
        return $mails;
    }

    /**
     * Get headers for all messages in the defined mailbox,
     * returns an array of string formatted with header info,
     * one element per mail message.
     *
     * @see imap_headers()
     */
    public function getMailboxHeaders(): array
    {
        return Imap::headers($this->getImapStream());
    }

    /**
     * Get information about the current mailbox.
     *
     * Returns an object with following properties:
     *  Date - last change (current datetime)
     *  Driver - driver
     *  Mailbox - name of the mailbox
     *  Nmsgs - number of messages
     *  Recent - number of recent messages
     *  Unread - number of unread messages
     *  Deleted - number of deleted messages
     *  Size - mailbox size
     *
     * @return stdClass Object with info
     *
     * @see mailboxmsginfo
     */
    public function getMailboxInfo(): stdClass
    {
        return Imap::mailboxmsginfo($this->getImapStream());
    }

    /**
     * Gets mails ids sorted by some criteria.
     *
     * Criteria can be one (and only one) of the following constants:
     *  SORTDATE - mail Date
     *  SORTARRIVAL - arrival date (default)
     *  SORTFROM - mailbox in first From address
     *  SORTSUBJECT - mail subject
     *  SORTTO - mailbox in first To address
     *  SORTCC - mailbox in first cc address
     *  SORTSIZE - size of mail in octets
     *
     * @param int         $criteria       Sorting criteria (eg. SORTARRIVAL)
     * @param bool        $reverse        Sort reverse or not
     * @param string|null $searchCriteria See http://php.net/imap_search for a complete list of available criteria
     *
     * @psalm-param value-of<Imap::SORT_CRITERIA> $criteria
     *
     * @return int[] Mails ids
     *
     * @psalm-return list<int>
     */
    public function sortMails(
        int $criteria = SORTARRIVAL,
        bool $reverse = true,
        ?string $searchCriteria = 'ALL',
        string $charset = null
    ): array {
        return Imap::sort(
            $this->getImapStream(),
            $criteria,
            $reverse,
            $this->imapSearchOption,
            $searchCriteria,
            $charset
        );
    }

    /**
     * Get mails count in mail box.
     *
     * @see imap_num_msg()
     */
    public function countMails(): int
    {
        return Imap::num_msg($this->getImapStream());
    }

    /**
     * Return quota limit in KB.
     *
     * @param string $quota_root Should normally be in the form of which mailbox (i.e. INBOX)
     */
    public function getQuotaLimit(string $quota_root = 'INBOX'): int
    {
        $quota = $this->getQuota($quota_root);

        /** @var int */
        return $quota['STORAGE']['limit'] ?? 0;
    }

    /**
     * Return quota usage in KB.
     *
     * @param string $quota_root Should normally be in the form of which mailbox (i.e. INBOX)
     *
     * @return int|false FALSE in the case of call failure
     */
    public function getQuotaUsage(string $quota_root = 'INBOX')
    {
        $quota = $this->getQuota($quota_root);

        /** @var int|false */
        return $quota['STORAGE']['usage'] ?? 0;
    }

    /**
     * Get raw mail data.
     *
     * @param int  $msgId      ID of the message
     * @param bool $markAsSeen Mark the email as seen, when set to true
     *
     * @return string Message of the fetched body
     */
    public function getRawMail(int $msgId, bool $markAsSeen = true): string
    {
        $options = (SE_UID == $this->imapSearchOption) ? FT_UID : 0;
        if (!$markAsSeen) {
            $options |= FT_PEEK;
        }

        return Imap::fetchbody($this->getImapStream(), $msgId, '', $options);
    }

    /**
     * Get mail header field value.
     *
     * @param string $headersRaw        RAW headers as single string
     * @param string $header_field_name Name of the required header field
     *
     * @return string Value of the header field
     */
    public function getMailHeaderFieldValue(string $headersRaw, string $header_field_name): string
    {
        $header_field_value = '';

        if (\preg_match("/$header_field_name\:(.*)/i", $headersRaw, $matches)) {
            if (isset($matches[1])) {
                return \trim($matches[1]);
            }
        }

        return $header_field_value;
    }

    /**
     * Get mail header.
     *
     * @param int $mailId ID of the message
     *
     * @throws Exception
     *
     * @todo update type checking pending resolution of https://github.com/vimeo/psalm/issues/2619
     */
    public function getMailHeader(int $mailId): IncomingMailHeader
    {
        $headersRaw = Imap::fetchheader(
            $this->getImapStream(),
            $mailId,
            (SE_UID === $this->imapSearchOption) ? FT_UID : 0
        );

        /** @var object{
         * date?:scalar,
         * Date?:scalar,
         * subject?:scalar,
         * from?:HOSTNAMEANDADDRESS,
         * to?:HOSTNAMEANDADDRESS,
         * cc?:HOSTNAMEANDADDRESS,
         * bcc?:HOSTNAMEANDADDRESS,
         * reply_to?:HOSTNAMEANDADDRESS,
         * sender?:HOSTNAMEANDADDRESS
         * }
         */
        $head = \imap_rfc822_parse_headers($headersRaw);

        if (isset($head->date) && !\is_string($head->date)) {
            throw new UnexpectedValueException('date property of parsed headers corresponding to argument 1 passed to '.__METHOD__.'() was present but not a string!');
        }
        if (isset($head->Date) && !\is_string($head->Date)) {
            throw new UnexpectedValueException('Date property of parsed headers corresponding to argument 1 passed to '.__METHOD__.'() was present but not a string!');
        }
        if (isset($head->subject) && !\is_string($head->subject)) {
            throw new UnexpectedValueException('subject property of parsed headers corresponding to argument 1 passed to '.__METHOD__.'() was present but not a string!');
        }
        if (isset($head->from) && !\is_array($head->from)) {
            throw new UnexpectedValueException('from property of parsed headers corresponding to argument 1 passed to '.__METHOD__.'() was present but not an array!');
        }
        if (isset($head->sender) && !\is_array($head->sender)) {
            throw new UnexpectedValueException('sender property of parsed headers corresponding to argument 1 passed to '.__METHOD__.'() was present but not an array!');
        }
        if (isset($head->to) && !\is_array($head->to)) {
            throw new UnexpectedValueException('to property of parsed headers corresponding to argument 1 passed to '.__METHOD__.'() was present but not an array!');
        }
        if (isset($head->cc) && !\is_array($head->cc)) {
            throw new UnexpectedValueException('cc property of parsed headers corresponding to argument 1 passed to '.__METHOD__.'() was present but not an array!');
        }
        if (isset($head->bcc) && !\is_array($head->bcc)) {
            throw new UnexpectedValueException('bcc property of parsed headers corresponding to argument 1 passed to '.__METHOD__.'() was present but not an array!');
        }
        if (isset($head->reply_to) && !\is_array($head->reply_to)) {
            throw new UnexpectedValueException('reply_to property of parsed headers corresponding to argument 1 passed to '.__METHOD__.'() was present but not an array!');
        }

        $header = new IncomingMailHeader();
        $header->headersRaw = $headersRaw;
        $header->headers = $head;
        $header->id = $mailId;
        $header->imapPath = $this->imapPath;
        $header->mailboxFolder = $this->mailboxFolder;
        $header->isSeen = ($this->flagIsSet($mailId, '\Seen')) ? true : false;
        $header->isAnswered = ($this->flagIsSet($mailId, '\Answered')) ? true : false;
        $header->isRecent = ($this->flagIsSet($mailId, '\Recent')) ? true : false;
        $header->isFlagged = ($this->flagIsSet($mailId, '\Flagged')) ? true : false;
        $header->isDeleted = ($this->flagIsSet($mailId, '\Deleted')) ? true : false;
        $header->isDraft = ($this->flagIsSet($mailId, '\Draft')) ? true : false;
        $header->mimeVersion = $this->getMailHeaderFieldValue($headersRaw, 'MIME-Version');
        $header->xVirusScanned = $this->getMailHeaderFieldValue($headersRaw, 'X-Virus-Scanned');
        $header->organization = $this->getMailHeaderFieldValue($headersRaw, 'Organization');
        $header->contentType = $this->getMailHeaderFieldValue($headersRaw, 'Content-Type');
        $header->xMailer = $this->getMailHeaderFieldValue($headersRaw, 'X-Mailer');
        $header->contentLanguage = $this->getMailHeaderFieldValue($headersRaw, 'Content-Language');
        $header->xSenderIp = $this->getMailHeaderFieldValue($headersRaw, 'X-Sender-IP');
        $header->priority = $this->getMailHeaderFieldValue($headersRaw, 'Priority');
        $header->importance = $this->getMailHeaderFieldValue($headersRaw, 'Importance');
        $header->sensitivity = $this->getMailHeaderFieldValue($headersRaw, 'Sensitivity');
        $header->autoSubmitted = $this->getMailHeaderFieldValue($headersRaw, 'Auto-Submitted');
        $header->precedence = $this->getMailHeaderFieldValue($headersRaw, 'Precedence');
        $header->failedRecipients = $this->getMailHeaderFieldValue($headersRaw, 'Failed-Recipients');
        $header->xOriginalTo = $this->getMailHeaderFieldValue($headersRaw, 'X-Original-To');

        if (isset($head->date) && !empty(\trim($head->date))) {
            $header->date = self::parseDateTime($head->date);
        } elseif (isset($head->Date) && !empty(\trim($head->Date))) {
            $header->date = self::parseDateTime($head->Date);
        } else {
            $now = new DateTime();
            $header->date = self::parseDateTime($now->format('Y-m-d H:i:s'));
        }

        $header->subject = (isset($head->subject) && !empty(\trim($head->subject))) ? $this->decodeMimeStr($head->subject) : null;
        if (isset($head->from) && !empty($head->from)) {
            [$header->fromHost, $header->fromName, $header->fromAddress] = $this->possiblyGetHostNameAndAddress($head->from);
        } elseif (\preg_match('/smtp.mailfrom=[-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,4}/', $headersRaw, $matches)) {
            $header->fromAddress = \substr($matches[0], 14);
        }
        if (isset($head->sender) && !empty($head->sender)) {
            [$header->senderHost, $header->senderName, $header->senderAddress] = $this->possiblyGetHostNameAndAddress($head->sender);
        }
        if (isset($head->to)) {
            $toStrings = [];
            foreach ($head->to as $to) {
                $to_parsed = $this->possiblyGetEmailAndNameFromRecipient($to);
                if ($to_parsed) {
                    [$toEmail, $toName] = $to_parsed;
                    $toStrings[] = $toName ? "$toName <$toEmail>" : $toEmail;
                    $header->to[$toEmail] = $toName;
                }
            }
            $header->toString = \implode(', ', $toStrings);
        }

        if (isset($head->cc)) {
            $ccStrings = [];
            foreach ($head->cc as $cc) {
                $cc_parsed = $this->possiblyGetEmailAndNameFromRecipient($cc);
                if ($cc_parsed) {
                    [$ccEmail, $ccName] = $cc_parsed;
                    $ccStrings[] = $ccName ? "$ccName <$ccEmail>" : $ccEmail;
                    $header->cc[$ccEmail] = $ccName;
                }
            }
            $header->ccString = \implode(', ', $ccStrings);
        }

        if (isset($head->bcc)) {
            foreach ($head->bcc as $bcc) {
                $bcc_parsed = $this->possiblyGetEmailAndNameFromRecipient($bcc);
                if ($bcc_parsed) {
                    $header->bcc[$bcc_parsed[0]] = $bcc_parsed[1];
                }
            }
        }

        if (isset($head->reply_to)) {
            foreach ($head->reply_to as $replyTo) {
                $replyTo_parsed = $this->possiblyGetEmailAndNameFromRecipient($replyTo);
                if ($replyTo_parsed) {
                    $header->replyTo[$replyTo_parsed[0]] = $replyTo_parsed[1];
                }
            }
        }

        if (isset($head->message_id)) {
            if (!\is_string($head->message_id)) {
                throw new UnexpectedValueException('Message ID was expected to be a string, '.\gettype($head->message_id).' found!');
            }
            $header->messageId = $head->message_id;
        }

        return $header;
    }

    /**
     * taken from https://www.electrictoolbox.com/php-imap-message-parts/.
     *
     * @param stdClass[] $messageParts
     * @param stdClass[] $flattenedParts
     *
     * @psalm-param array<string, PARTSTRUCTURE> $flattenedParts
     *
     * @return stdClass[]
     *
     * @psalm-return array<string, stdClass>
     */
    public function flattenParts(array $messageParts, array $flattenedParts = [], string $prefix = '', int $index = 1, bool $fullPrefix = true): array
    {
        foreach ($messageParts as $part) {
            $flattenedParts[$prefix.$index] = $part;
            if (isset($part->parts)) {
                /** @var stdClass[] */
                $part_parts = $part->parts;

                if (self::PART_TYPE_TWO == $part->type) {
                    $flattenedParts = $this->flattenParts($part_parts, $flattenedParts, $prefix.$index.'.', 0, false);
                } elseif ($fullPrefix) {
                    $flattenedParts = $this->flattenParts($part_parts, $flattenedParts, $prefix.$index.'.');
                } else {
                    $flattenedParts = $this->flattenParts($part_parts, $flattenedParts, $prefix);
                }
                unset($flattenedParts[$prefix.$index]->parts);
            }
            ++$index;
        }

        /** @var array<string, stdClass> */
        return $flattenedParts;
    }

    /**
     * Get mail data.
     *
     * @param int  $mailId     ID of the mail
     * @param bool $markAsSeen Mark the email as seen, when set to true
     */
    public function getMail(int $mailId, bool $markAsSeen = true): IncomingMail
    {
        $mail = new IncomingMail();
        $mail->setHeader($this->getMailHeader($mailId));

        $mailStructure = Imap::fetchstructure(
            $this->getImapStream(),
            $mailId,
            (SE_UID === $this->imapSearchOption) ? FT_UID : 0
        );

        if (empty($mailStructure->parts)) {
            $this->initMailPart($mail, $mailStructure, 0, $markAsSeen);
        } else {
            /** @var array<string, stdClass> */
            $parts = $mailStructure->parts;
            foreach ($this->flattenParts($parts) as $partNum => $partStructure) {
                $this->initMailPart($mail, $partStructure, $partNum, $markAsSeen);
            }
        }

        return $mail;
    }

    /**
     * Download attachment.
     *
     * @param array  $params        Array of params of mail
     * @param object $partStructure Part of mail
     * @param bool   $emlOrigin     True, if it indicates, that the attachment comes from an EML (mail) file
     *
     * @psalm-param array<string, string> $params
     * @psalm-param PARTSTRUCTURE $partStructure
     *
     * @return IncomingMailAttachment $attachment
     */
    public function downloadAttachment(DataPartInfo $dataInfo, array $params, object $partStructure, bool $emlOrigin = false): IncomingMailAttachment
    {
        if ('RFC822' == $partStructure->subtype && isset($partStructure->disposition) && 'attachment' == $partStructure->disposition) {
            $fileName = \strtolower($partStructure->subtype).'.eml';
        } elseif ('ALTERNATIVE' == $partStructure->subtype) {
            $fileName = \strtolower($partStructure->subtype).'.eml';
        } elseif ((!isset($params['filename']) || empty(\trim($params['filename']))) && (!isset($params['name']) || empty(\trim($params['name'])))) {
            $fileName = \strtolower($partStructure->subtype);
        } else {
            $fileName = (isset($params['filename']) && !empty(\trim($params['filename']))) ? $params['filename'] : $params['name'];
            $fileName = $this->decodeMimeStr($fileName);
            $fileName = $this->decodeRFC2231($fileName);
        }

        /** @var scalar|array|object|null */
        $sizeInBytes = $partStructure->bytes ?? null;

        /** @var scalar|array|object|null */
        $encoding = $partStructure->encoding ?? null;

        if (null !== $sizeInBytes && !\is_int($sizeInBytes)) {
            throw new UnexpectedValueException('Supplied part structure specifies a non-integer, non-null bytes header!');
        }
        if (null !== $encoding && !\is_int($encoding)) {
            throw new UnexpectedValueException('Supplied part structure specifies a non-integer, non-null encoding header!');
        }
        if (isset($partStructure->type) && !\is_int($partStructure->type)) {
            throw new UnexpectedValueException('Supplied part structure specifies a non-integer, non-null type header!');
        }

        $partStructure_id = ($partStructure->ifid && isset($partStructure->id)) ? \trim($partStructure->id) : null;

        $attachment = new IncomingMailAttachment();
        $attachment->id = \bin2hex(\random_bytes(20));
        $attachment->contentId = isset($partStructure_id) ? \trim($partStructure_id, ' <>') : null;
        if (isset($partStructure->type)) {
            $attachment->type = $partStructure->type;
        }
        $attachment->encoding = $encoding;
        $attachment->subtype = ($partStructure->ifsubtype && isset($partStructure->subtype)) ? \trim($partStructure->subtype) : null;
        $attachment->description = ($partStructure->ifdescription && isset($partStructure->description)) ? \trim((string) $partStructure->description) : null;
        $attachment->name = $fileName;
        $attachment->sizeInBytes = $sizeInBytes;
        $attachment->disposition = (isset($partStructure->disposition) && \is_string($partStructure->disposition)) ? $partStructure->disposition : null;

        /** @var scalar|array|object|resource|null */
        $charset = $params['charset'] ?? null;

        if (isset($charset) && !\is_string($charset)) {
            throw new InvalidArgumentException('Argument 2 passed to '.__METHOD__.'() must specify charset as a string when specified!');
        }
        $attachment->charset = (isset($charset) && !empty(\trim($charset))) ? $charset : null;
        $attachment->emlOrigin = $emlOrigin;

        $attachment->addDataPartInfo($dataInfo);

        $attachment->fileInfoRaw = $attachment->getFileInfo(FILEINFO_RAW);
        $attachment->fileInfo = $attachment->getFileInfo(FILEINFO_NONE);
        $attachment->mime = $attachment->getFileInfo(FILEINFO_MIME);
        $attachment->mimeType = $attachment->getFileInfo(FILEINFO_MIME_TYPE);
        $attachment->mimeEncoding = $attachment->getFileInfo(FILEINFO_MIME_ENCODING);
        $attachment->fileExtension = $attachment->getFileInfo(FILEINFO_EXTENSION);

        $attachmentsDir = $this->getAttachmentsDir();

        if (null != $attachmentsDir) {
            if (true == $this->getAttachmentFilenameMode()) {
                $fileSysName = $attachment->name;
            } else {
                $fileSysName = \bin2hex(\random_bytes(16)).'.bin';
            }

            $filePath = $attachmentsDir.DIRECTORY_SEPARATOR.$fileSysName;

            if (\strlen($filePath) > self::MAX_LENGTH_FILEPATH) {
                $ext = \pathinfo($filePath, PATHINFO_EXTENSION);
                $filePath = \substr($filePath, 0, self::MAX_LENGTH_FILEPATH - 1 - \strlen($ext)).'.'.$ext;
            }

            $attachment->setFilePath($filePath);
            $attachment->saveToDisk();
        }

        return $attachment;
    }

    /**
     * Converts a string to UTF-8.
     *
     * @param string $string      MIME string to decode
     * @param string $fromCharset Charset to convert from
     *
     * @return string Converted string if conversion was successful, or the original string if not
     */
    public function convertToUtf8(string $string, string $fromCharset): string
    {
        $fromCharset = mb_strtolower($fromCharset);
        $newString = '';

        if ('default' === $fromCharset) {
            $fromCharset = $this->decodeMimeStrDefaultCharset;
        }

        switch ($fromCharset) {
            case 'default': // Charset default is already ASCII (not encoded)
            case 'utf-8': // Charset UTF-8 is OK
                $newString .= $string;
                break;
            default:
                // If charset exists in mb_list_encodings(), convert using mb_convert function
                if (\in_array($fromCharset, $this->lowercase_mb_list_encodings(), true)) {
                    $newString .= \mb_convert_encoding($string, 'UTF-8', $fromCharset);
                } else {
                    // Fallback: Try to convert with iconv()
                    $iconv_converted_string = @\iconv($fromCharset, 'UTF-8', $string);
                    if (!$iconv_converted_string) {
                        // If iconv() could also not convert, return string as it is
                        // (unknown charset)
                        $newString .= $string;
                    } else {
                        $newString .= $iconv_converted_string;
                    }
                }
                break;
        }

        return $newString;
    }

    /**
     * Decodes a mime string.
     *
     * @param string $string MIME string to decode
     *
     * @return string Converted string if conversion was successful, or the original string if not
     *
     * @throws Exception
     *
     * @todo update implementation pending resolution of https://github.com/vimeo/psalm/issues/2619 & https://github.com/vimeo/psalm/issues/2620
     */
    public function decodeMimeStr(string $string): string
    {
        $newString = '';
        /** @var list<object{charset?:string, text?:string}>|false */
        $elements = \imap_mime_header_decode($string);

        if (false === $elements) {
            return $string;
        }

        foreach ($elements as $element) {
            $newString .= $this->convertToUtf8($element->text, $element->charset);
        }

        return $newString;
    }

    /**
     * @psalm-pure
     */
    public function isUrlEncoded(string $string): bool
    {
        $hasInvalidChars = \preg_match('#[^%a-zA-Z0-9\-_\.\+]#', $string);
        $hasEscapedChars = \preg_match('#%[a-zA-Z0-9]{2}#', $string);

        return !$hasInvalidChars && $hasEscapedChars;
    }

    /**
     * Converts the datetime to a RFC 3339 compliant format.
     *
     * @param string $dateHeader Header datetime
     *
     * @return string RFC 3339 compliant format or original (unchanged) format,
     *                if conversation is not possible
     *
     * @psalm-pure
     */
    public function parseDateTime(string $dateHeader): string
    {
        if (empty(\trim($dateHeader))) {
            throw new InvalidParameterException('parseDateTime() expects parameter 1 to be a parsable string datetime');
        }

        $dateHeaderUnixtimestamp = \strtotime($dateHeader);

        if (!$dateHeaderUnixtimestamp) {
            return $dateHeader;
        }

        $dateHeaderRfc3339 = \date(DATE_RFC3339, $dateHeaderUnixtimestamp);

        if (!$dateHeaderRfc3339) {
            return $dateHeader;
        }

        return $dateHeaderRfc3339;
    }

    /**
     * Gets IMAP path.
     */
    public function getImapPath(): string
    {
        return $this->imapPath;
    }

    /**
     * Get message in MBOX format.
     *
     * @param int $mailId message number
     */
    public function getMailMboxFormat(int $mailId): string
    {
        $option = (SE_UID == $this->imapSearchOption) ? FT_UID : 0;

        return Imap::fetchheader($this->getImapStream(), $mailId, $option | FT_PREFETCHTEXT).Imap::body($this->getImapStream(), $mailId, $option);
    }

    /**
     * Get folders list.
     *
     * @return (false|mixed|string)[][]
     *
     * @psalm-return list<array{fullpath: string, attributes: mixed, delimiter: mixed, shortpath: false|string}>
     */
    public function getMailboxes(string $search = '*'): array
    {
        /** @psalm-var array<int, scalar|array|object{name?:string}|resource|null> */
        $mailboxes = Imap::getmailboxes($this->getImapStream(), $this->imapPath, $search);

        return $this->possiblyGetMailboxes($mailboxes);
    }

    /**
     * Get folders list.
     *
     * @return (false|mixed|string)[][]
     *
     * @psalm-return list<array{fullpath: string, attributes: mixed, delimiter: mixed, shortpath: false|string}>
     */
    public function getSubscribedMailboxes(string $search = '*'): array
    {
        /** @psalm-var array<int, scalar|array|object{name?:string}|resource|null> */
        $mailboxes = Imap::getsubscribed($this->getImapStream(), $this->imapPath, $search);

        return $this->possiblyGetMailboxes($mailboxes);
    }

    /**
     * Subscribe to a mailbox.
     *
     * @throws Exception
     */
    public function subscribeMailbox(string $mailbox): void
    {
        Imap::subscribe(
            $this->getImapStream(),
            $this->getCombinedPath($mailbox)
        );
    }

    /**
     * Unsubscribe from a mailbox.
     *
     * @throws Exception
     */
    public function unsubscribeMailbox(string $mailbox): void
    {
        Imap::unsubscribe(
            $this->getImapStream(),
            $this->getCombinedPath($mailbox)
        );
    }

    /**
     * Appends $message to $mailbox.
     *
     * @param string|array $message
     *
     * @psalm-param string|array{0:COMPOSE_ENVELOPE, 1:COMPOSE_BODY} $message
     *
     * @return true
     *
     * @see Imap::append()
     */
    public function appendMessageToMailbox(
        $message,
        string $mailbox = '',
        string $options = null,
        string $internal_date = null
    ): bool {
        if (
            \is_array($message) &&
            self::EXPECTED_SIZE_OF_MESSAGE_AS_ARRAY === \count($message) &&
            isset($message[0], $message[1])
        ) {
            $message = Imap::mail_compose($message[0], $message[1]);
        }

        if (!\is_string($message)) {
            throw new InvalidArgumentException('Argument 1 passed to '.__METHOD__.' must be a string or envelope/body pair.');
        }

        return Imap::append(
            $this->getImapStream(),
            $this->getCombinedPath($mailbox),
            $message,
            $options,
            $internal_date
        );
    }

    /**
     * Returns the list of available encodings in lower case.
     *
     * @return string[]
     *
     * @psalm-return list<string>
     */
    protected function lowercase_mb_list_encodings(): array
    {
        $lowercase_encodings = [];
        $encodings = \mb_list_encodings();
        foreach ($encodings as $encoding) {
            $lowercase_encodings[] = \strtolower($encoding);
        }

        return $lowercase_encodings;
    }

    /** @return resource */
    protected function initImapStreamWithRetry()
    {
        $retry = $this->connectionRetry;

        do {
            try {
                return $this->initImapStream();
            } catch (ConnectionException $exception) {
            }
        } while (--$retry > 0 && (!$this->connectionRetryDelay || !\usleep((int) $this->connectionRetryDelay * 1000)));

        throw $exception;
    }

    /**
     * Retrieve the quota settings per user.
     *
     * @param string $quota_root Should normally be in the form of which mailbox (i.e. INBOX)
     *
     * @see imap_get_quotaroot()
     */
    protected function getQuota(string $quota_root = 'INBOX'): array
    {
        return Imap::get_quotaroot($this->getImapStream(), $quota_root);
    }

    /**
     * Open an IMAP stream to a mailbox.
     *
     * @throws Exception if an error occured
     *
     * @return resource IMAP stream on success
     */
    protected function initImapStream()
    {
        foreach ($this->timeouts as $type => $timeout) {
            Imap::timeout($type, $timeout);
        }

        $imapStream = Imap::open(
            $this->imapPath,
            $this->imapLogin,
            $this->imapPassword,
            $this->imapOptions,
            $this->imapRetriesNum,
            $this->imapParams
        );

        return $imapStream;
    }

    /**
     * @param string|0 $partNum
     *
     * @psalm-param PARTSTRUCTURE $partStructure
     * @psalm-suppress InvalidArgument
     *
     * @todo refactor type checking pending resolution of https://github.com/vimeo/psalm/issues/2619
     */
    protected function initMailPart(IncomingMail $mail, object $partStructure, $partNum, bool $markAsSeen = true, bool $emlParse = false): void
    {
        if (!isset($mail->id)) {
            throw new InvalidArgumentException('Argument 1 passeed to '.__METHOD__.'() did not have the id property set!');
        }

        $options = (SE_UID === $this->imapSearchOption) ? FT_UID : 0;

        if (!$markAsSeen) {
            $options |= FT_PEEK;
        }
        $dataInfo = new DataPartInfo($this, $mail->id, $partNum, $partStructure->encoding, $options);

        /** @var array<string, string> */
        $params = [];
        if (!empty($partStructure->parameters)) {
            foreach ($partStructure->parameters as $param) {
                $params[\strtolower($param->attribute)] = '';
                $value = $param->value ?? null;
                if (isset($value) && '' !== \trim($value)) {
                    $params[\strtolower($param->attribute)] = $this->decodeMimeStr($value);
                }
            }
        }
        if (!empty($partStructure->dparameters)) {
            foreach ($partStructure->dparameters as $param) {
                $paramName = \strtolower(\preg_match('~^(.*?)\*~', $param->attribute, $matches) ? (!isset($matches[1]) ?: $matches[1]) : $param->attribute);
                if (isset($params[$paramName])) {
                    $params[$paramName] .= $param->value;
                } else {
                    $params[$paramName] = $param->value;
                }
            }
        }

        $isAttachment = isset($params['filename']) || isset($params['name']) || isset($partStructure->id);

        $dispositionAttachment = (isset($partStructure->disposition) &&
            \is_string($partStructure->disposition) &&
            'attachment' === \mb_strtolower($partStructure->disposition));

        // ignore contentId on body when mail isn't multipart (https://github.com/barbushin/php-imap/issues/71)
        if (
            !$partNum &&
            TYPETEXT === $partStructure->type &&
            !$dispositionAttachment
        ) {
            $isAttachment = false;
        }

        if ($isAttachment) {
            $mail->setHasAttachments(true);
        }

        // check if the part is a subpart of another attachment part (RFC822)
        if ('RFC822' === $partStructure->subtype && isset($partStructure->disposition) && 'attachment' === $partStructure->disposition) {
            // Although we are downloading each part separately, we are going to download the EML to a single file
            //incase someone wants to process or parse in another process
            $attachment = self::downloadAttachment($dataInfo, $params, $partStructure, false);
            $mail->addAttachment($attachment);
        }

        // If it comes from an EML file it is an attachment
        if ($emlParse) {
            $isAttachment = true;
        }

        // Do NOT parse attachments, when getAttachmentsIgnore() is true
        if (
            $this->getAttachmentsIgnore()
            && (TYPEMULTIPART !== $partStructure->type
                && (TYPETEXT !== $partStructure->type || !\in_array(\mb_strtolower($partStructure->subtype), ['plain', 'html'], true)))
        ) {
            return;
        }

        if ($isAttachment) {
            $attachment = self::downloadAttachment($dataInfo, $params, $partStructure, $emlParse);
            $mail->addAttachment($attachment);
        } else {
            if (isset($params['charset']) && !empty(\trim($params['charset']))) {
                $dataInfo->charset = $params['charset'];
            }
        }

        if (!empty($partStructure->parts)) {
            foreach ($partStructure->parts as $subPartNum => $subPartStructure) {
                $not_attachment = (!isset($partStructure->disposition) || 'attachment' !== $partStructure->disposition);

                if (TYPEMESSAGE === $partStructure->type && 'RFC822' === $partStructure->subtype && $not_attachment) {
                    $this->initMailPart($mail, $subPartStructure, $partNum, $markAsSeen);
                } elseif (TYPEMULTIPART === $partStructure->type && 'ALTERNATIVE' === $partStructure->subtype && $not_attachment) {
                    // https://github.com/barbushin/php-imap/issues/198
                    $this->initMailPart($mail, $subPartStructure, $partNum, $markAsSeen);
                } elseif ('RFC822' === $partStructure->subtype && isset($partStructure->disposition) && 'attachment' === $partStructure->disposition) {
                    //If it comes from am EML attachment, download each part separately as a file
                    $this->initMailPart($mail, $subPartStructure, $partNum.'.'.($subPartNum + 1), $markAsSeen, true);
                } else {
                    $this->initMailPart($mail, $subPartStructure, $partNum.'.'.($subPartNum + 1), $markAsSeen);
                }
            }
        } else {
            if (TYPETEXT === $partStructure->type) {
                if ('plain' === \mb_strtolower($partStructure->subtype)) {
                    if ($dispositionAttachment) {
                        return;
                    }

                    $mail->addDataPartInfo($dataInfo, DataPartInfo::TEXT_PLAIN);
                } elseif (!$partStructure->ifdisposition) {
                    $mail->addDataPartInfo($dataInfo, DataPartInfo::TEXT_HTML);
                } elseif (!\is_string($partStructure->disposition)) {
                    throw new InvalidArgumentException('disposition property of object passed as argument 2 to '.__METHOD__.'() was present but not a string!');
                } elseif (!$dispositionAttachment) {
                    $mail->addDataPartInfo($dataInfo, DataPartInfo::TEXT_HTML);
                }
            } elseif (TYPEMESSAGE === $partStructure->type) {
                $mail->addDataPartInfo($dataInfo, DataPartInfo::TEXT_PLAIN);
            }
        }
    }

    protected function decodeRFC2231(string $string): string
    {
        if (\preg_match("/^(.*?)'.*?'(.*?)$/", $string, $matches)) {
            $data = $matches[2] ?? '';
            if ($this->isUrlEncoded($data)) {
                $string = $this->decodeMimeStr(\urldecode($data));
            }
        }

        return $string;
    }

    /**
     * Combine Subfolder or Folder to the connection.
     * Have the imapPath a folder added to the connection info, then will the $folder added as subfolder.
     * If the parameter $absolute TRUE, then will the connection new builded only with this folder as root element.
     *
     * @param string $folder   Folder, the will added to the path
     * @param bool   $absolute Add folder as root element to the connection and remove all other from this
     *
     * @return string Return the new path
     */
    protected function getCombinedPath(string $folder, bool $absolute = false): string
    {
        if (empty(\trim($folder))) {
            return $this->imapPath;
        } elseif ('}' === \substr($this->imapPath, -1)) {
            return $this->imapPath.$folder;
        } elseif (true === $absolute) {
            $folder = ('/' === $folder) ? '' : $folder;
            $posConnectionDefinitionEnd = \strpos($this->imapPath, '}');

            if (false === $posConnectionDefinitionEnd) {
                throw new UnexpectedValueException('"}" was not present in IMAP path!');
            }

            return \substr($this->imapPath, 0, $posConnectionDefinitionEnd + 1).$folder;
        }

        return $this->imapPath.$this->getPathDelimiter().$folder;
    }

    /**
     * @psalm-return array{0: string, 1: null|string}|null
     *
     * @return (null|string)[]|null
     */
    protected function possiblyGetEmailAndNameFromRecipient(object $recipient): ?array
    {
        if (isset($recipient->mailbox, $recipient->host)) {
            /** @var string */
            $recipientMailbox = $recipient->mailbox;
            /** @var string */
            $recipientHost = $recipient->host;
            /** @var string|null */
            $recipientPersonal = $recipient->personal ?? null;

            if (!\is_string($recipientMailbox)) {
                throw new UnexpectedValueException('mailbox was present on argument 1 passed to '.__METHOD__.'() but was not a string!');
            } elseif (!\is_string($recipientHost)) {
                throw new UnexpectedValueException('host was present on argument 1 passed to '.__METHOD__.'() but was not a string!');
            } elseif (null !== $recipientPersonal && !\is_string($recipientPersonal)) {
                throw new UnexpectedValueException('personal was present on argument 1 passed to '.__METHOD__.'() but was not a string!');
            }

            if ('' !== \trim($recipientMailbox) && '' !== \trim($recipientHost)) {
                $recipientEmail = \strtolower($recipientMailbox.'@'.$recipientHost);
                $recipientName = (\is_string($recipientPersonal) && '' !== \trim($recipientPersonal)) ? $this->decodeMimeStr($recipientPersonal) : null;

                return [
                    $recipientEmail,
                    $recipientName,
                ];
            }
        }

        return null;
    }

    /**
     * @psalm-param array<int, scalar|array|object{name?:string}|resource|null> $t
     *
     * @todo revisit implementation pending resolution of https://github.com/vimeo/psalm/issues/2619
     *
     * @return (false|mixed|string)[][]
     *
     * @psalm-return list<array{fullpath: string, attributes: mixed, delimiter: mixed, shortpath: false|string}>
     */
    protected function possiblyGetMailboxes(array $t): array
    {
        $arr = [];
        if ($t) {
            foreach ($t as $index => $item) {
                if (!\is_object($item)) {
                    throw new UnexpectedValueException('Index '.(string) $index.' of argument 1 passed to '.__METHOD__.'() corresponds to a non-object value, '.\gettype($item).' given!');
                }
                /** @var scalar|array|object|resource|null */
                $item_name = $item->name ?? null;

                if (!isset($item->name, $item->attributes, $item->delimiter)) {
                    throw new UnexpectedValueException('The object at index '.(string) $index.' of argument 1 passed to '.__METHOD__.'() was missing one or more of the required properties "name", "attributes", "delimiter"!');
                } elseif (!\is_string($item_name)) {
                    throw new UnexpectedValueException('The object at index '.(string) $index.' of argument 1 passed to '.__METHOD__.'() has a non-string value for the name property!');
                }

                // https://github.com/barbushin/php-imap/issues/339
                $name = $this->decodeStringFromUtf7ImapToUtf8($item_name);
                $name_pos = \strpos($name, '}');
                if (false === $name_pos) {
                    throw new UnexpectedValueException('Expected token "}" not found in subscription name!');
                }
                $arr[] = [
                    'fullpath' => $name,
                    'attributes' => $item->attributes,
                    'delimiter' => $item->delimiter,
                    'shortpath' => \substr($name, $name_pos + 1),
                ];
            }
        }

        return $arr;
    }

    /**
     * @psalm-param HOSTNAMEANDADDRESS $t
     *
     * @psalm-return array{0:string|null, 1:string|null, 2:string}
     */
    protected function possiblyGetHostNameAndAddress(array $t): array
    {
        $out = [
            $t[0]->host ?? (isset($t[1], $t[1]->host) ? $t[1]->host : null),
            1 => null,
        ];
        foreach ([0, 1] as $index) {
            $maybe = isset($t[$index], $t[$index]->personal) ? $t[$index]->personal : null;
            if (\is_string($maybe) && '' !== \trim($maybe)) {
                $out[1] = $this->decodeMimeStr($maybe);

                break;
            }
        }

        /** @var string */
        $out[] = \strtolower($t[0]->mailbox.'@'.(string) $out[0]);

        /** @var array{0:string|null, 1:string|null, 2:string} */
        return $out;
    }

    /**
     * @todo revisit redundant condition issues pending fix of https://github.com/vimeo/psalm/issues/2626
     */
    protected function pingOrDisconnect(): void
    {
        if ($this->imapStream && !Imap::ping($this->imapStream)) {
            $this->disconnect();
            $this->imapStream = null;
        }
    }

    /**
     * Search the mailbox for emails from multiple, specific senders.
     *
     * This function wraps Mailbox::searchMailbox() to overcome a shortcoming in ext-imap
     *
     * @return int[]
     *
     * @psalm-return list<int>
     */
    protected function searchMailboxFromWithOrWithoutDisablingServerEncoding(string $criteria, bool $disableServerEncoding, string $sender, string ...$senders): array
    {
        \array_unshift($senders, $sender);

        $senders = \array_values(\array_unique(\array_map(
            /**
             * @param string $sender
             *
             * @return string
             */
            static function ($sender) use ($criteria): string {
                return $criteria.' FROM '.\mb_strtolower($sender);
            },
            $senders
        )));

        return $this->searchMailboxMergeResultsWithOrWithoutDisablingServerEncoding(
            $disableServerEncoding,
            ...$senders
        );
    }

    /**
     * Search the mailbox using different criteria, then merge the results.
     *
     * @param bool   $disableServerEncoding
     * @param string $single_criteria
     * @param string ...$criteria
     *
     * @return int[]
     *
     * @psalm-return list<int>
     */
    protected function searchMailboxMergeResultsWithOrWithoutDisablingServerEncoding($disableServerEncoding, $single_criteria, ...$criteria)
    {
        \array_unshift($criteria, $single_criteria);

        $criteria = \array_values(\array_unique($criteria));

        $out = [];

        foreach ($criteria as $criterion) {
            $out = \array_merge($out, $this->searchMailbox($criterion, $disableServerEncoding));
        }

        /** @psalm-var list<int> */
        return \array_values(\array_unique($out, SORT_NUMERIC));
    }
}
