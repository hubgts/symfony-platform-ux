<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use App\Entity\Team as TeamEntity;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps attendu par PATCH /api/teams/{id}. Aucun processor à écrire : l'ObjectMapper d'API Platform
 * applique ce DTO sur l'entité chargée, Doctrine persiste, puis l'entité est re-mappée vers la ressource.
 */
#[Map(target: TeamEntity::class)]
final class RenameTeam
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 80)]
    public string $name = '';
}
