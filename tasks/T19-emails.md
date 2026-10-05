# T19 — Emails transactionnels (Brevo)
**Dépend de** : T12, T14, T15  **Branche** : `feature/T19-emails`  **Réf.** : PLAN §15

## À faire
- Mailer Brevo (SMTP relay ou transport API ; justifier dans DECISIONS), toutes les notifications en **queue**.
- Templates Markdown FR aux couleurs de la marque : liste complète PLAN §15 (client + admin).
- Email expédition : n° de suivi, lien (modèle paramétré), **rappel retrait le jour même**.
- Commande BO « Renvoyer l'email de confirmation ».

## ⚠️ Avertissements
- Local/préprod : mailer `log` ou boîte de test.
- Aucune donnée personnelle dans les logs d'envoi.

## Tests
Chaque événement déclenche le bon email une seule fois (`Mail::fake` / `Notification::fake`).
