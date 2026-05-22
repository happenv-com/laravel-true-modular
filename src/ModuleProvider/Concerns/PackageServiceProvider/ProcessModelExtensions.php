<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\ModuleProvider\Concerns\PackageServiceProvider;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\MissingAttributeException;

trait ProcessModelExtensions
{
    protected function processModelExtensions(): self
    {
        if (blank($this->module->modelExtensions)) {
            return $this;
        }

        foreach ($this->module->modelExtensions as $model => $extension) {
            $reflection = new ReflectionClass($extension);

            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);


            // Handle missing attribute violation for the model using the provided extension
            $model::handleMissingAttributeViolationUsing(fn($model, $key) => $this->handleNamedAttribute($extension, $model, $key));

            foreach ($methods as $method) {
                if ($method->class !== $extension) {
                    continue;
                }

                if ($method->isConstructor()) {
                    continue;
                }

                $methodName = $method->getName();

                /**
                 * @var class-string<Model> $model
                 */
                assert(\class_exists($model));
                
                // Skip attributes, they are handled by handleMissingAttributeViolationUsing
                if(
                    str_starts_with($methodName, 'get')
                    && str_ends_with($methodName, 'Attribute'))
                {
                    continue;
                }


                    $model::resolveRelationUsing(
                        $methodName,
                        fn ($modelInstance) => new $extension($modelInstance)->{$methodName}()
                    );
                


            }

            
        }

        return $this;
    }

    private function handleNamedAttribute($extension, $model, $key)
    {
   

        $methodName = 'get' . Str::studly($key) . 'Attribute';

        $extension = new $extension($model);



        dump($extension, $methodName, $key);

        if (method_exists($extension, $methodName)) {
                return new $extension($model)->{$methodName}();
            }
            
            throw new MissingAttributeException($model, $key);
        
    }
}
