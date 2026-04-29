<?php

namespace Happenv\LaravelTrueModularModuleProvider\Concerns\Package;

use Happenv\LaravelTrueModularModuleProvider\Module;

/**
 * @mixin Module
 */
trait HasBroadcasts
{
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
        $this->broadcastFileNames = array_merge(
            $this->broadcastFileNames,
            collect($broadcastFileNames)->flatten()->toArray()
        );

        return $this;
    }
}
