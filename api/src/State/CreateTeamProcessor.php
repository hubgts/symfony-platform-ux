<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Input\CreateTeam;
use App\ApiResource\Pokemon;
use App\ApiResource\Team;
use App\Entity\Team as TeamEntity;
use App\Repository\PokemonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * State processor « classique » : reçoit le DTO d'entrée déjà validé, construit l'entité, la persiste
 * et renvoie la ressource de sortie.
 *
 * @implements ProcessorInterface<CreateTeam, Team>
 */
final readonly class CreateTeamProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PokemonRepository $pokemonRepository,
        private ObjectMapperInterface $objectMapper,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Team
    {
        \assert($data instanceof CreateTeam);

        $team = new TeamEntity(name: $data->name, trainer: $data->trainer);
        $team->replaceMembers($this->pokemonRepository->findBy([
            'id' => array_map(static fn (Pokemon $pokemon): int => $pokemon->id, $data->members),
        ]));

        $this->entityManager->persist($team);
        $this->entityManager->flush();

        return $this->objectMapper->map($team, Team::class);
    }
}
