<?php

declare(strict_types=1);

namespace App\Api\Model;

/** Critères de recherche du Pokédex, traduits en paramètres des filtres API Platform. */
final readonly class PokemonCriteria
{
    public const string TYPES_ANY = 'any';
    public const string TYPES_ALL = 'all';

    /**
     * @param list<string>       $types       codes de types
     * @param list<int>          $generations numéros de génération
     * @param array<string, int> $minStats    minimum par statistique (hp, attack… baseStatTotal)
     */
    public function __construct(
        public string $query = '',
        public array $types = [],
        public string $typeMode = self::TYPES_ANY,
        public array $generations = [],
        public ?string $category = null,
        public ?string $stage = null,
        public array $minStats = [],
        public int $maxTotal = 0,
        public string $sort = 'id',
        public string $direction = 'asc',
    ) {
    }

    /** @return array<string, mixed> corps JSON de la requête QUERY */
    public function toFilters(): array
    {
        $filters = [];

        if ('' !== trim($this->query)) {
            $filters['q'] = trim($this->query);                                    // FreeTextQueryFilter + OrFilter
        }
        if ($this->types) {
            // « au moins un » : ExactFilter (IN) ; « tous » : filtre maison AllTypesFilter
            $filters[self::TYPES_ALL === $this->typeMode ? 'allTypes' : 'type'] = array_values($this->types);
        }
        if ($this->generations) {
            $filters['generation'] = array_values($this->generations);          // ExactFilter, liste = IN
        }

        $filters += match ($this->category) {                                     // ExactFilter booléens
            'legendary' => ['legendary' => true],
            'mythical' => ['mythical' => true],
            'standard' => ['legendary' => false, 'mythical' => false],
            default => [],
        };

        $filters += match ($this->stage) {                                        // stade + ExistsFilter
            'base' => ['evolutionStage' => 0, 'fullyEvolved' => false],
            'middle' => ['hasPreEvolution' => true, 'fullyEvolved' => false],
            'final' => ['hasPreEvolution' => true, 'fullyEvolved' => true],
            'single' => ['evolutionStage' => 0, 'fullyEvolved' => true],
            default => [],
        };

        foreach (array_filter($this->minStats) as $stat => $min) {
            $filters[$stat] = ['gte' => $min];                                    // ComparisonFilter
        }
        if ($this->maxTotal > 0) {
            $filters['baseStatTotal'] = ['between' => \sprintf('%d..%d', $this->minStats['baseStatTotal'] ?? 0, $this->maxTotal)];
        }

        $filters['order'] = [$this->sort => $this->direction];                   // SortFilter (+ départage par id côté API)

        return $filters;
    }
}
