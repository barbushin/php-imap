<?php

/**
 * Example: Get and parse all emails without saving their attachments.
 * 
 * @author Sebastian Krätzig <info@ts3-tools.info>
 */

    require_once __DIR__ . '/../vendor/autoload.php';
    use PhpImap\Mailbox;
    use PhpImap\Exceptions\ConnectionException;

    $mailbox = new Mailbox(
        '{imap.gmail.com:993/imap/ssl}INBOX', // IMAP server and mailbox folder
        'some@gmail.com', // Username for the before configured mailbox
        '*********', // Password for the before configured username
        null, // Directory, where attachments will be saved (optional)
        'US-ASCII' // Server encoding (optional)    
    );

    // OR
    $mailbox = new Mailbox(
        '{imap.gmail.com:993/imap/ssl}INBOX', // IMAP server and mailbox folder
        'some@gmail.com', // Username for the before configured mailbox
        '*********' // Password for the before configured username
    );

    // If you haven't defined the server encoding (charset) in 'new Mailbox()', you can change it any time
    $mailbox->setServerEncoding('US-ASCII');

    try {
        $mail_ids = $mailbox->searchMailbox('UNSEEN');
    } catch(ConnectionException $ex) {
        die("IMAP connection failed: " . $ex->getMessage());
    } catch (Exception $ex) {
        die("An error occured: " . $ex->getMessage());
    }

    foreach ($mail_ids as $mail_id) {
        echo "+------ P A R S I N G ------+\n";

        $email = $mailbox->getMail(
            $mail_id, // ID of the email, you want to get
            false // Do NOT mark emails as seen (optional)
        );

        echo "from-name: " . (isset($email->fromName)) ? $email->fromName : $email->fromAddress . "\n";
        echo "from-email: " . $email->fromAddress . "\n";
        echo "to: " . $email->to . "\n";
        echo "subject: " . $email->subject . "\n";
        echo "message_id: " . $email->messageId . "\n";
    
        echo "mail has attachments? ";
        if ($email->hasAttachments()) {
            echo "Yes\n";
        } else {
            echo "No\n";
        }
    
        if (!empty($email->getAttachments())) {
            echo count($email->getAttachments()) . " attachements\n";
        }
        if ($email->textHtml) {
            echo "Message HTML:\n" . $email->textHtml;
        } else {
            echo "Message Plain:\n" . $email->textPlain;
        }
    
        if (!empty($email->autoSubmitted)) {
            // Mark email as "read" / "seen"
            $mailbox->markMailAsRead($mail_id);
                    echo "+------ IGNORING: Auto-Reply ------+\n";
        }
    
        if (!empty($email_content->precedence)) {
            // Mark email as "read" / "seen"
            $mailbox->markMailAsRead($mail_id);
            echo "+------ IGNORING: Non-Delivery Report/Receipt ------+\n";
        }    
    }

    $mailbox->disconnect();