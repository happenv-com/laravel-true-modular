<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\Setup\TrueModularSetup;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class SetupCommand extends Command
{
    protected $signature = 'true-modular:setup';

    protected $description = 'Prepare the application to run as a modular monolith';

    public function handle(): int
    {
        $setup = new TrueModularSetup(new Filesystem, $this->laravel->basePath());

        $composerType = text(
            label: 'Composer package type used to identify modules',
            default: Application::DEFAULT_COMPOSER_TYPE,
            required: true,
        );

        $modulesDirectory = text(
            label: 'Directory where modules will live',
            default: Application::DEFAULT_MODULES_DIRECTORY,
            required: true,
        );

        $convertApp = confirm(
            label: 'Convert the current "app" folder into a "core" module?',
            default: true,
        );

        $namespace = $convertApp
            ? text(
                label: 'Namespace for the core module',
                default: Application::DEFAULT_MODULES_NAMESPACE,
                required: true,
            )
            : null;

        // Always: switch bootstrap/app.php to ModularApplication.
        if ($setup->useModularApplication($composerType, $modulesDirectory, $namespace)) {
            $this->components->info('Updated bootstrap/app.php to use ModularApplication.');
        } else {
            $this->components->warn('Could not find bootstrap/app.php — skipped Application swap.');
        }

        if (! $convertApp) {
            $this->components->info('Done. Create modules under '.$modulesDirectory.'/ and they will boot in dependency order.');

            return self::SUCCESS;
        }

        if (! confirm(
            label: sprintf('This moves app/ into %s/core and rewrites the App\\ namespace to %s\\Core. Continue?', $modulesDirectory, $namespace),
            default: true,
        )) {
            $this->components->warn('Skipped the app → core conversion.');

            return self::SUCCESS;
        }

        try {
            $report = $setup->convertAppToCoreModule($modulesDirectory, $composerType, (string) $namespace);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->reportConversion($report);

        if (confirm(
            label: 'Also remove the now-unused "App\\\\": "app/" entry from composer.json autoload?',
            default: false,
        )) {
            $setup->removeAppAutoload()
                ? $this->components->info('Removed the App\\ autoload entry from composer.json.')
                : $this->components->warn('No App\\ autoload entry found to remove.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{vendor: string, moduleNamespace: string, modulePath: string, rewritten: list<string>, remaining: list<string>}  $report
     */
    private function reportConversion(array $report): void
    {
        $this->components->info(sprintf(
            'Moved app/ into %s (namespace %s\\, %d files rewritten).',
            $report['modulePath'],
            $report['moduleNamespace'],
            count($report['rewritten']),
        ));
        $this->components->info('Registered '.$report['vendor'].'/core as a Composer path package and emptied bootstrap/providers.php.');

        if ($report['remaining'] !== []) {
            $this->components->warn('These files still reference App\\ and may need a manual update:');
            foreach ($report['remaining'] as $file) {
                $this->line('  • '.$file);
            }
        }

        $this->newLine();
        $this->components->bulletList([
            'Run "composer update '.$report['vendor'].'/core" to install the module and auto-discover its provider.',
            'Then run "composer dump-autoload".',
        ]);
    }
}
