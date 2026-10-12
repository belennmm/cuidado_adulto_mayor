<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordRecoveryCode extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject('Restablecer contrasena')->line('Copia este codigo en el formulario. Caduca en 15 minutos y permite cinco intentos.')->line($this->token)->action('Abrir formulario', config('auth.password_reset_url'))->line('Si no lo solicitaste, ignora este correo.');
    }
}
