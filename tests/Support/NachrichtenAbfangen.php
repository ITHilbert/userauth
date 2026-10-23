<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Fängt versendete Nachrichten ab, gleichgültig ob die Umsetzung ein Mailable oder eine Notification nutzt,
 * und liest Reset-Link bzw. 2FA-Code aus dem gerenderten Inhalt. Hilfsklasse für die Härtungs-Tests (T5, T6).
 */
trait NachrichtenAbfangen
{
    protected function nachrichtenAbfangen(): void
    {
        Mail::fake();
        Notification::fake();
    }

    /**
     * Alle abgefangenen Nachrichten in Versandreihenfolge (je Quelle).
     *
     * @return list<array{an: list<string>, html: string, text: string}>
     */
    protected function gesendeteNachrichten(): array
    {
        $nachrichten = [];

        foreach ([Mail::sent(Mailable::class), Mail::queued(Mailable::class)] as $mailables) {
            foreach ($mailables as $mailable) {
                $html = (string) $mailable->render();
                $nachrichten[] = [
                    'an' => array_values(array_map(fn (array $empfaenger): string => (string) $empfaenger['address'], $mailable->to)),
                    'html' => $html,
                    'text' => $this->nurText($html),
                ];
            }
        }

        foreach (Notification::sentNotifications() as $nachNotifiable) {
            foreach ($nachNotifiable as $nachKlasse) {
                foreach ($nachKlasse as $eintraege) {
                    foreach ($eintraege as $eintrag) {
                        if (! in_array('mail', $eintrag['channels'], true)) {
                            continue;
                        }

                        $notification = $eintrag['notification'];
                        $notifiable = $eintrag['notifiable'];
                        $mail = $notification->toMail($notifiable);
                        $html = (string) $mail->render();

                        $nachrichten[] = [
                            'an' => [(string) $notifiable->routeNotificationFor('mail', $notification)],
                            'html' => $html,
                            'text' => $this->nurText($html),
                        ];
                    }
                }
            }
        }

        return $nachrichten;
    }

    /**
     * Nachrichten, deren Empfänger exakt (Groß-/Kleinschreibung beachtet) diese Adresse ist.
     *
     * @return list<array{an: list<string>, html: string, text: string}>
     */
    protected function nachrichtenAn(string $adresse): array
    {
        return array_values(array_filter(
            $this->gesendeteNachrichten(),
            fn (array $nachricht): bool => in_array($adresse, $nachricht['an'], true),
        ));
    }

    /**
     * Reset-Link (Route password.editwithtoken) aus der Nachricht oder null.
     *
     * @param  array{an: list<string>, html: string, text: string}  $nachricht
     */
    protected function linkAusNachricht(array $nachricht): ?string
    {
        if (preg_match('~https?://[^\s"\'<>]*password/editwithtoken/[^\s"\'<>]+~', $nachricht['html'], $treffer) !== 1) {
            return null;
        }

        return html_entity_decode($treffer[0]);
    }

    /**
     * Sechsstelliger Code aus der letzten Nachricht an die Adresse.
     */
    protected function zweiFaktorCode(string $adresse): string
    {
        $nachrichten = $this->nachrichtenAn($adresse);
        $this->assertNotEmpty($nachrichten, "Keine Nachricht an {$adresse} versendet.");

        $text = end($nachrichten)['text'];
        $this->assertSame(1, preg_match('/(?<!\d)(\d{6})(?!\d)/', $text, $treffer), 'Kein sechsstelliger Code in der Nachricht.');

        return $treffer[1];
    }

    private function nurText(string $html): string
    {
        $html = (string) preg_replace('~<style\b.*?</style>~si', ' ', $html);
        $html = (string) preg_replace('~\sstyle="[^"]*"~i', '', $html);

        return trim(html_entity_decode(strip_tags($html)));
    }
}
