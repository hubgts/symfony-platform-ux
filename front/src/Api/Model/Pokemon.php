<?php

declare(strict_types=1);

namespace App\Api\Model;

use Symfony\Component\Serializer\Attribute\SerializedName;

/** Pokémon tel que renvoyé par l'API (dénormalisé par le Serializer à partir du JSON-LD). */
final readonly class Pokemon
{
    /**
     * @param list<PokemonType> $types
     */
    public function __construct(
        #[SerializedName('@id')]
        public string $iri,
        public int $id,
        public string $slug,
        public string $name,
        public string $sprite,
        public string $artwork,
        public int $baseStatTotal,
        public int $generation = 1,
        public array $types = [],
        public bool $legendary = false,
        public bool $mythical = false,
        public string $genus = '',
        public string $description = '',
        public int $height = 0,
        public int $weight = 0,
        public int $hp = 0,
        public int $attack = 0,
        public int $defense = 0,
        public int $specialAttack = 0,
        public int $specialDefense = 0,
        public int $speed = 0,
        public ?string $evolvesFrom = null,
        public ?string $evolutionCondition = null,
    ) {
    }

    /** @return array<string, int> */
    public function getStats(): array
    {
        return [
            'PV' => $this->hp,
            'Attaque' => $this->attack,
            'Défense' => $this->defense,
            'Att. Spé.' => $this->specialAttack,
            'Déf. Spé.' => $this->specialDefense,
            'Vitesse' => $this->speed,
        ];
    }

    public function toGeneration(): Generation
    {
        return Generation::from($this->generation);
    }

    public function getEvolvesFromId(): ?int
    {
        return null === $this->evolvesFrom ? null : (int) basename($this->evolvesFrom);
    }

    /** Cri officiel, hébergé par le projet PokeAPI. */
    public function getCryUrl(): string
    {
        return \sprintf('https://raw.githubusercontent.com/PokeAPI/cries/main/cries/pokemon/latest/%d.ogg', $this->id);
    }
}
