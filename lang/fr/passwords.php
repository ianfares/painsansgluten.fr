<?php

declare(strict_types=1);

/*
 * Messages de réinitialisation du mot de passe en français (06/11/2026).
 * `sent` et `user` ont volontairement le même texte : ne pas confirmer à un
 * inconnu qu'une adresse e-mail a un compte.
 */
return [
    'reset' => 'Votre mot de passe a été réinitialisé.',
    'sent' => 'Si un compte correspond à cette adresse, vous allez recevoir un e-mail avec le lien de réinitialisation.',
    'throttled' => 'Veuillez patienter avant de réessayer.',
    'token' => 'Ce lien de réinitialisation n\'est plus valide : il a expiré ou a déjà été utilisé. Demandez un nouveau lien via « Mot de passe oublié ».',
    'user' => 'Si un compte correspond à cette adresse, vous allez recevoir un e-mail avec le lien de réinitialisation.',
];
