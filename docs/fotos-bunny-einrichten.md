# Foto-Speicher mit Bunny einrichten

Fotos im Foto-Totem von ToTeam liegen nicht auf dem ToTeam-Server, sondern im **eigenen Bunny-Account eurer Organisation**. So behaltet ihr die Kontrolle über eure Bilder, und die Kosten rechnet ihr direkt mit Bunny ab. In der Regel sind das nur ein paar Cent bis wenige Euro im Monat.

Diese Anleitung führt euch einmal durch die Einrichtung. Rechnet mit etwa 15 Minuten.

> **Wer kann das?** Die Einstellungen in ToTeam darf ändern, wer in der Organisation das Recht **„Organisationsprofil (Logo etc.) bearbeiten“** hat (Administratoren haben es immer).

---

## Überblick

Ihr legt bei Bunny drei Dinge an und tragt die Daten danach in ToTeam ein:

| Bei Bunny | Wofür | Was ihr in ToTeam eintragt |
|---|---|---|
| **Storage Zone** | Der Speicherplatz für die Fotos | Name der Storage Zone und Region |
| **Passwort der Storage Zone** | Damit ToTeam Fotos hochladen und löschen darf | Passwort der Storage Zone |
| **Pull Zone** | Damit die Fotos schnell im Browser angezeigt werden | Adresse der Pull Zone |
| **Token Authentication** (empfohlen) | Fotos sind nur über Links aus ToTeam abrufbar | Token-Authentication-Schlüssel |

---

## Schritt 1: Bunny-Account anlegen

