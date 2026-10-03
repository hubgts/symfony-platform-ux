<?php

declare(strict_types=1);

namespace App\Pokedex;

use App\Api\Model\Pokemon;
use App\Api\Model\PokemonType;

/**
 * Calculs d'affichage de la fiche Pokémon, faits côté front à partir des données de l'API :
 * stades d'évolution, faiblesses, Pokémon similaires, voisins dans le Pokédex.
 */
final class PokemonInsights
{
    /**
     * Range une famille d'évolution par stade : [[Évoli], [Aquali, Voltali, …]].
     *
     * @param list<Pokemon> $family
     *
     * @return list<list<Pokemon>>
     */
    public function groupEvolutionStages(array $family): array
    {
        $byId = [];
        foreach ($family as $member) {
            $byId[$member->id] = $member;
        }

        $stages = [];
        foreach ($family as $member) {
            $depth = 0;
            for ($parent = $member->getEvolvesFromId(); null !== $parent && isset($byId[$parent]); $parent = $byId[$parent]->getEvolvesFromId()) {
                ++$depth;
            }
            $stages[$depth][] = $member;
        }
        ksort($stages);

        return array_values($stages);
    }

    /**
     * Multiplicateur des dégâts reçus pour chaque type d'attaque, regroupé : [4 => [types], 2 => …, 0.5, 0.25, 0].
     *
     * @param list<PokemonType> $allTypes
     *
     * @return array<string, list<PokemonType>> clé = multiplicateur affiché (« 4 », « 2 », « 0.5 »…)
     */
    public function computeDefenses(Pokemon $pokemon, array $allTypes): array
    {
        $defenders = array_filter($allTypes, static fn (PokemonType $t): bool => \in_array($t->code, array_map(static fn (PokemonType $pt): string => $pt->code, $pokemon->types), true));

        $groups = ['4' => [], '2' => [], '0.5' => [], '0.25' => [], '0' => []];
        foreach ($allTypes as $attacker) {
            $multiplier = 1.0;
            foreach ($defenders as $defender) {
                $multiplier *= $defender->defense[$attacker->code] ?? 1;
            }
            $key = (string) $multiplier;
            if (isset($groups[$key])) {
                $groups[$key][] = $attacker;
            }
        }

        return $groups;
    }

    /**
     * Les Pokémon au profil de statistiques le plus proche (distance euclidienne sur les six stats).
     *
     * @param list<Pokemon> $all
     *
     * @return list<Pokemon>
     */
    public function findSimilarPokemon(Pokemon $pokemon, array $all, int $limit = 6): array
    {
        $reference = array_values($pokemon->getStats());
        $distances = [];
        foreach ($all as $candidate) {
            if ($candidate->id === $pokemon->id) {
                continue;
            }
            $distances[$candidate->id] = array_sum(array_map(static fn (int $a, int $b): int => ($a - $b) ** 2, $reference, array_values($candidate->getStats())));
        }
        asort($distances);

        $byId = array_column(array_map(static fn (Pokemon $p): array => [$p->id, $p], $all), 1, 0);

        return array_map(static fn (int $id): Pokemon => $byId[$id], \array_slice(array_keys($distances), 0, $limit));
    }

    /**
     * @param list<Pokemon> $all
     *
     * @return array{previous: ?Pokemon, next: ?Pokemon, rank: int, total: int}
     */
    public function locateInPokedex(Pokemon $pokemon, array $all): array
    {
        $byId = array_column(array_map(static fn (Pokemon $p): array => [$p->id, $p], $all), 1, 0);
        $stronger = \count(array_filter($all, static fn (Pokemon $p): bool => $p->baseStatTotal > $pokemon->baseStatTotal));

        return [
            'previous' => $byId[$pokemon->id - 1] ?? null,
            'next' => $byId[$pokemon->id + 1] ?? null,
            'rank' => $stronger + 1,
            'total' => \count($all),
        ];
    }
}
