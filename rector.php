<?php

declare( strict_types=1 );

use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

return RectorConfig::configure()
    ->withPhpSets( php82: true )
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        strictBooleans: true,
        rectorPreset: true
    )
    ->withPaths( [
        __DIR__.'/object-cache.php',
    ] )
    ->withPhpPolyfill()
    ->withFileExtensions( [ 'php' ] )
    ->withSkip( [
        __DIR__.'/vendor',
        RemoveExtraParametersRector::class,
        EncapsedStringsToSprintfRector::class,
        DisallowedEmptyRuleFixerRector::class,
        SimplifyEmptyCheckOnEmptyArrayRector::class,
    ] )
    ->withParallel()
    ->withPHPStanConfigs( [
        __DIR__.'/phpstan-rector.neon',
    ] )
    ->withImportNames( importShortClasses: false, removeUnusedImports: true )
;
