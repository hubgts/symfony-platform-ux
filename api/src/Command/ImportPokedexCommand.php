<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Pokemon;
use App\Entity\PokemonType;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Charge le Pokédex en base depuis l'instantané data/pokedex.json.
 *
 * Avec --refresh, l'instantané est d'abord régénéré depuis PokeAPI (https://pokeapi.co, gratuite et sans clé) :
 * les requêtes partent en parallèle grâce aux réponses asynchrones de HttpClient.
 */
#[AsCommand(name: 'app:pokedex:import', description: 'Importe les Pokémon (PokeAPI) dans la base')]
final readonly class ImportPokedexCommand
{
    private const string POKEAPI = 'https://pokeapi.co/api/v2/';

    /** Couleurs de badge usuelles des 18 types. */
    private const array TYPE_COLORS = [
        'normal' => '#A8A77A', 'fire' => '#EE8130', 'water' => '#6390F0', 'electric' => '#F7D02C',
        'grass' => '#7AC74C', 'ice' => '#96D9D6', 'fighting' => '#C22E28', 'poison' => '#A33EA1',
        'ground' => '#E2BF65', 'flying' => '#A98FF3', 'psychic' => '#F95587', 'bug' => '#A6B91A',
        'rock' => '#B6A136', 'ghost' => '#735797', 'dragon' => '#6F35FC', 'dark' => '#705746',
        'steel' => '#B7B7CE', 'fairy' => '#D685AD',
    ];

    private const array GENERATIONS = [
        'generation-i' => 1, 'generation-ii' => 2, 'generation-iii' => 3, 'generation-iv' => 4, 'generation-v' => 5,
        'generation-vi' => 6, 'generation-vii' => 7, 'generation-viii' => 8, 'generation-ix' => 9,
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/data/pokedex.json')]
        private string $snapshotPath,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Regénère l\'instantané depuis PokeAPI avant l\'import')]
        bool $refresh = false,
        #[Argument(description: 'Nombre de Pokémon à récupérer avec --refresh (1025 = les 9 générations)')]
        int $limit = 1025,
    ): int {
        if ($refresh || !is_file($this->snapshotPath)) {
            $io->section(\sprintf('Récupération de %d Pokémon depuis PokeAPI', $limit));
            $snapshot = $this->fetchFromPokeApi($limit, $io);
            umask(0o022);
            file_put_contents($this->snapshotPath, json_encode($snapshot, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
            $io->note(\sprintf('Instantané écrit dans %s', $this->snapshotPath));
        } else {
            $snapshot = json_decode((string) file_get_contents($this->snapshotPath), true, flags: \JSON_THROW_ON_ERROR);
        }

        $this->loadSnapshot($snapshot);

        $io->success(\sprintf('%d types et %d Pokémon importés, 3 équipes de démo créées.', \count($snapshot['types']), \count($snapshot['pokemon'])));

        return 0;
    }

    /**
     * @return array{types: list<array<string, string>>, pokemon: list<array<string, mixed>>}
     */
    private function fetchFromPokeApi(int $limit, SymfonyStyle $io): array
    {
        $types = $this->fetchAll(array_map(static fn (string $code): string => 'type/'.$code, array_keys(self::TYPE_COLORS)), fn (array $type): array => [
            'code' => $type['name'],
            'name' => $this->findFrenchName($type['names'], 'name') ?? ucfirst($type['name']),
            'color' => self::TYPE_COLORS[$type['name']],
            // Multiplicateur des dégâts reçus selon le type de l'attaque (seuls les cas ≠ ×1 sont stockés).
            'defense' => array_merge(
                array_fill_keys(array_column($type['damage_relations']['double_damage_from'], 'name'), 2),
                array_fill_keys(array_column($type['damage_relations']['half_damage_from'], 'name'), 0.5),
                array_fill_keys(array_column($type['damage_relations']['no_damage_from'], 'name'), 0),
            ),
        ]);
        $typeNames = array_column($types, 'name', 'code');

        // Les réponses de PokeAPI sont volumineuses (attaques, jeux…) : on n'en garde que les champs utiles.
        $ids = range(1, $limit);
        $pokemon = $this->fetchAll(array_map(static fn (int $id): string => 'pokemon/'.$id, $ids), static fn (array $p): array => [
            'slug' => $p['name'],
            'height' => $p['height'],
            'weight' => $p['weight'],
            'stats' => array_column(array_map(static fn (array $stat): array => [$stat['stat']['name'], $stat['base_stat']], $p['stats']), 1, 0),
            // Les visuels manquent pour certains Pokémon récents : on retombe sur l'image disponible.
            'sprite' => $p['sprites']['front_default'] ?? $p['sprites']['other']['home']['front_default'] ?? $p['sprites']['other']['official-artwork']['front_default'] ?? '',
            'artwork' => $p['sprites']['other']['official-artwork']['front_default'] ?? $p['sprites']['other']['home']['front_default'] ?? $p['sprites']['front_default'] ?? '',
            'types' => array_map(static fn (array $t): string => $t['type']['name'], $p['types']),
        ], $io);
        $species = $this->fetchAll(array_map(static fn (int $id): string => 'pokemon-species/'.$id, $ids), fn (array $s): array => [
            'name' => $this->findFrenchName($s['names'], 'name'),
            'genus' => $this->findFrenchName($s['genera'], 'genus') ?? '',
            'description' => $this->cleanFlavorText($this->findFrenchName($s['flavor_text_entries'], 'flavor_text') ?? ''),
            'generation' => self::GENERATIONS[$s['generation']['name']],
            'legendary' => $s['is_legendary'],
            'mythical' => $s['is_mythical'],
            'evolvesFrom' => $s['evolves_from_species'] ? (int) basename(rtrim($s['evolves_from_species']['url'], '/')) : null,
            'chain' => (int) basename(rtrim($s['evolution_chain']['url'] ?? '0', '/')),
        ], $io);

        // Chaînes d'évolution : on en tire la condition d'évolution de chaque espèce (niveau, objet, échange…).
        $details = [];
        $chainIds = array_values(array_unique(array_filter(array_column($species, 'chain'))));
        $this->fetchAll(array_map(static fn (int $id): string => 'evolution-chain/'.$id, $chainIds), function (array $chain) use (&$details): null {
            $walk = function (array $link) use (&$walk, &$details): void {
                $details[(int) basename(rtrim($link['species']['url'], '/'))] = $link['evolution_details'][0] ?? null;
                array_map($walk, $link['evolves_to']);
            };
            $walk($chain['chain']);

            return null;
        }, $io);

        // Noms français des objets et capacités cités dans les conditions.
        $items = $moves = [];
        foreach (array_filter($details) as $detail) {
            foreach (['item', 'held_item'] as $key) {
                if (isset($detail[$key]['name'])) {
                    $items[$detail[$key]['name']] = true;
                }
            }
            if (isset($detail['known_move']['name'])) {
                $moves[$detail['known_move']['name']] = true;
            }
        }
        $frenchName = fn (array $data): array => [$data['name'] => $this->findFrenchName($data['names'], 'name') ?? $data['name']];
        $labels = array_merge(
            ...$this->fetchAll(array_map(static fn (string $item): string => 'item/'.$item, array_keys($items)), $frenchName),
            ...$this->fetchAll(array_map(static fn (string $move): string => 'move/'.$move, array_keys($moves)), $frenchName),
        );

        $speciesNames = [];
        foreach ($ids as $i => $id) {
            $speciesNames[$id] = $species[$i]['name'] ?? ucfirst($pokemon[$i]['slug']);
        }

        $rows = [];
        foreach ($ids as $i => $id) {
            $p = $pokemon[$i];
            $s = $species[$i];
            $rows[] = [
                'id' => $id,
                'slug' => $p['slug'],
                'name' => $s['name'] ?? ucfirst($p['slug']),
                'genus' => $s['genus'],
                'description' => $s['description'],
                'height' => $p['height'],
                'weight' => $p['weight'],
                'generation' => $s['generation'],
                'legendary' => $s['legendary'],
                'mythical' => $s['mythical'],
                'stats' => [
                    'hp' => $p['stats']['hp'], 'attack' => $p['stats']['attack'], 'defense' => $p['stats']['defense'],
                    'specialAttack' => $p['stats']['special-attack'], 'specialDefense' => $p['stats']['special-defense'], 'speed' => $p['stats']['speed'],
                ],
                'sprite' => $p['sprite'],
                'artwork' => $p['artwork'],
                'types' => $p['types'],
                'evolvesFrom' => $s['evolvesFrom'],
                'evolutionCondition' => $s['evolvesFrom'] ? $this->describeEvolutionCondition($details[$id] ?? null, $labels, $typeNames, $speciesNames) : null,
            ];
        }

        return ['source' => 'https://pokeapi.co', 'fetchedAt' => date(\DATE_ATOM), 'types' => $types, 'pokemon' => $rows];
    }

    /**
     * Lance les requêtes par lots de 25 : HttpClient multiplexe chaque lot en parallèle. Chaque réponse est réduite
     * par $extract dès son décodage, car les documents de PokeAPI sont volumineux (attaques, jeux…).
     *
     * @template T
     *
     * @param list<string>                      $paths
     * @param callable(array<string, mixed>): T $extract
     *
     * @return list<T>
     */
    private function fetchAll(array $paths, callable $extract, ?SymfonyStyle $io = null): array
    {
        $io?->progressStart(\count($paths));
        $results = [];
        foreach (array_chunk($paths, 25) as $batch) {
            /** @var list<ResponseInterface> $responses */
            $responses = array_map(fn (string $path): ResponseInterface => $this->httpClient->request('GET', self::POKEAPI.$path), $batch);
            foreach ($responses as $response) {
                $results[] = $extract($response->toArray());
                $io?->progressAdvance();
            }
        }
        $io?->progressFinish();

        return $results;
    }

    /**
     * Traduit un « evolution_details » de PokeAPI en libellé lisible : « Niveau 16 », « Pierre Feu », « Échange en tenant… ».
     *
     * @param array<string, mixed>|null $detail
     * @param array<string, string>     $labels    noms français des objets et capacités
     * @param array<string, string>     $typeNames    noms français des types
     * @param array<int, string>        $speciesNames noms français des espèces, par numéro
     */
    private function describeEvolutionCondition(?array $detail, array $labels, array $typeNames, array $speciesNames): string
    {
        if (null === $detail) {
            return 'Condition spéciale';
        }

        $label = static fn (?array $ref): ?string => isset($ref['name']) ? ($labels[$ref['name']] ?? $ref['name']) : null;
        $speciesName = static fn (?array $ref): ?string => isset($ref['url']) ? ($speciesNames[(int) basename(rtrim($ref['url'], '/'))] ?? $ref['name']) : null;
        $base = match ($detail['trigger']['name'] ?? null) {
            'level-up' => isset($detail['min_level']) ? 'Niveau '.$detail['min_level'] : null,
            'use-item' => $label($detail['item'] ?? null),
            'trade' => isset($detail['trade_species']) ? 'Échange contre '.$speciesName($detail['trade_species']) : 'Échange',
            'shed' => 'Niveau 20, avec une place libre',
            default => null,
        };

        $extras = array_filter([
            isset($detail['held_item']) ? 'en tenant '.$label($detail['held_item']) : null,
            isset($detail['min_happiness']) ? 'avec une forte amitié' : null,
            isset($detail['min_affection']) ? 'avec une forte affection' : null,
            isset($detail['min_beauty']) ? 'avec une grande beauté' : null,
            isset($detail['known_move']) ? 'en connaissant '.$label($detail['known_move']) : null,
            isset($detail['known_move_type']['name']) ? 'avec une capacité '.($typeNames[$detail['known_move_type']['name']] ?? $detail['known_move_type']['name']) : null,
            match ($detail['time_of_day'] ?? '') { 'day' => 'de jour', 'night' => 'de nuit', 'dusk' => 'au crépuscule', default => null },
            ($detail['needs_overworld_rain'] ?? false) ? 'sous la pluie' : null,
            match ($detail['gender'] ?? null) { 1 => 'femelle', 2 => 'mâle', default => null },
            isset($detail['location']) ? 'dans un lieu particulier' : null,
            match ($detail['relative_physical_stats'] ?? null) { 1 => 'si Attaque > Défense', -1 => 'si Attaque < Défense', 0 => 'si Attaque = Défense', default => null },
            isset($detail['party_species']) ? 'avec '.$speciesName($detail['party_species']).' dans l\'équipe' : null,
            isset($detail['party_type']['name']) ? 'avec un Pokémon '.($typeNames[$detail['party_type']['name']] ?? $detail['party_type']['name']).' dans l\'équipe' : null,
            ($detail['turn_upside_down'] ?? false) ? 'console retournée' : null,
        ]);

        if (null === $base) {
            $base = $extras ? ucfirst(array_shift($extras)) : 'Condition spéciale';
            if ('level-up' === ($detail['trigger']['name'] ?? null) && !str_starts_with($base, 'Condition')) {
                $base = 'Gain de niveau '.lcfirst($base);
            }
        }

        return implode(', ', [$base, ...$extras]);
    }

    /** @param list<array<string, mixed>> $entries */
    private function findFrenchName(array $entries, string $field): ?string
    {
        foreach ($entries as $entry) {
            if ('fr' === ($entry['language']['name'] ?? null)) {
                return $entry[$field];
            }
        }

        return null;
    }

    private function cleanFlavorText(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace(["\f", "\u{00AD}\n"], [' ', ''], $text)));
    }

    /** @param array{types: list<array<string, string>>, pokemon: list<array<string, mixed>>} $snapshot */
    private function loadSnapshot(array $snapshot): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE team_member, team, pokemon_pokemon_type, pokemon, pokemon_type CASCADE');

        // Données dérivées, calculées depuis l'instantané (donc aussi lors d'un import hors ligne).
        $typeCounts = array_count_values(array_merge(...array_column($snapshot['pokemon'], 'types')));
        $parents = array_column($snapshot['pokemon'], 'evolvesFrom', 'id');
        $hasEvolution = array_flip(array_filter($parents));
        $stage = static function (int $id) use ($parents): int {
            for ($depth = 0; isset($parents[$id]); ++$depth) {
                $id = $parents[$id];
            }

            return $depth;
        };

        $types = [];
        foreach ($snapshot['types'] as $row) {
            $this->entityManager->persist($types[$row['code']] = new PokemonType($row['code'], $row['name'], $row['color'], $row['defense'] ?? []));
            $types[$row['code']]->pokemonCount = $typeCounts[$row['code']] ?? 0;
        }

        $pokemon = [];
        foreach ($snapshot['pokemon'] as $row) {
            $p = new Pokemon($row['id'], $row['slug']);
            $p->name = $row['name'];
            $p->genus = $row['genus'];
            $p->description = $row['description'];
            $p->height = $row['height'];
            $p->weight = $row['weight'];
            $p->generation = $row['generation'] ?? 1;
            $p->evolutionStage = $stage($row['id']);
            $p->fullyEvolved = !isset($hasEvolution[$row['id']]);
            $p->legendary = $row['legendary'];
            $p->mythical = $row['mythical'];
            $p->setStats(...$row['stats']);
            $p->sprite = $row['sprite'];
            $p->artwork = $row['artwork'];
            $p->replaceTypes(array_map(static fn (string $code): PokemonType => $types[$code], $row['types']));
            $this->entityManager->persist($pokemon[$row['id']] = $p);
        }

        // Second passage : une pré-évolution hors de l'import (ex. Pichu pour Pikachu) reste à null.
        foreach ($snapshot['pokemon'] as $row) {
            $pokemon[$row['id']]->evolvesFrom = $pokemon[$row['evolvesFrom']] ?? null;
            $pokemon[$row['id']]->evolutionCondition = $row['evolutionCondition'] ?? null;
        }

        $demoTeams = [
            ['Équipe de Kanto', 'Sacha', '-30 days', [25, 6, 9, 3, 143, 131]],
            ['Les Spectres', 'Mortimer', '-7 days', [94, 93, 92, 110]],
            ['Arène d\'Argenta', 'Pierre', '-1 day', [74, 95, 111]],
        ];
        foreach ($demoTeams as [$name, $trainer, $createdAt, $members]) {
            $team = new Team($name, $trainer, new \DateTimeImmutable($createdAt));
            $team->replaceMembers(array_filter(array_map(static fn (int $id): ?Pokemon => $pokemon[$id] ?? null, $members)));
            $this->entityManager->persist($team);
        }

        $this->entityManager->flush();
    }
}
