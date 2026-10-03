<?php

declare(strict_types=1);

namespace App\ApiResource\Attribute;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Query;
use ApiPlatform\Metadata\QueryParameter;

/**
 * Égalité stricte, une valeur ou une liste : ?generation=4 ou ?generation[]=1&generation[]=2.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ExactParameter extends QueryParameter
{
    /** @param list<Operation>|null $operations */
    public function __construct(?string $description = null, ?array $operations = null)
    {
        parent::__construct(
            filter: new ExactFilter(),
            description: $description,
            operations: $operations ?? [new GetCollection(), new Query()],
        );
    }
}
