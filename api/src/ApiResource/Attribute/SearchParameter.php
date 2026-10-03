<?php

declare(strict_types=1);

namespace App\ApiResource\Attribute;

use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Query;
use ApiPlatform\Metadata\QueryParameter;

/**
 * Recherche partielle insensible à la casse : ?name=chu.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SearchParameter extends QueryParameter
{
    /** @param list<Operation>|null $operations */
    public function __construct(?string $description = null, ?array $operations = null)
    {
        parent::__construct(
            filter: new PartialSearchFilter(),
            description: $description,
            operations: $operations ?? [new GetCollection(), new Query()],
        );
    }
}
