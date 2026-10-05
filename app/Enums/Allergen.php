<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Les 14 allergènes à déclaration obligatoire (PLAN.md §6.2). Un produit
 * déclare séparément ceux qu'il "contient" et ceux pour lesquels il y a
 * "traces possibles" (deux colonnes JSON distinctes sur `products`).
 */
enum Allergen: string
{
    case Gluten = 'gluten';
    case Crustaceans = 'crustaceans';
    case Eggs = 'eggs';
    case Fish = 'fish';
    case Peanuts = 'peanuts';
    case Soy = 'soy';
    case Milk = 'milk';
    case Nuts = 'nuts';
    case Celery = 'celery';
    case Mustard = 'mustard';
    case Sesame = 'sesame';
    case Sulphites = 'sulphites';
    case Lupin = 'lupin';
    case Molluscs = 'molluscs';

    public function label(): string
    {
        return match ($this) {
            self::Gluten => 'Gluten',
            self::Crustaceans => 'Crustacés',
            self::Eggs => 'Œufs',
            self::Fish => 'Poissons',
            self::Peanuts => 'Arachides',
            self::Soy => 'Soja',
            self::Milk => 'Lait',
            self::Nuts => 'Fruits à coque',
            self::Celery => 'Céleri',
            self::Mustard => 'Moutarde',
            self::Sesame => 'Graines de sésame',
            self::Sulphites => 'Anhydride sulfureux et sulfites',
            self::Lupin => 'Lupin',
            self::Molluscs => 'Mollusques',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $allergen): array => ['value' => $allergen->value, 'label' => $allergen->label()],
            self::cases(),
        );
    }
}
