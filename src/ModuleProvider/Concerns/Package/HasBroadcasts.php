<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModular\ModuleProvider\Concerns\Package\Support\MergesFlattened;
use Happenv\LaravelTrueModular\ModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasBroadcasts
{
    use MergesFlattened;

    /**
     * @var string[]
     */
    public array $broadcastFileNames = [];

    public function hasBroadcastChannel(string $broadcastFileName = 'channels'): static
    {
        $this->broadcastFileNames[] = $broadcastFileName;

        return $this;
    }

    public function hasBroadcastChannels(string ...$broadcastFileNames): static
    {
        $this->broadcastFileNames = $this->mergeFlattened($this->broadcastFileNames, $broadcastFileNames);

        return $this;
    }
}
