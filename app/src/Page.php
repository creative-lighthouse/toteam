<?php
namespace {

    use SilverStripe\CMS\Model\SiteTree;

    /**
 * Class \Page
 *
 * @mixin \SilverStripe\Assets\AssetControlExtension
 * @mixin \SilverStripe\Assets\Shortcodes\FileLinkTracking
 * @mixin \SilverStripe\CMS\Model\SiteTreeLinkTracking
 * @mixin \SilverStripe\Versioned\RecursivePublishable
 * @mixin \SilverStripe\Versioned\VersionedStateExtension
 */
    class Page extends SiteTree
    {
    }
}
