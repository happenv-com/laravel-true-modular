<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\Application;
use Happenv\LaravelTrueModular\Generators\ModuleGenerator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Override;
use RuntimeException;

use function Laravel\Prompts\confirm;

class MakeModuleCommand extends Command
{
    #[Override]
    protected $signature = 'module:make {name : The module name (e.g. blog)}';

    #[Override]
    protected $description = 'Scaffold a new module under the configured modules directory';

    public function handle(): int
    {
        $generator = new ModuleGenerator(new Filesystem, $this->laravel->basePath());

        try {
            $report = $generator->generate(
                (string) $this->argument('name'),
                Application::getModulesDirectory(),
                Application::getModulesNamespace(),
                Application::getModuleComposerType(),
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Module [%s] (v%s) created in %s.', $report['package'], $report['version'], $report['path']));

        foreach ($report['files'] as $file) {
            $this->components->twoColumnDetail('created', $file);
        }

        $this->components->info(sprintf('Registered %s in composer.json and exposed GET %s.', $report['package'], $report['route']));

        $command = 'composer update '.$report['package'];

        if (confirm(label: sprintf('Run "%s" now to install the module?', $command), default: true)) {
            return $this->runComposer($command);
        }

        $this->newLine();
        $this->components->bulletList([
            'Run "'.$command.'" to install the module and auto-discover its provider.',
        ]);

        return self::SUCCESS;
    }

    private function runComposer(string $command): int
    {
        $this->components->info('Running '.$command.' ...');

        $result = Process::path($this->laravel->basePath())
            ->forever()
            ->run($command, function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }
}
