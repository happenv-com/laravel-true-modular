<?php

declare(strict_types=1);

use Happenv\LaravelTrueModular\ModelExtension\AttributeResolversBag;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;

final class AttributeResolversBagTestModel extends Model {}

final class AttributeResolversBagCountingExtension
{
    public static int $built = 0;

    public function __construct(public Model $model)
    {
        self::$built++;
    }

    public function getColourAttribute(): string
    {
        return 'red';
    }
}

final class AttributeResolversBagSecondExtension
{
    public function __construct(public Model $model) {}

    public function getColourAttribute(): string
    {
        return 'blue';
    }

    public function getSizeAttribute(): string
    {
        return 'large';
    }
}

beforeEach(function (): void {
    AttributeResolversBag::flush();
    AttributeResolversBagCountingExtension::$built = 0;
});

afterEach(function (): void {
    AttributeResolversBag::flush();
});

it('keeps one resolver per extension however many times the application boots', function (): void {
    foreach (range(1, 3) as $boot) {
        AttributeResolversBag::addExtension(AttributeResolversBagTestModel::class, AttributeResolversBagCountingExtension::class);
    }

    expect(fn (): mixed => AttributeResolversBag::resolve(new AttributeResolversBagTestModel, 'weight'))
        ->toThrow(MissingAttributeException::class)
        ->and(AttributeResolversBagCountingExtension::$built)->toBe(1);
});

it('still asks every extension of a model, in the order they first registered', function (): void {
    AttributeResolversBag::addExtension(AttributeResolversBagTestModel::class, AttributeResolversBagCountingExtension::class);
    AttributeResolversBag::addExtension(AttributeResolversBagTestModel::class, AttributeResolversBagSecondExtension::class);
    AttributeResolversBag::addExtension(AttributeResolversBagTestModel::class, AttributeResolversBagCountingExtension::class);

    $model = new AttributeResolversBagTestModel;

    expect(AttributeResolversBag::resolve($model, 'colour'))->toBe('red')
        ->and(AttributeResolversBag::resolve($model, 'size'))->toBe('large');
});
