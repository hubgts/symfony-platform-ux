<?php

declare(strict_types=1);

namespace App\ApiResource\Attribute;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\ApiResource\Input\CreateTeam;
use App\ApiResource\Input\RenameTeam;
use App\Entity\Team as TeamEntity;
use App\State\CreateTeamProcessor;

/**
 * Configuration API de {@see \App\ApiResource\Team}, la ressource en écriture de la démo :
 *
 * - POST  : DTO d'entrée {@see CreateTeam} + processor « classique » {@see CreateTeamProcessor} (map: false) ;
 * - PATCH : DTO d'entrée {@see RenameTeam} appliqué sur l'entité par l'ObjectMapper d'API Platform, sans code ;
 * - GET : provider Doctrine (via stateOptions) puis ObjectMapper entité → ressource ;
 * - DELETE : provider/processor Doctrine seuls (map: false).
 *
 * Pas de cache HTTP : les équipes changent (réponses « no-cache, private »).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TeamResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct(
            shortName: 'Team',
            normalizationContext: ['groups' => ['team:read']],
            stateOptions: new Options(entityClass: TeamEntity::class),
            order: ['createdAt' => 'DESC'],
            operations: [
                new GetCollection(
                    parameters: [
                        // DateFilter « standalone » (5.0) : ?createdAt[after]=2026-01-01
                        'createdAt' => new QueryParameter(filter: new DateFilter(), property: 'createdAt'),
                        'trainer' => new QueryParameter(filter: new PartialSearchFilter(), property: 'trainer'),
                        'order[:property]' => new QueryParameter(filter: new SortFilter(), properties: ['createdAt', 'name']),
                    ],
                ),
                new Get(),
                new Post(
                    input: CreateTeam::class,
                    processor: CreateTeamProcessor::class,
                    // Le processor fait lui-même le mapping : on coupe l'ObjectMapper automatique pour cette opération.
                    map: false,
                ),
                new Patch(input: RenameTeam::class),
                // Rien à mapper pour une suppression : Doctrine charge puis supprime directement l'entité.
                new Delete(map: false),
            ],
        );
    }
}
