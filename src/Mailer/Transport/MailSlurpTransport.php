<?php
namespace CakeMailSlurp\Mailer\Transport;

use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Email;
use Exception;
use GuzzleHttp\Client;
use MailSlurp\Apis\AttachmentControllerApi;
use MailSlurp\Apis\InboxControllerApi;
use MailSlurp\Configuration;
use MailSlurp\Models\SendEmailOptions;
use MailSlurp\Models\UploadAttachmentOptions;

/**
 * Send Mail Using MailSlurp Service
 */
class MailSlurpTransport extends AbstractTransport
{

    /**
     * Default config for this class
     *
     * @var array
     */
    protected $_defaultConfig = [
        'inboxId' => null,
        'apiKey' => null,
        'email' => null,
    ];

    /**
     * $mailSlurpConfig
     *
     * @var object
     */
    protected $mailSlurpConfig = null;

    /**
     * Constructor
     *
     * @param array $config Configuration options.
     */
    public function __construct($config = [])
    {
        if (!$config['inboxId']) {
            throw new Exception('Missing MailSlurp Inbox Information');
        }
        if (!$config['apiKey']) {
            throw new Exception('Missing MailSlurp Api Key');
        }
        if (!$config['email']) {
            throw new Exception('Missing MailSlurp Email Address');
        }

        $this->setConfig($config);

        $this->mailSlurpConfig = Configuration::getDefaultConfiguration()->setApiKey('x-api-key', $config['apiKey']);
    }

    /**
     * uploadAttachment method
     *
     * @param string $filename The Filename
     * @param array $attachmentInfo The Attachment Info
     * @return bool|string Attachment ID
     */
    private function uploadAttachment($filename, $attachmentInfo)
    {
        $data = null;
        if (!empty($attachmentInfo['data'])) {
            $data = $attachmentInfo['data'];
        }
        if (!empty($attachmentInfo['file'])) {
            $data = file_get_contents($attachmentInfo['file']);
        }

        if (!empty($data)) {
            $base64Contents = base64_encode($data);
            $contentType = empty($info['mimetype']) ? 'application/octet-stream' : $info['mimetype'];

            $uploadAttachmentOptions = new UploadAttachmentOptions();
            $uploadAttachmentOptions->setFilename($filename);
            $uploadAttachmentOptions->setContentType($contentType);
            $uploadAttachmentOptions->setBase64Contents($base64Contents);

            $attachmentController = new AttachmentControllerApi(null, $this->mailSlurpConfig);
            $attachment = $attachmentController->uploadAttachment($uploadAttachmentOptions);

            return isset($attachment[0]) ? $attachment[0] : false;
        }

        return false;
    }

    /**
     * formatEmailAddresses method - Need to convert from CakePHP style email address to mailslurp's
     *
     * @param array $emails The list of named emails
     * @return array of formatted emails
     */
    private function formatEmailAddresses(array $emails = [])
    {
        $formatted = [];
        foreach ($emails as $email => $name) {
            $formatted[] = $name . ' <' . $email . '>';
        }

        return $formatted;
    }

    /**
     * Send mail
     *
     * @param \Cake\Mailer\Email $email Email instance
     * @return string|bool
     */
    public function send(Email $email)
    {
        $inboxController = new InboxControllerApi(new Client(), $this->mailSlurpConfig);
        $result = $inboxController->doesInboxExist($this->getConfig('email'));
        $response = [];

        // Let's Make sure the email for the inbox exists before we get started
        if ($result->getExists() === true) {
            $sendEmailOptions = new SendEmailOptions();
            $sendEmailOptions->setTo($this->formatEmailAddresses($email->getTo()));
            $sendEmailOptions->setCc($this->formatEmailAddresses($email->getCc()));
            $sendEmailOptions->setBcc($this->formatEmailAddresses($email->getBcc()));
            $from = $email->getFrom();
            if (!empty($from)) {
                $sendEmailOptions->setFrom(array_values($from)[0] . ' <' . $this->getConfig('email') . '>');
            }
            $replyTo = $email->getReplyTo();
            if (!empty($replyTo)) {
                $sendEmailOptions->setReplyTo(array_values($replyTo)[0]);
            }
            $sendEmailOptions->setSubject($email->getSubject());
            $sendEmailOptions->setCharset($email->getCharset());

            $format = $email->getEmailFormat();
            $htmlMessage = $email->message('html');
            $textMessage = $email->message('text');

            $attachmentIds = [];
            $attachments = $email->getAttachments();
            foreach ($attachments as $filename => $attachmentInfo) {
                $attachmentId = $this->uploadAttachment($filename, $attachmentInfo);
                if (!empty($attachmentId)) {
                   $attachmentIds[] = $attachmentId;
                }
            }

            if (!empty($attachmentIds)) {
                $sendEmailOptions->setAttachments($attachmentIds);
            }

            if ($format !== 'text') {
                $sendEmailOptions->setIsHtml(true);
                $sendEmailOptions->setBody($htmlMessage);
                $response['html'] = $inboxController->sendEmailAndConfirm($this->getConfig('inboxId'), $sendEmailOptions);
            } else {
                $sendEmailOptions->setIsHtml(false);
                $sendEmailOptions->setBody($textMessage);
                $response['text'] = $inboxController->sendEmailAndConfirm($this->getConfig('inboxId'), $sendEmailOptions);
            }
        } else {
            throw new Exception('Make sure MailSlurp is configured properly. Missing Inbox!');
        }

        return $response;
    }
}
