<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider\GuardsModulePaths;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

/**
 * Builds an object that uses the GuardsModulePaths trait, wiring a recording
 * logger behind the Log facade so warnings can be asserted without booting a
 * full application.
 *
 * @return array{0: object, 1: object} the subject under test and the recording logger
 */
function makeGuard(string $moduleName = 'myapp/example'): array
{
    $logger = new class
    {
        /** @var array<int, string> */
        public array $recorded = [];

        public function warning(string $message, array $context = []): void
        {
            $this->recorded[] = $message;
        }

        public function __call(string $name, array $arguments): void {}
    };

    $container = new Container;
    $container->instance('log', $logger);
    Facade::setFacadeApplication($container);

    $module = new class($moduleName)
    {
        public function __construct(public string $name) {}
    };

    $sut = new class($module)
    {
        use GuardsModulePaths;

        public function __construct(public object $module) {}

        public function check(string $path, string $capability): bool
        {
            return $this->moduleDirectoryMissing($path, $capability);
        }
    };

    return [$sut, $logger];
}

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

describe('GuardsModulePaths::moduleDirectoryMissing', function (): void {
    it('returns false and logs nothing when the directory exists', function (): void {
        [$sut, $logger] = makeGuard();

        expect($sut->check(sys_get_temp_dir(), 'views'))->toBeFalse();
        expect($logger->recorded)->toBeEmpty();
    });

    it('returns true and logs a warning when the directory is missing', function (): void {
        [$sut, $logger] = makeGuard('filamerce/deliverio');

        expect($sut->check('/no/such/dir/at/all', 'views'))->toBeTrue();
        expect($logger->recorded)->toHaveCount(1);
        expect($logger->recorded[0])
            ->toContain('filamerce/deliverio')
            ->toContain('/no/such/dir/at/all')
            ->toContain('"views"');
    });
});
