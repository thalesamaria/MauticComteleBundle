<?php

namespace MauticPlugin\MauticComteleBundle\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Mautic\SmsBundle\Api\AbstractSmsApi;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Mautic\CoreBundle\Helper\PhoneNumberHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PageBundle\Model\TrackableModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Monolog\Logger;

class ComteleApi extends AbstractSmsApi
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * MessageBirdApi constructor.
     *
     * @param TrackableModel    $pageTrackableModel
     * @param PhoneNumberHelper $phoneNumberHelper
     * @param IntegrationHelper $integrationHelper
     * @param Logger            $logger
     *
     * @param Http              $http
     */
    public function __construct(TrackableModel $pageTrackableModel, PhoneNumberHelper $phoneNumberHelper, IntegrationHelper $integrationHelper, Logger $logger)
    {
        $this->logger = $logger;
        $this->integrationHelper = $integrationHelper;
        parent::__construct($pageTrackableModel);
    }
    /**
     * @param $number
     *
     * @return string
     *
     * @throws NumberParseException
     */
    protected function sanitizeNumber($number)
    {
        $util   = PhoneNumberUtil::getInstance();
        $parsed = $util->parse($number, 'BR');
        return $util->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * @param Lead   $contact
     * @param string $content
     *
     * @return bool|mixed|string
     */
    public function sendSms(Lead $contact, $content)
    {
        $number = $contact->getLeadPhoneNumber();

        if ($number === null) {
            return false;
        }

        $integration = $this->integrationHelper->getIntegrationObject('Comtele');
        if ($integration && $integration->getIntegrationSettings()->getIsPublished()) {
            $data = $integration->getDecryptedApiKeys();
            if (isset($data['auth_token'])) {
                $body = [
                    'Receivers' => $this->sanitizeNumber($number),
                    'Content' => $content,
                    'Sender' => 'MauticApi',
                ];
                try {
                    $headers = [
                        'auth-key' => $data['auth_token'],
                        'Content-Type'  => 'application/json',
                    ];

                    $client = new Client();
                    $response = $client->post(
                        'https://sms.comtele.com.br/api/v2/send',
                        [
                            'headers' => $headers,
                            'body' => json_encode($body),
                        ]
                    );
                    return ($response->getStatusCode() == 200) ? true : false;
                } catch (ServerException $exception) {
                    $this->parseResponse($exception->getResponse(), $body);
                } catch (Exception $e) {
                    if (method_exists($e, 'getErrorMessage')) {
                        return $e->getErrorMessage();
                    } elseif (!empty($e->getMessage())) {
                        return $e->getMessage();
                    }

                    return false;
                }
            }
        }
    }
}
