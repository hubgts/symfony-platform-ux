<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Test\ApiTestCase;
use App\ApiResource\Pokemon;

/**
 * Tests HTTP de la ressource Pokemon. ApiTestCase vit désormais dans le paquet api-platform/test (5.0).
 * La base de test est alimentée par `make test` (instantané PokeAPI).
 */
final class PokemonTest extends ApiTestCase
{
    public function testGetCollectionIsMappedFromEntities(): void
    {
        static::createClient()->request('GET', '/api/pokemon');

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['totalItems' => 1025, 'member' => [['@id' => '/api/pokemon/1', 'name' => 'Bulbizarre']]]);
        self::assertMatchesResourceCollectionJsonSchema(Pokemon::class);
    }

    public function testFreeTextAndTypeFilters(): void
    {
        $response = static::createClient()->request('GET', '/api/pokemon?q=dra&type=dragon&generation=1&order[id]=asc');

        self::assertSame(['Minidraco', 'Draco', 'Dracolosse'], array_column($response->toArray()['member'], 'name'));
    }

    public function testComparisonFilterBetween(): void
    {
        $response = static::createClient()->request('GET', '/api/pokemon?generation=1&baseStatTotal[between]=600..700&order[baseStatTotal]=desc');
        $names = array_column($response->toArray()['member'], 'name');

        // Dracolosse et Mew sont ex aequo à 600 : seul le premier rang est garanti.
        self::assertSame('Mewtwo', $names[0]);
        self::assertEqualsCanonicalizing(['Mewtwo', 'Dracolosse', 'Mew'], $names);
    }

    public function testExistsFilterOnRelation(): void
    {
        $response = static::createClient()->request('GET', '/api/pokemon?type=dragon&generation=1&hasPreEvolution=false');

        self::assertSame(['Minidraco'], array_column($response->toArray()['member'], 'name'));
    }

    public function testHttpQueryMethodReadsCriteriaFromBody(): void
    {
        $response = static::createClient()->request('QUERY', '/api/pokemon', [
            'json' => ['type' => 'ghost', 'generation' => 1, 'order' => ['speed' => 'desc']],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(['Ectoplasma', 'Spectrum', 'Fantominus'], array_column($response->toArray()['member'], 'name'));
    }

    public function testJsonApiUsesIdentifierAsId(): void
    {
        static::createClient()->request('GET', '/api/pokemon/25', ['headers' => ['Accept' => 'application/vnd.api+json']]);

        // Défaut 5.0 : l'identifiant dans data.id, l'IRI dans data.links.self.
        self::assertJsonContains(['data' => ['id' => '25', 'links' => ['self' => '/api/pokemon/25']]]);
    }

    public function testSubresourceByType(): void
    {
        $response = static::createClient()->request('GET', '/api/pokemon_types/ghost/pokemon');

        self::assertContains('Ectoplasma', array_column($response->toArray()['member'], 'name'));
    }

    public function testGenerationFilterAcceptsSeveralValues(): void
    {
        static::createClient()->request('GET', '/api/pokemon?generation[]=1&generation[]=2');

        self::assertJsonContains(['totalItems' => 251]);
    }

    public function testEvolutionFamilyFromCustomProvider(): void
    {
        $response = static::createClient()->request('GET', '/api/pokemon/135/evolutions');
        $family = array_column($response->toArray()['member'], null, 'name');

        // Toute la famille d'Évoli, quelle que soit la forme demandée, avec les conditions d'évolution.
        self::assertCount(9, $family);
        self::assertSame('Évoli', array_key_first($family));
        self::assertSame('Pierre Foudre', $family['Voltali']['evolutionCondition']);
        self::assertSame('/api/pokemon/133', $family['Voltali']['evolvesFrom']);
    }

    public function testTypeExposesDamageMultipliers(): void
    {
        static::createClient()->request('GET', '/api/pokemon_types/ghost');

        self::assertJsonContains(['name' => 'Spectre', 'defense' => ['normal' => 0, 'fighting' => 0, 'ghost' => 2, 'dark' => 2]]);
    }

    public function testAllTypesFilterRequiresEveryType(): void
    {
        $response = static::createClient()->request('GET', '/api/pokemon?allTypes[]=fire&allTypes[]=flying&generation=1');

        // Dracaufeu (Feu/Vol) et Sulfura (Feu/Vol), mais pas Salamèche (Feu seul) ni Roucool (Vol seul).
        self::assertSame(['Dracaufeu', 'Sulfura'], array_column($response->toArray()['member'], 'name'));
    }

    public function testTypeFilterKeepsEveryTypeOfTheMatchingPokemon(): void
    {
        $response = static::createClient()->request('GET', '/api/pokemon?type=fire&q=dracaufeu');

        // Le fetch join des types utilise son propre alias : le filtre ne tronque pas la collection hydratée.
        self::assertSame(['fire', 'flying'], array_column($response->toArray()['member'][0]['types'], 'code'));
    }

    public function testEvolutionStageAndFullyEvolvedFilters(): void
    {
        $response = static::createClient()->request('GET', '/api/pokemon?generation=1&evolutionStage=0&fullyEvolved=true&mythical=false&legendary=false&order[id]=asc&itemsPerPage=3');

        // Canarticho n'y est pas : il évolue en Palarticho (Gén. VIII).
        self::assertSame(['Kangourex', 'Scarabrute', 'Tauros'], array_column($response->toArray()['member'], 'name'));
    }

    public function testSortIsStableAcrossPages(): void
    {
        $client = static::createClient();
        $ids = [];
        foreach ([1, 2, 3, 4, 5, 6] as $page) {
            $data = $client->request('GET', '/api/pokemon?order[baseStatTotal]=desc&itemsPerPage=200&page='.$page)->toArray();
            array_push($ids, ...array_column($data['member'], 'id'));
        }

        // Sans départage par l'identifiant, des ex aequo pouvaient apparaître sur deux pages ou sur aucune.
        self::assertCount(1025, array_unique($ids));
    }

    public function testReadOnlyResourcesAreHttpCacheable(): void
    {
        static::createClient()->request('GET', '/api/pokemon/25');

        self::assertResponseHeaderSame('cache-control', 'max-age=3600, public, s-maxage=3600');
        self::assertResponseHasHeader('etag');
    }
}
