<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;

return Architecture::define()
    // apply on src directory for now for ease gradual changes
    ->layer('Source', 'src')
    ->withPresets(Preset::PSR4(), Preset::CODEQUALITY())
    ->layer('Kernel', 'src/KernelServiceProvider.php')
    ->layer('Application', [
        'src/Application.php',
        'src/ModularApplication.php',
        'src/ServiceProviderSorter.php',
        'src/helpers.php',
    ])
    ->layer('Config', 'src/Config')
    ->layer('ModelExtension', 'src/ModelExtension')
    ->layer('Setup', 'src/Setup')
    ->layer('Generators', 'src/Generators')
    ->layer('Commands', 'src/Commands')
    ->layer('ModuleSystemException', 'src/ModuleSystem/Exceptions')
    ->layer('ModuleGraph', 'src/ModuleSystem/Graph')
    ->layer('ModuleSystem', 'src/ModuleSystem', [
        'src/ModuleSystem/Exceptions',
        'src/ModuleSystem/Graph',
    ])
    ->layer('ModuleProviderException', 'src/ModuleProvider/Exceptions')
    ->layer('ModuleDefinition', [
        'src/ModuleProvider/Module.php',
        'src/ModuleProvider/Concerns/Package',
    ])
    ->layer('ModuleProvider', 'src/ModuleProvider', [
        'src/ModuleProvider/Exceptions',
        'src/ModuleProvider/Module.php',
        'src/ModuleProvider/Concerns/Package',
    ])
    ->layer('ArchitectureReport', 'src/Architecture/Report')
    ->layer('ArchitectureGraph', 'src/Architecture/Graph')
    ->layer('ArchitectureModule', 'src/Architecture/Module')
    ->layer('ArchitectureSource', 'src/Architecture/Source')
    ->layer('ArchitectureIndex', 'src/Architecture/Index')
    ->layer('ArchitectureAnalyzer', 'src/Architecture/Analyzer')
    ->layer('ArchitectureRenderer', 'src/Architecture/Renderer')
    ->ruleset([
        'Config' => [],
        'ModelExtension' => [],
        'ModuleSystemException' => [],
        'ModuleGraph' => [],
        'ModuleProviderException' => [],
        'ArchitectureReport' => [],
        'Application' => ['ModuleSystem', 'ModuleSystemException', 'Setup'],
        'Setup' => ['Application'],
        'ModuleDefinition' => ['ModuleProviderException'],
        'ModuleSystem' => ['+Application', '+ModuleDefinition', 'ModuleGraph'],
        'ModuleProvider' => ['+ModuleSystem', 'Config', 'ModelExtension'],
        'Generators' => ['+Application'],
        'ArchitectureGraph' => ['ModuleGraph'],
        'ArchitectureModule' => ['+ModuleSystem'],
        'ArchitectureSource' => ['+ArchitectureModule'],
        'ArchitectureIndex' => ['+ArchitectureGraph', '+ArchitectureSource'],
        'ArchitectureAnalyzer' => ['+ArchitectureIndex', 'ArchitectureReport'],
        'ArchitectureRenderer' => ['ArchitectureReport'],
        'Commands' => ['+ArchitectureAnalyzer', '+ArchitectureRenderer', '+Generators'],
        'Kernel' => ['+Commands'],
    ]);
