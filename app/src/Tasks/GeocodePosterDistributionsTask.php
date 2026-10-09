<?php

namespace App\Tasks;

use App\Marketing\PosterDistribution;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Ergänzt Koordinaten und Adressteile (Straße, PLZ, Ort, Stadtteil) von
 * Plakat-Einträgen, denen sie fehlen — auch von GPS-Einträgen. Neue und
 * geänderte Einträge werden beim Speichern automatisch geocodiert
 * (PosterDistribution::geocode()).
 */
class GeocodePosterDistributionsTask extends BuildTask
{
    protected string $title = 'Plakat-Orte geocodieren';
    protected static string $description = 'Ermittelt über Nominatim Koordinaten und Adresse aller Plakat-Einträge, denen sie fehlen (Koordinaten nur bei eindeutigem Ort, max. 1 Anfrage/s).';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $found = 0;
        $missing = 0;
        // Ohne Ort fehlt die Adresse; Einträge ganz ohne Ort-Text und GPS gibt es nicht
        $entries = PosterDistribution::get()->filter('City', ['', null]);
        foreach ($entries as $entry) {
            // Auch früher nicht gefundene Orte noch einmal versuchen
            $entry->GeocodedLocation = null;
            $entry->write();
            $label = $entry->Location ?: "GPS {$entry->Latitude},{$entry->Longitude}";
            if ($entry->City) {
                $found++;
                $output->writeln("Gefunden: {$label} (#{$entry->ID}) → " . trim("{$entry->Street}, {$entry->PostalCode} {$entry->City}", ', '));
            } else {
                $missing++;
                $output->writeln("Nicht eindeutig: {$label} (#{$entry->ID})");
            }
        }
        $output->writeln("Fertig. Gefunden: {$found}, nicht eindeutig: {$missing}.");
        return Command::SUCCESS;
    }
}
