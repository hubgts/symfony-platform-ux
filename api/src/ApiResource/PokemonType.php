<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\Attribute\PokemonTypeResource;
use App\Entity\PokemonType as PokemonTypeEntity;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Ressource API (DTO) d'un type de Pokémon, séparée de l'entité Doctrine. Configuration : {@see PokemonTypeResource}.
 */
#[PokemonTypeResource]
#[Map(source: PokemonTypeEntity::class)]
final class PokemonType
{
    #[ApiProperty(identifier: true, example: 'fire')]
    #[Groups(['type:read', 'pokemon:read', 'team:read'])]
    public string $code;

    #[ApiProperty(example: 'Feu')]
    #[Groups(['type:read', 'pokemon:read', 'team:read'])]
    public string $name;

    #[ApiProperty(description: 'Couleur hexadécimale du badge', example: '#EE8130')]
    #[Groups(['type:read', 'pokemon:read', 'team:read'])]
    public string $color;

    /** @var array<string, float|int> */
    #[ApiProperty(description: 'Multiplicateur des dégâts reçus par type d\'attaque (absent = ×1)', example: ['water' => 2, 'grass' => 0.5])]
    #[Groups(['type:read'])]
    public array $defense = [];

    #[ApiProperty(description: 'Nombre de Pokémon de ce type')]
    #[Groups(['type:read'])]
    public int $pokemonCount = 0;
}
