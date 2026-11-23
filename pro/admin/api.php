<?php
/* BH Insight Pro — espace BH Insight (pro.bhinsight.fr/admin) : échanges entre les pages et le serveur.
 * Accès : un seul compte. À la toute première ouverture, le mot de passe se choisit avec le code d'installation à usage unique
 * (seule son empreinte est dans _serveur/config.php). Mot de passe oublié : lien envoyé à l'adresse des Réglages, ou code de secours.
 */
require dirname(__DIR__) . '/_serveur/base.php';
bh_session('bhadmin');

function bh_connecte()
{
    if (empty($_SESSION['bh']) || $_SESSION['bh'] !== bh_reglage('admin_jeton')) bh_erreur('Session terminée : merci de vous reconnecter.', 401);
}
function client_mail($c, $sujet, $m) { return bh_mail($c['email'], $sujet, $m); }
function lignes_mail($cmd)
{
    $d = array();
    foreach (json_decode($cmd['lignes'], true) as $l) $d[] = $l['q'] . ' × ' . $l['label'];
    return implode("\n", $d);
}
function client_admin_vue($c)
{
    $n = bh_un('SELECT COUNT(*) AS n, COALESCE(SUM(ht), 0) AS ht FROM commandes WHERE client_id = ? AND etat <> \'annulee\'', array($c['id']));
    $l = json_decode((string) $c['livraison'], true);
    return array('id' => (int) $c['id'], 'numero' => (string) $c['numero'], 'email' => $c['email'], 'etat' => $c['etat'], 'societe' => $c['societe'], 'enseigne' => $c['enseigne'],
        'nom' => $c['nom'], 'prenom' => $c['prenom'], 'tel' => $c['tel'], 'siret' => $c['siret'], 'tva' => $c['tva'], 'adresse' => $c['adresse'], 'cp' => $c['cp'],
        'ville' => $c['ville'], 'pays' => $c['pays'], 'livraison' => is_array($l) ? $l : null, 'cree' => bh_date_fr($c['cree']), 'valide' => bh_date_fr($c['valide']),
        'commandes' => (int) $n['n'], 'ht' => (int) $n['ht']);
}
function nouveau_jeton() { $j = bin2hex(random_bytes(24)); bh_reglage_ecrire('admin_jeton', $j); return $j; }

