<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use App\Models\ProAccountRequest;
use App\Rules\FrenchVatNumber;
use App\Rules\Siret;
use App\Rules\Turnstile;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du formulaire « Demande de compte professionnel » (T26 B1).
 * Toute valeur est contrôlée côté serveur ; les listes de choix (types
 * d'activité, produits) sont relues en base, jamais prises du navigateur.
 */
class StoreProAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $siret = preg_replace('/[\s.]/', '', (string) $this->input('siret'));
        $vat = strtoupper((string) preg_replace('/[\s.]/', '', (string) $this->input('vat_number')));

        $this->merge([
            'siret' => $siret,
            'vat_number' => $vat === '' ? null : $vat,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $activityTypes = app(ShopSettings::class)->pro_activity_types;

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'siret' => ['required', new Siret],
            'vat_number' => ['nullable', new FrenchVatNumber],
            'activity_type' => ['required', 'string', Rule::in($activityTypes)],
            'activity_other' => [Rule::requiredIf($this->input('activity_type') === ProAccountRequest::OTHER), 'nullable', 'string', 'max:150'],
            'contact_last_name' => ['required', 'string', 'max:100'],
            'contact_first_name' => ['required', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9 +().-]{6,30}$/'],
            'address_line1' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^\d{5}$/'],
            'city' => ['required', 'string', 'max:100'],
            'products_of_interest' => ['nullable', 'array'],
            'products_of_interest.*' => ['string', Rule::in(ProAccountRequest::productOptions())],
            'volumes' => ['nullable', 'string', 'max:1000'],
            'description' => ['required', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'cf-turnstile-response' => ['required', new Turnstile],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Merci d\'indiquer la raison sociale.',
            'siret.required' => 'Merci d\'indiquer votre numéro SIRET.',
            'activity_type.required' => 'Merci de choisir votre type d\'activité.',
            'activity_type.in' => 'Ce type d\'activité n\'est pas proposé.',
            'activity_other.required' => 'Merci de préciser votre activité.',
            'contact_last_name.required' => 'Merci d\'indiquer votre nom.',
            'contact_first_name.required' => 'Merci d\'indiquer votre prénom.',
            'email.required' => 'Merci d\'indiquer votre adresse email.',
            'email.email' => 'Cette adresse email n\'est pas valide.',
            'phone.required' => 'Merci d\'indiquer votre numéro de téléphone.',
            'phone.regex' => 'Ce numéro de téléphone n\'est pas valide.',
            'address_line1.required' => 'Merci d\'indiquer l\'adresse de votre établissement.',
            'postal_code.required' => 'Merci d\'indiquer le code postal.',
            'postal_code.regex' => 'Le code postal doit comporter 5 chiffres.',
            'city.required' => 'Merci d\'indiquer la ville.',
            'products_of_interest.*.in' => 'Ce produit n\'est pas proposé.',
            'description.required' => 'Merci de décrire votre besoin.',
            'description.max' => 'La description est trop longue (2 000 caractères maximum).',
            'consent.accepted' => 'Vous devez accepter le traitement de vos données pour que nous étudiions votre demande.',
            'cf-turnstile-response.required' => 'Merci de valider la vérification anti-robot.',
        ];
    }
}
