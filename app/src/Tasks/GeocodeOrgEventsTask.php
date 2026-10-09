<?php

namespace App\Tasks;

use App\Teams\OrgEvent;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Ergänzt die Koordinaten von Events, die vor Einführung der Karte angelegt wurden.
 * Neue und geänderte Events werden beim Speichern automatisch geocodiert (OrgEvent::geocode()).
 */
class GeocodeOrgEventsTask extends BuildTask
{
    protected string $title = 'Event-Adressen geocodieren';
    protected static string $description = 'Ermittelt über Nominatim die Koordinaten aller Events mit Adresse, die noch keine haben (max. 1 Anfrage/s).';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $found = 0;
        $missing = 0;
        foreach (OrgEvent::get() as $event) {
            if (!$event->geocoderQuery() || $event->hasCoordinates()) {
                continue;
            }
            // Auch früher nicht gefundene Adressen noch einmal versuchen
            $event->GeocodedAddress = null;
            $event->write();
            if ($event->hasCoordinates()) {
                $found++;
                $output->writeln("Gefunden: {$event->Title} (#{$event->ID})");
            } else {
                $missing++;
                $output->writeln("Nicht gefunden: {$event->Title} (#{$event->ID}) — {$event->GeocodedAddress}");
            }
        }
        $output->writeln("Fertig. Gefunden: {$found}, nicht gefunden: {$missing}.");
        return Command::SUCCESS;
    }
}
