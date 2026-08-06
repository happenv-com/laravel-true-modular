<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModuleSystem\ModuleActivation;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleAwarePackageManifest;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleRegistry;
use Illuminate\Filesystem\Filesystem;

/**
 * @param  array<string, mixed>  $manifest
 */
function manifestFixture(array $manifest): string
{
    $path = sys_get_temp_dir().'/true-modular-manifest-'.uniqid().'.php';
    file_put_contents($path, '<?php return '.var_export($manifest, true).';');

    return $path;
}

/**
 * @param  array<string, mixed>  $manifest
 */
function packageManifest(array $manifest, string $envValue): ModuleAwarePackageManifest
{
    $_ENV[ModuleActivation::ENV_KEY] = $envValue;

    return new ModuleAwarePackageManifest(
        new Filesystem,
        dirname(appModulesFixture()),
        manifestFixture($manifest),
        static fn (): ModuleActivation => new ModuleActivation(
            new ModuleRegistry(appModulesFixture()),
            dirname(appModulesFixture()),
            'myapp',
        ),
    );
}

afterEach(function (): void {
    unset($_ENV[ModuleActivation::ENV_KEY], $_SERVER[ModuleActivation::ENV_KEY]);

    foreach (glob(sys_get_temp_dir().'/true-modular-manifest-*.php') ?: [] as $leftover) {
        unlink($leftover);
    }
});

it('drops the providers and the facade aliases of a disabled module together', function (): void {
    $manifest = packageManifest([
        'myapp/amazon' => [
            'providers' => ['Myapp\Amazon\Provider'],
            'aliases' => ['Amazon' => 'Myapp\Amazon\Facade'],
        ],
        'myapp/pim' => ['providers' => ['Myapp\Pim\Provider']],
    ], 'amazon');

    expect($manifest->providers())->toBe(['Myapp\Pim\Provider'])
        ->and($manifest->aliases())->toBe([]);
});

it('also drops the vendor packages a disabled module declares it owns', function (): void {
    $manifest = packageManifest([
        'myapp/amazon' => ['providers' => ['Myapp\Amazon\Provider']],
        'myapp/amazon-sdk' => ['providers' => ['Myapp\AmazonSdk\Provider']],
        'myapp/pim' => ['providers' => ['Myapp\Pim\Provider']],
    ], 'amazon');

    expect($manifest->providers())->toBe(['Myapp\Pim\Provider']);
});

it('returns the manifest untouched when nothing is disabled', function (): void {
    $manifest = packageManifest([
        'myapp/amazon' => ['providers' => ['Myapp\Amazon\Provider']],
        'myapp/pim' => ['providers' => ['Myapp\Pim\Provider']],
    ], '');

    expect($manifest->providers())->toBe(['Myapp\Amazon\Provider', 'Myapp\Pim\Provider']);
});

it('leaves an unrelated package alone when a module is disabled', function (): void {
    $manifest = packageManifest([
        'myapp/amazon' => ['providers' => ['Myapp\Amazon\Provider']],
        'acme/unrelated' => ['providers' => ['Acme\Unrelated\Provider']],
    ], 'amazon');

    expect($manifest->providers())->toBe(['Acme\Unrelated\Provider']);
});
