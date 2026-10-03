# symfony-platform-ux

Projet de démonstration composé de deux applications Symfony 8.1 (PHP 8.5) conteneurisées :

| | Application | URL |
|---|---|---|
| `api/` | **API Platform 5.0** : Pokédex national (1025 Pokémon des 9 générations, 18 types) et équipes de dresseurs | http://localhost:8180/api |
| `front/` | **Symfony UX** qui consomme l'API côté serveur (Live Components, Turbo, Chart.js…) | http://localhost:8181 |

Les données viennent de [PokeAPI](https://pokeapi.co), une API gratuite et sans clé.

```
navigateur ──▶ front (FrankenPHP :8181) ──HttpClient──▶ api (FrankenPHP :8180) ──▶ PostgreSQL 17
                Symfony UX                 JSON-LD        API Platform 5 + Doctrine
```

## Démarrage

```bash
make dc-init   # première fois : image, composer ×2, base, migrations, import des données, importmap
make dc-up     # les fois suivantes
make help      # toutes les cibles (dc-down, dc-logs, dc-sh-api, test, api-import-refresh…)
```

Sur un dépôt fraîchement cloné, commence **obligatoirement** par `make dc-init` : en mode worker, FrankenPHP démarre Symfony dès le lancement du conteneur, et `make dc-up` seul redémarrerait en boucle tant que `vendor/` n'existe pas. `dc-init` installe les dépendances dans des conteneurs éphémères avant de démarrer les serveurs.

`make dc-init` peut être relancé sans risque. L'import lit l'instantané versionné `api/data/pokedex.json`, ce qui évite d'interroger PokeAPI à chaque installation. `make api-import-refresh` le régénère depuis PokeAPI en une dizaine de secondes : Pokémon, espèces, chaînes d'évolution, objets et capacités, soit environ 2 700 requêtes envoyées par lots de 25. Tous les Pokémon sont pris par défaut ; `LIMIT=151` se limite à Kanto.

| Quoi | Où |
|---|---|
| Documentation de l'API (Scalar) | http://localhost:8180/api/docs?ui=scalar (ou Swagger UI / ReDoc) |
| OpenAPI 3.2 brut | `curl -H 'Accept: application/vnd.openapi+json' localhost:8180/api/docs` |
| Tests de l'API | `make test` (18 tests, base `pokedex_test` recréée) |

## API Platform 5 : ce qui est montré

### Ressources séparées des entités, configuration dans des attributs dédiés

Les entités Doctrine (`api/src/Entity`) ne portent aucun attribut API. Ce sont des DTO dans `api/src/ApiResource` qui décrivent l'API publique, et le **Symfony ObjectMapper** passe des unes aux autres.

La configuration API (opérations, filtres, cache) est rangée dans **`api/src/ApiResource/Attribute/`** : des attributs maison qui étendent `ApiResource` et `QueryParameter`. API Platform les lit comme les siens (`is_a(…, ApiResource::class)`), et les classes de ressource restent courtes :

```php
#[PokemonResource]            // opérations GET / QUERY / évolutions, cache, recherches multi-propriétés
#[PokemonsOfTypeResource]     // sous-ressource /api/pokemon_types/{code}/pokemon
#[Map(source: PokemonEntity::class)]
final class Pokemon
{
    #[ComparisonParameter]    // ?attack[gte]=100, ?attack[between]=80..120
    public int $attack;
    // …
}
```

Écrite dans un constructeur PHP plutôt que dans un attribut, la configuration peut aussi factoriser : les paramètres de recherche communs à `GET` et `QUERY` sont définis une seule fois (`PokemonResource::buildSearchParameters()`).

| Notion | Fichier |
|---|---|
| Attributs de ressource : `PokemonResource`, `PokemonsOfTypeResource`, `PokemonTypeResource`, `TeamResource` | `api/src/ApiResource/Attribute/` |
| Attributs de paramètre : `ComparisonParameter`, `ExactParameter`, `BooleanParameter`, `SearchParameter` | `api/src/ApiResource/Attribute/` |
| `stateOptions` + `#[Map(source:)]` : provider Doctrine sur l'entité, mapping vers le DTO | `api/src/ApiResource/Attribute/PokemonResource.php`, `api/src/ApiResource/Pokemon.php:28` |
| Collection imbriquée : `#[Map(source: 'types', transform: new MapCollection(...))]` | `api/src/ApiResource/Pokemon.php:124` |
| Relation exposée en IRI sans chargement : `transform: [self::class, 'createReference']` | `api/src/ApiResource/Pokemon.php:130` |
| **POST** avec DTO d'entrée + **processor classique** (`map: false`) | `api/src/ApiResource/Attribute/TeamResource.php:53`, `api/src/State/CreateTeamProcessor.php` |
| **PATCH** sans aucun code : DTO d'entrée `#[Map(target: Team::class)]` appliqué sur l'entité par l'ObjectMapper | `api/src/ApiResource/Attribute/TeamResource.php:59`, `api/src/ApiResource/Input/RenameTeam.php` |
| **Opération personnalisée + provider classique** : `/api/pokemon/{id}/evolutions` renvoie toute la famille d'évolution (branches comprises) | `api/src/ApiResource/Attribute/PokemonResource.php:55`, `api/src/State/EvolutionFamilyProvider.php` |
| Validation métier sur le DTO d'entrée (Count, Unique avec `normalizer`, Callback) → 422 | `api/src/ApiResource/Input/CreateTeam.php` |
| Sous-ressource `/api/pokemon_types/{code}/pokemon` (`Link` + `toProperty`) | `api/src/ApiResource/Attribute/PokemonsOfTypeResource.php` |
| **Filtre personnalisé** « possède tous ces types » (`MEMBER OF`, sans jointure) | `api/src/Filter/AllTypesFilter.php` |
| **Extensions Doctrine** : tri stable, préchargement des relations | `api/src/Doctrine/StableSortExtension.php`, `api/src/Doctrine/FetchJoinExtension.php` |

### Filtres et tris

Tous les filtres utilisent le système `QueryParameter` avec les filtres « standalone ». Aucun `#[ApiFilter]`, aucun `SearchFilter`/`OrderFilter`/`RangeFilter`/`BooleanFilter` : ceux-ci sont dépréciés et seront supprimés en 6.0.

| Filtre | Exemple | Déclaré dans |
|---|---|---|
| `FreeTextQueryFilter` + `OrFilter` + `PartialSearchFilter` | `?q=dra` (nom, slug ou espèce) | `PokemonResource.php:77` |
| `ExactFilter` sur une relation, **au moins un** des types | `?type=fire`, `?type[]=fire&type[]=dragon` | `PokemonResource.php:83` |
| Filtre maison `AllTypesFilter`, **tous** les types | `?allTypes[]=fire&allTypes[]=flying` → Dracaufeu, Sulfura, Ho-Oh… | `PokemonResource.php:85` |
| `ExistsFilter` standalone (5.0) | `?hasPreEvolution=true` | `PokemonResource.php:87` |
| `SortFilter` sur 12 propriétés, remplace `OrderFilter` | `?order[speed]=desc`, `?order[weight]=asc` | `PokemonResource.php:89` |
| `#[ComparisonParameter]` : `ComparisonFilter` avec **`[between]`** (5.0) sur les 6 stats, la puissance, la taille, le poids | `?attack[gte]=120`, `?baseStatTotal[between]=580..700` | `Pokemon.php` |
| `#[ExactParameter]` : une ou plusieurs valeurs | `?generation[]=1&generation[]=2`, `?evolutionStage=0` | `Pokemon.php` |
| `#[BooleanParameter]` : `ExactFilter` typé booléen | `?legendary=true`, `?mythical=false`, `?fullyEvolved=true` | `Pokemon.php` |
| `#[SearchParameter]` : `PartialSearchFilter` limité à une opération | `?name=chu`, `/api/teams?name=spect` | `Pokemon.php`, `Team.php` |
| `DateFilter` standalone (5.0) | `/api/teams?createdAt[after]=2026-10-01` | `TeamResource.php:47` |

`evolutionStage`, `fullyEvolved` et `pokemonCount` sont **calculés à l'import** depuis l'instantané (donc aussi hors ligne), puis stockés : ils deviennent filtrables en SQL sans requête supplémentaire.

### Méthode HTTP `QUERY` (RFC 10008)

Avec `QUERY`, les critères voyagent dans le corps de la requête au lieu de l'URL. Le front s'en sert pour toutes ses recherches (`front/src/Api/PokedexApi.php:42`).

```bash
curl -X QUERY 'localhost:8180/api/pokemon?page=1' -H 'Content-Type: application/json' -H 'Accept: application/ld+json' \
     -d '{"allTypes":["psychic","fairy"],"speed":{"gte":80},"order":{"baseStatTotal":"desc"}}'
# → Psy ET Fée, Vitesse ≥ 80 : Tokopiyon, Gardevoir…
```

### Autres points

* **JSON:API** : `use_iri_as_id: false` est le défaut 5.0. `data.id` contient l'identifiant et l'IRI passe dans `data.links.self` (`curl -H 'Accept: application/vnd.api+json' localhost:8180/api/pokemon/25`).
* **OpenAPI 3.2** et **Scalar API Reference** (`enable_scalar`).
* **`symfony/type-info`** remplace l'ancien `PropertyInfo\Type` (voir `nativeType: new BuiltinType(...)`).
* **`api-platform/test`** : `ApiTestCase` vit dans son propre paquet (`use ApiPlatform\Test\ApiTestCase`), voir `api/tests/Api`.
* Messages de validation en français (`default_locale: fr`).

### Points découverts en construisant la démo (5.0.2)

1. **422 sur erreur de dénormalisation et DTO d'entrée** : en 5.0, une erreur de type sur une propriété portant une contrainte doit donner un 422. Mais `DenormalizationViolationFactory` lit les contraintes de la *classe de ressource*, pas du DTO `input`. Résultat : `POST /api/teams {"name": 42}` renvoie toujours **400**, car les contraintes sont dans `CreateTeam`, pas dans `Team`.
2. **ObjectMapper en lecture inversée** : quand seul le DTO déclare `#[Map(source: Entity::class)]`, un `transform` sur une propriété de même nom n'est appliqué que si `source:` est écrit explicitement (`#[Map(source: 'types', transform: ...)]`).
3. **DELETE et ObjectMapper** : par défaut, la ressource est re-mappée vers l'entité avant la suppression, ce qui déclenche les `transform` à l'envers. `new Delete(map: false)` laisse Doctrine charger puis supprimer l'entité directement.
4. **`SortFilter` sur plusieurs clés** : les tris sont appliqués dans l'ordre de déclaration des `properties`, pas dans l'ordre de l'URL. Le front n'envoie donc qu'une clé de tri.
5. **Tri et pagination** : dès qu'un tri est demandé, `OrderExtension` n'ajoute plus son ordre par défaut. Les ex aequo (même puissance…) n'ont alors pas d'ordre garanti, et PostgreSQL peut les répéter ou les sauter d'une page à l'autre. `StableSortExtension` ajoute l'identifiant comme critère de départage (testé : 1025 identifiants uniques sur 6 pages).
6. **Fetch join et filtres** : précharger `types` en réutilisant la jointure d'un filtre `?type=fire` tronquerait la collection (Dracaufeu n'aurait plus que Feu). `FetchJoinExtension` crée ses propres alias, après les filtres.
7. **`QUERY` et pagination** : `page` et `itemsPerPage` restent dans l'URL. Dans le corps, ils sont ignorés.
8. **Docblocks** : pour qu'un `array $members` documenté `list<Pokemon>` soit dénormalisé à partir d'IRI, il faut installer `phpstan/phpdoc-parser` et `phpdocumentor/type-resolver`.

