<?php
/* BH Insight Pro — espace client (pro.bhinsight.fr) : échanges entre les pages et le serveur.
 * Appel : api.php?a=action (GET pour lire, POST pour modifier). Réponses en JSON. Partie commune : _serveur/base.php.
 */
require __DIR__ . '/_serveur/base.php';
bh_session('bhpro');

function client_connecte()
{
    if (empty($_SESSION['client'])) bh_erreur('Session terminée : merci de vous reconnecter.', 401);
    $c = bh_un('SELECT * FROM clients WHERE id = ?', array($_SESSION['client']));
    if (!$c || $c['etat'] !== 'actif') { $_SESSION = array(); bh_erreur('Compte inaccessible : merci de contacter BH Insight.', 401); }
    return $c;
}
function client_vue($c)
{
    $l = json_decode((string) $c['livraison'], true);
    return array('numero' => $c['numero'], 'email' => $c['email'], 'societe' => $c['societe'], 'enseigne' => $c['enseigne'], 'nom' => $c['nom'], 'prenom' => $c['prenom'],
        'tel' => $c['tel'], 'siret' => $c['siret'], 'tva' => $c['tva'], 'adresse' => $c['adresse'], 'cp' => $c['cp'], 'ville' => $c['ville'], 'pays' => $c['pays'],
        'livraison' => is_array($l) ? $l : null,
        'factures_nouvelles' => (int) bh_un('SELECT COUNT(*) AS n FROM factures WHERE client_id = ? AND lue IS NULL', array($c['id']))['n']);
}
function prevenir_bh($sujet, $m, $repondre = '') { $m['pour'] = 'bh'; return bh_mail(bh_email_bh(), $sujet, $m, $repondre, 'BH Insight Pro · avis'); }
function adresse_saisie($e, $prefixe = '')
{
    $x = array();
    foreach (array('societe' => 120, 'nom' => 120, 'adresse' => 200, 'cp' => 12, 'ville' => 80, 'pays' => 60) as $k => $max)
        $x[$k] = isset($e[$prefixe . $k]) && !is_array($e[$prefixe . $k]) ? bh_texte($e[$prefixe . $k], $max) : '';
    return $x;
}

$a = isset($_GET['a']) ? $_GET['a'] : '';
switch ($a) {

case 'etat':
    if (empty($_SESSION['client'])) bh_json(array('ok' => true, 'connecte' => false));
    $c = bh_un('SELECT * FROM clients WHERE id = ? AND etat = ?', array($_SESSION['client'], 'actif'));
    if (!$c) bh_json(array('ok' => true, 'connecte' => false));
    bh_json(array('ok' => true, 'connecte' => true, 'client' => client_vue($c)));

case 'connexion':
    bh_verifier_post();
    $cle = 'cx:' . bh_ip();
    if (!bh_limite($cle, 10, 900)) bh_erreur('Trop d’essais : merci de patienter un quart d’heure.', 429);
    $email = bh_champ('email', 200);
    $c = bh_un('SELECT * FROM clients WHERE email = ?', array($email));
    if (!$c || !password_verify((string) (bh_entree()['mdp'] ?? ''), $c['mdp'])) { bh_noter_essai($cle); bh_erreur('Adresse e-mail ou mot de passe incorrect.', 403); }
    if ($c['etat'] === 'attente') bh_erreur('Votre compte est en cours de validation par BH Insight. Vous recevrez un e-mail dès qu’il sera ouvert.', 403);
    if ($c['etat'] !== 'actif') bh_erreur('Ce compte n’est pas ouvert. Merci de contacter BH Insight.', 403);
    session_regenerate_id(true);
    $_SESSION['client'] = (int) $c['id'];
    bh_json(array('ok' => true));

case 'deconnexion':
    bh_verifier_post();
    $_SESSION = array();
    session_destroy();
    bh_json(array('ok' => true));

case 'inscription':
    bh_verifier_post();
    $cle = 'ins:' . bh_ip();
    if (!bh_limite($cle, 5, 3600)) bh_erreur('Trop de demandes : merci de réessayer plus tard.', 429);
    $e = bh_entree();
    $x = array();
    foreach (array('societe' => 120, 'enseigne' => 120, 'siret' => 20, 'tva' => 20, 'adresse' => 200, 'cp' => 12, 'ville' => 80, 'pays' => 60, 'nom' => 80, 'prenom' => 80, 'tel' => 30, 'email' => 200) as $k => $max)
        $x[$k] = bh_champ($k, $max);
    if ($x['pays'] === '') $x['pays'] = 'France';
    foreach (array('societe' => 'la raison sociale', 'siret' => 'le n° SIRET', 'adresse' => 'l’adresse', 'cp' => 'le code postal', 'ville' => 'la ville', 'nom' => 'le nom', 'prenom' => 'le prénom', 'tel' => 'le téléphone') as $k => $lib)
        if ($x[$k] === '') bh_erreur('Merci d’indiquer ' . $lib . '.');
    if (!bh_email_valide($x['email'])) bh_erreur('Adresse e-mail invalide.');
    $mdp = (string) ($e['mdp'] ?? '');
    if (strlen($mdp) < 8) bh_erreur('Mot de passe : 8 caractères au moins.');
    if (empty($e['cgv'])) bh_erreur('Merci d’accepter les conditions générales de vente.');
    if (bh_un('SELECT id FROM clients WHERE email = ?', array($x['email']))) bh_erreur('Un compte existe déjà avec cette adresse e-mail.');
    $livr = !empty($e['autre_livraison']) ? adresse_saisie($e, 'l_') : null;
    if ($livr && ($livr['adresse'] === '' || $livr['cp'] === '' || $livr['ville'] === '')) bh_erreur('Merci de compléter l’adresse de livraison.');
    bh_noter_essai($cle);
    bh_exec('INSERT INTO clients (email, mdp, etat, societe, enseigne, nom, prenom, tel, siret, tva, adresse, cp, ville, pays, livraison, cree) VALUES (?, ?, \'attente\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        array($x['email'], password_hash($mdp, PASSWORD_DEFAULT), $x['societe'], $x['enseigne'], $x['nom'], $x['prenom'], $x['tel'], $x['siret'], $x['tva'],
            $x['adresse'], $x['cp'], $x['ville'], $x['pays'], $livr ? json_encode($livr, JSON_UNESCAPED_UNICODE) : null, bh_maintenant()));
    prevenir_bh('Nouvelle demande de compte — ' . $x['societe'], array('etiquette' => 'Inscription', 'titre' => 'Nouvelle demande de compte',
        'texte' => 'Un professionnel demande l’ouverture d’un compte. Il attend votre validation.',
        'lignes' => array(array('Société', $x['societe']), array('Ville', $x['cp'] . ' ' . $x['ville']), array('Contact', $x['prenom'] . ' ' . $x['nom']), array('E-mail', $x['email']), array('Téléphone', $x['tel'])),
        'bouton' => array('Voir les inscriptions', BH_URL_ADMIN . 'clients.html')), $x['email']);
    bh_json(array('ok' => true));