$a = isset($_GET['a']) ? $_GET['a'] : '';
switch ($a) {

case 'etat':
    $installe = (bool) bh_reglage('admin_mdp');
    $ok = $installe && !empty($_SESSION['bh']) && $_SESSION['bh'] === bh_reglage('admin_jeton');
    $badges = $ok ? array(
        'commandes' => (int) bh_un("SELECT COUNT(*) AS n FROM commandes WHERE etat = 'recue'")['n'],
        'clients' => (int) bh_un("SELECT COUNT(*) AS n FROM clients WHERE etat = 'attente'")['n'],
        'messages' => (int) bh_un('SELECT COUNT(*) AS n FROM messages WHERE traite IS NULL')['n']) : null;
    bh_json(array('ok' => true, 'installe' => $installe, 'connecte' => $ok, 'badges' => $badges));

case 'installer':
    bh_verifier_post();
    if (bh_reglage('admin_mdp')) bh_erreur('L’espace BH Insight est déjà installé.');
    $cle = 'inst:' . bh_ip();
    if (!bh_limite($cle, 8, 900)) bh_erreur('Trop d’essais : merci de patienter un quart d’heure.', 429);
    bh_noter_essai($cle);
    $code = strtoupper(trim(bh_champ('code', 40)));
    if (!hash_equals(BH_CODE_INSTALLATION, hash('sha256', $code))) bh_erreur('Code d’installation incorrect.', 403);
    $mdp = (string) (bh_entree()['mdp'] ?? '');
    if (strlen($mdp) < 10) bh_erreur('Mot de passe : 10 caractères au moins.');
    bh_reglage_ecrire('admin_mdp', password_hash($mdp, PASSWORD_DEFAULT));
    session_regenerate_id(true);
    $_SESSION['bh'] = nouveau_jeton();
    bh_json(array('ok' => true));

case 'connexion':
    bh_verifier_post();
    $cle = 'acx:' . bh_ip();
    if (!bh_limite($cle, 8, 900)) bh_erreur('Trop d’essais : merci de patienter un quart d’heure.', 429);
    $hash = bh_reglage('admin_mdp');
    if (!$hash || !password_verify((string) (bh_entree()['mdp'] ?? ''), $hash)) { bh_noter_essai($cle); bh_erreur('Mot de passe incorrect.', 403); }
    session_regenerate_id(true);
    if (!bh_reglage('admin_jeton')) nouveau_jeton();
    $_SESSION['bh'] = bh_reglage('admin_jeton');
    bh_json(array('ok' => true));

case 'oubli':
    bh_verifier_post();
    if (!bh_reglage('admin_mdp')) bh_erreur('L’espace BH Insight n’est pas encore installé.');
    $cle = 'aoubli:' . bh_ip();
    if (!bh_limite($cle, 3, 3600)) bh_erreur('Trop de demandes : merci de réessayer dans une heure.', 429);
    bh_noter_essai($cle);
    $jeton = bin2hex(random_bytes(24));
    bh_reglage_ecrire('admin_reinit', json_encode(array('h' => hash('sha256', $jeton), 'fin' => time() + 3600)));
    $mel = bh_email_bh();
    bh_mail($mel, 'BH Insight Pro : nouveau mot de passe de votre espace', array('pour' => 'bh', 'etiquette' => 'Espace BH Insight', 'titre' => 'Nouveau mot de passe',
        'texte' => 'Une demande de nouveau mot de passe a été faite pour l’espace BH Insight. Pour le choisir, cliquez sur le bouton ci-dessous : ce lien est valable une heure.',
        'bouton' => array('Choisir mon mot de passe', BH_URL_ADMIN . 'index.html#reinit=' . $jeton),
        'apres' => 'Si vous n’êtes pas à l’origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable.'), '', 'BH Insight Pro · avis');
    $p = explode('@', $mel);
    bh_json(array('ok' => true, 'adresse' => substr($p[0], 0, 2) . '…@' . $p[1]));

case 'reinit':
    bh_verifier_post();
    $cle = 'areinit:' . bh_ip();
    if (!bh_limite($cle, 8, 900)) bh_erreur('Trop d’essais : merci de patienter un quart d’heure.', 429);
    $mdp = (string) (bh_entree()['mdp'] ?? '');
    if (strlen($mdp) < 10) bh_erreur('Mot de passe : 10 caractères au moins.');
    $ok = false;
    $jeton = bh_champ('jeton', 100);
    $code = strtoupper(trim(bh_champ('code', 40)));
    if ($jeton !== '') {
        $r = json_decode((string) bh_reglage('admin_reinit', ''), true);
        $ok = is_array($r) && $r['fin'] > time() && hash_equals($r['h'], hash('sha256', $jeton));
    } elseif ($code !== '') {
        $h = hash('sha256', $code);
        $ok = hash_equals(BH_CODE_SECOURS, $h) && bh_reglage('secours_utilise', '') !== $h;
        if ($ok) bh_reglage_ecrire('secours_utilise', $h);
    }
    if (!$ok) { bh_noter_essai($cle); bh_erreur($jeton !== '' ? 'Lien expiré ou déjà utilisé : refaites une demande.' : 'Code de secours incorrect ou déjà utilisé.', 403); }
    bh_reglage_ecrire('admin_mdp', password_hash($mdp, PASSWORD_DEFAULT));
    bh_reglage_ecrire('admin_reinit', '');
    session_regenerate_id(true);
    $_SESSION['bh'] = nouveau_jeton();     /* les autres sessions sont fermées */
    bh_mail(bh_email_bh(), 'BH Insight Pro : mot de passe changé', array('pour' => 'bh', 'etiquette' => 'Espace BH Insight', 'titre' => 'Mot de passe changé',
        'texte' => 'Le mot de passe de l’espace BH Insight vient d’être changé. Si ce n’est pas vous, contactez votre hébergeur et changez-le sans attendre.'), '', 'BH Insight Pro · avis');
    bh_json(array('ok' => true));

case 'deconnexion':
    bh_verifier_post();
    $_SESSION = array();
    session_destroy();
    bh_json(array('ok' => true));

case 'mdp':
    bh_verifier_post(); bh_connecte();
    $e = bh_entree();
    if (!password_verify((string) ($e['actuel'] ?? ''), (string) bh_reglage('admin_mdp'))) bh_erreur('Mot de passe actuel incorrect.');
    if (strlen((string) ($e['nouveau'] ?? '')) < 10) bh_erreur('Nouveau mot de passe : 10 caractères au moins.');
    bh_reglage_ecrire('admin_mdp', password_hash((string) $e['nouveau'], PASSWORD_DEFAULT));
    $_SESSION['bh'] = nouveau_jeton();
    bh_json(array('ok' => true));

case 'commandes':
    bh_connecte();
    $etat = isset($_GET['etat']) ? $_GET['etat'] : '';
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $sql = 'SELECT c.*, k.societe, k.numero AS client_numero, k.ville FROM commandes c JOIN clients k ON k.id = c.client_id WHERE 1';
    $p = array();
    if (isset($GLOBALS['BH_ETATS'][$etat])) { $sql .= ' AND c.etat = ?'; $p[] = $etat; }
    if ($q !== '') { $sql .= ' AND (c.numero LIKE ? OR c.reference LIKE ? OR k.societe LIKE ? OR k.numero = ?)'; $like = '%' . $q . '%'; array_push($p, $like, $like, $like, $q); }
    $l = array();
    foreach (bh_tous($sql . ' ORDER BY c.id DESC LIMIT 500', $p) as $c) {
        $v = bh_commande_vue($c);
        $v['client'] = array('societe' => $c['societe'], 'numero' => $c['client_numero'], 'ville' => $c['ville']);
        $l[] = $v;
    }
    $n = array();
    foreach (bh_tous('SELECT etat, COUNT(*) AS n FROM commandes GROUP BY etat') as $r) $n[$r['etat']] = (int) $r['n'];
    bh_json(array('ok' => true, 'commandes' => $l, 'compte' => $n));

case 'commande':
    bh_connecte();
    $cmd = bh_un('SELECT * FROM commandes WHERE numero = ?', array(isset($_GET['n']) ? $_GET['n'] : ''));
    if (!$cmd) bh_erreur('Commande introuvable.', 404);
    $c = bh_un('SELECT * FROM clients WHERE id = ?', array($cmd['client_id']));
    $msgs = array();
    foreach (bh_tous('SELECT texte, cree, traite FROM messages WHERE commande = ? ORDER BY id DESC', array($cmd['numero'])) as $m)
        $msgs[] = array('texte' => $m['texte'], 'cree' => bh_date_fr($m['cree']), 'traite' => (bool) $m['traite']);
    bh_json(array('ok' => true, 'commande' => bh_commande_vue($cmd, true), 'client' => client_admin_vue($c), 'messages' => $msgs));

case 'commande_maj':
    bh_verifier_post(); bh_connecte();
    $e = bh_entree();
    $cmd = bh_un('SELECT * FROM commandes WHERE numero = ?', array(bh_champ('numero', 20)));
    if (!$cmd) bh_erreur('Commande introuvable.', 404);
    $c = bh_un('SELECT * FROM clients WHERE id = ?', array($cmd['client_id']));
    $prevenir = !isset($e['prevenir']) || $e['prevenir'];
    $action = bh_champ('action', 20);
    $maintenant = bh_maintenant();
    $suivre = BH_URL_PRO . 'historique.html?n=' . rawurlencode($cmd['numero']);
    if (isset($e['note_interne'])) bh_exec('UPDATE commandes SET note_interne = ? WHERE id = ?', array(bh_texte_long($e['note_interne'], 2000), $cmd['id']));
    if (isset($e['colis'])) {
        $colis = bh_champ('colis', 60);
        bh_exec('UPDATE commandes SET colis = ? WHERE id = ?', array($colis, $cmd['id']));
        if ($cmd['etat'] === 'expediee' && $colis !== (string) $cmd['colis']) {     /* le bon de livraison suit le n° de colis */
            $cmd = bh_un('SELECT * FROM commandes WHERE id = ?', array($cmd['id']));
            $bl = json_decode($cmd['bl'], true); $bl['colis'] = $colis;
            bh_exec('UPDATE commandes SET bl = ? WHERE id = ?', array(json_encode($bl, JSON_UNESCAPED_UNICODE), $cmd['id']));
        }
    }
    $cmd = bh_un('SELECT * FROM commandes WHERE id = ?', array($cmd['id']));
    if ($action === 'preparer') {
        if (!in_array($cmd['etat'], array('recue'), true)) bh_erreur('Cette commande n’est plus à préparer.');
        bh_exec('UPDATE commandes SET etat = \'preparation\', preparee = ? WHERE id = ?', array($maintenant, $cmd['id']));
        if ($prevenir) client_mail($c, 'BH Insight : commande ' . $cmd['numero'] . ' en préparation', array('etiquette' => 'Votre commande', 'titre' => 'En préparation',
            'texte' => 'Bonne nouvelle : votre commande ' . $cmd['numero'] . ' est en préparation.', 'citation' => lignes_mail($cmd), 'bouton' => array('Suivre ma commande', $suivre)));
    } elseif ($action === 'expedier') {
        if (!in_array($cmd['etat'], array('recue', 'preparation'), true)) bh_erreur('Cette commande ne peut pas être expédiée.');
        $date = bh_champ('date', 10);
        $quand = $maintenant;
        if ($date !== '') {
            $t = strtotime($date . ' ' . date('H:i:s'));
            if (!$t || $t < strtotime(date('Y-m-d', strtotime($cmd['creee']))) || $t > time() + 86400 * 30) bh_erreur('Date d’expédition impossible : pas avant la commande, 30 jours au plus dans l’avenir.');
            $quand = date('Y-m-d H:i:s', $t);
        }
        bh_db()->beginTransaction();
        bh_exec('UPDATE commandes SET etat = \'expediee\', expediee = ?, preparee = COALESCE(preparee, ?) WHERE id = ?', array($quand, $quand, $cmd['id']));
        $cmd = bh_un('SELECT * FROM commandes WHERE id = ?', array($cmd['id']));
        bh_exec('UPDATE commandes SET bl = ? WHERE id = ?', array(json_encode(bh_bl_contenu($cmd, $c), JSON_UNESCAPED_UNICODE), $cmd['id']));
        $f = bh_facturer($cmd, $c);
        bh_db()->commit();
        if ($prevenir) client_mail($c, 'BH Insight : commande ' . $cmd['numero'] . ' expédiée', array('etiquette' => 'Votre commande', 'titre' => 'Commande expédiée',
            'texte' => 'Votre commande ' . $cmd['numero'] . ' est partie. Le bon de livraison et la facture sont dans votre espace pro.',
            'lignes' => array_merge(array(array('Expédiée le', bh_date_fr($quand))), $cmd['colis'] ? array(array('N° de colis', $cmd['colis'])) : array(),
                array(array('Facture', $f['numero']), array('Total TTC', bh_eur($cmd['ttc'])))),
            'citation' => lignes_mail($cmd), 'bouton' => array('Voir ma commande', $suivre)));
    } elseif ($action === 'annuler') {
        if (!in_array($cmd['etat'], array('recue', 'preparation'), true)) bh_erreur('Une commande expédiée ne peut plus être annulée.');
        bh_exec('UPDATE commandes SET etat = \'annulee\', annulee = ? WHERE id = ?', array($maintenant, $cmd['id']));
        if ($prevenir) client_mail($c, 'BH Insight : commande ' . $cmd['numero'] . ' annulée', array('etiquette' => 'Votre commande', 'titre' => 'Commande annulée',
            'texte' => 'Votre commande ' . $cmd['numero'] . ' a été annulée. Pour toute question, répondez simplement à cet e-mail.', 'bouton' => array('Mes commandes', $suivre)));
    } elseif ($action === 'retablir') {
        if ($cmd['etat'] !== 'annulee' && $cmd['etat'] !== 'preparation') bh_erreur('Rien à rétablir.');
        bh_exec('UPDATE commandes SET etat = \'recue\', annulee = NULL, preparee = NULL WHERE id = ?', array($cmd['id']));
    } elseif ($action !== '' && $action !== 'enregistrer') bh_erreur('Action inconnue.');
    $cmd = bh_un('SELECT * FROM commandes WHERE id = ?', array($cmd['id']));
    bh_json(array('ok' => true, 'commande' => bh_commande_vue($cmd, true)));

case 'bl':
    bh_connecte();
    $cmd = bh_un('SELECT * FROM commandes WHERE numero = ?', array(isset($_GET['n']) ? $_GET['n'] : ''));
    if (!$cmd || !$cmd['bl']) bh_erreur('Bon de livraison introuvable : il est édité à l’expédition.', 404);
    bh_json(array('ok' => true, 'doc' => json_decode($cmd['bl'], true)));

case 'facture':
    bh_connecte();
    $f = bh_un('SELECT * FROM factures WHERE numero = ? OR commande = ?', array(isset($_GET['n']) ? $_GET['n'] : '', isset($_GET['n']) ? $_GET['n'] : ''));
    if (!$f) bh_erreur('Facture introuvable : elle est émise à l’expédition.', 404);
    bh_json(array('ok' => true, 'doc' => json_decode($f['donnees'], true)));

case 'factures':
    bh_connecte();
    $l = array();
    foreach (bh_tous('SELECT f.numero, f.commande, f.emise, f.ttc, k.societe FROM factures f JOIN clients k ON k.id = f.client_id ORDER BY f.id DESC LIMIT 1000') as $f)
        $l[] = array('numero' => $f['numero'], 'commande' => $f['commande'], 'emise' => bh_date_fr($f['emise']), 'ttc' => (int) $f['ttc'], 'societe' => $f['societe']);
    bh_json(array('ok' => true, 'factures' => $l));

case 'clients':
    bh_connecte();
    $l = array();
    foreach (bh_tous("SELECT * FROM clients ORDER BY CASE etat WHEN 'attente' THEN 0 ELSE 1 END, societe COLLATE NOCASE") as $c) $l[] = client_admin_vue($c);
    bh_json(array('ok' => true, 'clients' => $l));

case 'client':
    bh_connecte();
    $c = bh_un('SELECT * FROM clients WHERE id = ?', array((int) ($_GET['id'] ?? 0)));
    if (!$c) bh_erreur('Client introuvable.', 404);
    $cmds = array();
    foreach (bh_tous('SELECT * FROM commandes WHERE client_id = ? ORDER BY id DESC', array($c['id'])) as $x) $cmds[] = bh_commande_vue($x);
    bh_json(array('ok' => true, 'client' => client_admin_vue($c), 'commandes' => $cmds));

case 'client_maj':
    bh_verifier_post(); bh_connecte();
    $e = bh_entree();
    $nouveau = empty($e['id']);
    $c = $nouveau ? null : bh_un('SELECT * FROM clients WHERE id = ?', array((int) $e['id']));
    if (!$nouveau && !$c) bh_erreur('Client introuvable.', 404);
    $x = array();
    foreach (array('societe' => 120, 'enseigne' => 120, 'siret' => 20, 'tva' => 20, 'adresse' => 200, 'cp' => 12, 'ville' => 80, 'pays' => 60, 'nom' => 80, 'prenom' => 80, 'tel' => 30, 'email' => 200, 'numero' => 12) as $k => $max)
        $x[$k] = bh_champ($k, $max);
    if ($x['societe'] === '') bh_erreur('Merci d’indiquer la raison sociale.');
    if (!bh_email_valide($x['email'])) bh_erreur('Adresse e-mail invalide.');
    $autre = bh_un('SELECT id FROM clients WHERE email = ? AND id <> ?', array($x['email'], $nouveau ? 0 : $c['id']));
    if ($autre) bh_erreur('Un autre compte utilise déjà cette adresse e-mail.');
    $etat = bh_champ('etat', 12);
    if (!in_array($etat, array('attente', 'actif', 'suspendu', 'refuse'), true)) $etat = $nouveau ? 'actif' : $c['etat'];
    $mdp = (string) ($e['mdp'] ?? '');
    if ($mdp !== '' && strlen($mdp) < 8) bh_erreur('Mot de passe : 8 caractères au moins.');
    if ($nouveau && $mdp === '') bh_erreur('Merci de choisir un mot de passe pour ce compte (8 caractères au moins).');
    $ouverture = $etat === 'actif' && ($nouveau || $c['etat'] !== 'actif') && !($c && $c['valide']);
    if ($x['numero'] === '' && $etat === 'actif') $x['numero'] = ($c && $c['numero']) ? $c['numero'] : bh_numero_client();
    if ($x['numero'] !== '' && bh_un('SELECT id FROM clients WHERE numero = ? AND id <> ?', array($x['numero'], $nouveau ? 0 : $c['id']))) bh_erreur('Ce n° client est déjà pris.');
    if ($nouveau) {
        bh_exec('INSERT INTO clients (email, mdp, etat, numero, societe, enseigne, nom, prenom, tel, siret, tva, adresse, cp, ville, pays, cree, valide) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array($x['email'], password_hash($mdp, PASSWORD_DEFAULT), $etat, $x['numero'] ?: null, $x['societe'], $x['enseigne'], $x['nom'], $x['prenom'], $x['tel'], $x['siret'], $x['tva'],
                $x['adresse'], $x['cp'], $x['ville'], $x['pays'] ?: 'France', bh_maintenant(), $etat === 'actif' ? bh_maintenant() : null));
        $id = (int) bh_db()->lastInsertId();
    } else {
        bh_exec('UPDATE clients SET email = ?, etat = ?, numero = ?, societe = ?, enseigne = ?, nom = ?, prenom = ?, tel = ?, siret = ?, tva = ?, adresse = ?, cp = ?, ville = ?, pays = ?, valide = COALESCE(valide, ?) WHERE id = ?',
            array($x['email'], $etat, $x['numero'] ?: null, $x['societe'], $x['enseigne'], $x['nom'], $x['prenom'], $x['tel'], $x['siret'], $x['tva'], $x['adresse'], $x['cp'], $x['ville'], $x['pays'],
                $etat === 'actif' ? bh_maintenant() : null, $c['id']));
        if ($mdp !== '') bh_exec('UPDATE clients SET mdp = ? WHERE id = ?', array(password_hash($mdp, PASSWORD_DEFAULT), $c['id']));
        $id = (int) $c['id'];
    }
    $c2 = bh_un('SELECT * FROM clients WHERE id = ?', array($id));
    if ($ouverture && (!isset($e['prevenir']) || $e['prevenir'])) bh_mail($c2['email'], 'BH Insight Pro : votre compte est ouvert', array('etiquette' => 'Bienvenue', 'titre' => 'Votre compte est ouvert',
        'texte' => 'Votre espace pro BH Insight est ouvert : vous pouvez dès maintenant commander nos flacons au flacon, au carton ou en lot, suivre vos commandes et retrouver vos factures.'
            . ($nouveau ? "\n\nVotre mot de passe vous est communiqué par BH Insight ; vous pourrez le changer dans « Mon compte »." : ''),
        'lignes' => array(array('N° client', $c2['numero']), array('Identifiant', $c2['email'])), 'bouton' => array('Ouvrir mon espace pro', BH_URL_PRO)));
    bh_json(array('ok' => true, 'client' => client_admin_vue($c2)));

