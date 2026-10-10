<?php

namespace App\Teams;

use App\Teams\OrganizationMembership;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\Tab;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class \App\Teams\Organization
 *
 * @property ?string $Title
 * @property ?string $Description
 * @property ?string $JoinMode
 * @property ?string $Username
 * @property bool $EnableAnnouncements
 * @property bool $EnableCalendar
 * @property bool $EnableFood
 * @property bool $EnableLinks
 * @property bool $EnableMap
 * @property bool $EnableTasks
 * @property ?string $LogoColor
 * @property int $LogoID
 * @property int $CoverImageID
 * @method \SilverStripe\Assets\Image Logo()
 * @method \SilverStripe\Assets\Image CoverImage()
 * @method \SilverStripe\ORM\DataList|\App\Teams\OrganizationMembership[] Memberships()
 * @method \SilverStripe\ORM\DataList|\App\Teams\OrgRole[] OrgRoles()
 * @method \SilverStripe\ORM\DataList|\App\Teams\OrgEvent[] Events()
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
class Organization extends DataObject implements PermissionProvider
{
    /**
     * Maps the frontend Totem key to the DB field that enables it for this organization.
     */
    private static $totem_fields = [
        'announcements' => 'EnableAnnouncements',
        'calendar'      => 'EnableCalendar',
        'food'          => 'EnableFood',
        'links'         => 'EnableLinks',
        'map'           => 'EnableMap',
        'tasks'         => 'EnableTasks',
        'skript'        => 'EnableSkript',
        'marketing'     => 'EnableMarketing',
        'inventory'     => 'EnableInventory',
    ];

    private static $db = [
        "Title"       => "Varchar(255)",
        "Description" => "Text",
        "JoinMode"    => "Enum('open,application,invite_only,hidden','invite_only')",
        "Username"    => "Varchar(100)",
        // Akzentfarbe aus dem Logo (#rrggbb), "none" = nicht ermittelbar; null = noch nicht berechnet
        "LogoColor"   => "Varchar(7)",

        "EnableAnnouncements" => "Boolean(1)",
        "EnableCalendar"      => "Boolean(1)",
        "EnableFood"          => "Boolean(1)",
        "EnableLinks"         => "Boolean(1)",
        "EnableMap"           => "Boolean(1)",
        "EnableTasks"         => "Boolean(1)",
        "EnableSkript"        => "Boolean(1)",
        "EnableMarketing"     => "Boolean(1)",
        "EnableInventory"     => "Boolean(1)",
    ];

    private static $defaults = [
        "EnableAnnouncements" => true,
        "EnableCalendar"      => true,
        "EnableFood"          => true,
        "EnableLinks"         => true,
        "EnableMap"           => true,
        "EnableTasks"         => true,
        "EnableSkript"        => true,
        "EnableMarketing"     => true,
        "EnableInventory"     => true,
    ];

    private static $has_one = [
        "Logo"       => Image::class,
        "CoverImage" => Image::class,
    ];

    private static $owns = [
        "Logo",
        "CoverImage",
    ];

    private static $has_many = [
        "Memberships" => OrganizationMembership::class,
        "OrgRoles"    => OrgRole::class,
        "Events"      => OrgEvent::class . '.Organization',
    ];

    private static $summary_fields = [
        "Title"             => "Titel",
        "Username"          => "Benutzername",
        "Description"       => "Beschreibung",
        "JoinMode"          => "Beitrittsmodus",
        "Memberships.Count" => "Anzahl Mitglieder",
    ];

    private static $field_labels = [
        "Title"       => "Titel",
        "Username"    => "Benutzername",
        "Description" => "Beschreibung",
        "JoinMode"    => "Beitrittsmodus",
        "CoverImage"  => "Coverbild",
        "Memberships" => "Mitgliedschaften",

        "EnableAnnouncements" => "Ankündigungen",
        "EnableCalendar"      => "Kalender",
        "EnableFood"          => "Essensplanung",
        "EnableLinks"         => "Links & Downloads",
        "EnableMap"           => "Lagepläne",
        "EnableTasks"         => "Aufgaben",
        "EnableSkript"        => "Skript",
        "EnableMarketing"     => "Marketing",
        "EnableInventory"     => "Inventar",
    ];

