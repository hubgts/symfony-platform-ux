<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\ApiResource\Pokemon;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Corps attendu par POST /api/teams. Les membres sont envoyés sous forme d'IRI ("/api/pokemon/25") :
 * API Platform les résout en ressources {@see Pokemon} avant validation.
 */
final class CreateTeam
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 80)]
    #[ApiProperty(example: 'Les Invincibles')]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[ApiProperty(example: 'Sacha')]
    public string $trainer = '';

    /** @var list<Pokemon> */
    #[Assert\Count(min: 1, max: 6, minMessage: 'Une équipe compte au moins {{ limit }} Pokémon.', maxMessage: 'Une équipe compte au plus {{ limit }} Pokémon.')]
    #[Assert\Unique(message: 'Un Pokémon ne peut figurer qu\'une fois dans l\'équipe.', normalizer: [self::class, 'extractMemberId'])]
    #[ApiProperty(example: ['/api/pokemon/25', '/api/pokemon/6'])]
    public array $members = [];

    public static function extractMemberId(Pokemon $pokemon): int
    {
        return $pokemon->id;
    }

    /** Règle métier : un seul Pokémon légendaire ou fabuleux par équipe. */
    #[Assert\Callback]
    public function validateLegendaries(ExecutionContextInterface $context): void
    {
        $legendaries = [];
        foreach ($this->members as $pokemon) {
            if ($pokemon->legendary || $pokemon->mythical) {
                $legendaries[$pokemon->id] = $pokemon;
            }
        }

        if (\count($legendaries) > 1) {
            $context->buildViolation('Un seul Pokémon légendaire ou fabuleux par équipe ({{ names }}).')
                ->setParameter('{{ names }}', implode(', ', array_map(static fn (Pokemon $p): string => $p->name, $legendaries)))
                ->atPath('members')
                ->addViolation();
        }
    }
}
