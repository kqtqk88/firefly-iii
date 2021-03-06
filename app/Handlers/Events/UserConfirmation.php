<?php
/**
 * UserConfirmation.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Handlers\Events;


use Exception;
use FireflyIII\Events\ResendConfirmation;
use FireflyIII\Events\UserRegistration;
use FireflyIII\User;
use Illuminate\Mail\Message;
use Log;
use Mail;
use Preferences;
use Swift_TransportException;

/**
 * Class UserConfirmation
 *
 * @package FireflyIII\Handlers\Events
 */
class UserConfirmation
{
    /**
     * Create the event listener.
     *
     */
    public function __construct()
    {
        //
    }

    /**
     * @param ResendConfirmation $event
     *
     * @return bool
     */
    public function resendConfirmation(ResendConfirmation $event): bool
    {
        $user      = $event->user;
        $ipAddress = $event->ipAddress;
        $this->doConfirm($user, $ipAddress);

        return true;
    }

    /**
     * Handle the event.
     *
     * @param  UserRegistration $event
     *
     * @return bool
     */
    public function sendConfirmation(UserRegistration $event): bool
    {
        $user      = $event->user;
        $ipAddress = $event->ipAddress;
        $this->doConfirm($user, $ipAddress);

        return true;
    }

    /**
     * @param User   $user
     * @param string $ipAddress
     */
    private function doConfirm(User $user, string $ipAddress)
    {
        $confirmAccount = env('MUST_CONFIRM_ACCOUNT', false);
        if ($confirmAccount === false) {
            Preferences::setForUser($user, 'user_confirmed', true);
            Preferences::setForUser($user, 'user_confirmed_last_mail', 0);
            Preferences::mark();

            return;
        }
        $email = $user->email;
        $code  = str_random(16);
        $route = route('do_confirm_account', [$code]);
        Preferences::setForUser($user, 'user_confirmed', false);
        Preferences::setForUser($user, 'user_confirmed_last_mail', time());
        Preferences::setForUser($user, 'user_confirmed_code', $code);
        try {
            Mail::send(
                ['emails.confirm-account-html', 'emails.confirm-account'], ['route' => $route, 'ip' => $ipAddress],
                function (Message $message) use ($email) {
                    $message->to($email, $email)->subject('Please confirm your Firefly III account');
                }
            );
        } catch (Swift_TransportException $e) {
            Log::error($e->getMessage());
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }

        return;
    }

}
