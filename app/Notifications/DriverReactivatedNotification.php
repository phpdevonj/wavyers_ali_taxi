<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DriverReactivatedNotification extends Notification
{
    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject(__('message.driver_reactivated_subject', ['app' => $app]))
            ->greeting(__('message.driver_reactivated_greeting', ['name' => $notifiable->first_name ?: $notifiable->display_name]))
            ->line(__('message.driver_reactivated_line1'))
            ->line(__('message.driver_reactivated_line2'))
            ->salutation(__('message.driver_reactivated_salutation', ['app' => $app]));
    }
}
