<?php

namespace DMatrix\Telex\Repository;

use GuzzleHttp\Client;

class TelexClient
{

    protected $client;
    protected $telexDomain;
    protected $organizationKey;

    public function __construct($organizationKey = null, $organizationId = null, $telexDomain = 'https://telex.im')
    {

        $this->telexDomain = $telexDomain;
        $this->organizationKey = $organizationKey ?? config('services.telex.key');
        $this->organizationId = $organizationId ?? config('services.telex.id');
        $this->client = new Client([
            'headers' => [
                'ORGANIZATION-KEY' => $this->organizationKey,
                'ORGANIZATION-ID' => $this->organizationId
            ]
        ]);
    }

    private function parseArrayItem(array $jsonInputArray, String $arrayLabel = '')
    {
        $multipartArray = [];

        foreach ($jsonInputArray as $key => $value) {
            $multipartKey = "[${key}]";
            if ($arrayLabel != '') {
                $multipartKey = "${arrayLabel}${multipartKey}";
            }

            if (gettype($value) === 'array') {
                $nestedItems = self::parseArrayItem($value, $multipartKey);
                $multipartArray = array_merge($multipartArray, $nestedItems);
            } else {
                $multipartArray[$multipartKey] = $value;
            }
        }

        return $multipartArray;
    }

    public function sendEmail(string $template_id, array $customer, array $placeholderData, array $options = [])
    {
        $url = "{$this->telexDomain}/api/send-message";

        $receiverEmail = $options['receiver_email'] ?? '';
        $tagData = $options['tag_data'] ?? [];
        $metadata = $options['metadata'] ?? [];
        $attachments = $options['attachments'] ?? null;
        $organizationKey = $options['organization_key'] ?? $this->organizationKey;
        $organizationId = $options['organization_id'] ?? $this->organizationId;

        $data = [
            'customers' => array($customer) ?? [],
            'placeholders' => $placeholderData ?? [],
            'attachments' => $attachments ?? [],
            'event_type' => $metadata['metadata']['channel'],
            'tag_data' => $tagData,
            'template_uuid' => $template_id,
            'organization_id' => $organizationId,
            'sender' => $options['sender']
        ];

        if (!empty($receiverEmail)) {
            $data['metadata']['receiver_email'] = $receiverEmail;
        }

        $data['metadata'] = array_merge($data['metadata'], $metadata);

        $requestType = 'json';

        if (isset($attachments)) {
            $requestType = 'multipart';
            $requestData = [];

            foreach ($data as $key => $value) {
                if (gettype($value) != 'array') {
                    $multipartArray = [
                        'name' => $key,
                        'contents' => $value
                    ];

                    array_push($requestData, $multipartArray);
                } else {

                    $multipartArray = self::parseArrayItem($value, $key);
                    foreach ($multipartArray as $key => $value) {
                        $data  = [
                            'name' => $key,
                            'contents' => $value
                        ];

                        array_push($requestData, $data);
                    }
                }
            }

            $data = $requestData;
        }

        $payload = [
            $requestType => $data
        ];

        if (!empty($organizationKey)) {
            $client = new Client([
                'headers' => [
                    'ORGANIZATION-KEY' => $organizationKey,
                    'ORGANIZATION-ID' => $organizationId
                ]
            ]);

            $response = $client->request('POST', $url, $payload);

            $response = [
                'status' => $response->getStatusCode() === 201 ? 'ok' : 'error'
            ];

            return $response;
        } else {
            $response = $this->client->request('POST', $url, $payload);

            $response = [
                'status' => $response->getStatusCode() === 201 ? 'ok' : 'error'
            ];

            return $response;
        }
    }

    public function sendSMS($params)
    {

        $receiver = rtrim($params['to'] ?? $params['receiver'], ",");
        $receiver = explode(',', $receiver);
        $client = new Client(
            [
                'headers' => [
                    'ORGANIZATION-KEY' => $this->key,
                    'ORGANIZATION-ID' => $this->id
                ]
            ]
        );
        $data = [];
        $data['template_uuid'] = $params['params']['message_type']['sms_template_id'] ?? $params['template_id'];
        $data['sender'] = $params['sender'] ?? '';
        $data['placeholders'] = $params['params'] ?? $params['placeholders'];
        $data['message_type'] = "sms";
        $url = "{$this->telexDomain}/api/send-message";

        $receiverCount = count($receiver);

        if ($receiverCount > 1) {
            $tempParams = $data;
            $customerData = [];
            for ($i = 0; $i < $receiverCount; $i++) {
                $tempParams['receiver'] = $receiver[$i];
                $customerData[] = [
                    'name' => $params['receiver_name'] ?? '',
                    'email' => str_replace_last(";","",$tempParams['receiver_email'] ?? ""),
                    'phone' => $tempParams['receiver']
                ];
            }
            $data['customers'] = $customerData;

        } else {
            $data['receiver'] = $receiver[0];
            $customerData = [
                'name' => $params['receiver_name'] ?? '',
                'email' => str_replace_last(";","",$data['receiver_email'] ?? ""),
                'phone' => $data['receiver']
            ];
            $data['customers'] = array($customerData);

        }
        $payload = [
            'json' => $data
        ];

        $response = $client->request('POST', $url, $payload);

            $response = [
                'status' => $response->getStatusCode() === 201 ? 'ok' : 'error'
            ];

            return $response;
    }
}
