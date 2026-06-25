<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Architecture\Module;

final readonly class ModuleDescriptor
{
    /**
     * @param  array<string, string>  $require
     */
    public function __construct(
        public string $name,
        public string $shortName,
        public string $path,
        public ?string $namespace,
        public ?string $version,
        public ?string $provider,
        public array $require,
        public bool $isCore,
    ) {}

    public function isCore(): bool
    {
        return $this->isCore;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'shortName' => $this->shortName,
            'path' => $this->path,
            'namespace' => $this->namespace,
            'version' => $this->version,
            'provider' => $this->provider,
            'require' => $this->require,
            'isCore' => $this->isCore,
        ];
    }
}
