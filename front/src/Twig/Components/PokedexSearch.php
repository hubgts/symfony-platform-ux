<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Api\Model\Generation;
use App\Api\Model\PokemonCriteria;
use App\Api\Model\PokemonPage;
use App\Api\Model\PokemonType;
use App\Api\PokedexApi;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Recherche du Pokédex en Live Component. Chaque critère est une LiveProp modifiable (data-model),
 * reflétée dans l'URL (url: true) — une recherche se partage par simple lien — et traduite en filtres API.
 */
#[AsLiveComponent]
final class PokedexSearch
{
    use DefaultActionTrait;

    /** Statistiques filtrables par un minimum : propriété => libellé. */
    public const array STATS = [
        'hp' => 'PV', 'attack' => 'Attaque', 'defense' => 'Défense',
        'specialAttack' => 'Att. Spé.', 'specialDefense' => 'Déf. Spé.', 'speed' => 'Vitesse',
    ];

    /** Tris proposés : propriété du SortFilter => [libellé, sens par défaut]. */
    public const array SORTS = [
        'id' => ['N° Pokédex', 'asc'], 'name' => ['Nom', 'asc'], 'generation' => ['Génération', 'asc'],
        'baseStatTotal' => ['Puissance totale', 'desc'], 'hp' => ['PV', 'desc'], 'attack' => ['Attaque', 'desc'],
        'defense' => ['Défense', 'desc'], 'specialAttack' => ['Att. Spé.', 'desc'], 'specialDefense' => ['Déf. Spé.', 'desc'],
        'speed' => ['Vitesse', 'desc'], 'height' => ['Taille', 'desc'], 'weight' => ['Poids', 'desc'],
    ];

    public const array CATEGORIES = ['legendary' => 'Légendaires', 'mythical' => 'Fabuleux', 'standard' => 'Ni l\'un ni l\'autre'];
    public const array STAGES = ['base' => 'Forme de base', 'middle' => 'Évolution intermédiaire', 'final' => 'Évolution finale', 'single' => 'Sans évolution'];
    public const array PER_PAGE = [24, 48, 96];

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $q = '';

    /** @var list<string> */
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public array $types = [];

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $typeMode = PokemonCriteria::TYPES_ANY;

    /** @var list<string> numéros de génération, en chaînes comme dans l'URL (un list<int> y serait ignoré) */
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public array $generations = [];

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $category = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $stage = '';

    // Minimums par statistique (0 = pas de filtre).
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public int $hp = 0;
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public int $attack = 0;
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public int $defense = 0;
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public int $specialAttack = 0;
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public int $specialDefense = 0;
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public int $speed = 0;
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public int $minTotal = 0;
    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public int $maxTotal = 0;

    #[LiveProp(writable: true, url: true, onUpdated: 'onSortUpdated')]
    public string $sort = 'id';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $direction = 'asc';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public int $perPage = 24;

    #[LiveProp(writable: true, url: true)]
    public string $view = 'grid';

    #[LiveProp(url: true)]
    public int $page = 1;

    public function __construct(private readonly PokedexApi $api)
    {
    }

    public function resetPage(): void
    {
        $this->page = 1;
    }

    /** Changer de critère de tri reprend son sens naturel (décroissant pour une stat, croissant pour un nom). */
    public function onSortUpdated(): void
    {
        $this->direction = self::SORTS[$this->sort][1] ?? 'asc';
        $this->resetPage();
    }

