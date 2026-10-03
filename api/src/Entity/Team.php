<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Équipe de six Pokémon au plus, créée par les dresseurs via l'API.
 * La représentation publique est {@see \App\ApiResource\Team}.
 */
#[ORM\Entity(repositoryClass: TeamRepository::class)]
class Team
{
    #[ORM\Id, ORM\Column(type: UuidType::NAME, unique: true)]
    public private(set) Uuid $id;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    /** @var Collection<int, Pokemon> */
    #[ORM\ManyToMany(targetEntity: Pokemon::class)]
    #[ORM\JoinTable(name: 'team_member')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    public private(set) Collection $members;

    public function __construct(
        #[ORM\Column(length: 80)]
        public string $name,

        #[ORM\Column(length: 80)]
        public string $trainer,

        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->members = new ArrayCollection();
    }

    /** @param iterable<Pokemon> $members */
    public function replaceMembers(iterable $members): void
    {
        $this->members->clear();
        foreach ($members as $member) {
            $this->members->add($member);
        }
    }
}
