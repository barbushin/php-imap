<?php

/**
 * Example: Get and parse all unseen emails with saving their attachments one by one.
 * 
 * @author Sebastian Krätzig <info@ts3-tools.info>
 */

    require_once __DIR__ . '/../vendor/autoload.php';
    use PhpImap\Mailbox;
    use PhpImap\Exceptions\ConnectionException;

    $mailbox = new Mailbox(
        '{imap.gmail.com:993/imap/ssl}INBOX', // IMAP server and mailbox folder
        'some@gmail.com', // Username for the before configured mailbox
        '*********' // Password for the before configured username
    );

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
    
        if (! empty($email->getAttachments())) {
            echo count($email->getAttachments()) . " attachements\n";
        }

        // Save attachments one by one
        if (! $mbox_connection->getAttachmentsIgnore()) {
            $attachments = $email_content->getAttachments();

            foreach ($attachments as $attachment) {
                echo "--> Saving " . $attachment->name . "...";

                // Set individually filePath for each single attachment
                // In this case, every file will get the current Unix timestamp
                $attachment->setFilePath(__DIR__.'/files/'.time());

                if ($attachment->saveToDisk()) {
                    echo "OK, saved!\n";
                } else {
                    echo "ERROR, could not save!\n";
                }
            }
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