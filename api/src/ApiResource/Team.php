<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\Attribute\SearchParameter;
use App\ApiResource\Attribute\TeamResource;
use App\Entity\Team as TeamEntity;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Équipe de dresseur. Opérations (POST avec processor, PATCH par ObjectMapper…) : voir {@see TeamResource}.
 */
#[TeamResource]
#[Map(source: TeamEntity::class)]
final class Team
{
    #[ApiProperty(identifier: true)]
    #[Groups(['team:read'])]
    public Uuid $id;

    #[ApiProperty(example: 'Les Invincibles')]
    #[Groups(['team:read'])]
    #[SearchParameter(operations: [new GetCollection()])]
    public string $name;

    #[ApiProperty(example: 'Sacha')]
    #[Groups(['team:read'])]
    public string $trainer;

    #[Groups(['team:read'])]
    public \DateTimeImmutable $createdAt;

    /** @var list<Pokemon> */
    #[Groups(['team:read'])]
    #[Map(source: 'members', transform: new MapCollection(targetClass: Pokemon::class))]
    public array $members = [];

    #[ApiProperty(description: 'Somme des statistiques de base de toute l\'équipe')]
    #[Groups(['team:read'])]
    #[Map(source: 'members', transform: [self::class, 'sumBaseStats'])]
    public int $power = 0;

    /** @param iterable<\App\Entity\Pokemon> $members */
    public static function sumBaseStats(iterable $members): int
    {
        $power = 0;
        foreach ($members as $member) {
            $power += $member->baseStatTotal;
        }

        return $power;
    }
}
