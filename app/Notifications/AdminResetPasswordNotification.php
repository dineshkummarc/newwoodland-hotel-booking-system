<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Laravel's standard reset-link email, pointed at the staff reset page
 * (admin.password.reset) instead of the customer one.
 */
class AdminResetPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return url(route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your staff password')
            ->line('You are receiving this email because we received a password reset request for your Newwoodland staff account.')
            ->action('Reset Password', $url)
            ->line('This link expires in '.config('auth.passwords.admins.expire').' minutes.')
            ->line('If you did not request a password reset, no action is needed — and you may want to tell your administrator.');
    }
}