    private static $table_name = 'Organization';
    private static $singular_name = "Organisation";
    private static $plural_name = "Organisationen";

    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if ($this->Username) {
            if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $this->Username)) {
                $result->addFieldError('Username', 'Nur Kleinbuchstaben, Zahlen, Punkte, Bindestriche und Unterstriche erlaubt. Muss mit Buchstabe oder Zahl beginnen.');
            } elseif (Organization::get()->filter('Username', $this->Username)->exclude('ID', $this->ID ?: 0)->exists()) {
                $result->addFieldError('Username', 'Dieser Benutzername ist bereits vergeben.');
            }
        }

        return $result;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab('Root', Tab::create('Totems', 'Totems'));
        foreach (self::config()->get('totem_fields') as $fieldName) {
            $fields->addFieldToTab(
                'Root.Totems',
                CheckboxField::create($fieldName, $this->fieldLabel($fieldName))
            );
        }

        return $fields;
    }

    /**
     * Die Standardrolle, die neuen bzw. neu angenommenen Mitgliedern automatisch
     * zugewiesen wird. Fällt auf null zurück, falls eine Organisation ihre "Mitglied"-Rolle
     * umbenannt/gelöscht hat — dann muss ein Admin die Person manuell einer Rolle zuordnen.
     */
    public function getDefaultRole(): ?OrgRole
    {
        return OrgRole::get()->filter([
            'OrganizationID' => $this->ID,
            'Title'          => 'Mitglied',
        ])->first();
    }

    /**
     * Titel aller Rollen dieser Organisation, die eine bestimmte Berechtigung
     * gewähren (direkt oder über den ORG_ADMIN-Wildcard). Für Hinweistexte im
     * Frontend, z.B. "Nur folgende Rollen dürfen Termine erstellen: ...".
     * @return string[]
     */
    public function rolesWithPermission(string $code): array
    {
        $titles = [];
        foreach (OrgRole::get()->filter('OrganizationID', $this->ID) as $role) {
            if ($role->hasPermission($code)) {
                $titles[] = $role->Title;
            }
        }
        return $titles;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Neues Logo → Farbe beim nächsten getLogoColor() neu berechnen
        if ($this->isChanged('LogoID')) {
            $this->LogoColor = null;
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if (!OrgRole::get()->filter('OrganizationID', $this->ID)->exists()) {
            $this->createDefaultRoles();
        }
    }

    /**
     * Legt die 3 Standardrollen an, sobald eine Organisation zum ersten Mal
     * gespeichert wird (bzw. noch keine eigenen Rollen hat). Sicherer Standard für
     * neue Organisationen: nur "Administrator" darf sofort alles, "Moderator" und
     * "Mitglied" bekommen keine Geld-Eingabe-Rechte, bis ein Admin sie bewusst vergibt.
     */
    private function createDefaultRoles(): void
    {
        $admin = OrgRole::create();
        $admin->Title = 'Administrator';
        $admin->OrganizationID = $this->ID;
        $admin->SortOrder = 0;
        $admin->setPermissionCodes([OrgPermissions::ORG_ADMIN]);
        $admin->write();

        $moderator = OrgRole::create();
        $moderator->Title = 'Moderator';
        $moderator->OrganizationID = $this->ID;
        $moderator->SortOrder = 1;
        $moderator->setPermissionCodes([
            OrgPermissions::ORG_MANAGE_MEMBERS,
            OrgPermissions::TASKS_CREATE,
            OrgPermissions::TASKS_EDIT,
            OrgPermissions::TASKS_DELETE,
            OrgPermissions::MONEY_BUDGETS_MANAGE,
            OrgPermissions::MONEY_APPROVE_ENTRIES,
            OrgPermissions::CALENDAR_MANAGE,
            OrgPermissions::CALENDAR_DELETE,
            OrgPermissions::FOOD_MANAGE_MEALS,
            OrgPermissions::LINKS_MANAGE,
            OrgPermissions::MAPS_MANAGE_MAPS,
            OrgPermissions::MAPS_MANAGE_LAYERS,
            OrgPermissions::ROOMS_CREATE,
            OrgPermissions::ROOMS_EDIT,
            OrgPermissions::ROOMS_VIEW,
            OrgPermissions::ROOMS_DELETE,
            OrgPermissions::ANNOUNCEMENTS_CREATE,
            OrgPermissions::INVENTORY_CREATE,
            OrgPermissions::INVENTORY_EDIT,
            OrgPermissions::INVENTORY_DELETE,
            OrgPermissions::INVENTORY_MANAGE_TYPES,
            OrgPermissions::INVENTORY_REQUEST_RENTAL,
            OrgPermissions::INVENTORY_APPROVE_RENTALS,
            OrgPermissions::STORAGE_CREATE,
            OrgPermissions::STORAGE_EDIT,
            OrgPermissions::STORAGE_DELETE,
        ]);
        $moderator->write();

        $member = OrgRole::create();
        $member->Title = 'Mitglied';
        $member->OrganizationID = $this->ID;
        $member->SortOrder = 2;
        $member->setPermissionCodes([
            OrgPermissions::TASKS_CREATE,
            OrgPermissions::TASKS_EDIT,
            OrgPermissions::TASKS_DELETE,
            OrgPermissions::INVENTORY_REQUEST_RENTAL,
        ]);
        $member->write();
    }

    /**
     * Zählt aktive Mitgliedschaften, die noch mindestens eine Rolle mit ORG_ADMIN halten.
     * Wird vor jeder Änderung geprüft, die die Admin-Mindestanforderung verletzen könnte
     * (Rolle löschen, ORG_ADMIN entziehen, Rollen einer Person entfernen, Mitgliedschaft löschen).
     *
     * @param int[] $excludeRoleIDs Rollen, die bei der Zählung so behandelt werden, als wären sie kein Admin (mehr)
     * @param int[] $excludeMembershipIDs Mitgliedschaften, die bei der Zählung ausgeschlossen werden
     */
    public function adminHolderCount(array $excludeRoleIDs = [], array $excludeMembershipIDs = []): int
    {
        $adminRoleIDs = [];
        foreach (OrgRole::get()->filter('OrganizationID', $this->ID) as $role) {
            if (in_array($role->ID, $excludeRoleIDs, true)) {
                continue;
            }
            if ($role->hasPermission(OrgPermissions::ORG_ADMIN)) {
                $adminRoleIDs[] = $role->ID;
            }
        }

        if (empty($adminRoleIDs)) {
            return 0;
        }

        $memberships = OrganizationMembership::get()->filter([
            'OrganizationID' => $this->ID,
            'Role'           => 'member',
        ]);
        if (!empty($excludeMembershipIDs)) {
            $memberships = $memberships->exclude('ID', $excludeMembershipIDs);
        }

        $count = 0;
        foreach ($memberships as $membership) {
            if ($membership->Roles()->filter('ID', $adminRoleIDs)->exists()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Einzige Quelle für die Logo-URL einer Organisation — liefert das Logo in
     * einem quadratischen Zuschnitt der gewünschten Größe, oder null, falls (noch)
     * kein Logo hochgeladen wurde. Es gibt (anders als beim Mitglieder-Avatar mit
     * Gravatar-Fallback) keine automatische Ersatzgrafik; das Frontend zeigt in
     * diesem Fall stattdessen die Initialen der Organisation an.
     */
    public function RenderLogo(int $size = 180): ?string
    {
        if ($this->LogoID && $this->Logo()->exists()) {
            return $this->Logo()->Fill($size, $size)->getURL();
        }
        return null;
    }

    /**
     * Akzentfarbe der Organisation (#rrggbb), z.B. für die Markierung links an
     * Termin-Karten. Wird beim ersten Abruf aus dem Logo berechnet und gespeichert;
     * null, wenn es kein Logo gibt oder es zu farblos/hell ist (Frontend nimmt dann
     * die Primärfarbe).
     */
    public function getLogoColor(): ?string
    {
        $color = $this->getField('LogoColor');
        if ($color === null && $this->LogoID && $this->isInDB()) {
            $color = $this->computeLogoColor() ?? 'none';
            $this->LogoColor = $color;
            $this->write();
        }
        return $color && $color !== 'none' ? $color : null;
    }

    /**
     * Dominante Farbe des Logos: Transparente, sehr dunkle, sehr helle und graue
     * Pixel zählen nicht (ein reiner Durchschnitt wäre bei den meist schwarz/weißen
     * Logos fast immer Schwarz oder Grau). Die übrigen werden nach Farbton in 12
     * Gruppen sortiert, die größte gewinnt. Die Helligkeit wird anschließend in einen
     * mittleren Bereich gezogen, damit die Farbe auf hellen und dunklen Karten sichtbar ist.
     */
    private function computeLogoColor(): ?string
    {
        $logo = $this->Logo();
        $data = $logo->exists() ? $logo->getString() : null;
        $source = $data ? @imagecreatefromstring($data) : false;
        if (!$source) {
            return null; // z.B. SVG
        }

        $size = 64;
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));

        $buckets = [];
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $c = imagecolorat($image, $x, $y);
                if ((($c >> 24) & 0x7F) > 64) {
                    continue; // überwiegend transparent
                }
                $rgb = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
                [$h, $sat, $light] = self::rgbToHsl(...$rgb);
                if ($sat < 0.25 || $light < 0.12 || $light > 0.92) {
                    continue; // grau, fast schwarz oder fast weiß
                }
                $bucket = (int) floor($h * 12) % 12;
                $w = $sat;
                $buckets[$bucket] ??= ['weight' => 0, 'sum' => [0, 0, 0]];
                $buckets[$bucket]['weight'] += $w;
                foreach ($rgb as $i => $v) {
                    $buckets[$bucket]['sum'][$i] += $v * $w;
                }
            }
        }
        if (!$buckets) {
            return null; // Logo ohne nennenswerte Farbe
        }

        usort($buckets, fn ($a, $b) => $b['weight'] <=> $a['weight']);
        $top = $buckets[0];
        $rgb = array_map(fn ($v) => $v / $top['weight'], $top['sum']);
        [$h, $sat, $light] = self::rgbToHsl(...$rgb);
        [$r, $g, $b] = self::hslToRgb($h, $sat, max(0.35, min(0.6, $light)));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** @return float[] [Farbton, Sättigung, Helligkeit], jeweils 0–1 */
    private static function rgbToHsl(float $r, float $g, float $b): array
    {
        [$r, $g, $b] = [$r / 255, $g / 255, $b / 255];
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        if (!$d) {
            return [0, 0, $l];
        }
        $s = $d / (1 - abs(2 * $l - 1));
        $h = match ($max) {
            $r => fmod(($g - $b) / $d + 6, 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        return [$h / 6, $s, $l];
    }

    /** @return int[] [r, g, b], jeweils 0–255 */
    private static function hslToRgb(float $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h * 6, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match ((int) floor($h * 6) % 6) {
            0 => [$c, $x, 0],
            1 => [$x, $c, 0],
            2 => [0, $c, $x],
            3 => [0, $x, $c],
            4 => [$x, 0, $c],
            default => [$c, 0, $x],
        };
        return array_map(fn ($v) => (int) round(($v + $m) * 255), [$r, $g, $b]);
    }

    /**
     * Returns which Totems (feature modules) are enabled for this organization,
     * keyed by the frontend Totem key (e.g. 'calendar', 'food').
     *
     * @return array<string, bool>
     */
    public function getEnabledTotems(): array
    {
        $enabled = [];
        foreach (self::config()->get('totem_fields') as $totemKey => $fieldName) {
            $enabled[$totemKey] = (bool) $this->$fieldName;
        }
        return $enabled;
    }

    public function providePermissions()
    {
        return [
            'CREATE_ORGANIZATIONS' => [
                'name' => 'Organisationen erstellen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Erstellen, von Organisationen'
            ],
            'EDIT_ORGANIZATIONS' => [
                'name' => 'Organisationen bearbeiten',
                'category' => 'Teams',
                'help' => 'Erlaubt das Bearbeiten, von Organisationen'
            ],
            'VIEW_ORGANIZATIONS' => [
                'name' => 'Organisationen ansehen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Ansehen, von Organisationen'
            ],
            'DELETE_ORGANIZATIONS' => [
                'name' => 'Organisationen löschen',
                'category' => 'Teams',
                'help' => 'Erlaubt das Löschen, von Organisationen'
            ],
        ];
    }

    public function canCreate($member = null, $context = [])
    {
        //Check user for CREATE_ORGANIZATIONS permission
        return Permission::check('CREATE_ORGANIZATIONS', 'any', $member);
    }

    public function canEdit($member = null, $context = [])
    {
        //Check user for EDIT_ORGANIZATIONS permission
        return Permission::check('EDIT_ORGANIZATIONS', 'any', $member);
    }

    public function canDelete($member = null, $context = [])
    {
        //Check user for DELETE_ORGANIZATIONS permission
        return Permission::check('DELETE_ORGANIZATIONS', 'any', $member);
    }

    public function canView($member = null, $context = [])
    {
        //Check user for VIEW_ORGANIZATIONS permission
        return Permission::check('VIEW_ORGANIZATIONS', 'any', $member);
    }
}