case 'oubli':
    bh_verifier_post();
    $cle = 'oubli:' . bh_ip();
    if (!bh_limite($cle, 4, 3600)) bh_erreur('Trop de demandes : merci de réessayer plus tard.', 429);
    bh_noter_essai($cle);
    $c = bh_un('SELECT * FROM clients WHERE email = ? AND etat = ?', array(bh_champ('email', 200), 'actif'));
    if ($c) {
        $jeton = bin2hex(random_bytes(24));
        bh_exec('UPDATE clients SET reinit = ?, reinit_fin = ? WHERE id = ?', array(hash('sha256', $jeton), time() + 3600, $c['id']));
        bh_mail($c['email'], 'BH Insight Pro : nouveau mot de passe', array('etiquette' => 'Votre compte', 'titre' => 'Nouveau mot de passe',
            'texte' => "Bonjour,\n\nVous avez demandé un nouveau mot de passe pour votre espace pro BH Insight. Pour le choisir, cliquez sur le bouton ci-dessous : ce lien est valable une heure.",
            'bouton' => array('Choisir mon mot de passe', BH_URL_PRO . 'index.html#reinit=' . $jeton),
            'apres' => 'Si vous n’avez rien demandé, ignorez ce message : votre mot de passe actuel reste valable.'));
    }
    bh_json(array('ok' => true));     /* même réponse que le compte existe ou non */

case 'reinit':
    bh_verifier_post();
    $cle = 'reinit:' . bh_ip();
    if (!bh_limite($cle, 10, 900)) bh_erreur('Trop d’essais : merci de patienter un quart d’heure.', 429);
    $mdp = (string) (bh_entree()['mdp'] ?? '');
    if (strlen($mdp) < 8) bh_erreur('Mot de passe : 8 caractères au moins.');
    $c = bh_un('SELECT * FROM clients WHERE reinit = ? AND reinit_fin > ?', array(hash('sha256', bh_champ('jeton', 100)), time()));
    if (!$c) { bh_noter_essai($cle); bh_erreur('Lien expiré ou déjà utilisé : refaites une demande.'); }
    bh_exec('UPDATE clients SET mdp = ?, reinit = NULL, reinit_fin = NULL WHERE id = ?', array(password_hash($mdp, PASSWORD_DEFAULT), $c['id']));
    bh_json(array('ok' => true));

