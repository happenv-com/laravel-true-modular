<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Commands;

use Happenv\LaravelTrueModular\ModuleSystem\ModuleActivation;
use Happenv\LaravelTrueModular\ModuleSystem\ModuleActivationAudit;
use Illuminate\Console\Command;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;

/**
 * Validates module activation.
 *
 * Run it on deploy, before the process starts serving: a bad disabled list must
 * abort the release, not surface as a missing class on the first request.
 */
final class ModuleCheckCommand extends Command
{
    protected $signature = 'module:check';

    protected $description = 'Validate module activation: installed modules, the disabled list, dependencies and owned packages';

    /**
     * @throws FilesystemException
     * @throws JsonException
     */
    public function handle(ModuleActivationAudit $audit, ModuleActivation $activation): int
    {
        $problems = $audit->problems();

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->components->error($problem);
            }

            return self::FAILURE;
        }

        $disabled = $activation->disabled();

        $this->components->info($disabled === []
            ? 'All modules enabled.'
            : sprintf('Disabled via %s: %s', $activation->channel(), implode(', ', $disabled)));

        return self::SUCCESS;
    }
}
