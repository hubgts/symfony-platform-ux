<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\Attribute\BooleanParameter;
use App\ApiResource\Attribute\ComparisonParameter;
use App\ApiResource\Attribute\ExactParameter;
use App\ApiResource\Attribute\PokemonResource;
use App\ApiResource\Attribute\PokemonsOfTypeResource;
use App\ApiResource\Attribute\SearchParameter;
use App\Entity\Pokemon as PokemonEntity;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Ressource API (DTO) d'un Pokémon, séparée de l'entité Doctrine {@see PokemonEntity}.
 *
 * - opérations, cache et recherches multi-propriétés : {@see PokemonResource} ;
 * - filtres d'une propriété : attributs `*Parameter` posés sur la propriété (nouveauté 5.0) ;
 * - `#[Map(source:)]` : le Symfony ObjectMapper construit ce DTO à partir de l'entité.
 */
#[PokemonResource]
#[PokemonsOfTypeResource]
#[Map(source: PokemonEntity::class)]
final class Pokemon
{
    #[ApiProperty(identifier: true, description: 'Numéro du Pokédex national', example: 25)]
    #[Groups(['pokemon:read', 'team:read'])]
    #[ComparisonParameter]
    public int $id;

    #[ApiProperty(example: 'pikachu')]
    #[Groups(['pokemon:read', 'team:read'])]
    public string $slug;

    #[ApiProperty(example: 'Pikachu')]
    #[Groups(['pokemon:read', 'team:read'])]
    #[SearchParameter]
    public string $name;

    #[ApiProperty(example: 'Pokémon Souris')]
    #[Groups(['pokemon:read'])]
    public string $genus;

    #[Groups(['pokemon:read'])]
    public string $description;

    #[ApiProperty(description: 'Taille en décimètres')]
    #[Groups(['pokemon:read'])]
    #[ComparisonParameter]
    public int $height;

    #[ApiProperty(description: 'Poids en hectogrammes')]
    #[Groups(['pokemon:read'])]
    #[ComparisonParameter]
    public int $weight;

    #[ApiProperty(description: 'Génération d\'apparition (1 = Kanto … 9 = Paldea)', example: 1)]
    #[Groups(['pokemon:read', 'team:read'])]
    #[ExactParameter]
    public int $generation;

    #[Groups(['pokemon:read', 'team:read'])]
    #[BooleanParameter]
    public bool $legendary;

    #[Groups(['pokemon:read', 'team:read'])]
    #[BooleanParameter]
    public bool $mythical;

    #[ApiProperty(description: 'Stade d\'évolution : 0 = forme de base, 1 = première évolution, 2 = seconde', example: 1)]
    #[Groups(['pokemon:read'])]
    #[ExactParameter]
    public int $evolutionStage;

    #[ApiProperty(description: 'Vrai si plus aucune évolution n\'est possible')]
    #[Groups(['pokemon:read'])]
    #[BooleanParameter]
    public bool $fullyEvolved;

    #[Groups(['pokemon:read'])]
    #[ComparisonParameter]
    public int $hp;

    #[Groups(['pokemon:read'])]
    #[ComparisonParameter]
    public int $attack;

    #[Groups(['pokemon:read'])]
    #[ComparisonParameter]
    public int $defense;

    #[Groups(['pokemon:read'])]
    #[ComparisonParameter]
    public int $specialAttack;

    #[Groups(['pokemon:read'])]
    #[ComparisonParameter]
    public int $specialDefense;

    #[Groups(['pokemon:read'])]
    #[ComparisonParameter]
    public int $speed;

    #[ApiProperty(description: 'Somme des six statistiques de base')]
    #[Groups(['pokemon:read', 'team:read'])]
    #[ComparisonParameter]
    public int $baseStatTotal;

    #[ApiProperty(description: 'Petit sprite (96×96)')]
    #[Groups(['pokemon:read', 'team:read'])]
    public string $sprite;

    #[ApiProperty(description: 'Illustration officielle')]
    #[Groups(['pokemon:read', 'team:read'])]
    public string $artwork;

    /** @var list<PokemonType> */
    #[Groups(['pokemon:read', 'team:read'])]
    #[Map(source: 'types', transform: new MapCollection(targetClass: PokemonType::class))]
    public array $types = [];

    #[ApiProperty(description: 'Forme précédente dans la chaîne d\'évolution', readableLink: false)]
    #[Groups(['pokemon:read'])]
    // Seule l'IRI est sérialisée : une référence suffit, sans charger l'entité liée ni la mapper récursivement.
    #[Map(source: 'evolvesFrom', transform: [self::class, 'createReference'])]
    public ?Pokemon $evolvesFrom = null;

    #[ApiProperty(description: 'Condition pour évoluer depuis evolvesFrom', example: 'Niveau 16')]
    #[Groups(['pokemon:read'])]
    public ?string $evolutionCondition = null;

    public static function createReference(?PokemonEntity $entity): ?self
    {
        if (null === $entity) {
            return null;
        }

        $reference = new self();
        $reference->id = $entity->id;

        return $reference;
    }
}
