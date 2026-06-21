<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AccountInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(    
        public string $setupToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = Setting::getValue('company_name', 'Pâtisserie Sur Mesure');
        $expiry = (int) Setting::getValue('invitation_expiry_time', INVITATION_EXPIRY_TIME);
        $url = route('setup-password', ['token' => $this->setupToken, 'email' => $notifiable->email]);
        $durationLabel = $expiry >= 60 ? ($expiry / 60) . ' heures' : $expiry . ' minutes';

        return (new MailMessage)
            ->subject('Invitation à rejoindre ' . $company)
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Un compte a été créé pour vous sur l\'application de gestion **' . $company . '**.')
            ->line('Cliquez sur le bouton ci-dessous pour définir votre mot de passe et activer votre compte.')
            ->action('Définir mon mot de passe', $url)
            ->line('Ce lien est valable '.$durationLabel.'.')
            ->salutation('L\'équipe ' . $company);
    }
}
