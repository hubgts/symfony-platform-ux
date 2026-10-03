<?php

declare(strict_types=1);

namespace App\ApiResource\Attribute;

use ApiPlatform\Doctrine\Orm\Filter\ComparisonFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Query;
use ApiPlatform\Metadata\QueryParameter;

/**
 * Filtre numérique sur la propriété annotée : ?attack[gte]=100, ?speed[lt]=50, ?baseStatTotal[between]=500..600.
 * Par défaut, actif sur les recherches GET et QUERY de la collection.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ComparisonParameter extends QueryParameter
{
    /** @param list<Operation>|null $operations */
    public function __construct(?string $description = null, ?array $operations = null)
    {
        parent::__construct(
            filter: new ComparisonFilter(new ExactFilter()),
            description: $description,
            operations: $operations ?? [new GetCollection(), new Query()],
        );
    }
}
