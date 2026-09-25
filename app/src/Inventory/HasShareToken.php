<?php

namespace App\Inventory;

/**
 * Öffentlicher Teilen-Link (auch ohne Anmeldung aufrufbar) über einen
 * zufälligen, nicht erratbaren Schlüssel in der Spalte `ShareToken`.
 * Genutzt von Inventar-Objekten und Räumen.
 *
 * @property ?string $ShareToken
 */
trait HasShareToken
{
    /** Legt bei Bedarf einen Teilen-Schlüssel an und gibt ihn zurück */
    public function ensureShareToken(): string
    {
        if (!$this->ShareToken) {
            do {
                $token = bin2hex(random_bytes(16));
            } while (static::get()->filter('ShareToken', $token)->exists());
            $this->ShareToken = $token;
            $this->write();
        }
        return $this->ShareToken;
    }

    /** Link deaktivieren — bereits verteilte Links/QR-Codes/NFC-Tags funktionieren danach nicht mehr */
    public function revokeShareToken(): void
    {
        $this->ShareToken = null;
        $this->write();
    }
}
