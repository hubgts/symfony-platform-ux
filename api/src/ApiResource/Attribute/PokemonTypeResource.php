<?php

declare(strict_types=1);

namespace App\ApiResource\Attribute;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\PokemonType as PokemonTypeEntity;

/**
 * Configuration API de {@see \App\ApiResource\PokemonType}.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class PokemonTypeResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct(
            shortName: 'PokemonType',
            operations: [
                new GetCollection(paginationEnabled: false, order: ['name' => 'ASC']),
                new Get(),
            ],
            normalizationContext: ['groups' => ['type:read']],
            // `stateOptions` branche les provider/processor Doctrine sur l'entité.
            stateOptions: new Options(entityClass: PokemonTypeEntity::class),
            cacheHeaders: PokemonResource::CACHE_HEADERS,
        );
    }
}
