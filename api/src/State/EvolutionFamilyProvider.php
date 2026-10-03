<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Pokemon;
use App\Entity\Pokemon as PokemonEntity;
use App\Repository\PokemonRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * State provider « classique » : renvoie toute la famille d'évolution d'un Pokémon, de la forme de base
 * jusqu'aux dernières évolutions, branches comprises (ex. les huit évolutions d'Évoli).
 *
 * @implements ProviderInterface<Pokemon>
 */
final readonly class EvolutionFamilyProvider implements ProviderInterface
{
    public function __construct(
        private PokemonRepository $repository,
        private ObjectMapperInterface $objectMapper,
    ) {
    }

    /** @return list<Pokemon> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $pokemon = $this->repository->find($uriVariables['id'] ?? 0)
            ?? throw new NotFoundHttpException('Pokémon introuvable.');

        $root = $pokemon;
        while (null !== $root->evolvesFrom) {
            $root = $root->evolvesFrom;
        }

        // Parcours en largeur : les formes sont rangées par stade d'évolution.
        $family = [];
        $queue = [$root];
        while ($current = array_shift($queue)) {
            $family[] = $current;
            array_push($queue, ...$this->repository->findBy(['evolvesFrom' => $current], ['id' => 'ASC']));
        }

        return array_map(fn (PokemonEntity $member): Pokemon => $this->objectMapper->map($member, Pokemon::class), $family);
    }
}
