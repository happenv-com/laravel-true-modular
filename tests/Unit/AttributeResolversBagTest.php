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

it('lists the extensions of a model in the order they first registered', function (): void {
    AttributeResolversBag::addExtension(AttributeResolversBagTestModel::class, AttributeResolversBagSecondExtension::class);
    AttributeResolversBag::addExtension(AttributeResolversBagTestModel::class, AttributeResolversBagCountingExtension::class);
    AttributeResolversBag::addExtension(AttributeResolversBagTestModel::class, AttributeResolversBagSecondExtension::class);

    expect(AttributeResolversBag::extensionsFor(AttributeResolversBagTestModel::class))->toBe([
        AttributeResolversBagSecondExtension::class,
        AttributeResolversBagCountingExtension::class,
    ]);
});

it('lists no extensions for a model nothing extends, nor after a flush', function (): void {
    expect(AttributeResolversBag::extensionsFor(AttributeResolversBagTestModel::class))->toBe([]);

    AttributeResolversBag::addExtension(AttributeResolversBagTestModel::class, AttributeResolversBagCountingExtension::class);
    AttributeResolversBag::flush();

    expect(AttributeResolversBag::extensionsFor(AttributeResolversBagTestModel::class))->toBe([]);
});

it('lists first the extension that resolve() answers with', function (array $registrationOrder): void {
    foreach ($registrationOrder as $extension) {
        AttributeResolversBag::addExtension(AttributeResolversBagTestModel::class, $extension);
    }

    $model = new AttributeResolversBagTestModel;
    $first = AttributeResolversBag::extensionsFor(AttributeResolversBagTestModel::class)[0];

    expect(AttributeResolversBag::resolve($model, 'colour'))->toBe((new $first($model))->getColourAttribute());
})->with([
    'counting extension first' => [[AttributeResolversBagCountingExtension::class, AttributeResolversBagSecondExtension::class]],
    'second extension first' => [[AttributeResolversBagSecondExtension::class, AttributeResolversBagCountingExtension::class]],
]);
