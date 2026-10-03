<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PokemonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité de persistance d'un Pokémon. La représentation publique est {@see \App\ApiResource\Pokemon}.
 */
#[ORM\Entity(repositoryClass: PokemonRepository::class)]
#[ORM\Index(name: 'pokemon_name_idx', columns: ['name'])]
#[ORM\Index(name: 'pokemon_generation_idx', columns: ['generation'])]
#[ORM\Index(name: 'pokemon_base_stat_total_idx', columns: ['base_stat_total'])]
class Pokemon
{
    #[ORM\Column(length: 100)]
    public string $name = '';

    #[ORM\Column(length: 100)]
    public string $genus = '';

    #[ORM\Column(type: 'text')]
    public string $description = '';

    /** Taille en décimètres, comme dans PokeAPI. */
    #[ORM\Column]
    public int $height = 0;

    /** Poids en hectogrammes, comme dans PokeAPI. */
    #[ORM\Column]
    public int $weight = 0;

    /** Génération d'apparition (1 = Kanto … 9 = Paldea). */
    #[ORM\Column(options: ['default' => 1])]
    public int $generation = 1;

    #[ORM\Column]
    public bool $legendary = false;

    #[ORM\Column]
    public bool $mythical = false;

    #[ORM\Column]
    public int $hp = 0;

    #[ORM\Column]
    public int $attack = 0;

    #[ORM\Column]
    public int $defense = 0;

    #[ORM\Column]
    public int $specialAttack = 0;

    #[ORM\Column]
    public int $specialDefense = 0;

    #[ORM\Column]
    public int $speed = 0;

    /** Somme des six statistiques de base, stockée pour pouvoir trier et filtrer dessus en SQL. */
    #[ORM\Column]
    public private(set) int $baseStatTotal = 0;

    #[ORM\Column(length: 255)]
    public string $sprite = '';

    #[ORM\Column(length: 255)]
    public string $artwork = '';

    /** Stade dans la chaîne d'évolution : 0 = forme de base, 1 = première évolution… (calculé à l'import). */
    #[ORM\Column(options: ['default' => 0])]
    public int $evolutionStage = 0;

    /** Vrai si aucune forme n'évolue depuis ce Pokémon (calculé à l'import). */
    #[ORM\Column(options: ['default' => true])]
    public bool $fullyEvolved = true;

    /** Condition pour évoluer depuis {@see $evolvesFrom} : « Niveau 16 », « Pierre Feu »… */
    #[ORM\Column(length: 160, nullable: true)]
    public ?string $evolutionCondition = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?Pokemon $evolvesFrom = null;

    /** @var Collection<int, PokemonType> */
    #[ORM\ManyToMany(targetEntity: PokemonType::class)]
    #[ORM\JoinTable(name: 'pokemon_pokemon_type')]
    #[ORM\InverseJoinColumn(name: 'pokemon_type_code', referencedColumnName: 'code')]
    #[ORM\OrderBy(['code' => 'ASC'])]
    public private(set) Collection $types;

    public function __construct(
        /** Numéro du Pokédex national, repris tel quel comme identifiant. */
        #[ORM\Id, ORM\Column]
        public private(set) int $id,

        #[ORM\Column(length: 100, unique: true)]
        public private(set) string $slug,
    ) {
        $this->types = new ArrayCollection();
    }

    public function setStats(int $hp, int $attack, int $defense, int $specialAttack, int $specialDefense, int $speed): void
    {
        $this->hp = $hp;
        $this->attack = $attack;
        $this->defense = $defense;
        $this->specialAttack = $specialAttack;
        $this->specialDefense = $specialDefense;
        $this->speed = $speed;
        $this->baseStatTotal = $hp + $attack + $defense + $specialAttack + $specialDefense + $speed;
    }

    /** @param iterable<PokemonType> $types */
    public function replaceTypes(iterable $types): void
    {
        $this->types->clear();
        foreach ($types as $type) {
            $this->types->add($type);
        }
    }
}
