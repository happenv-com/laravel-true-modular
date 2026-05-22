<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Foundation\Application as FoundationApplication;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Override;
use ReflectionException;
use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\JsonException;
use TypeError;

final class Application extends FoundationApplication
{
    /**
     * The array of initializing callbacks.
     *
     * @var callable[]
     */
    private $initializingCallbacks = [];

    /**
     * The array of initialized callbacks.
     *
     * @var callable[]
     */
    private $initializedCallbacks = [];

    /**
     * Register a service provider with the application.
     *
     * @param  ServiceProvider|string  $provider
     * @param  bool  $force
     * @return ServiceProvider
     *
     * @throws ReflectionException
     * @throws TypeError
     * @throws InvalidArgumentException
     */
    #[Override]
    public function register($provider, $force = false)
    {
        if (($registered = $this->getProvider($provider)) && ! $force) {
            return $registered;
        }

        // If the given "provider" is a string, we will resolve it, passing in the
        // application instance automatically for the developer. This is simply
        // a more convenient way of specifying your service provider classes.
        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();

        // If there are bindings / singletons set as properties on the provider we
        // will spin through them and register them with the application, which
        // serves as a convenience layer while registering a lot of bindings.
        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }

        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $key = is_int($key) ? $value : $key;

                $this->singleton($key, $value);
            }
        }

        $this->markAsRegistered($provider);

        // If the application has already booted, we will call this boot method on
        // the provider class so it has an opportunity to do its boot logic and
        // will be ready for any usage by this developer's application logic.
        if ($this->isBooted()) {
            $this->initializeProvider($provider);
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     * @throws ModuleSystem\Exceptions\CircularDependencyException
     * @throws InvalidArgumentException
     * @throws FilesystemException
     * @throws JsonException
     */
    #[Override]
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        // dump($this->serviceProviders);

        // Sort service providers according to module dependency order
        $sorter = $this->resolve(ServiceProviderSorter::class);
        $this->serviceProviders = $sorter->sort($this->serviceProviders);

        $this->fireAppCallbacks($this->initializingCallbacks);

        array_walk($this->serviceProviders, function (ServiceProvider $p): void {
            $this->initializeProvider($p);
        });

        $this->fireAppCallbacks($this->initializedCallbacks);

        // Once the application has booted we will also fire some "booted" callbacks
        // for any listeners that need to do work after this initial booting gets
        // finished. This is useful when ordering the boot-up processes we run.
        $this->fireAppCallbacks($this->bootingCallbacks);

        array_walk($this->serviceProviders, function (ServiceProvider $p): void {
            $this->bootProvider($p);
        });

        $this->booted = true;

        $this->fireAppCallbacks($this->bootedCallbacks);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function initializeProvider(ServiceProvider $provider): void
    {
        if (method_exists($provider, 'callInitializingCallbacks')) {
            $provider->callInitializingCallbacks();
        }

        if (method_exists($provider, 'initialize')) {
            $this->call([$provider, 'initialize']);
        }

        if (method_exists($provider, 'callInitializedCallbacks')) {
            $provider->callInitializedCallbacks();
        }
    }

    /**
     * @throws ReflectionException
     * @throws TypeError
     */
    /**
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws TypeError
     */
    #[Override]
    public function registerDeferredProvider($provider, $service = null): void
    {
        // Once the provider that provides the deferred service has been registered we
        // will remove it from our local list of the deferred services with related
        // providers so that this container does not try to resolve it out again.
        if ($service) {
            unset($this->deferredServices[$service]);
        }

        $this->register($instance = new $provider($this));

        if (! $this->isBooted()) {
            $this->booting(function () use ($instance): void {
                $this->initializeProvider($instance);
                $this->bootProvider($instance);
            });
        }
    }

    /**
     * Register a new initializing listener.
     *
     * @param  callable  $callback
     */
    public function initializing($callback): void
    {
        $this->initializingCallbacks[] = $callback;
    }

    /**
     * Register a new "initialized" listener.
     *
     * @param  callable  $callback
     */
    public function initialized($callback): void
    {
        $this->initializedCallbacks[] = $callback;

        if ($this->isBooted()) {
            $callback($this);
        }
    }

    #[Override]
    public function flush(): void
    {
        parent::flush();

        $this->initializingCallbacks = [];
        $this->initializedCallbacks = [];
    }

    protected static string $moduleComposerType = 'true-module';

    public static function moduleComposerType(string $type): string
    {
        self::$moduleComposerType = $type;

        return self::class;
    }

    public static function getModuleComposerType(): string
    {
        return self::$moduleComposerType;
    }
}
