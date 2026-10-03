<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\Model\Generation;
use App\Api\Model\Pokemon;
use App\Api\PokedexApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class StatsController extends AbstractController
{
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function __invoke(PokedexApi $api, ChartBuilderInterface $chartBuilder): Response
    {
        $types = $api->fetchTypes();
        usort($types, static fn ($a, $b): int => $b->pokemonCount <=> $a->pokemonCount);

        $byType = $chartBuilder->createChart(Chart::TYPE_BAR)
            ->setData([
                'labels' => array_map(static fn ($t): string => $t->name, $types),
                'datasets' => [[
                    'label' => 'Pokémon',
                    'data' => array_map(static fn ($t): int => $t->pokemonCount, $types),
                    'backgroundColor' => array_map(static fn ($t): string => $t->color, $types),
                    'borderRadius' => 6,
                ]],
            ])
            ->setOptions(['plugins' => ['legend' => ['display' => false]]]);

        // Tri par puissance décroissante, puis par numéro pour départager les ex aequo.
        $pokemon = $api->fetchAllPokemon();
        usort($pokemon, static fn (Pokemon $a, Pokemon $b): int => [$b->baseStatTotal, $a->id] <=> [$a->baseStatTotal, $b->id]);

        $perGeneration = array_count_values(array_map(static fn (Pokemon $p): int => $p->generation, $pokemon));
        $byGeneration = $chartBuilder->createChart(Chart::TYPE_BAR)
            ->setData([
                'labels' => array_map(static fn (Generation $g): string => $g->toRoman().' · '.$g->name, Generation::cases()),
                'datasets' => [[
                    'label' => 'Pokémon',
                    'data' => array_map(static fn (Generation $g): int => $perGeneration[$g->value] ?? 0, Generation::cases()),
                    'backgroundColor' => '#e3350d',
                    'borderRadius' => 6,
                ]],
            ])
            ->setOptions(['plugins' => ['legend' => ['display' => false]]]);

        return $this->render('stats/index.html.twig', [
            'byGeneration' => $byGeneration,
            'byType' => $byType,
            'strongest' => $this->buildRankingChart($chartBuilder, \array_slice($pokemon, 0, 10)),
            'weakest' => $this->buildRankingChart($chartBuilder, array_reverse(\array_slice($pokemon, -10))),
            'count' => \count($pokemon),
            'legendaries' => \count(array_filter($pokemon, static fn (Pokemon $p): bool => $p->legendary || $p->mythical)),
            'average' => (int) round(array_sum(array_map(static fn (Pokemon $p): int => $p->baseStatTotal, $pokemon)) / max(1, \count($pokemon))),
        ]);
    }

    /**
     * Classement horizontal de Pokémon par puissance totale, chaque barre à la couleur du type principal.
     *
     * @param list<Pokemon> $ranking
     */
    private function buildRankingChart(ChartBuilderInterface $chartBuilder, array $ranking): Chart
    {
        return $chartBuilder->createChart(Chart::TYPE_BAR)
            ->setData([
                'labels' => array_map(static fn (Pokemon $p): string => $p->name, $ranking),
                'datasets' => [[
                    'label' => 'Puissance totale',
                    'data' => array_map(static fn (Pokemon $p): int => $p->baseStatTotal, $ranking),
                    'backgroundColor' => array_map(static fn (Pokemon $p): string => $p->types[0]->color, $ranking),
                    'borderRadius' => 6,
                ]],
            ])
            ->setOptions(['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]]);
    }
}