case 'compte':
    $c = client_connecte();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') bh_json(array('ok' => true, 'client' => client_vue($c)));
    bh_verifier_post();
    $x = array();
    foreach (array('societe' => 120, 'enseigne' => 120, 'siret' => 20, 'tva' => 20, 'adresse' => 200, 'cp' => 12, 'ville' => 80, 'pays' => 60, 'nom' => 80, 'prenom' => 80, 'tel' => 30) as $k => $max)
        $x[$k] = bh_champ($k, $max);
    foreach (array('societe', 'adresse', 'cp', 'ville', 'nom') as $k) if ($x[$k] === '') bh_erreur('Merci de remplir tous les champs obligatoires.');
    $e = bh_entree();
    $livr = !empty($e['autre_livraison']) ? adresse_saisie($e, 'l_') : null;
    if ($livr && ($livr['adresse'] === '' || $livr['cp'] === '' || $livr['ville'] === '')) bh_erreur('Merci de compléter l’adresse de livraison.');
    bh_exec('UPDATE clients SET societe = ?, enseigne = ?, siret = ?, tva = ?, adresse = ?, cp = ?, ville = ?, pays = ?, nom = ?, prenom = ?, tel = ?, livraison = ? WHERE id = ?',
        array($x['societe'], $x['enseigne'], $x['siret'], $x['tva'], $x['adresse'], $x['cp'], $x['ville'], $x['pays'], $x['nom'], $x['prenom'], $x['tel'],
            $livr ? json_encode($livr, JSON_UNESCAPED_UNICODE) : null, $c['id']));
    bh_json(array('ok' => true, 'client' => client_vue(bh_un('SELECT * FROM clients WHERE id = ?', array($c['id'])))));

case 'mdp':
    bh_verifier_post();
    $c = client_connecte();
    $e = bh_entree();
    if (!password_verify((string) ($e['actuel'] ?? ''), $c['mdp'])) bh_erreur('Mot de passe actuel incorrect.');
    if (strlen((string) ($e['nouveau'] ?? '')) < 8) bh_erreur('Nouveau mot de passe : 8 caractères au moins.');
    bh_exec('UPDATE clients SET mdp = ? WHERE id = ?', array(password_hash((string) $e['nouveau'], PASSWORD_DEFAULT), $c['id']));
    bh_json(array('ok' => true));

case 'catalogue':
    client_connecte();
    $c = bh_catalogue();
    bh_json(array('ok' => true, 'articles' => array_values(bh_articles()), 'carton' => (int) $c['carton'], 'prix' => $c['prix'], 'taux' => BH_TVA,
        'plv_prevus' => count($c['plv'])));

case 'commander':
    bh_verifier_post();
    $c = client_connecte();
    if (!bh_limite('cmd:' . $c['id'], 30, 3600)) bh_erreur('Trop de commandes envoyées en une heure : merci de contacter BH Insight.', 429);
    $e = bh_entree();
    $lignes = bh_lignes_panier(isset($e['panier']) ? $e['panier'] : array());
    if (!$lignes) bh_erreur('Votre panier est vide.');
    $t = bh_totaux($lignes);
    if (!empty($e['autre_adresse'])) {
        $livr = adresse_saisie($e, 'l_');
        if ($livr['adresse'] === '' || $livr['cp'] === '' || $livr['ville'] === '') bh_erreur('Merci de compléter l’adresse de livraison.');
        if ($livr['societe'] === '') $livr['societe'] = $c['societe'];
    } else {
        $enreg = json_decode((string) $c['livraison'], true);
        $livr = is_array($enreg) ? $enreg : array('societe' => $c['enseigne'] ?: $c['societe'], 'nom' => trim($c['prenom'] . ' ' . $c['nom']), 'adresse' => $c['adresse'], 'cp' => $c['cp'], 'ville' => $c['ville'], 'pays' => $c['pays']);
    }
    $num = bh_numero_commande();
    $quand = bh_maintenant();
    bh_exec('INSERT INTO commandes (numero, client_id, etat, reference, note, lignes, ht, tva, ttc, flacons, livraison, creee) VALUES (?, ?, \'recue\', ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        array($num, $c['id'], bh_champ('reference', 80), bh_texte_long($e['note'] ?? '', 1000), json_encode($lignes, JSON_UNESCAPED_UNICODE),
            $t['ht'], $t['tva'], $t['ttc'], $t['flacons'], json_encode($livr, JSON_UNESCAPED_UNICODE), $quand));
    bh_noter_essai('cmd:' . $c['id']);
    $detail = array();
    foreach ($lignes as $l) $detail[] = $l['q'] . ' × ' . $l['label'] . ' — ' . bh_eur($l['total']);
    $refs = array(array('N° de commande', $num), array('Total HT', bh_eur($t['ht'])), array('Total TTC', bh_eur($t['ttc'])));
    prevenir_bh('Nouvelle commande ' . $num . ' — ' . $c['societe'], array('etiquette' => 'Commande', 'titre' => 'Nouvelle commande',
        'texte' => 'Une commande vient d’arriver.' . ($e['note'] ?? '' ? "\n\nNote du client : " . bh_texte_long($e['note'], 1000) : ''),
        'lignes' => array_merge($refs, array(array('Client', $c['societe'] . ' · n° ' . $c['numero']))), 'citation' => implode("\n", $detail),
        'bouton' => array('Ouvrir la commande', BH_URL_ADMIN . 'commande.html?n=' . $num)), $c['email']);
    bh_mail($c['email'], 'BH Insight : commande ' . $num . ' reçue', array('etiquette' => 'Votre commande', 'titre' => 'Commande reçue',
        'texte' => 'Merci ! Nous avons bien reçu votre commande. Nous vous prévenons dès son expédition.',
        'lignes' => $refs, 'citation' => implode("\n", $detail), 'bouton' => array('Suivre ma commande', BH_URL_PRO . 'historique.html?n=' . $num)));
    bh_json(array('ok' => true, 'numero' => $num, 'ht' => $t['ht'], 'ttc' => $t['ttc']));