## Symfony UX : ce qui est montré

Tout passe par **AssetMapper** et l'importmap : pas de Node, pas de build.

| Fonctionnalité | Démonstration | Fichiers |
|---|---|---|
| **Live Component** + `LiveProp(writable, url: true)` | Recherche du Pokédex : texte, types (« au moins un » ou « tous »), générations, catégorie, stade d'évolution, minimum par statistique (curseurs), fourchette de puissance, 12 tris avec sens, 24/48/96 par page. Toute la recherche est dans l'URL (`/?types[0]=fire&types[1]=flying&typeMode=all`) et traduite en filtres API | `front/src/Twig/Components/PokedexSearch.php`, `templates/components/PokedexSearch.html.twig` |
| `LiveAction` + `LiveArg` | Vue **tableau** dont les en-têtes trient (second clic = sens inverse), pastilles des filtres actifs retirables une par une, pagination | `PokedexSearch.php:129` (`sortBy`), `:147` (`removeFilter`), `templates/components/_pokedex_table.html.twig` |
| `computed.*`, `data-loading` | Un seul appel API par rendu, indicateur de chargement | `templates/components/PokedexSearch.html.twig` |
| URL propre | Les LiveProp restées à leur valeur par défaut sont retirées de l'URL renvoyée au navigateur | `front/src/EventSubscriber/LiveUrlCleanerSubscriber.php` |
| **Formulaire Symfony en Live Component** (`ComponentWithFormTrait`) | Création d'équipe avec aperçu recalculé à chaque saisie. Les violations 422 de l'API sont replacées sur les champs | `front/src/Twig/Components/TeamBuilder.php:60` |
| **UX Autocomplete** | Sélection des Pokémon avec Tom Select (`'autocomplete' => true`) | `front/src/Form/TeamType.php:42` |
| Live Component avec objet hydraté par le Serializer | Renommage d'une équipe en ligne via `PATCH`, erreur 422 affichée sans rechargement | `front/src/Twig/Components/TeamCard.php:25` |
| **Turbo Drive** + View Transitions | Navigation sans rechargement, l'illustration est animée de la grille à la fiche | `templates/base.html.twig:8`, `view-transition-name` dans `PokemonCard` |
| **Turbo Frame** promu en navigation (`data-turbo-action="advance"`) | Clic sur une évolution : seule la fiche est remplacée, la page **garde sa position de défilement**, l'URL, le titre et l'historique suivent. Les autres liens (`target="_top"`) restent des navigations complètes | `templates/pokemon/show.html.twig`, `templates/components/EvolutionChain.html.twig` |
| **Turbo Frame** paresseux | « Autres Pokémon du même type », chargé quand il devient visible | `templates/pokemon/show.html.twig:107` |
| **Fiche Pokémon** | Chaîne d'évolution par stade avec ses conditions (« Niveau 16 », « Pierre Feu », « Échange en tenant… »), faiblesses et résistances, profils de stats proches, voisins dans le Pokédex, liens vers le Pokédex filtré (type, génération), bouton « Créer une équipe avec… » | `templates/pokemon/show.html.twig`, `front/src/Pokedex/PokemonInsights.php`, composants `EvolutionChain`, `TypeDefenses`, `PokemonMini` |
| **Turbo Stream** | Suppression d'une équipe : la carte est retirée du DOM (`<twig:Turbo:Stream:Remove>`) | `front/src/Controller/TeamController.php:44`, `templates/team/delete.stream.html.twig` |
| **Twig Components** anonymes | `<twig:PokemonCard>`, `<twig:TypeBadge>`, `<twig:StatBar>` | `front/templates/components/` |
| **UX Chart.js** | Radar des statistiques, répartition par génération et par type, les 10 plus et les 10 moins puissants | `front/src/Controller/PokedexController.php:34`, `StatsController.php` |
| **UX Icons** | Icônes Tabler via `ux_icon()`, enregistrées localement dans `assets/icons` (pas d'appel réseau au runtime) | partout |
| **Stimulus** maison | `cry` (joue le cri), `keyboard-nav` (← / → entre Pokémon, via Turbo), `range-value` (valeur d'un curseur pendant le glissement), `collapse-on-mobile` (filtres repliés sur mobile), `dismiss` (fermeture auto des flashs) | `front/assets/controllers/` |

Le front n'appelle l'API **que côté serveur**, avec un client HTTP dédié (`framework.http_client.scoped_clients.pokedex.client`). Les réponses JSON-LD sont dénormalisées en objets `readonly` par le Serializer (`front/src/Api`). Le panneau « Requête envoyée à l'API » du Pokédex montre la requête `QUERY` exacte.

## Optimisations

Mesures sur un poste de développement (requêtes « à chaud »).

| Optimisation | Avant | Après |
|---|---|---|
| **Requêtes N+1** : `FetchJoinExtension` précharge `types` (et `members.types` pour les équipes) ; `pokemonCount` calculé à l'import au lieu d'un `COUNT` par type imbriqué | 65 requêtes SQL pour une page de 24 Pokémon, 40 pour les équipes | **3** requêtes |
| `pokemonCount` stocké (avant : `EXTRA_LAZY`, et encore avant : collection chargée) | `/api/pokemon_types` en 1,19 s | 0,06 s |
| **Cache HTTP** RFC 9111 : l'API envoie `Cache-Control: public, max-age=3600` + `ETag` sur les Pokémon, types et évolutions (pas sur les équipes) ; le client du front est un `CachingHttpClient` (`framework.http_client…caching`) | fiche Pokémon : 3 appels API à chaque affichage | 0 appel tant que le cache est valide (0,52 s → 0,14 s) |
| **Mode worker FrankenPHP** : Symfony démarre une fois puis sert les requêtes depuis la mémoire ; `watch` recharge à chaque modification de fichier | `/api/pokemon_types` 60 ms, fiche front 140 ms | **8 ms**, **35 ms** |
| Tri stable par `id` | pagination triée non déterministe | 1025 identifiants uniques sur 6 pages |
| Index SQL `generation`, `base_stat_total` | — | sans effet mesurable à 1025 lignes, utiles si la table grossit |

Temps de réponse actuels : liste triée de l'API ~30 ms, équipes ~20 ms, famille d'évolution ~25 ms ; côté front, Pokédex ~70 ms, fiche ~35 ms, statistiques ~20 ms.

* `CachingHttpClient` ne met en cache que `GET` et `HEAD` : la recherche du Pokédex, en `QUERY`, interroge toujours l'API.
* Après un import, `make api-import` vide aussi les caches du front (`make front-cache-clear`).
* Pour revenir au mode PHP classique : `FRANKENPHP_CONFIG: ''` dans `compose.yaml`.

## Structure

```
.
├── Makefile                 # dc-up, dc-init, test…
├── compose.yaml             # database (postgres:17), api, front
├── docker/php/              # image FrankenPHP PHP 8.5 commune (UID/GID de l'hôte)
├── api/                     # Symfony 8.1 + API Platform 5.0
│   ├── src/Entity/          # persistance Doctrine
│   ├── src/ApiResource/     # ressources API (DTO), Attribute/ (configuration), Input/
│   ├── src/State/           # processor et provider classiques
│   ├── src/Filter/          # filtre personnalisé AllTypesFilter
│   ├── src/Doctrine/        # extensions : tri stable, fetch join
│   ├── src/Command/         # app:pokedex:import (PokeAPI → instantané → base)
│   ├── data/pokedex.json    # instantané PokeAPI versionné
│   └── tests/Api/           # ApiTestCase (api-platform/test)
└── front/                   # Symfony 8.1 + Symfony UX 3.5 (AssetMapper)
    ├── src/Api/             # client de l'API (fetch…, search…) + modèles
    ├── src/Pokedex/         # calculs de la fiche (évolutions, défenses, similaires)
    ├── src/Twig/Components/ # Live Components
    └── templates/components # Twig Components anonymes
```
