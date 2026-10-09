<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Page « Professionnels » (T26 B1) : texte de présentation (repris tel quel de
 * la page B2B du Shopify actuel, éditable en BO) et liste des types d'activité.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('shop.pro_intro_html', <<<'HTML'
<p style="text-align: center;">Vous êtes <strong>artisan boulanger</strong>, <strong>restaurateur</strong>, <strong>traiteur</strong> ou <strong>professionnel des métiers de bouche</strong> ?<br>Rejoignez notre <strong>Espace Professionnels</strong> et profitez de conditions exclusives pour commander nos produits sans gluten, pensés pour répondre aux exigences des pros :</p>
<p style="text-align: center;">Prix dégressifs selon les volumes<br>Livraison rapide et soignée partout en France<br>Produits artisanaux garantis sans gluten<br>Accompagnement personnalisé pour vos besoins spécifiques</p>
<p style="text-align: center;">Remplissez le formulaire ci-dessous pour être recontacté(e) dans les plus brefs délais.</p>
HTML);
        $this->migrator->add('shop.pro_activity_types', [
            'Boulangerie / Pâtisserie',
            'Restaurant',
            'Burger',
            'Pizzeria',
            'Épicerie / commerce',
            'Autre',
        ]);
    }
};
