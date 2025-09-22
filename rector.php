<?php

declare(strict_types=1);

use Netwerkstatt\SilverstripeRector\Rector\Injector\UseCreateRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Netwerkstatt\SilverstripeRector\Rector\DataObject\EnsureTableNameIsSetRector;
use Rector\Set\ValueObject\LevelSetList;
use Netwerkstatt\SilverstripeRector\Set\SilverstripeSetList;
use Netwerkstatt\SilverstripeRector\Set\SilverstripeLevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app/_config.php',
        __DIR__ . '/app/src',
    ])
    //    ->withPreparedSets(deadCode: true)
    ->withSets([
        //rector lists
        LevelSetList::UP_TO_PHP_83,
        // SetList::CODE_QUALITY,
        // SetList::CODING_STYLE,
        // silverstripe rector
        // SilverstripeSetList::CODE_STYLE,
        SilverstripeLevelSetList::UP_TO_SS_6_0
    ])
    ->withRules([
        // EnsureTableNameIsSetRector::class,
    ])


    // any rules that are included in the selected sets you want to skip
    ->withSkip([
        //        ClassPropertyAssignToConstructorPromotionRector::class,
        //        ReturnNeverTypeRector::class
        // EnsureTableNameIsSetRector::class,
    ]);
