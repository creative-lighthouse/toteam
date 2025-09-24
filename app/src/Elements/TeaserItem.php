<?php

namespace App\Elements;

use Override;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\LinkField\Models\Link;
use SilverStripe\LinkField\Form\LinkField;

/**
 * Class \App\Elements\TeaserItem
 *
 * @property string $Title
 * @property string $Content
 * @property int $ImageID
 * @property int $ButtonID
 * @property int $ParentID
 * @method Image Image()
 * @method Link Button()
 * @method TeaserElement Parent()
 */
class TeaserItem extends DataObject
{
    private static $db = [
        "Title" => "Varchar(255)",
        "Content" => "HTMLText",
    ];

    private static $has_one = [
        'Image' => Image::class,
        'Button' => Link::class,
        'Parent' => TeaserElement::class,
    ];

    private static $owns = [
        'Image',
        'Button',
    ];

    private static $field_labels = [
        'Title' => 'Titel',
        "Content" => "Text",
        'Image' => 'Bild',
        'Button' => 'Button',
    ];

    private static $summary_fields = [
        'Image.CMSThumbnail' => 'Bild',
        'Title' => 'Titel',
    ];

    private static $table_name = 'TeaserItem';
    private static $singular_name = "Teaser Eintrag";
    private static $plural_name = "Teaser Einträge";

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ParentID');
        $fields->removeByName('ButtonID');
        $fields->addFieldToTab('Root.Main', LinkField::create('Button'));
        return $fields;
    }
}