    #[LiveAction]
    public function goToPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }

    #[LiveAction]
    public function toggleDirection(): void
    {
        $this->direction = 'asc' === $this->direction ? 'desc' : 'asc';
        $this->resetPage();
    }

    /** Clic sur un en-tête du tableau : trie par cette colonne, ou inverse le sens si elle l'est déjà. */
    #[LiveAction]
    public function sortBy(#[LiveArg] string $column): void
    {
        if (!isset(self::SORTS[$column])) {
            return;
        }

        if ($column === $this->sort) {
            $this->toggleDirection();

            return;
        }

        $this->sort = $column;
        $this->onSortUpdated();
    }

    /** Retire un critère depuis sa « pastille » de filtre actif. */
    #[LiveAction]
    public function removeFilter(#[LiveArg] string $filter, #[LiveArg] string $value = ''): void
    {
        match ($filter) {
            'q' => $this->q = '',
            'types' => $this->types = array_values(array_diff($this->types, [$value])),
            'generations' => $this->generations = array_values(array_diff($this->generations, [$value])),
            'category' => $this->category = '',
            'stage' => $this->stage = '',
            'total' => [$this->minTotal, $this->maxTotal] = [0, 0],
            default => isset(self::STATS[$filter]) ? $this->{$filter} = 0 : null,
        };
        $this->resetPage();
    }

    #[LiveAction]
    public function clearFilters(): void
    {
        $this->q = $this->category = $this->stage = '';
        $this->types = $this->generations = [];
        $this->typeMode = PokemonCriteria::TYPES_ANY;
        foreach (array_keys(self::STATS) as $stat) {
            $this->{$stat} = 0;
        }
        $this->minTotal = $this->maxTotal = 0;
        $this->resetPage();
    }

    /** Appelée une seule fois par rendu grâce à `computed.results` dans le template. */
    public function getResults(): PokemonPage
    {
        $minStats = ['baseStatTotal' => $this->minTotal];
        foreach (array_keys(self::STATS) as $stat) {
            $minStats[$stat] = $this->{$stat};
        }

        return $this->api->searchPokemon(new PokemonCriteria(
            query: $this->q,
            types: $this->types,
            typeMode: $this->typeMode,
            generations: array_map('intval', $this->generations),
            category: $this->category ?: null,
            stage: $this->stage ?: null,
            minStats: $minStats,
            maxTotal: $this->maxTotal,
            sort: isset(self::SORTS[$this->sort]) ? $this->sort : 'id',
            direction: 'desc' === $this->direction ? 'desc' : 'asc',
        ), $this->page, \in_array($this->perPage, self::PER_PAGE, true) ? $this->perPage : 24);
    }

    /** @return array<string, PokemonType> par code */
    public function getTypeOptions(): array
    {
        return array_column(array_map(static fn (PokemonType $t): array => [$t->code, $t], $this->api->fetchTypes()), 1, 0);
    }

    /**
     * Pastilles des critères actifs : [libellé, filtre, valeur, couleur].
     *
     * @return list<array{label: string, filter: string, value: string, color: ?string}>
     */
    public function getActiveFilters(): array
    {
        $pills = [];
        $add = static function (string $label, string $filter, string $value = '', ?string $color = null) use (&$pills): void {
            $pills[] = ['label' => $label, 'filter' => $filter, 'value' => $value, 'color' => $color];
        };

        if ('' !== trim($this->q)) {
            $add('« '.trim($this->q).' »', 'q');
        }
        $types = $this->getTypeOptions();
        foreach ($this->types as $code) {
            $add((PokemonCriteria::TYPES_ALL === $this->typeMode && \count($this->types) > 1 ? 'et ' : '').($types[$code]->name ?? $code), 'types', $code, $types[$code]->color ?? null);
        }
        foreach ($this->generations as $generation) {
            $add(Generation::from((int) $generation)->getLabel(), 'generations', (string) $generation);
        }
        if (isset(self::CATEGORIES[$this->category])) {
            $add(self::CATEGORIES[$this->category], 'category');
        }
        if (isset(self::STAGES[$this->stage])) {
            $add(self::STAGES[$this->stage], 'stage');
        }
        foreach (self::STATS as $stat => $label) {
            if ($this->{$stat} > 0) {
                $add($label.' ≥ '.$this->{$stat}, $stat);
            }
        }
        if ($this->minTotal > 0 || $this->maxTotal > 0) {
            $add('Puissance '.($this->maxTotal > 0 ? $this->minTotal.' à '.$this->maxTotal : '≥ '.$this->minTotal), 'total');
        }

        return $pills;
    }
}
