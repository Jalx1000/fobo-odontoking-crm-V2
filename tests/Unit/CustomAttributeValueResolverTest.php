<?php

use Webkul\Admin\Helpers\CustomAttributeValueResolver;
use Webkul\Attribute\Repositories\AttributeRepository;

/**
 * Build a resolver with an (optionally mocked) attribute repository.
 */
function makeResolver($repo = null): CustomAttributeValueResolver
{
    $repo ??= Mockery::mock(AttributeRepository::class);

    return new CustomAttributeValueResolver($repo);
}

/**
 * Build a lightweight attribute stub with sane defaults.
 */
function attr(array $props)
{
    return (object) array_merge([
        'type'        => 'text',
        'lookup_type' => null,
        'options'     => collect(),
    ], $props);
}

afterEach(fn () => Mockery::close());

it('resolves a lookup-backed select to its label instead of the raw id (city bug)', function () {
    $repo = Mockery::mock(AttributeRepository::class);
    $repo->shouldReceive('getLookUpEntity')
        ->with('lead_pipelines', 7)
        ->andReturn((object) ['id' => 7, 'name' => 'Bogotá']);

    $attribute = attr(['type' => 'select', 'lookup_type' => 'lead_pipelines']);

    expect(makeResolver($repo)->resolve($attribute, 7))->toBe('Bogotá');
});

it('memoizes lookup labels so a repeated id only hits the repository once', function () {
    $repo = Mockery::mock(AttributeRepository::class);
    $repo->shouldReceive('getLookUpEntity')
        ->once()
        ->with('lead_pipelines', 7)
        ->andReturn((object) ['id' => 7, 'name' => 'Bogotá']);

    $attribute = attr(['type' => 'lookup', 'lookup_type' => 'lead_pipelines']);

    $resolver = makeResolver($repo);

    expect($resolver->resolve($attribute, 7))->toBe('Bogotá');
    expect($resolver->resolve($attribute, 7))->toBe('Bogotá');
});

it('resolves a plain select from its own options', function () {
    $attribute = attr([
        'type'    => 'select',
        'options' => collect([
            (object) ['id' => 3, 'name' => 'Alto'],
            (object) ['id' => 4, 'name' => 'Bajo'],
        ]),
    ]);

    expect(makeResolver()->resolve($attribute, 4))->toBe('Bajo');
});

it('resolves a multiselect to comma-joined labels', function () {
    $attribute = attr([
        'type'    => 'multiselect',
        'options' => collect([
            (object) ['id' => 1, 'name' => 'A'],
            (object) ['id' => 2, 'name' => 'B'],
            (object) ['id' => 3, 'name' => 'C'],
        ]),
    ]);

    expect(makeResolver()->resolve($attribute, '1,3'))->toBe('A, C');
});

it('falls back to the raw id when the lookup entity is missing', function () {
    $repo = Mockery::mock(AttributeRepository::class);
    $repo->shouldReceive('getLookUpEntity')->andReturn(null);

    $attribute = attr(['type' => 'lookup', 'lookup_type' => 'lead_pipelines']);

    expect(makeResolver($repo)->resolve($attribute, 999))->toBe('999');
});

it('returns an empty string for null or empty values', function () {
    $attribute = attr(['type' => 'select']);

    expect(makeResolver()->resolve($attribute, null))->toBe('');
    expect(makeResolver()->resolve($attribute, ''))->toBe('');
});

it('resolves boolean values to Sí/No', function () {
    $attribute = attr(['type' => 'boolean']);

    expect(makeResolver()->resolve($attribute, '1'))->toBe('Sí');
    expect(makeResolver()->resolve($attribute, '0'))->toBe('No');
});

it('formats a date value', function () {
    $attribute = attr(['type' => 'date']);

    expect(makeResolver()->resolve($attribute, '2026-07-07 12:30:00'))->toBe('2026-07-07');
});