case 'catalogue':
    bh_connecte();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') bh_json(array('ok' => true, 'catalogue' => bh_catalogue(), 'flacons' => bh_flacons(), 'gammes' => bh_gammes()));
    bh_verifier_post();
    $e = bh_entree();
    $c = bh_catalogue();
    $p = isset($e['prix']) && is_array($e['prix']) ? $e['prix'] : array();
    foreach (array('opt', 'ab') as $g) {
        $v = isset($p[$g]) ? (int) $p[$g] : 0;
        if ($v <= 0 || $v > 100000) bh_erreur('Prix d’un flacon impossible.');
        $c['prix'][$g] = $v;
    }
    $carton = (int) ($e['carton'] ?? 0);
    if ($carton < 1 || $carton > 500) bh_erreur('Nombre de flacons par carton impossible (1 à 500).');
    $c['carton'] = $carton;
    $ids = array(); foreach (bh_flacons() as $f) $ids[] = $f['id'];
    $lots = array();
    foreach ((array) ($e['lots'] ?? array()) as $i => $l) {
        $nom = bh_texte($l['nom'] ?? '', 60);
        if ($nom === '') continue;
        $compo = array();
        foreach ($ids as $id) { $q = (int) ($l['compo'][$id] ?? 0); if ($q > 0) $compo[$id] = min($q, 500); }
        $lots[] = array('id' => preg_match('/^[a-z0-9-]{1,30}$/', (string) ($l['id'] ?? '')) ? $l['id'] : 'lot' . ($i + 1) . '-' . substr(bin2hex(random_bytes(3)), 0, 4),
            'nom' => $nom, 'compo' => $compo, 'actif' => !empty($l['actif']));
        if (count($lots) >= 12) break;
    }
    $c['lots'] = $lots;
    $plv = array();
    foreach ((array) ($e['plv'] ?? array()) as $i => $x) {
        $nom = bh_texte($x['nom'] ?? '', 60);
        if ($nom === '') continue;
        $prix = isset($x['prix']) && $x['prix'] !== null && $x['prix'] !== '' ? (int) $x['prix'] : null;
        $plv[] = array('id' => preg_match('/^[a-z0-9-]{1,30}$/', (string) ($x['id'] ?? '')) ? $x['id'] : 'plv' . ($i + 1) . '-' . substr(bin2hex(random_bytes(3)), 0, 4),
            'nom' => $nom, 'desc' => bh_texte($x['desc'] ?? '', 120), 'prix' => $prix !== null && $prix > 0 ? $prix : null);
        if (count($plv) >= 12) break;
    }
    $c['plv'] = $plv;
    bh_reglage_ecrire('catalogue', json_encode($c, JSON_UNESCAPED_UNICODE));
    bh_json(array('ok' => true, 'catalogue' => bh_catalogue()));

