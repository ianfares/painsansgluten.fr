# {!! $shop->shop_name ?: 'Mon Sans Gluten by Angélique' !!}

> Boulangerie artisanale 100 % sans gluten à Avranches (Manche, Normandie). Pains, viennoiseries, pâtisseries et biscuits fabriqués à la commande, expédiés en point relais Chronopost partout en France métropolitaine (hors Corse). Paiement par carte bancaire ou par virement.

## À savoir

- Tous les produits sont sans gluten ; les allergènes et la composition de chaque produit sont indiqués sur sa fiche.
- Produits frais, fabriqués à la commande : la date d'expédition est annoncée avant le paiement.
- Livraison uniquement en point relais Chronopost, en France métropolitaine (hors Corse). Les colis sont à retirer le jour même de leur mise à disposition.
@if ($shop->city)
- Adresse : {!! collect([$shop->address_line1, trim(($shop->postal_code ?? '').' '.$shop->city)])->filter()->implode(', ') !!}@if ($shop->address_note) ({!! $shop->address_note !!})@endif
@endif
@if ($shop->contact_email)
- Contact : {!! $shop->contact_email !!}@if ($shop->contact_phone) — {!! $shop->contact_phone !!}@endif

@endif

## Catégories

@foreach ($categories as $category)
- [{!! $category->name !!}]({!! route('content.show', $category) !!})@if ($category->description): {!! \Illuminate\Support\Str::limit($category->description, 200) !!}@endif

@endforeach

## Produits

@forelse ($products as $product)
- [{!! $product->name !!}]({!! route('products.show', $product) !!})@if ($product->short_description || $product->description): {!! $seo->plainText($product->short_description ?: $product->description, 200) !!}@endif

@empty
- Catalogue en cours de mise en ligne : [boutique]({!! route('boutique.index') !!})
@endforelse

## Informations

- [Questions fréquentes]({!! route('faq') !!})@if ($faqCount) ({!! $faqCount !!} réponses)@endif

- [Contact]({!! route('contact') !!})
@foreach ($pages as $page)
@if ($page->slug !== 'contact')
- [{!! $page->title !!}]({!! route('content.show', $page) !!})
@endif
@endforeach
