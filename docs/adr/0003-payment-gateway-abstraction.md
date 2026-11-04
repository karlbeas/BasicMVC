# ADR 0003 — Paiement : abstraction multi-agrégateurs

- **Date** : 2026-11-04
- **Statut** : accepté
- **Répond à** : MANIFEST §10, question ouverte n° 1

## Contexte

Les offres Pass Projet (5 $ / 30 jours) et Pro (10 $/mois, 96 $/an) se paient en Mobile Money et par carte. Trois agrégateurs étaient envisagés : CinetPay, Notch Pay, Flutterwave. Leur couverture diffère (Afrique francophone, Cameroun, anglophones) et le pilote démarre au Cameroun avant la Côte d'Ivoire et le Sénégal, puis les pays anglophones.

## Décision

1. Une interface `App\Services\Payments\PaymentGateway` commune : `createCheckout()` (montant, devise, référence, URL de retour/notification → URL de paiement), `parseNotification()` (webhook → référence), `verify()` (requête serveur à l'agrégateur → statut réel, montant, devise).
2. **Premier connecteur : CinetPay** (Mobile Money MTN/Orange/Wave et cartes, couverture Cameroun + Afrique de l'Ouest francophone). Notch Pay et Flutterwave s'ajoutent comme connecteurs sans modifier le reste.
3. Le connecteur actif est choisi par configuration (`PAYMENT_PROVIDER`), avec ses clés dans `.env` uniquement. Un connecteur `fake` sert aux tests et à la démonstration.
4. **Activation uniquement après vérification serveur** : le webhook n'est qu'un signal ; l'abonnement n'est activé qu'après `verify()` confirmant statut payé, montant et devise attendus. Traitement idempotent (référence unique, plusieurs webhooks possibles). La page de retour du navigateur n'active jamais rien.
5. Tables `subscriptions` et `payments` : montants en centimes + devise, statut, référence agrégateur, charge utile brute du webhook conservée pour audit.
6. Compatible mutualisé : appels sortants HTTPS via cURL (délai court), pas de processus permanent ; les vérifications différées et l'expiration des Pass passent par la file `jobs`.

## Conséquences

- (+) Le choix commercial d'un agrégateur reste réversible ; plusieurs agrégateurs peuvent coexister par pays plus tard.
- (−) Un peu plus de code au départ ; chaque connecteur exige ses propres tests (webhooks simulés).
- Le porteur de projet doit ouvrir le compte marchand CinetPay (clés API et site ID) avant la mise en production.