case 'messages':
    bh_connecte();
    $l = array();
    foreach (bh_tous('SELECT m.*, k.societe, k.email, k.numero FROM messages m LEFT JOIN clients k ON k.id = m.client_id ORDER BY m.traite IS NOT NULL, m.id DESC LIMIT 500') as $m)
        $l[] = array('id' => (int) $m['id'], 'texte' => $m['texte'], 'commande' => (string) $m['commande'], 'cree' => bh_date_fr($m['cree']) . ' ' . date('H:i', strtotime($m['cree'])),
            'traite' => (bool) $m['traite'], 'societe' => (string) $m['societe'], 'email' => (string) $m['email'], 'client_id' => (int) $m['client_id']);
    bh_json(array('ok' => true, 'messages' => $l));

case 'message_traite':
    bh_verifier_post(); bh_connecte();
    $e = bh_entree();
    bh_exec('UPDATE messages SET traite = ? WHERE id = ?', array(!empty($e['traite']) ? bh_maintenant() : null, (int) ($e['id'] ?? 0)));
    bh_json(array('ok' => true));

case 'reglages':
    bh_connecte();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') bh_json(array('ok' => true, 'email_bh' => bh_email_bh(), 'emetteur' => bh_emetteur()));
    bh_verifier_post();
    $e = bh_entree();
    $mel = bh_champ('email_bh', 200);
    if (!bh_email_valide($mel)) bh_erreur('Adresse e-mail des avis invalide.');
    bh_reglage_ecrire('email_bh', $mel);
    $em = bh_emetteur();
    $x = isset($e['emetteur']) && is_array($e['emetteur']) ? $e['emetteur'] : array();
    foreach (array('raison' => 120, 'adresse' => 200, 'cp' => 12, 'ville' => 80, 'pays' => 60, 'email' => 200) as $k => $max) if (isset($x[$k])) $em[$k] = bh_texte($x[$k], $max);
    foreach (array('mentions' => 300, 'conditions' => 400, 'penalites' => 400) as $k => $max) if (isset($x[$k])) $em[$k] = bh_texte_long($x[$k], $max);
    if ($em['raison'] === '' || $em['adresse'] === '') bh_erreur('Raison sociale et adresse obligatoires (elles figurent sur les factures).');
    bh_reglage_ecrire('emetteur', json_encode($em, JSON_UNESCAPED_UNICODE));
    bh_json(array('ok' => true, 'email_bh' => bh_email_bh(), 'emetteur' => bh_emetteur()));

default:
    bh_erreur('Action inconnue.', 404);
}
