<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;

class ResetPasswordQueued extends BaseResetPassword implements ShouldQueue
{
    use Queueable;
}
