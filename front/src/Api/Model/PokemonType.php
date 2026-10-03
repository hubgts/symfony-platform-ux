<?php

declare(strict_types=1);

namespace App\Api\Model;

/** Type de Pokémon tel que renvoyé par l'API (dénormalisé par le Serializer). */
final readonly class PokemonType
{
    public function __construct(
        public string $code,
        public string $name,
        public string $color,
        public int $pokemonCount = 0,
        /** @var array<string, float|int> multiplicateur des dégâts reçus, par type d'attaque */
        public array $defense = [],
    ) {
    }
}
