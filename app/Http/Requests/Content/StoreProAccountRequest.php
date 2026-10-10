<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use App\Rules\Siret;
use App\Rules\Turnstile;
use App\Settings\ShopSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du formulaire « Demande de compte professionnel » (T26 B1).
 * Seuls le nom, le prénom, le téléphone, l'email, le consentement et
 * Turnstile sont obligatoires ; le reste est facultatif mais validé s'il est
 * rempli. Toute valeur est contrôlée côté serveur ; la liste des types
 * d'activité est relue en base, jamais prise du navigateur.
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

        $this->merge([
            'siret' => $siret === '' ? null : $siret,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $activityTypes = app(ShopSettings::class)->pro_activity_types;

        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'siret' => ['nullable', new Siret],
            'activity_type' => ['nullable', 'string', Rule::in($activityTypes)],
            'activity_other' => ['nullable', 'string', 'max:150'],
            'contact_last_name' => ['required', 'string', 'max:100'],
            'contact_first_name' => ['required', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9 +().-]{6,30}$/'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
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
            'activity_type.in' => 'Ce type d\'activité n\'est pas proposé.',
            'contact_last_name.required' => 'Merci d\'indiquer votre nom.',
            'contact_first_name.required' => 'Merci d\'indiquer votre prénom.',
            'email.required' => 'Merci d\'indiquer votre adresse email.',
            'email.email' => 'Cette adresse email n\'est pas valide.',
            'phone.required' => 'Merci d\'indiquer votre numéro de téléphone.',
            'phone.regex' => 'Ce numéro de téléphone n\'est pas valide.',
            'postal_code.regex' => 'Le code postal doit comporter 5 chiffres.',
            'description.max' => 'La description est trop longue (2 000 caractères maximum).',
            'consent.accepted' => 'Vous devez accepter le traitement de vos données pour que nous étudiions votre demande.',
            'cf-turnstile-response.required' => 'Merci de valider la vérification anti-robot.',
        ];
    }
}
