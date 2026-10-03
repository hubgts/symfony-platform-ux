<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Common\Filter\OpenApiFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\JsonSchemaFilterInterface;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use Doctrine\ORM\QueryBuilder;

/**
 * Filtre personnalisé « possède TOUS ces éléments » sur une relation multiple :
 * ?allTypes[]=fire&allTypes[]=flying ne garde que les Pokémon Feu ET Vol (Dracaufeu, Sulfura…).
 *
 * L'ExactFilter natif sur une relation fait un IN, donc « au moins un ». Ici chaque valeur devient un
 * `:valeur MEMBER OF o.types`, que Doctrine traduit en EXISTS : pas de jointure, pas de doublon de lignes.
 */
final class AllTypesFilter implements FilterInterface, OpenApiParameterFilterInterface, JsonSchemaFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;
    use OpenApiFilterTrait;

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        /** @var Parameter $parameter */
        $parameter = $context['parameter'];
        $values = array_values(array_unique(array_filter(array_map('strval', (array) $parameter->getValue()))));
        $alias = $queryBuilder->getRootAliases()[0];
        $property = $parameter->getProperty() ?? $parameter->getKey();

        foreach ($values as $value) {
            $parameterName = $queryNameGenerator->generateParameterName($property);
            $queryBuilder
                ->andWhere(\sprintf(':%s MEMBER OF %s.%s', $parameterName, $alias, $property))
                ->setParameter($parameterName, $value);
        }
    }

    public function getSchema(Parameter $parameter): array
    {
        return ['type' => 'string'];
    }
}