1. Auf [bunny.net](https://bunny.net) auf **Sign up** klicken und ein Konto anlegen. Nehmt am besten eine E-Mail-Adresse der Organisation, keine private, damit der Zugang erhalten bleibt, wenn Personen wechseln.
2. Unter **Billing** ein Zahlungsmittel hinterlegen oder Guthaben aufladen. Bunny bietet zum Start eine kostenlose Testphase an; danach wird nach Verbrauch abgerechnet (es gibt einen kleinen Mindestbetrag pro Monat).
3. **Datenschutz:** Fotos von Personen sind personenbezogene Daten. Schließt bei Bunny den **Auftragsverarbeitungsvertrag (DPA)** ab. Ihr findet ihn in den Account-Einstellungen bzw. auf der Website von Bunny unter „DPA“. Bunny ist ein europäisches Unternehmen (Slowenien).

---

## Schritt 2: Storage Zone anlegen

1. Im Bunny-Dashboard links auf **Storage** gehen und **Add Storage Zone** klicken.
2. Ausfüllen:
   - **Storage Zone Name:** ein eindeutiger Name, z. B. `mein-verein-fotos`. Nur Kleinbuchstaben, Zahlen und Bindestriche.
   - **Storage Tier:** **Standard** reicht völlig und ist günstiger als „Edge (SSD)“.
   - **Main Storage Region:** **Falkenstein (DE)** empfehlen wir, dann bleiben die Daten in Deutschland.
   - **Geo-Replication:** keine weiteren Regionen auswählen, das kostet nur extra.
3. Mit **Add Storage Zone** speichern.

**Merkt euch:** den Namen der Storage Zone und die Region.

---

## Schritt 3: Passwort der Storage Zone kopieren

1. Die eben angelegte Storage Zone öffnen.
2. Links auf **FTP & API Access** gehen.
3. Dort steht unter **Password** ein langes Passwort. Auf das Kopieren-Symbol klicken.

> ⚠️ Nehmt das normale **Password**, **nicht** das „Read-only Password“. Mit dem Read-only-Passwort kann ToTeam keine Fotos hochladen.
>
> Das Passwort ist ein Generalschlüssel für euren Speicher. Gebt es nicht weiter und tragt es nur in ToTeam ein. ToTeam zeigt es nach dem Speichern nie wieder an.

---

## Schritt 4: Pull Zone verbinden

Die Pull Zone ist die „Auslieferung“: Über sie lädt der Browser die Fotos schnell aus einem Rechenzentrum in eurer Nähe.

1. In der Storage Zone links auf **Connected Pull Zones** gehen und **Connect Pull Zone** bzw. **Add Pull Zone** klicken.
2. Ausfüllen:
   - **Pull Zone Name:** z. B. `mein-verein-fotos`. Daraus wird die Adresse `https://mein-verein-fotos.b-cdn.net`.
   - **Origin Type:** **Storage Zone**, mit eurer Storage Zone aus Schritt 2 (ist meist schon vorausgewählt).
   - **Tier:** **Standard**.
   - **Pricing Zones:** Wenn eure Mitglieder fast nur in Europa sind, könnt ihr die anderen Kontinente abwählen. Das spart Kosten, ist aber nicht nötig.
3. Mit **Add Pull Zone** speichern.

**Merkt euch:** die Adresse der Pull Zone (unter **Hostnames**, endet auf `.b-cdn.net`).

---

## Schritt 5: Token Authentication einschalten (empfohlen)

Ohne diesen Schritt funktioniert alles auch, aber dann kann jede Person ein Foto öffnen, die den Link kennt. Die Links sind zwar zufällig und nicht erratbar, laufen aber nie ab. Mit Token Authentication erzeugt ToTeam Links, die nach einigen Stunden ungültig werden.

1. Die Pull Zone öffnen (links **CDN** → eure Pull Zone).
2. Links auf **Security** → **Token Authentication** gehen.
3. **Token Authentication** einschalten.
4. Den **Url Token Authentication Key** kopieren.
5. Speichern.

> Sobald das eingeschaltet ist, funktionieren Fotos nur noch über Links aus ToTeam. Tragt den Schlüssel deshalb **direkt danach** in ToTeam ein (Schritt 6). Sonst werden Fotos, die schon hochgeladen sind, in der Zwischenzeit nicht angezeigt.

**Optional, um Kosten zu sparen:** In der Pull Zone unter **Caching** die Einstellung **Query String Vary** (Cache je nach Abfrageparametern) ausschalten. Dann speichert Bunny ein Foto nur einmal zwischen, auch wenn sich der Token im Link stündlich ändert. Die Prüfung des Tokens funktioniert trotzdem.

**Nicht nötig:** den **Bunny Optimizer**. ToTeam erzeugt Vorschaubilder schon beim Hochladen selbst.

---

## Schritt 6: In ToTeam eintragen

1. In ToTeam das Menü öffnen und **Fotos** auswählen.
2. Oben rechts auf das **Zahnrad** (⚙) klicken. Gehört ihr zu mehreren Organisationen, zuerst die Organisation wählen.
3. Die Felder ausfüllen:

   | Feld in ToTeam | Was rein muss | Beispiel |
   |---|---|---|
   | **Storage Zone** | Name aus Schritt 2 | `mein-verein-fotos` |
   | **Region der Storage Zone** | Region aus Schritt 2 | Falkenstein (DE) |
   | **Passwort der Storage Zone** | Passwort aus Schritt 3 | `a1b2c3…` |
   | **Adresse der Pull Zone** | Adresse aus Schritt 4 | `https://mein-verein-fotos.b-cdn.net` |
   | **Token-Authentication-Schlüssel** | Schlüssel aus Schritt 5 (optional) | `d4e5f6…` |

4. Auf **Verbindung testen** klicken. Erscheint „Verbindung zu Bunny klappt“, passt alles.
5. Auf **Speichern** klicken.

Fertig! Ab jetzt können alle mit dem Recht **„Fotos hochladen“** Fotos hochladen.

---

## Schritt 7: Ausprobieren

1. Im Foto-Totem auf **+ Hochladen** klicken und ein, zwei Handyfotos auswählen.
2. Nach dem Hochladen sollten die Fotos im Raster erscheinen. Ein Klick öffnet das Foto.

Erscheint das Foto in der Liste, aber das **Bild bleibt leer**, liegt es fast immer an der Pull Zone oder am Token-Schlüssel (siehe unten).

---

## Rechte in ToTeam

Unter **Organisation verwalten → Rollen & Berechtigungen** gibt es die Kategorie **Fotos**:

- **Fotos hochladen:** Fotos hochladen. Was man selbst hochgeladen hat, darf man auch bearbeiten, taggen und löschen.
- **Fremde Fotos bearbeiten, taggen & löschen:** für alle Fotos der Organisation.

Sehen können die Fotos alle Mitglieder der Organisation. Wer auf einem Foto markiert ist, kann die eigene Markierung jederzeit selbst entfernen.

> Bei Organisationen, die es schon vor dem Foto-Totem gab, haben die Rollen diese Rechte noch nicht. Administratoren können sie dort den Rollen geben.

---

## Was kostet das ungefähr?

ToTeam verkleinert Fotos vor dem Hochladen. Ein Foto braucht dann etwa **0,5 bis 1,5 MB** statt der 3 bis 10 MB vom Handy.

Grobe Beispielrechnung für **10.000 Fotos (ca. 10 GB)**:

- **Speicher:** rund 10 GB × ca. 1 Cent pro GB und Monat ≈ **10 Cent im Monat**
- **Auslieferung:** abhängig davon, wie oft Fotos angesehen werden, in Europa ca. 1 Cent pro GB

Dazu kommt der monatliche Mindestbetrag von Bunny. Die aktuellen Preise findet ihr auf [bunny.net/pricing](https://bunny.net/pricing). Sie können sich ändern, prüft sie also selbst.

---

## Häufige Probleme

| Meldung / Problem | Ursache | Lösung |
|---|---|---|
| „Bunny lehnt den Zugang ab“ | Falsches Passwort, oder das Read-only-Passwort wurde verwendet | Das normale **Password** aus *FTP & API Access* neu kopieren und eintragen |
| „Storage Zone nicht gefunden“ | Tippfehler im Namen oder falsche Region | Namen und Region mit Schritt 2 vergleichen |
| „Bunny ist gerade nicht erreichbar“ | Netzwerkproblem | Später erneut versuchen |
| Fotos erscheinen, aber das Bild bleibt leer | Falsche Pull-Zone-Adresse, oder Token Authentication ist an, aber der Schlüssel fehlt bzw. ist falsch | Adresse prüfen (muss auf `.b-cdn.net` enden bzw. eure eigene Domain sein) und den Token-Schlüssel neu eintragen |
| Bilder verschwinden nach ein paar Stunden aus offenen Tabs | Die Links laufen absichtlich ab | Seite neu laden. ToTeam erzeugt dann frische Links |

---

## Wichtig, wenn ihr später etwas ändert

- **Passwort neu erzeugt** (z. B. weil es versehentlich weitergegeben wurde): einfach das neue Passwort in ToTeam eintragen. Die vorhandenen Fotos bleiben erhalten.
- **Token-Schlüssel neu erzeugt:** sofort den neuen Schlüssel in ToTeam eintragen, sonst werden keine Bilder angezeigt.
- **Andere Storage Zone verwenden:** Die bisherigen Fotos liegen weiter in der alten Storage Zone. Werden sie nicht mit umgezogen (Dateien mit gleichen Pfaden in die neue Zone kopieren), sind sie in ToTeam danach nicht mehr erreichbar.
- **Storage Zone bei Bunny löschen:** Dann sind alle Fotos weg, auch in ToTeam. Das lässt sich nicht rückgängig machen.
- **Fotos in ToTeam löschen:** löscht die Dateien automatisch auch bei Bunny.
