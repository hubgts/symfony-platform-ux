<?php

declare(strict_types=1);

namespace App\ApiResource\Attribute;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\ApiResource\PokemonType;
use App\Entity\Pokemon as PokemonEntity;

/**
 * Sous-ressource /api/pokemon_types/{code}/pokemon : les Pokémon d'un type.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class PokemonsOfTypeResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct(
            shortName: 'Pokemon',
            uriTemplate: '/pokemon_types/{code}/pokemon',
            uriVariables: [
                'code' => new Link(fromClass: PokemonType::class, toProperty: 'types'),
            ],
            normalizationContext: ['groups' => ['pokemon:read']],
            stateOptions: new Options(entityClass: PokemonEntity::class),
            cacheHeaders: PokemonResource::CACHE_HEADERS,
            operations: [new GetCollection(paginationEnabled: false)],
        );
    }
}
