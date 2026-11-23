<?php
/* BH Insight Pro — réglages fixes de la partie serveur (espace client et espace BH Insight).
 * Ce fichier est publié dans un dépôt public : n'y mettre aucun secret (seulement des empreintes).
 * Les données (comptes, commandes, factures) sont hors du dossier publié, voir bh_dossier() dans base.php.
 */

/* Empreinte SHA-256 du code d'installation à usage unique : il sert une seule fois, à la première ouverture de l'espace BH Insight,
   pour choisir son mot de passe. Le code lui-même a été donné au propriétaire, il n'est pas ici. */
define('BH_CODE_INSTALLATION', '8664d4210a91760669b5daf04c6ea45691576c5a38f610cd3283c8999bbb232d');
/* Code de secours (« Mot de passe oublié ? » → « J'ai un code de secours ») : à usage unique, seule son empreinte est ici. */
define('BH_CODE_SECOURS', 'a8bc1ad65fb8e260fe5e6cfa68cbc58f69fdcbfd27ed1415039c57fe3c84aa9d');

/* E-mails : l'expéditeur doit être une adresse d'un domaine rattaché à l'hébergement OVH (règle OVH pour mail()). */
define('BH_EXPEDITEUR', 'pro@bhinsight.fr');
define('BH_EMAIL_BH_DEFAUT', 'contact' . '@' . 'bhinsight.fr');     /* avis de commandes et d'inscriptions, modifiable dans Réglages */

define('BH_URL_PRO', 'https://pro.bhinsight.fr/');
define('BH_URL_ADMIN', 'https://pro.bhinsight.fr/admin/');
define('BH_URL_SITE', 'https://bhinsight.fr');

define('BH_TVA', 20);     /* taux de TVA en % */
