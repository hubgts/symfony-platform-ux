<?php

declare(strict_types=1);

namespace App\Api\Model;

/** Les neuf générations, avec la région où elles se déroulent. */
enum Generation: int
{
    case Kanto = 1;
    case Johto = 2;
    case Hoenn = 3;
    case Sinnoh = 4;
    case Unys = 5;
    case Kalos = 6;
    case Alola = 7;
    case Galar = 8;
    case Paldea = 9;

    public function toRoman(): string
    {
        return ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'][$this->value - 1];
    }

    public function getLabel(): string
    {
        return \sprintf('Gén. %s · %s', $this->toRoman(), $this->name);
    }
}
