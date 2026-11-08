<?php

namespace App\Maps;

use App\Maps\MapLayer;
use App\Rooms\Room;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;

/**
 * Class \App\Maps\MapPOI
 *
 * @property ?string $Title
 * @property ?string $MarkerText
 * @property ?string $Description
 * @property bool $Active
 * @property ?string $Coordinates
 * @property ?string $Type
 * @property ?string $Shape
 * @property int $ParentID
 * @property int $RoomID
 * @method \App\Maps\MapLayer Parent()
 * @method \App\Rooms\Room Room()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class MapPOI extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "MarkerText" => "Varchar(4)",
        "Description" => "Text",
        "Active" => "Boolean",
        "Coordinates" => "Varchar(100)",
        "Type" => "Enum('marker,room,area', 'marker')",
        // Nur bei Type "area" (Raum als Fläche auf einem Event-Lageplan): die Eckpunkte als
        // JSON-Liste [[lat, lng], …] im Koordinatensystem des Plans; Coordinates ist dann der
        // Mittelpunkt (für die Beschriftung)
        "Shape" => "Text",
    ];

    private static $has_one = [
        "Parent" => MapLayer::class,
        "Room" => Room::class,
    ];

    private static $field_labels = [
        "Title" => "Titel",
        "Description" => "Beschreibung",
        "Active" => "Aktiv",
        "Coordinates" => "Koordinaten",
        "Type" => "Markertyp",
        "Room" => "Raum",
    ];

    private static $defaults = [
        "Active" => true,
    ];

    private static $summary_fields = [
        "Title" => "Titel",
        "Active.Nice" => "Aktiv",
    ];

    private static $table_name = 'MapPOI';
    private static $singular_name = "POI";
    private static $plural_name = "POIs";

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ParentID');
        return $fields;
    }

    /** Höchstens so viele Eckpunkte pro Fläche */
    public const MAX_SHAPE_POINTS = 200;

    /** @return array<array{0: float, 1: float}> Eckpunkte einer Fläche (leer bei anderen Typen) */
    public function getShapePoints(): array
    {
        $points = json_decode((string) $this->Shape, true);
        if (!is_array($points)) {
            return [];
        }
        return array_values(array_filter(array_map(
            fn ($p) => is_array($p) && count($p) === 2 && is_numeric($p[0]) && is_numeric($p[1]) ? [(float) $p[0], (float) $p[1]] : null,
            $points
        )));
    }

    /**
     * Eckpunkte setzen und den Mittelpunkt (Coordinates) daraus berechnen.
     * @param array<array{0: float, 1: float}> $points
     */
    public function setShapePoints(array $points): void
    {
        $points = array_map(fn (array $p) => [round((float) $p[0], 8), round((float) $p[1], 8)], array_values($points));
        $this->Shape = json_encode($points);
        $center = self::centroid($points);
        $this->Coordinates = $center ? $center[0] . ',' . $center[1] : null;
    }

    /**
     * Schwerpunkt eines Vielecks (für die Beschriftung). Bei entarteten Flächen der
     * Mittelwert der Punkte.
     * @param array<array{0: float, 1: float}> $points
     * @return array{0: float, 1: float}|null
     */
    public static function centroid(array $points): ?array
    {
        $n = count($points);
        if (!$n) {
            return null;
        }
        $area = 0.0;
        $cx = 0.0;
        $cy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            [$y1, $x1] = $points[$i];
            [$y2, $x2] = $points[($i + 1) % $n];
            $cross = $x1 * $y2 - $x2 * $y1;
            $area += $cross;
            $cx += ($x1 + $x2) * $cross;
            $cy += ($y1 + $y2) * $cross;
        }
        if (abs($area) < 1e-14) {
            return [
                round(array_sum(array_column($points, 0)) / $n, 8),
                round(array_sum(array_column($points, 1)) / $n, 8),
            ];
        }
        return [round($cy / (3 * $area), 8), round($cx / (3 * $area), 8)];
    }

    /** Gelöschter Raum: Fotos verlieren die Markierung (sonst zeigten sie einen Raum, den es nicht mehr gibt) */
    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        if ($this->Type === 'area' && $this->ID) {
            DB::prepared_query('DELETE FROM "Photo_MapAreas" WHERE "MapPOIID" = ?', [$this->ID]);
        }
    }

    public function getDetailInfo()
    {
        return $this->Description;
    }

    public function getMarkerText()
    {
        $markerText = $this->getField('MarkerText');
        if(!empty($markerText)) {
            return $markerText;
        }

        if ($this->Type === 'room') {
            $room = $this->Room();
            if ($room && $room->exists() && $room->Title) {
                return mb_strtoupper(mb_substr($room->Title, 0, 1));
            }
        }

        return "42";
    }

    public function getMarkerColor()
    {
        return $this->Parent()->LayerColor;
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CREATE_MAPLAYERS');
    }

    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'EDIT_MAPLAYERS');
    }

    public function canView($member = null, $context = [])
    {
        return Permission::checkMember($member, 'VIEW_MAPS');
    }

    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'DELETE_MAPLAYERS');
    }
}
