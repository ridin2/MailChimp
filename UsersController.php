<?php

namespace App\Http\Controllers;

use \Illuminate\Contracts\Auth\Guard;
use App\Services\MailChimp\MailChimpService;

class UsersController extends Controller
{

    /**
     * The Guard implementation
     * @var Guard
     */
    private $auth;

    /**
    * @var MailChimpService
    */
    private $mailChimpSubscriptionService;

    /**
     * UsersController constructor.
     * @param Guard $auth
     * @param MailChimpService $mailChimpService
     */
    public function __construct(Guard $auth, MailChimpService $mailChimpService)
    {
        $this->auth = $auth;
        $this->mailChimpSubscriptionService = $mailChimpService;
    }

    /**
     * Show user's mail chimp subscribes
     * @return \Illuminate\View\View
     */
    public function showMailSubscriptions()
    {
        $user = $this->auth->user();
        $mailingLists = $this->mailChimpSubscriptionService->getActiveMailingListsWithStatusByEmail($user->email);

        return $this->render('users.mail_subscriptions', compact('mailingLists'));
    }

    /**
     * @param $listId
     * @return \Illuminate\Http\JsonResponse
     */
    public function unsubscribe($listId)
    {
        $user = $this->auth->user();
        $this->mailChimpSubscriptionService->unsubscribeByEmailAndListId($user->email, $listId);

        return response()->json([
            'alertText' => trans('users/front.you_unsubscribe_success'),
            'buttonText' => trans('users/front.subscribe'),
            'action' => 'subscribe',
        ]);
    }

    /**
     * @param $listId
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe($listId)
    {
        $user = $this->auth->user();
        $this->mailChimpSubscriptionService->subscribeByEmailAndListId($user->email, $listId);

        return response()->json([
            'alertText' => trans('users/front.you_subscribe_success'),
            'buttonText' => trans('users/front.unsubscribe'),
            'action' => 'unsubscribe',
        ]);
    }
}
