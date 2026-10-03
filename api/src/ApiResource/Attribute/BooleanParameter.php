<?php

declare(strict_types=1);

namespace App\ApiResource\Attribute;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Query;
use ApiPlatform\Metadata\QueryParameter;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Filtre booléen : ?legendary=true. ExactFilter typé booléen remplace le BooleanFilter déprécié en 4.4.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class BooleanParameter extends QueryParameter
{
    /** @param list<Operation>|null $operations */
    public function __construct(?string $description = null, ?array $operations = null)
    {
        parent::__construct(
            filter: new ExactFilter(),
            description: $description,
            nativeType: new BuiltinType(TypeIdentifier::BOOL),
            castToNativeType: true,
            operations: $operations ?? [new GetCollection(), new Query()],
        );
    }
}
