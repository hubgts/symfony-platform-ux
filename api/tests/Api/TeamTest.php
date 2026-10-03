<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Test\ApiTestCase;

final class TeamTest extends ApiTestCase
{
    public function testCreateRenameAndDeleteTeam(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/teams', ['json' => [
            'name' => 'Équipe test',
            'trainer' => 'Ondine',
            'members' => ['/api/pokemon/120', '/api/pokemon/121'],
        ]]);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['name' => 'Équipe test', 'power' => 860, 'members' => [['name' => 'Stari'], ['name' => 'Staross']]]);
        $iri = $response->toArray()['@id'];

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Équipe renommée'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['name' => 'Équipe renommée', 'trainer' => 'Ondine']);

        $client->request('DELETE', $iri);
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', $iri);
        self::assertResponseStatusCodeSame(404);
    }

    public function testBusinessRulesReturn422(): void
    {
        static::createClient()->request('POST', '/api/teams', ['json' => [
            'name' => 'Légendes',
            'trainer' => 'Red',
            'members' => ['/api/pokemon/150', '/api/pokemon/151', '/api/pokemon/150'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [
            ['propertyPath' => 'members', 'message' => 'Un seul Pokémon légendaire ou fabuleux par équipe (Mewtwo, Mew).'],
            ['propertyPath' => 'members', 'message' => 'Un Pokémon ne peut figurer qu\'une fois dans l\'équipe.'],
        ]]);
    }

    public function testDateFilter(): void
    {
        $response = static::createClient()->request('GET', '/api/teams?createdAt[after]='.new \DateTimeImmutable('-3 days')->format('Y-m-d'));

        self::assertSame(["Arène d'Argenta"], array_column($response->toArray()['member'], 'name'));
    }
}
