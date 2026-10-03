<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Pokemon;
use App\Entity\Team;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Supprime les requêtes N+1 des collections : les relations affichées sont chargées dans la même requête
 * (fetch join). Une liste de 24 Pokémon passe ainsi de ~65 requêtes SQL à 3.
 *
 * Les jointures utilisent des alias neufs, jamais ceux des filtres : avec ?type=fire, la jointure du filtre
 * restreint les lignes, la nôtre hydrate la collection complète (Dracaufeu garde Feu ET Vol).
 * Priorité -20 : après les filtres (-16) pour ne pas être réutilisée par eux.
 */
#[AsTaggedItem(priority: -20)]
final class FetchJoinExtension implements QueryCollectionExtensionInterface
{
    /** Relations à précharger, par entité racine (chemins relatifs à la racine). */
    private const array PATHS = [
        Pokemon::class => ['types'],
        Team::class => ['members', 'members.types'],
    ];

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $entity = $queryBuilder->getRootEntities()[0];
        $aliases = ['' => $queryBuilder->getRootAliases()[0]];

        foreach (self::PATHS[$entity] ?? [] as $path) {
            $parent = str_contains($path, '.') ? substr($path, 0, strrpos($path, '.')) : '';
            $association = substr($path, (int) strrpos('.'.$path, '.'));
            $aliases[$path] = $queryNameGenerator->generateJoinAlias($association);

            $queryBuilder
                ->leftJoin($aliases[$parent].'.'.$association, $aliases[$path])
                ->addSelect($aliases[$path]);
        }
    }
}
