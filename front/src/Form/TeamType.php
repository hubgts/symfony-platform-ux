<?php

declare(strict_types=1);

namespace App\Form;

use App\Api\Model\Pokemon;
use App\Api\PokedexApi;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de création d'équipe. Volontairement, seules les règles de saisie évidentes sont vérifiées ici :
 * les règles métier (6 Pokémon max, un seul légendaire, pas de doublon) sont celles de l'API, renvoyées en 422.
 */
final class TeamType extends AbstractType
{
    public function __construct(private readonly PokedexApi $api)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'équipe',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['placeholder' => 'Les Invincibles'],
            ])
            ->add('trainer', TextType::class, [
                'label' => 'Dresseur',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['placeholder' => 'Sacha'],
            ])
            ->add('members', ChoiceType::class, [
                'label' => 'Pokémon',
                'multiple' => true,
                // Symfony UX Autocomplete : le <select> devient un champ Tom Select avec recherche.
                'autocomplete' => true,
                'choices' => $this->api->fetchAllPokemon(),
                'choice_value' => static fn (?Pokemon $pokemon): ?string => $pokemon ? (string) $pokemon->id : null,
                'choice_label' => static fn (Pokemon $pokemon): string => \sprintf('#%03d %s', $pokemon->id, $pokemon->name),
                'help' => 'Jusqu\'à 6 Pokémon, un seul légendaire : ces règles sont vérifiées par l\'API.',
                'tom_select_options' => ['plugins' => ['remove_button' => []]],
            ]);
    }
}
