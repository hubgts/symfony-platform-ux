<?php

declare(strict_types=1);

namespace App\Api\Model;

use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class Team
{
    /**
     * @param list<Pokemon> $members
     */
    public function __construct(
        #[SerializedName('@id')]
        public string $iri,
        public string $id,
        public string $name,
        public string $trainer,
        public \DateTimeImmutable $createdAt,
        public int $power = 0,
        public array $members = [],
    ) {
    }
}
