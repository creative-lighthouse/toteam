<?php

namespace App\Auth;

use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Environment;

/**
 * Sends the one-time login/verification codes issued by LoginCode.
 * Best-effort, mirrors App\Feedback\Feedback::notifySubmitter().
 */
class LoginCodeMailer
{
    public static function sendLoginCode(string $email, string $code): bool
    {
        return self::send(
            $email,
            'Dein ToTeam-Anmeldecode',
            "Hallo,\n\ndein Anmeldecode für ToTeam lautet:\n\n{$code}\n\n"
                . "Der Code ist 10 Minuten gültig. Falls du das nicht warst, kannst du diese E-Mail ignorieren.\n\n"
                . "Dein ToTeam-Team"
        );
    }

    public static function sendEmailChangeCode(string $email, string $code): bool
    {
        return self::send(
            $email,
            'Bestätige deine neue E-Mail-Adresse bei ToTeam',
            "Hallo,\n\ndu hast beantragt, diese E-Mail-Adresse mit deinem ToTeam-Konto zu verknüpfen. "
                . "Bestätige das mit folgendem Code:\n\n{$code}\n\n"
                . "Der Code ist 10 Minuten gültig. Falls du das nicht warst, kannst du diese E-Mail ignorieren.\n\n"
                . "Dein ToTeam-Team"
        );
    }

    public static function sendPasswordResetCode(string $email, string $code): bool
    {
        return self::send(
            $email,
            'Passwort zurücksetzen bei ToTeam',
            "Hallo,\n\ndu hast angefragt, dein ToTeam-Passwort zurückzusetzen. Nutze dafür folgenden Code:\n\n{$code}\n\n"
                . "Der Code ist 10 Minuten gültig. Falls du das nicht warst, kannst du diese E-Mail ignorieren "
                . "— dein Passwort bleibt dann unverändert.\n\n"
                . "Dein ToTeam-Team"
        );
    }

    private static function send(string $to, string $subject, string $body): bool
    {
        try {
            $email = Email::create()
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($body);

            $from = Environment::getEnv('MAIL_FROM_ADDRESS');
            if ($from) {
                $email->setFrom($from);
            }

            $email->send();
            return true;
        } catch (\Throwable $e) {
            error_log('LoginCodeMailer::send fehlgeschlagen: ' . $e->getMessage());
            return false;
        }
    }
}
