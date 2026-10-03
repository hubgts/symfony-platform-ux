<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Api\ApiValidationException;
use App\Api\Model\Team;
use App\Api\PokedexApi;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Carte d'équipe avec renommage en ligne : la LiveAction envoie un PATCH (application/merge-patch+json) à l'API,
 * et affiche l'éventuelle erreur 422 sans rechargement.
 */
#[AsLiveComponent]
final class TeamCard
{
    use DefaultActionTrait;

    /** L'objet Team est (dé)sérialisé entre deux requêtes Live grâce au Serializer. */
    #[LiveProp(useSerializerForHydration: true)]
    public Team $team;

    #[LiveProp]
    public bool $editing = false;

    #[LiveProp(writable: true)]
    public string $name = '';

    public ?string $error = null;

    public function __construct(private readonly PokedexApi $api)
    {
    }

    #[LiveAction]
    public function edit(): void
    {
        $this->editing = true;
        $this->name = $this->team->name;
    }

    #[LiveAction]
    public function cancel(): void
    {
        $this->editing = false;
    }

    #[LiveAction]
    public function rename(): void
    {
        try {
            $this->team = $this->api->renameTeam($this->team->id, $this->name);
            $this->editing = false;
        } catch (ApiValidationException $e) {
            $this->error = $e->getMessage();
        }
    }
}
