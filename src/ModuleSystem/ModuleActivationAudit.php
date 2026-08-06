<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Happenv\LaravelTrueModular\Application;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

use function Safe\file_get_contents;
use function Safe\json_decode;

/**
 * Validates module activation state.
 *
 * Reports problems instead of throwing, so the caller decides the consequence:
 * the console command exits non-zero, the deploy entrypoint aborts the container,
 * a test asserts an empty list.
 */
final class ModuleActivationAudit
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleActivation $activation,
        private readonly string $basePath,
    ) {}

    /**
     * @return list<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function problems(): array
    {
        return [
            ...$this->orphanedModules(),
            ...$this->unknownOrProtectedDisables(),
            ...$this->brokenDependencies(),
            ...$this->contestedOwnedPackages(),
        ];
    }

    /**
     * A module directory nobody requires is the failure this whole feature exists
     * to make legible: its classes stop autoloading while its files stay on disk,
     * so the first symptom is a bare "class not found" from an unrelated place.
     *
     * @return list<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function orphanedModules(): array
    {
        $modules = $this->registry->getAllModules();
        $declared = $this->requirements($this->rootComposer());

        foreach ($modules as $module) {
            $declared = [...$declared, ...$this->requirements($module['composer'])];
        }

        $declared = array_flip($declared);
        $problems = [];

        foreach (array_keys($modules) as $name) {
            if (isset($declared[$name])) {
                continue;
            }

            $problems[] = sprintf(
                'Module [%s] sits in %s/ but no composer.json requires it, so its classes will not autoload. '
                    .'Add it back to the root require, or delete the directory. To switch a module OFF, use %s or %s.',
                $name,
                Application::getModulesDirectory(),
                ModuleActivation::ENV_KEY,
                ModuleActivation::FILE_NAME,
            );
        }

        return $problems;
    }

    /**
     * @return list<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function unknownOrProtectedDisables(): array
    {
        $modules = $this->registry->getAllModules();
        $channel = $this->activation->channel();
        $problems = [];

        foreach ($this->activation->disabled() as $name) {
            if (! isset($modules[$name])) {
                $problems[] = sprintf(
                    'The disabled list names [%s], which is not a known module. Fix the name in %s.',
                    $name,
                    $channel === 'env' ? ModuleActivation::ENV_KEY : ModuleActivation::FILE_NAME,
                );

                continue;
            }

            if (! $this->activation->isDisablable($name)) {
                $problems[] = sprintf(
                    'Module [%s] declares `disablable: false` and must not be switched off.',
                    $name,
                );
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function brokenDependencies(): array
    {
        $disabled = $this->activation->disabled();

        if ($disabled === []) {
            return [];
        }

        $disabledNames = array_flip($disabled);
        $problems = [];

        foreach (array_keys($this->registry->getAllModules()) as $name) {
            if (isset($disabledNames[$name])) {
                continue;
            }

            foreach ($this->registry->getDependencies($name) as $dependency) {
                if (! isset($disabledNames[$dependency])) {
                    continue;
                }

                $problems[] = sprintf(
                    'Module [%s] is enabled but depends on disabled [%s]. Add [%s] to the disabled list too, '
                        .'or drop [%s] from it — disabling never cascades on its own.',
                    $name,
                    $dependency,
                    $name,
                    $dependency,
                );
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function contestedOwnedPackages(): array
    {
        $disabled = $this->activation->disabled();

        if ($disabled === []) {
            return [];
        }

        $disabledNames = array_flip($disabled);
        $modules = $this->registry->getAllModules();
        $problems = [];

        foreach ($disabled as $name) {
            foreach ($this->activation->owns($name) as $package) {
                foreach ($this->claimants($package, $modules, $disabledNames) as $claimant) {
                    $problems[] = sprintf(
                        'Package [%s] is declared as owned by disabled module [%s], but %s requires it too. '
                            .'Remove it from the `owns` list — it is not owned exclusively.',
                        $package,
                        $name,
                        $claimant,
                    );
                }
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, array{composer: array<string, mixed>, name: string, path: string}>  $modules
     * @param  array<string, int>  $disabledNames
     * @return list<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function claimants(string $package, array $modules, array $disabledNames): array
    {
        $claimants = [];

        if (in_array($package, $this->requirements($this->rootComposer()), true)) {
            $claimants[] = 'the root application';
        }

        foreach ($modules as $name => $module) {
            if (isset($disabledNames[$name])) {
                continue;
            }

            if (in_array($package, $this->requirements($module['composer']), true)) {
                $claimants[] = sprintf('enabled module [%s]', $name);
            }
        }

        return $claimants;
    }

    /**
     * @param  array<string, mixed>  $composer
     * @return list<string>
     */
    private function requirements(array $composer): array
    {
        $require = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $requireDev = is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];

        return array_map(strval(...), array_keys($require + $requireDev));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function rootComposer(): array
    {
        $path = $this->basePath.'/composer.json';

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($path), associative: true);

        return is_array($decoded) ? $decoded : [];
    }
}
