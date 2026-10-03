<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Model\Pokemon;
use App\Api\Model\PokemonCriteria;
use App\Api\Model\PokemonPage;
use App\Api\Model\PokemonType;
use App\Api\Model\Team;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Seul point de contact du front avec l'API Platform. Les réponses JSON-LD/Hydra sont dénormalisées
 * en objets {@see Model} par le Serializer de Symfony.
 */
final readonly class PokedexApi
{
    public function __construct(
        #[Target('pokedex.client')]
        private HttpClientInterface $client,
        private DenormalizerInterface $serializer,
        private CacheInterface $cache,
    ) {
    }

    /**
     * Recherche avec la méthode HTTP QUERY (RFC 10008, API Platform 5) : les critères voyagent dans le corps JSON,
     * la pagination reste dans l'URL.
     */
    public function searchPokemon(PokemonCriteria $criteria, int $page = 1, int $perPage = 24): PokemonPage
    {
        $filters = $criteria->toFilters();
        $url = \sprintf('/api/pokemon?page=%d&itemsPerPage=%d', $page, $perPage);
        $data = $this->client->request('QUERY', $url, ['json' => $filters])->toArray();

        $lastPage = 1;
        if (preg_match('/[?&]page=(\d+)/', $data['view']['last'] ?? '', $matches)) {
            $lastPage = (int) $matches[1];
        }

        return new PokemonPage(
            items: $this->denormalizeList($data['member'], Pokemon::class),
            totalItems: $data['totalItems'],
            page: $page,
            lastPage: $lastPage,
            request: "QUERY {$url}\nContent-Type: application/json\n\n".json_encode($filters, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
        );
    }

    public function fetchPokemon(int $id): Pokemon
    {
        $response = $this->client->request('GET', '/api/pokemon/'.$id);
        if (404 === $response->getStatusCode()) {
            throw new NotFoundHttpException(\sprintf('Pokémon n°%d introuvable.', $id));
        }

        return $this->serializer->denormalize($response->toArray(), Pokemon::class);
    }

    /**
     * Famille d'évolution complète (opération personnalisée /api/pokemon/{id}/evolutions).
     *
     * @return list<Pokemon>
     */
    public function fetchEvolutionFamily(int $id): array
    {
        return $this->denormalizeList($this->client->request('GET', '/api/pokemon/'.$id.'/evolutions')->toArray()['member'], Pokemon::class);
    }

    /** Sous-ressource /api/pokemon_types/{code}/pokemon. */
    public function fetchPokemonOfType(string $code): array
    {
        return $this->denormalizeList($this->client->request('GET', '/api/pokemon_types/'.rawurlencode($code).'/pokemon')->toArray()['member'], Pokemon::class);
    }

    /**
     * Tout le Pokédex, mis en cache une heure : il alimente l'autocomplete du formulaire d'équipe et les statistiques.
     * La première page donne le total, les suivantes partent en parallèle (réponses asynchrones de HttpClient).
     *
     * @return list<Pokemon>
     */
    public function fetchAllPokemon(): array
    {
        return $this->cache->get('pokedex_all_pokemon', function (ItemInterface $item): array {
            $item->expiresAfter(3600);
            $query = static fn (int $page): array => ['query' => ['page' => $page, 'itemsPerPage' => 200, 'order[id]' => 'asc']];

            $first = $this->client->request('GET', '/api/pokemon', $query(1))->toArray();
            $lastPage = (int) ceil($first['totalItems'] / 200);
            $responses = array_map(fn (int $page): ResponseInterface => $this->client->request('GET', '/api/pokemon', $query($page)), $lastPage > 1 ? range(2, $lastPage) : []);

            $rows = $first['member'];
            foreach ($responses as $response) {
                array_push($rows, ...$response->toArray()['member']);
            }

            return $this->denormalizeList($rows, Pokemon::class);
        });
    }

    /** @return list<PokemonType> */
    public function fetchTypes(): array
    {
        return $this->denormalizeList($this->client->request('GET', '/api/pokemon_types')->toArray()['member'], PokemonType::class);
    }

    /** @return list<Team> */
    public function fetchTeams(): array
    {
        return $this->denormalizeList($this->client->request('GET', '/api/teams')->toArray()['member'], Team::class);
    }

    public function fetchTeam(string $id): Team
    {
        return $this->serializer->denormalize($this->client->request('GET', '/api/teams/'.$id)->toArray(), Team::class);
    }

    /**
     * @param list<int> $memberIds
     *
     * @throws ApiValidationException
     */
    public function createTeam(string $name, string $trainer, array $memberIds): Team
    {
        $response = $this->client->request('POST', '/api/teams', ['json' => [
            'name' => $name,
            'trainer' => $trainer,
            'members' => array_map(static fn (int $id): string => '/api/pokemon/'.$id, $memberIds),
        ]]);

        return $this->serializer->denormalize($this->decodeResponse($response), Team::class);
    }

    /** @throws ApiValidationException */
    public function renameTeam(string $id, string $name): Team
    {
        $response = $this->client->request('PATCH', '/api/teams/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'body' => json_encode(['name' => $name], \JSON_THROW_ON_ERROR),
        ]);

        return $this->serializer->denormalize($this->decodeResponse($response), Team::class);
    }

    public function deleteTeam(string $id): void
    {
        $this->client->request('DELETE', '/api/teams/'.$id)->getContent();
    }

    /** @return array<string, mixed> */
    private function decodeResponse(ResponseInterface $response): array
    {
        if (422 === $response->getStatusCode()) {
            throw new ApiValidationException($response->toArray(false)['violations'] ?? []);
        }

        return $response->toArray();
    }

    /**
     * @template T of object
     *
     * @param list<array<string, mixed>> $rows
     * @param class-string<T>            $class
     *
     * @return list<T>
     */
    private function denormalizeList(array $rows, string $class): array
    {
        return $this->serializer->denormalize($rows, $class.'[]');
    }
}
