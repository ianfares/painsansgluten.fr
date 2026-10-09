<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FaqItem;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Contenus du 09/10/2026 (T26). Idempotent et prudent : ne remplace jamais un
 * texte déjà saisi en BO.
 *
 * - « Notre histoire » : texte d'Angélique (Facebook, 19/09), repris tel quel, publiée.
 * - « Où nous trouver » : vide, non publiée (la cliente la remplira).
 * - Textes légaux : repris du site Shopify actuel (rédigés par la cliente,
 *   sept. 2026), NON publiés : passages à adapter listés dans docs/JOURNAL.md.
 * - FAQ : brouillon rédigé à partir des seuls faits validés (politiques de la
 *   cliente, fonctionnement du site), NON publié tant qu'Angélique n'a pas relu.
 *
 *   php artisan db:seed --class=T26ContentSeeder
 */
class T26ContentSeeder extends Seeder
{
    private const PLACEHOLDER = 'Page en cours de rédaction.';

    private const LEGAL_PAGES = [
        'mentions-legales' => 'Mentions légales',
        'cgv' => 'Conditions générales de vente',
        'politique-de-confidentialite' => 'Politique de confidentialité',
        'politique-de-remboursement' => 'Politique de remboursement',
        'livraison' => 'Livraison',
    ];

    public function run(): void
    {
        $this->storyPage();
        $this->findUsPage();
        $this->legalPages();
        $this->faq();
    }

    private function storyPage(): void
    {
        Page::query()->firstOrCreate(['slug' => 'notre-histoire'], [
            'title' => 'Notre histoire',
            'is_published' => true,
            'seo_title' => 'Notre histoire — Angélique, boulangère sans gluten à Avranches',
            'seo_description' => 'Bienvenue chez Mon Sans Gluten by Angélique ! C\'est comme ça qu\'est née l\'envie de créer mon propre laboratoire sans gluten, pour continuer à faire ce que j\'aime et proposer de bons produits à ceux qui, comme moi, doivent se passer de gluten.',
            'content' => implode("\n", [
                '<p>Bienvenue chez Mon Sans Gluten by Angélique !</p>',
                '<p>Pour ceux qui ne me connaissent pas encore, je suis Angélique, boulangère de métier depuis plusieurs années.</p>',
                '<p>Étant devenue allergique au blé, j\'ai dû revoir complètement mon alimentation. Et quand on est boulangère et qu\'on aime le bon pain, ce n\'est pas toujours évident !</p>',
                '<p>C\'est comme ça qu\'est née l\'envie de créer mon propre laboratoire sans gluten, pour continuer à faire ce que j\'aime et proposer de bons produits à ceux qui, comme moi, doivent se passer de gluten.</p>',
                '<p>Pains, viennoiseries, biscuits sucrés et salés, gâteaux, tartelettes… Je vous prépare plein de bonnes choses !</p>',
                '<p>Vous pourrez retrouver mes produits sur les marchés, les commander sur mon site internet et également les découvrir chez des professionnels partenaires.</p>',
                '<p><strong>« Le gluten s\'efface, le goût reste. »</strong></p>',
            ]),
        ]);
    }

    private function findUsPage(): void
    {
        Page::query()->firstOrCreate(['slug' => 'ou-nous-trouver'], [
            'title' => 'Où nous trouver',
            'is_published' => false,
            'content' => '<p>À RENSEIGNER : marchés (lieux, jours, horaires) et professionnels partenaires.</p>',
        ]);
    }

    private function legalPages(): void
    {
        foreach (self::LEGAL_PAGES as $slug => $title) {
            $page = Page::query()->firstOrNew(['slug' => $slug], ['title' => $title, 'is_published' => false]);

            if (filled($page->content) && trim(strip_tags($page->content)) !== self::PLACEHOLDER) {
                continue;
            }

            $page->content = (string) file_get_contents(database_path("seeders/content/{$slug}.html"));
            $page->save();
        }
    }

    private function faq(): void
    {
        if (FaqItem::query()->exists()) {
            return;
        }

        $items = [
            'Nos produits' => [
                ['Tous vos produits sont-ils sans gluten ?', 'Oui. Tous nos produits sont fabriqués dans notre laboratoire 100 % sans gluten, à Avranches.'],
                ['Où trouver la liste des ingrédients et des allergènes ?', 'Sur chaque fiche produit, dans les rubriques « Ingrédients » et « Allergènes ».'],
                ['Comment conserver mes produits ?', 'Les conditions de conservation sont indiquées sur chaque fiche produit et sur l\'emballage. Nos produits sont des denrées périssables : respectez-les dès réception.'],
            ],
            'Commande' => [
                ['Quand ma commande est-elle préparée ?', 'Nos produits sont fabriqués à la commande. La date d\'expédition prévue vous est indiquée avant le paiement, puis dans l\'email de confirmation.'],
                ['Dois-je créer un compte pour commander ?', 'Non, vous pouvez commander sans compte. Un compte vous permet de retrouver vos commandes et vos factures.'],
                ['Quels sont les moyens de paiement ?', 'La carte bancaire (paiement sécurisé) ou le virement bancaire. En cas de virement, votre commande est préparée une fois le virement reçu.'],
            ],
            'Livraison' => [
                ['Où livrez-vous ?', 'En point relais Chronopost, en France métropolitaine (hors Corse). Nous ne livrons pas à domicile, ni dans les DOM-TOM, ni à l\'étranger.'],
                ['Combien coûte la livraison ?', 'Les frais de livraison dépendent du poids de votre commande. Ils sont calculés et affichés avant la validation de la commande.'],
                ['Puis-je retirer ma commande sur place ?', 'Non. Notre laboratoire n\'accueille pas de public et aucun retrait sur place n\'est proposé.'],
                ['Quand dois-je retirer mon colis ?', 'Nous vous recommandons de le retirer le jour même de sa mise à disposition au point relais, pour préserver la fraîcheur de vos produits.'],
                ['Comment suivre mon colis ?', 'Vous recevez un email avec le lien de suivi Chronopost dès l\'expédition de votre commande.'],
            ],
            'Réclamations' => [
                ['Mon colis est abîmé ou un produit manque : que faire ?', 'Contactez-nous dans les meilleurs délais, idéalement dans les 24 heures suivant la réception, avec votre numéro de commande et des photos du colis et du produit.'],
                ['Puis-je me rétracter ?', 'Les produits alimentaires périssables ne bénéficient pas du droit de rétractation (article L.221-28 du Code de la consommation). Vos garanties légales restent applicables en cas de produit non conforme.'],
            ],
        ];

        $position = 0;
        foreach ($items as $group => $questions) {
            foreach ($questions as [$question, $answer]) {
                FaqItem::query()->create([
                    'group' => $group,
                    'question' => $question,
                    'answer' => "<p>{$answer}</p>",
                    'position' => ++$position,
                    'is_published' => false,
                ]);
            }
        }
    }
}
