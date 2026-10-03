<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Tri stable : quand un tri est demandé (SortFilter), API Platform n'ajoute plus son tri par défaut.
 * Deux lignes ex aequo (même puissance…) n'ont alors pas d'ordre garanti et PostgreSQL peut les
 * répéter ou les sauter d'une page à l'autre. On ajoute l'identifiant comme critère de départage.
 *
 * Priorité -40 : après OrderExtension (-32), avant la pagination (-64).
 */
#[AsTaggedItem(priority: -40)]
final class StableSortExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $orderBy = $queryBuilder->getDQLPart('orderBy');
        if (!$orderBy) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $identifiers = $queryBuilder->getEntityManager()->getClassMetadata($queryBuilder->getRootEntities()[0])->getIdentifierFieldNames();
        $sorted = implode(',', array_map('strval', $orderBy));

        foreach ($identifiers as $identifier) {
            if (!str_contains($sorted, $alias.'.'.$identifier.' ')) {
                $queryBuilder->addOrderBy($alias.'.'.$identifier, 'ASC');
            }
        }
    }
}
