<?php

namespace App\Services\MailChimp;

use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class MailChimpService
{
    /**
     * @var Client
     */
    private $httpClient;

    /**
     * MailChimpService constructor.
     * @param Client $httpClient
     */
    public function __construct(Client $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /*
        |--------------------------------------------------------------------------
        | CHANGE SUBSCRIPTION STATUS
        |--------------------------------------------------------------------------
    */

    /**
     * @param string $email
     * @param string $listId
     */
    public function unsubscribeByEmailAndListId(string $email, string $listId): void
    {
        $this->changeSubscriptionStatusByEmailAndListId($email, $listId, 'unsubscribed');
    }

    /**
     * @param string $email
     * @param string $listId
     */
    public function subscribeByEmailAndListId(string $email, string $listId): void
    {
        $response = $this->changeSubscriptionStatusByEmailAndListId($email, $listId, 'subscribed');

        if ($response->getStatusCode() == "404") {
            $this->createSubscriberByEmailAndListId($email, $listId);
        }
    }

    /**
     * @param string $email
     * @param string $listId
     * @param string $status
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function changeSubscriptionStatusByEmailAndListId(string $email, string $listId, string $status): \Psr\Http\Message\ResponseInterface
    {
        $requestSubscriberUrl = $this->buildRequestUrlForSubscriber($email, $listId);

        $response = $this->httpClient->patch($requestSubscriberUrl, [
            'headers' => [
                'Authorization' => 'Basic ' . config('mail_chimp.credentials'),
            ],
            'json' => [
                'status' => $status,
            ],
            'http_errors' => false,
        ]);

        return $response;
    }

    /**
     * @param string $email
     * @param string $listId
     */
    public function createSubscriberByEmailAndListId(string $email, string $listId): void
    {
        $requestSubscriberUrl = $this->buildRequestUrlForListMembers($listId);

        $this->httpClient->post($requestSubscriberUrl, [
            'headers' => [
                'Authorization' => 'Basic ' . config('mail_chimp.credentials'),
            ],
            'json' => [
                'status' => 'subscribed',
                'email_address' => $email,
            ],
            'http_errors' => false,
        ]);
    }

    /*
        |--------------------------------------------------------------------------
        | GET MAILING LISTS
        |--------------------------------------------------------------------------
    */

    /**
     * @param string $email
     * @return Collection
     */
    public function getActiveMailingListsWithStatusByEmail(string $email): Collection
    {
        $mailingLists = $this->getActiveMailingLists();
        $mailingListsByEmail = $this->getActiveMailingListsByEmail($email);

        foreach($mailingLists as $mailingId => $mailingName)
        {
            $mailingLists[$mailingId] = [
                'name' => $mailingName,
                'action' => isset($mailingListsByEmail[$mailingId]) ? 'unsubscribe' : 'subscribe',
            ];
        }

        return $mailingLists;
    }

    /**
     * @return Collection
     */
    private function getActiveMailingLists(): Collection
    {
        $requestListsUrl = $this->buildRequestUrlForLists();

        $response = $this->httpClient->get($requestListsUrl, [
            'headers' => [
                'Authorization' => 'Basic ' .  config('mail_chimp.credentials'),
            ],
            'http_errors' => false,
        ]);

        return $this->getContents($response);
    }

    /**
     * @param string $email
     * @return Collection
     */
    private function getActiveMailingListsByEmail(string $email): Collection
    {
        $requestListsUrl = $this->buildRequestUrlForLists();

        $response = $this->httpClient->get($requestListsUrl, [
            'headers' => [
                'Authorization' => 'Basic ' .  config('mail_chimp.credentials'),
            ],
            'query' => [
                'email' => $email,
            ],
            'http_errors' => false,
        ]);

        return $this->getContents($response);
    }

    /**
     * @param $response
     * @return Collection
     */
    private function getContents($response): Collection
    {
        if ($response->getStatusCode() == "404") {
            return collect();
        }

        $notDecodedResponse = $response->getBody()->getContents();
        $decodedResponse = json_decode($notDecodedResponse, true);
        $lists = $decodedResponse['lists'];

        return collect($lists)->pluck('name', 'id');
    }

    /*
        |--------------------------------------------------------------------------
        | BUILD URL
        |--------------------------------------------------------------------------
    */

    /**
     * @param string $email
     * @param string $listId
     * @return string
     */
    private function buildRequestUrlForSubscriber(string $email, string $listId): string
    {
        $subscriberHash = md5($email);

        return config('mail_chimp.api_url')."/lists/".$listId."/members/$subscriberHash";
    }

    /**
     * @param string $listId
     * @return string
     */
    private function buildRequestUrlForListMembers(string $listId): string
    {
        return config('mail_chimp.api_url')."/lists/".$listId."/members";
    }

    /**
     * @return string
     */
    private function buildRequestUrlForLists(): string
    {
        return config('mail_chimp.api_url')."/lists/";
    }
}