<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Api\ApiValidationException;
use App\Api\Model\Pokemon;
use App\Api\PokedexApi;
use App\Form\TeamType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Formulaire Symfony rendu en Live Component : validation à la volée, aperçu de l'équipe pendant la saisie,
 * puis POST vers l'API. Les violations 422 renvoyées par API Platform sont replacées sur les bons champs.
 */
#[AsLiveComponent]
final class TeamBuilder extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    /** Pokémon pré-sélectionné, depuis le bouton « Créer une équipe avec… » de la fiche. */
    #[LiveProp]
    public ?int $with = null;

    public function __construct(private readonly PokedexApi $api)
    {
    }

    protected function instantiateForm(): FormInterface
    {
        $members = array_values(array_filter($this->api->fetchAllPokemon(), fn (Pokemon $p): bool => $p->id === $this->with));

        return $this->createForm(TeamType::class, $members ? ['members' => $members] : null);
    }

    /** @return list<Pokemon> Membres sélectionnés, lus dans les valeurs brutes du formulaire (avant soumission). */
    public function getPreview(): array
    {
        $ids = array_map('intval', (array) ($this->formValues['members'] ?? []));

        return array_values(array_filter($this->api->fetchAllPokemon(), static fn (Pokemon $p): bool => \in_array($p->id, $ids, true)));
    }

    #[LiveAction]
    public function save(): ?Response
    {
        $this->submitForm();
        $form = $this->getForm();
        /** @var array{name: string, trainer: string, members: list<Pokemon>} $data */
        $data = $form->getData();

        try {
            $team = $this->api->createTeam($data['name'], $data['trainer'], array_map(static fn (Pokemon $p): int => $p->id, $data['members']));
        } catch (ApiValidationException $e) {
            foreach ($e->violations as $violation) {
                $field = $form->has($violation['propertyPath']) ? $form->get($violation['propertyPath']) : $form;
                $field->addError(new FormError($violation['message']));
            }
            // submitForm() a déjà construit la FormView : on la jette pour qu'elle soit recréée avec ces erreurs.
            $this->formView = null;

            return null;
        }

        $this->addFlash('success', \sprintf('Équipe « %s » créée par l\'API (%d de puissance).', $team->name, $team->power));

        return $this->redirectToRoute('team_index');
    }
}
