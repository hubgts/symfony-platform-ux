<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PokemonTypeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité de persistance : elle ne connaît rien de l'API (pas d'attribut API Platform ni de groupe de sérialisation).
 * La représentation publique est {@see \App\ApiResource\PokemonType}.
 */
#[ORM\Entity(repositoryClass: PokemonTypeRepository::class)]
#[ORM\Table(name: 'pokemon_type')]
class PokemonType
{
    /**
     * Nombre de Pokémon du type, calculé à l'import. Le compter à la volée coûtait un COUNT SQL
     * par type imbriqué dans chaque Pokémon sérialisé (39 requêtes pour une page de liste).
     */
    #[ORM\Column(options: ['default' => 0])]
    public int $pokemonCount = 0;

    public function __construct(
        #[ORM\Id, ORM\Column(length: 20)]
        public private(set) string $code,

        #[ORM\Column(length: 50)]
        public string $name,

        #[ORM\Column(length: 7)]
        public string $color,

        /** @var array<string, float|int> multiplicateur des dégâts reçus, par type d'attaque (absent = ×1) */
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        public array $defense = [],
    ) {
    }
}
