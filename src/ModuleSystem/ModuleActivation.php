<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleSystem;

use Happenv\LaravelTrueModular\Application;
use Illuminate\Support\Env;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

use function Safe\file_get_contents;
use function Safe\json_decode;

/**
 * The single reader of module activation state.
 *
 * Two channels, one precedence rule and NO merging: when the environment key is
 * set — even to an empty string — it IS the whole list; otherwise `modules.json`
 * is the whole list; otherwise nothing is disabled. Merging would make the
 * effective state a function of two sources read together, and would remove the
 * ability to switch a module back ON from the environment.
 */
final class ModuleActivation
{
    public const string ENV_KEY = 'MODULES_DISABLED';

    public const string FILE_NAME = 'modules.json';

    /** @var list<string>|null */
    private ?array $disabled = null;

    private ?string $channel = null;

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly string $basePath,
        private readonly string $vendor,
    ) {}

    public static function make(): self
    {
        return new self(
            ModuleRegistry::make(),
            base_path(),
            Application::getModulesVendor(),
        );
    }

    /**
     * Fully qualified names of the modules that must not be activated.
     *
     * @return list<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function disabled(): array
    {
        $this->resolve();

        return $this->disabled ?? [];
    }

    /**
     * Which channel produced the current list: `env`, `file` or `none`.
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function channel(): string
    {
        $this->resolve();

        return $this->channel ?? 'none';
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    public function isDisabled(string $moduleName): bool
    {
        return in_array(ModuleName::qualify($moduleName, $this->vendor), $this->disabled(), true);
    }

    /**
     * Whether the module allows being switched off at all. Declared by the module
     * itself in `extra.true-modular.disablable`; defaults to true.
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function isDisablable(string $moduleName): bool
    {
        return ($this->extra($moduleName)['disablable'] ?? true) !== false;
    }

    /**
     * Vendor packages the module declares itself the sole owner of. They leave
     * discovery together with the module — otherwise a package carrying its own
     * auto-discovered provider keeps booting after the module is switched off.
     *
     * @return list<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function owns(string $moduleName): array
    {
        $owns = $this->extra($moduleName)['owns'] ?? [];

        return is_array($owns)
            ? array_values(array_filter($owns, is_string(...)))
            : [];
    }

    /**
     * Composer package names to drop from the discovery manifest.
     *
     * Returns early when nothing is disabled, so the common case never reads the
     * module registry — which scans every module's composer.json.
     *
     * @return list<string>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function excludedPackages(): array
    {
        $disabled = $this->disabled();

        if ($disabled === []) {
            return [];
        }

        $excluded = $disabled;

        foreach ($disabled as $moduleName) {
            foreach ($this->owns($moduleName) as $package) {
                $excluded[] = $package;
            }
        }

        return array_values(array_unique($excluded));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    private function extra(string $moduleName): array
    {
        $qualified = ModuleName::qualify($moduleName, $this->vendor);
        $extra = $this->registry->getAllModules()[$qualified]['composer']['extra']['true-modular'] ?? [];

        return is_array($extra) ? $extra : [];
    }

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    private function resolve(): void
    {
        if ($this->disabled !== null) {
            return;
        }

        $fromEnv = Env::get(self::ENV_KEY);

        if (is_string($fromEnv)) {
            $this->channel = 'env';
            $this->disabled = $this->qualifyAll(explode(',', $fromEnv));

            return;
        }

        $path = $this->basePath.DIRECTORY_SEPARATOR.self::FILE_NAME;

        if (is_file($path)) {
            $decoded = json_decode(file_get_contents($path), associative: true);
            $listed = is_array($decoded) ? ($decoded['disabled'] ?? []) : [];

            $this->channel = 'file';
            $this->disabled = $this->qualifyAll(is_array($listed) ? $listed : []);

            return;
        }

        $this->channel = 'none';
        $this->disabled = [];
    }

    /**
     * @param  array<int|string, mixed>  $names
     * @return list<string>
     */
    private function qualifyAll(array $names): array
    {
        $qualified = [];

        foreach ($names as $name) {
            if (! is_string($name)) {
                continue;
            }

            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $qualified[] = ModuleName::qualify($name, $this->vendor);
        }

        return array_values(array_unique($qualified));
    }
}
