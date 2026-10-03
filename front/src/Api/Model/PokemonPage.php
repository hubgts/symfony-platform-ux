<?php

declare(strict_types=1);

namespace App\Api\Model;

/** Une page de résultats Hydra, accompagnée de la requête envoyée (affichée dans l'interface à but pédagogique). */
final readonly class PokemonPage
{
    /**
     * @param list<Pokemon> $items
     */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $page,
        public int $lastPage,
        public string $request,
    ) {
    }
}