case 'commandes':
    $c = client_connecte();
    $l = array();
    foreach (bh_tous('SELECT * FROM commandes WHERE client_id = ? ORDER BY id DESC LIMIT 300', array($c['id'])) as $cmd) $l[] = bh_commande_vue($cmd);
    bh_json(array('ok' => true, 'commandes' => $l));

case 'bl':
    $c = client_connecte();
    $cmd = bh_un('SELECT * FROM commandes WHERE numero = ? AND client_id = ?', array(isset($_GET['n']) ? $_GET['n'] : '', $c['id']));
    if (!$cmd || $cmd['etat'] !== 'expediee' || !$cmd['bl']) bh_erreur('Bon de livraison introuvable.', 404);
    bh_json(array('ok' => true, 'doc' => json_decode($cmd['bl'], true)));

case 'factures':
    $c = client_connecte();
    $l = array();
    foreach (bh_tous('SELECT numero, commande, emise, ttc, lue FROM factures WHERE client_id = ? ORDER BY id DESC', array($c['id'])) as $f)
        $l[] = array('numero' => $f['numero'], 'commande' => $f['commande'], 'emise' => bh_date_fr($f['emise']), 'ttc' => (int) $f['ttc'], 'nouvelle' => !$f['lue']);
    bh_json(array('ok' => true, 'factures' => $l));

case 'facture':
    $c = client_connecte();
    $f = bh_un('SELECT * FROM factures WHERE numero = ? AND client_id = ?', array(isset($_GET['n']) ? $_GET['n'] : '', $c['id']));
    if (!$f) bh_erreur('Facture introuvable.', 404);
    if (!$f['lue']) bh_exec('UPDATE factures SET lue = ? WHERE id = ?', array(bh_maintenant(), $f['id']));
    bh_json(array('ok' => true, 'doc' => json_decode($f['donnees'], true)));

case 'message':
    bh_verifier_post();
    $c = client_connecte();
    if (!bh_limite('msg:' . $c['id'], 10, 3600)) bh_erreur('Trop de messages en une heure : merci de réessayer plus tard.', 429);
    $texte = bh_texte_long(bh_entree()['texte'] ?? '', 3000);
    if ($texte === '') bh_erreur('Votre message est vide.');
    $cmd = bh_champ('commande', 20);
    if ($cmd !== '' && !bh_un('SELECT id FROM commandes WHERE numero = ? AND client_id = ?', array($cmd, $c['id']))) $cmd = '';
    bh_exec('INSERT INTO messages (client_id, commande, texte, cree) VALUES (?, ?, ?, ?)', array($c['id'], $cmd ?: null, $texte, bh_maintenant()));
    bh_noter_essai('msg:' . $c['id']);
    prevenir_bh(($cmd ? 'Réclamation ' . $cmd : 'Message') . ' — ' . $c['societe'], array('etiquette' => $cmd ? 'Réclamation' : 'Message', 'titre' => 'Message d’un client',
        'texte' => 'Pour répondre, répondez simplement à cet e-mail : votre réponse part directement au client.',
        'lignes' => array_merge(array(array('Client', $c['societe'] . ' · n° ' . $c['numero']), array('E-mail', $c['email'])), $cmd ? array(array('Commande', $cmd)) : array()),
        'citation' => $texte, 'bouton' => array('Voir les messages', BH_URL_ADMIN . 'messages.html')), $c['email']);
    bh_json(array('ok' => true));

default:
    bh_erreur('Action inconnue.', 404);
}
