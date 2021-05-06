<?php
namespace DMatrix\Telex\Repository;

use DMatrix\Telex\Repository\Contracts\TelexServiceInterface;
use GuzzleHttp\Client as RequestClient;
use GuzzleHttp\Promise;
use Illuminate\Support\Arr;

class Telex implements TelexServiceInterface
{
    protected $key;
    protected $id;
    public function __construct() {

        $this->key = config('services.telex.key');
        $this->id = config('services.telex.id');

    }
    public function sendEmail($params, $attachment = false)
    {

        $receiver = rtrim($params['to'], ",");
        $receiver = explode(',', $receiver);

        $client = new RequestClient(
            [
                'headers' => [
                    'ORGANIZATION-KEY' => $this->key,
                    'ORGANIZATION-ID' => $this->id
                ]
            ]
        );
        $payload = [];
        $payload['template_uuid'] = $params['params']['message_type']['email_template_id'];
        $payload['sender'] = $params['params']['message_type']['sender_email'];
        $payload['sender_email'] = $params['params']['message_type']['sender_email'];

        $payload['placeholders'] = $params['params'];

        $payload['attachments'] =  $attachment ? $params['attachments'] : [];
        $payload['message_type'] = "email";
        $url = config('services.telex.endpoint');


        $promises = [];
        array_walk($receiver, function($email) use ($payload, $attachment, $client, $url, &$promises) {
            $tempParams = $payload;
            $customerData[] = [
                'name' => $params['receiver_name'] ?? '',
                'email' => str_replace_last(";","", $email ?? "")
            ];
            $tempParams['customers'] = $customerData;
            $tempParams['receiver_email'] = $email;

            if (!$attachment) {
                $promises[] = $client->requestAsync('POST', $url, ['form_params' => $tempParams]);
            } else {

                $newPayload = $this->modifyPayload($tempParams);

                $promises[] = $client->requestAsync('POST', $url, ['multipart' => $newPayload]);
            }

        });

        $results = Promise\unwrap($promises);
        return $results[0]->getStatusCode();
    }

    public function sendSMS($params)
    {

        $receiver = rtrim($params['to'], ",");
        $receiver = explode(',', $receiver);
        $client = new RequestClient(
            [
                'headers' => [
                    'ORGANIZATION-KEY' => $this->key,
                    'ORGANIZATION-ID' => $this->id
                ]
            ]
        );
        $payload = [];
        $payload['template_uuid'] = $params['params']['message_type']['sms_template_id'];
        $payload['sender'] = env("DEFAULT_SMS_SENDER");
        $payload['placeholders'] = $params['params'];
        $payload['message_type'] = "sms";
        $url = config('services.telex.endpoint');

        $receiverCount = count($receiver);

        if ($receiverCount > 1) {
            $tempParams = $payload;
            $customerData = [];
            for ($i = 0; $i < $receiverCount; $i++) {
                $tempParams['receiver'] = $receiver[$i];
                $customerData[] = [
                    'name' => $params['receiver_name'] ?? '',
                    'email' => str_replace_last(";","",$tempParams['receiver_email'] ?? ""),
                    'phone' => $tempParams['receiver']
                ];
            }
            $payload['customers'] = $customerData;

        } else {
            $payload['receiver'] = $receiver[0];
            $customerData = [
                'name' => $params['receiver_name'] ?? '',
                'email' => str_replace_last(";","",$payload['receiver_email'] ?? ""),
                'phone' => $payload['receiver']
            ];
            $payload['customers'] = array($customerData);

        }
        $res = $client->request('POST', $url, $this->getPayload('form_params',  $payload));
        return $res->getStatusCode();
    }

    public function modifyPayload($params)
    {
        $multipart = [];
        //loop through the params
        foreach ($params as $name => $content) {
            if (!is_array($content)) {
                $multipart[] = ['name' => $name, 'contents' => $content];
            } else {
                if ($name == 'attachments') {
                    array_walk($content, function($attach, $key) use (&$multipart, $name) {
                        $multipart[] = ['name' => $name.'['.$key.']', 'contents' => $attach['file'], 'filename' => time() . '.'.$attach['extension']];
                    });
                }

                foreach ($content as $placeholder => $value) {
                    if (!is_array($value)) {
                        $multipart[] = ['name' => $name . '[' . $placeholder . ']', 'contents' => $value];
                    } else {

                        foreach ($value as $key => $detail) {
                            //looping through the value, the detail can also be an array e.g [roomname => 'deluxe']
                            if (is_array($detail)) {
                                foreach ($detail as $infoKey => $info) {
                                    $multipart[] = ['name' => $name . '[' . $placeholder . '][' . $infoKey . ']', 'contents' => $info];
                                }
                            } else {
                                $multipart[] = ['name' => $name . '[' . $placeholder . ']['. $key. ']', 'contents' => $detail];
                            }

                        }
                    }
                }
            }
        }

        return $multipart;
    }
    /**
     * Get the HTTP payload for sending the message.
     *
     * @return array
     */
    protected function getPayload($type, $payload)
    {
        // Change this to the format your API accepts
        return [
            $type => $payload
        ];
    }
}
