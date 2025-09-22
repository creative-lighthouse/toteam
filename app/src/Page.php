<?php

namespace {

    use DNADesign\Elemental\Models\ElementalArea;
    use DNADesign\Elemental\Extensions\ElementalPageExtension;
    use SilverStripe\CMS\Model\SiteTree;
    use SilverStripe\Forms\DropdownField;

    /**
     * Class \Page
     *
     * @property string $MenuPosition
     * @property int $ElementalAreaID
     * @method ElementalArea ElementalArea()
     * @mixin ElementalPageExtension
     */
    class Page extends SiteTree
    {
        private static $table_name = 'Page';

        private static $db = [
            "MenuPosition" => "Enum('main,footer', 'main')",
        ];

        private static $has_one = [];

        #[Override]
        public function getCMSFields()
        {
            $fields = parent::getCMSFields();
            $fields->addFieldToTab("Root.Main", new DropdownField("MenuPosition", "Menü", [
                "main" => "Hauptmenü",
                "footer" => "Footer",
            ]), "Content");
            return $fields;
        }
    }
}
