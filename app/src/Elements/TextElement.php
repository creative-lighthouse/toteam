<?php

namespace App\Elements;

use Override;
use DNADesign\Elemental\Models\BaseElement;

/**
 * Class \App\Elements\TextImageElement
 *
 * @property string $Text
 */
class TextElement extends BaseElement
{

    private static $db = [
        "Text" => "HTMLText",
    ];

    private static $field_labels = [
        "Text" => "Text"
    ];

    private static $table_name = 'TextElement';
    private static $icon = 'font-icon-block-content';

    #[Override]
    public function getType()
    {
        return "Text";
    }

    #[Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
