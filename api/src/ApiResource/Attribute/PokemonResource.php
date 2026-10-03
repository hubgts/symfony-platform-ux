<?php

declare(strict_types=1);

namespace App\ApiResource\Attribute;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\FreeTextQueryFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Query;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Pokemon as PokemonEntity;
use App\Filter\AllTypesFilter;
use App\State\EvolutionFamilyProvider;

/**
 * Configuration API de {@see \App\ApiResource\Pokemon} : `#[PokemonResource]` remplace un long `#[ApiResource(...)]`.
 *
 * API Platform lit tout attribut qui étend ApiResource. Écrite en PHP « normal », la configuration peut
 * factoriser ce qu'un attribut ne permet pas : les paramètres de recherche communs à GET et QUERY.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class PokemonResource extends ApiResource
{
    /** Les Pokémon ne changent qu'à l'import : cache HTTP public d'une heure (avec ETag). */
    public const array CACHE_HEADERS = ['public' => true, 'max_age' => 3600, 'shared_max_age' => 3600];

    /** Propriétés triables : ?order[baseStatTotal]=desc */
    public const array SORTABLE = ['id', 'name', 'generation', 'baseStatTotal', 'hp', 'attack', 'defense', 'specialAttack', 'specialDefense', 'speed', 'height', 'weight'];

    public function __construct()
    {
        parent::__construct(
            shortName: 'Pokemon',
            normalizationContext: ['groups' => ['pokemon:read']],
            stateOptions: new Options(entityClass: PokemonEntity::class),
            cacheHeaders: self::CACHE_HEADERS,
            operations: [
                new GetCollection(parameters: self::buildSearchParameters()),
                // RFC 10008 : QUERY /api/pokemon, les critères voyagent dans le corps (JSON ou form-urlencoded).
                new Query(
                    parameters: self::buildSearchParameters(),
                    description: 'Recherche avancée : mêmes critères que GET, mais dans le corps de la requête.',
                ),
                new Get(),
                // Opération personnalisée servie par un State Provider classique.
                new GetCollection(
                    uriTemplate: '/pokemon/{id}/evolutions',
                    uriVariables: ['id'],
                    provider: EvolutionFamilyProvider::class,
                    paginationEnabled: false,
                    // Le provider renvoie déjà des ressources : pas de mapping automatique.
                    map: false,
                    description: 'Famille d\'évolution complète du Pokémon, par stade.',
                ),
            ],
        );
    }

    /**
     * Paramètres de recherche portant sur plusieurs propriétés ou sur des relations.
     * Les filtres d'une seule propriété sont déclarés sur la propriété elle-même (voir ComparisonParameter…).
     *
     * @return array<string, QueryParameter>
     */
    public static function buildSearchParameters(): array
    {
        return [
            // Recherche plein texte : un seul paramètre, plusieurs propriétés, combinées en OU.
            'q' => new QueryParameter(
                filter: new FreeTextQueryFilter(new OrFilter(new PartialSearchFilter())),
                properties: ['name', 'slug', 'genus'],
                description: 'Recherche dans le nom, le slug et l\'espèce',
            ),
            // Filtre sur une relation (notation pointée), « au moins un » : ?type=fire ou ?type[]=fire&type[]=water
            'type' => new QueryParameter(filter: new ExactFilter(), property: 'types.code', description: 'Pokémon ayant au moins un de ces types'),
            // Filtre maison, « tous » : ?allTypes[]=fire&allTypes[]=flying
            'allTypes' => new QueryParameter(filter: new AllTypesFilter(), property: 'types', castToArray: true, description: 'Pokémon possédant tous ces types'),
            // ExistsFilter « standalone » (5.0) : ?hasPreEvolution=true
            'hasPreEvolution' => new QueryParameter(filter: new ExistsFilter(), property: 'evolvesFrom', description: 'Pokémon issu d\'une évolution'),
            // SortFilter remplace l'OrderFilter historique.
            'order[:property]' => new QueryParameter(filter: new SortFilter(), properties: self::SORTABLE),
        ];
    }
}
