<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\PokedexApi;
use App\Pokedex\PokemonInsights;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class PokedexController extends AbstractController
{
    public function __construct(private readonly PokedexApi $api)
    {
    }

    #[Route('/', name: 'pokedex_index', methods: ['GET'])]
    public function index(): Response
    {
        // Toute la page est pilotée par le Live Component <twig:PokedexSearch />.
        return $this->render('pokemon/index.html.twig');
    }

    #[Route('/pokemon/{id<\d+>}', name: 'pokemon_show', methods: ['GET'])]
    public function show(int $id, ChartBuilderInterface $chartBuilder, PokemonInsights $insights): Response
    {
        $pokemon = $this->api->fetchPokemon($id);
        $all = $this->api->fetchAllPokemon();
        $color = $pokemon->types[0]->color ?? '#888888';

        // Symfony UX Chart.js : le graphique est décrit en PHP, rendu par un contrôleur Stimulus.
        $chart = $chartBuilder->createChart(Chart::TYPE_RADAR)
            ->setData([
                'labels' => array_keys($pokemon->getStats()),
                'datasets' => [[
                    'label' => $pokemon->name,
                    'data' => array_values($pokemon->getStats()),
                    'backgroundColor' => $color.'55',
                    'borderColor' => $color,
                    'pointBackgroundColor' => $color,
                ]],
            ])
            ->setOptions([
                'plugins' => ['legend' => ['display' => false]],
                'scales' => ['r' => ['suggestedMin' => 0, 'suggestedMax' => 160, 'ticks' => ['display' => false]]],
            ]);

        return $this->render('pokemon/show.html.twig', [
            'pokemon' => $pokemon,
            'chart' => $chart,
            'stages' => $insights->groupEvolutionStages($this->api->fetchEvolutionFamily($id)),
            'defenses' => $insights->computeDefenses($pokemon, $this->api->fetchTypes()),
            'similar' => $insights->findSimilarPokemon($pokemon, $all),
            'position' => $insights->locateInPokedex($pokemon, $all),
        ]);
    }

    /** Contenu d'un <turbo-frame> chargé paresseusement depuis la fiche (sous-ressource API par type). */
    #[Route('/pokemon/{id<\d+>}/type/{code}', name: 'pokemon_same_type', methods: ['GET'])]
    public function showSameType(int $id, string $code): Response
    {
        $others = array_values(array_filter($this->api->fetchPokemonOfType($code), static fn ($p): bool => $p->id !== $id));

        return $this->render('pokemon/_same_type.html.twig', [
            'code' => $code,
            'pokemon' => \array_slice($others, 0, 12),
            'total' => \count($others),
        ]);
    }
}
