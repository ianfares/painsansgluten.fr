<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProRequestStatus;
use Database\Factories\ProAccountRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Demande de compte professionnel (T26 B1). `status`, `admin_comment` et
 * `processed_at` ne sont volontairement pas assignables en masse : seul
 * App\Actions\Pro\ProcessProAccountRequestAction les modifie.
 *
 * @property ProRequestStatus $status
 * @property string $email
 */
#[Fillable([
    'company_name', 'siret', 'vat_number', 'activity_type', 'activity_other',
    'contact_first_name', 'contact_last_name', 'job_title', 'email', 'phone',
    'address_line1', 'postal_code', 'city', 'products_of_interest', 'volumes',
    'description', 'consent_at',
])]
class ProAccountRequest extends Model
{
    /** @use HasFactory<ProAccountRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProRequestStatus::class,
            'products_of_interest' => 'array',
            'consent_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public const PIZZA_DOUGH = 'Pâte à pizza crue';

    public const OTHER = 'Autre';

    /**
     * Choix de la case « Produits qui vous intéressent » : catégories
     * actives, puis « Pâte à pizza crue » et « Autre ».
     *
     * @return list<string>
     */
    public static function productOptions(): array
    {
        return [
            ...Category::query()->where('is_active', true)->orderBy('position')->pluck('name')->all(),
            self::PIZZA_DOUGH,
            self::OTHER,
        ];
    }

    public function contactName(): string
    {
        return "{$this->contact_first_name} {$this->contact_last_name}";
    }
}
