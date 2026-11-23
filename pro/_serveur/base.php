<?php
/* BH Insight Pro — partie serveur commune à l'espace client (pro.bhinsight.fr, api.php) et à l'espace BH Insight (pro.bhinsight.fr/admin, admin/api.php).
 *
 * Données : une base SQLite dans un dossier HORS du dossier publié (voir bh_dossier) : une publication (OVH récupère le dépôt)
 * ne les touche jamais, et elles ne sont jamais servies directement.
 * Compatible PHP 7.2 et suivants. Aucune bibliothèque.
 * Charte et textes propres à BH Insight ; factures au nom de RVF (société éditrice de la marque).
 */
if (defined('BH_BASE')) return;
define('BH_BASE', 1);
date_default_timezone_set('Europe/Paris');
require __DIR__ . '/config.php';

/* ------------------------------------------------------------------ dossier des données */
/* Par défaut : à côté du dossier publié du site (sur l'hébergement OVH : …/bhinsight-pro-donnees, à côté de …/bhinsight).
   Variable d'environnement BH_DONNEES pour les essais. */
function bh_dossier()
{
    static $d = null;
    if ($d !== null) return $d;
    $env = getenv('BH_DONNEES');
    $d = $env ? $env : dirname(__DIR__, 3) . '/bhinsight-pro-donnees';
    if (!is_dir($d) && !@mkdir($d, 0700, true)) {
        /* secours : dans le dossier publié, interdit au web (mais une publication pourrait l'effacer) */
        $d = __DIR__ . '/_donnees';
        if (!is_dir($d)) @mkdir($d, 0700, true);
    }
    if (!is_dir("$d/sessions")) @mkdir("$d/sessions", 0700, true);
    if (!file_exists("$d/.htaccess")) @file_put_contents("$d/.htaccess", "Require all denied\nDeny from all\n");
    return $d;
}

/* ------------------------------------------------------------------ base de données */
function bh_db()
{
    static $db = null;
    if ($db) return $db;
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) bh_erreur('Base de données indisponible sur ce serveur (SQLite).', 500);
    $db = new PDO('sqlite:' . bh_dossier() . '/bhinsight-pro.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA busy_timeout = 8000; PRAGMA foreign_keys = ON;');
    $db->exec("
CREATE TABLE IF NOT EXISTS clients (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  numero TEXT UNIQUE,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  mdp TEXT NOT NULL,
  etat TEXT NOT NULL DEFAULT 'attente',
  societe TEXT, enseigne TEXT, nom TEXT, prenom TEXT, tel TEXT, siret TEXT, tva TEXT,
  adresse TEXT, cp TEXT, ville TEXT, pays TEXT,
  livraison TEXT,
  cree TEXT NOT NULL, valide TEXT,
  reinit TEXT, reinit_fin INTEGER
);
CREATE TABLE IF NOT EXISTS commandes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  numero TEXT NOT NULL UNIQUE,
  client_id INTEGER NOT NULL REFERENCES clients(id),
  etat TEXT NOT NULL DEFAULT 'recue',
  reference TEXT, note TEXT, note_interne TEXT,
  lignes TEXT NOT NULL,
  ht INTEGER NOT NULL, tva INTEGER NOT NULL, ttc INTEGER NOT NULL, flacons INTEGER NOT NULL,
  livraison TEXT NOT NULL,
  colis TEXT,
  bl TEXT,
  creee TEXT NOT NULL, preparee TEXT, expediee TEXT, annulee TEXT
);
CREATE INDEX IF NOT EXISTS commandes_client ON commandes(client_id);
CREATE TABLE IF NOT EXISTS factures (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  numero TEXT NOT NULL UNIQUE,
  commande TEXT NOT NULL UNIQUE,
  client_id INTEGER NOT NULL REFERENCES clients(id),
  emise TEXT NOT NULL,
  ttc INTEGER NOT NULL,
  donnees TEXT NOT NULL,
  lue TEXT
);
CREATE TRIGGER IF NOT EXISTS factures_fige BEFORE UPDATE OF numero, commande, client_id, emise, ttc, donnees ON factures BEGIN SELECT RAISE(ABORT, 'Facture émise : modification impossible.'); END;
CREATE TRIGGER IF NOT EXISTS factures_garde BEFORE DELETE ON factures BEGIN SELECT RAISE(ABORT, 'Facture émise : suppression impossible.'); END;
CREATE TABLE IF NOT EXISTS messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  client_id INTEGER REFERENCES clients(id),
  commande TEXT,
  texte TEXT NOT NULL,
  cree TEXT NOT NULL, traite TEXT
);
CREATE TABLE IF NOT EXISTS reglages (cle TEXT PRIMARY KEY, valeur TEXT);
CREATE TABLE IF NOT EXISTS tentatives (cle TEXT NOT NULL, quand INTEGER NOT NULL);
CREATE INDEX IF NOT EXISTS tentatives_cle ON tentatives(cle, quand);
");
    return $db;
}
function bh_un($sql, $p = array()) { $q = bh_db()->prepare($sql); $q->execute($p); $r = $q->fetch(); return $r === false ? null : $r; }
function bh_tous($sql, $p = array()) { $q = bh_db()->prepare($sql); $q->execute($p); return $q->fetchAll(); }
function bh_exec($sql, $p = array()) { $q = bh_db()->prepare($sql); $q->execute($p); return $q->rowCount(); }
function bh_reglage($cle, $defaut = null) { $r = bh_un('SELECT valeur FROM reglages WHERE cle = ?', array($cle)); return $r ? $r['valeur'] : $defaut; }
function bh_reglage_ecrire($cle, $valeur) { bh_exec('INSERT OR REPLACE INTO reglages (cle, valeur) VALUES (?, ?)', array($cle, $valeur)); }
function bh_maintenant() { return date('Y-m-d H:i:s'); }

/* ------------------------------------------------------------------ réponses et entrées */
function bh_json($donnees, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function bh_erreur($message, $code = 400) { bh_json(array('ok' => false, 'erreur' => $message), $code); }

/* Toute modification : POST, en-tête X-BH (qu'un formulaire d'un autre site ne peut pas ajouter) et origine identique au site */
function bh_verifier_post()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') bh_erreur('Méthode non autorisée.', 405);
    if (!isset($_SERVER['HTTP_X_BH'])) bh_erreur('Requête refusée.', 403);
    $origine = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
    $hote = $origine ? parse_url($origine, PHP_URL_HOST) . (parse_url($origine, PHP_URL_PORT) ? ':' . parse_url($origine, PHP_URL_PORT) : '') : '';
    if ($hote && isset($_SERVER['HTTP_HOST']) && strtolower($hote) !== strtolower($_SERVER['HTTP_HOST'])) bh_erreur('Requête refusée.', 403);
}
function bh_entree()
{
    static $e = null;
    if ($e !== null) return $e;
    $e = json_decode(file_get_contents('php://input'), true);
    if (!is_array($e)) $e = array();
    return $e;
}
function bh_champ($nom, $max = 200) { $e = bh_entree(); return isset($e[$nom]) && !is_array($e[$nom]) ? bh_texte($e[$nom], $max) : ''; }
function bh_texte($valeur, $max)
{
    $t = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $valeur));
    return function_exists('mb_substr') ? mb_substr($t, 0, $max, 'UTF-8') : substr($t, 0, $max);
}
function bh_texte_long($valeur, $max)
{
    $t = trim(str_replace("\r", '', preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', (string) $valeur)));
    return function_exists('mb_substr') ? mb_substr($t, 0, $max, 'UTF-8') : substr($t, 0, $max);
}
function bh_email_valide($e) { return (bool) filter_var($e, FILTER_VALIDATE_EMAIL); }
function bh_ip() { return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?'; }

/* ------------------------------------------------------------------ limites d'essais */
function bh_limite($cle, $max, $duree)
{
    $t = time();
    bh_exec('DELETE FROM tentatives WHERE quand < ?', array($t - 86400));
    $n = bh_un('SELECT COUNT(*) AS n FROM tentatives WHERE cle = ? AND quand > ?', array($cle, $t - $duree));
    return $n['n'] < $max;
}
function bh_noter_essai($cle) { bh_exec('INSERT INTO tentatives (cle, quand) VALUES (?, ?)', array($cle, time())); }

/* ------------------------------------------------------------------ sessions */
/* Une session par espace (client, BH Insight), gardée 12 h dans le dossier des données */
function bh_session($nom)
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '43200');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_save_path(bh_dossier() . '/sessions');
    session_name($nom);
    if (PHP_VERSION_ID >= 70300) session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax'));
    else session_set_cookie_params(0, '/; samesite=Lax', '', $https, true);
    session_start();
}

/* ------------------------------------------------------------------ numéros */
/* Compteurs qui ne repartent jamais à zéro. Commande : BH-AAMM-0001 ; facture : F2026-00001 (suite continue, exigée pour les factures) ;
   client : 1001, 1002… (modifiable par BH Insight à la validation). */
function bh_compteur($cle, $depart)
{
    $n = (int) bh_reglage($cle, (string) ($depart - 1)) + 1;
    bh_reglage_ecrire($cle, (string) $n);
    return $n;
}
function bh_numero_commande()
{
    do { $num = 'BH-' . date('ym') . '-' . sprintf('%04d', bh_compteur('compteur_commande', 1)); }
    while (bh_un('SELECT id FROM commandes WHERE numero = ?', array($num)));
    return $num;
}
function bh_numero_facture()
{
    do { $num = 'F' . date('Y') . '-' . sprintf('%05d', bh_compteur('compteur_facture', 1)); }
    while (bh_un('SELECT id FROM factures WHERE numero = ?', array($num)));
    return $num;
}
function bh_numero_client()
{
    do { $num = (string) bh_compteur('compteur_client', 1001); }
    while (bh_un('SELECT id FROM clients WHERE numero = ?', array($num)));
    return $num;
}

/* ------------------------------------------------------------------ catalogue et prix */
/* Les 8 couleurs sont fixes ; prix, taille des cartons, lots et présentoirs se règlent dans l'espace BH Insight (Catalogue et prix). */
function bh_flacons()
{
    return array(
        array('id' => 'opt-orange', 'g' => 'opt', 'nom' => 'Orange sunset', 'ton' => 'v-orange'),
        array('id' => 'opt-vert', 'g' => 'opt', 'nom' => 'Vert jungle', 'ton' => 'v-vert'),
        array('id' => 'opt-bleu', 'g' => 'opt', 'nom' => 'Bleu tropical', 'ton' => 'v-bleu'),
        array('id' => 'opt-rose', 'g' => 'opt', 'nom' => 'Rose tropical', 'ton' => 'v-rose'),
        array('id' => 'ab-lagon', 'g' => 'ab', 'nom' => 'Bleu lagon', 'ton' => 'v-lagon'),
        array('id' => 'ab-jaune', 'g' => 'ab', 'nom' => 'Jaune soleil', 'ton' => 'v-jaune'),
        array('id' => 'ab-rose', 'g' => 'ab', 'nom' => 'Rose paradis', 'ton' => 'v-paradis'),
        array('id' => 'ab-vert', 'g' => 'ab', 'nom' => 'Vert émeraude', 'ton' => 'v-emeraude'),
    );
}
function bh_gammes() { return array('opt' => 'Nettoyant Optique', 'ab' => 'Anti-Buée & Nettoyant'); }
function bh_catalogue_defaut()
{
    $tous = array(); $opt = array(); $ab = array();
    foreach (bh_flacons() as $f) { $tous[$f['id']] = 3; if ($f['g'] === 'opt') $opt[$f['id']] = 6; else $ab[$f['id']] = 6; }
    return array(
        'prix' => array('opt' => 150, 'ab' => 249),     /* centimes HT par flacon (propriétaire, 23/11/2026 : 1,50 € et 2,49 €) */
        'carton' => 24,
        'lots' => array(
            array('id' => 'decouverte', 'nom' => 'Lot découverte', 'compo' => $tous, 'actif' => true),
            array('id' => 'opt', 'nom' => 'Lot Nettoyant Optique', 'compo' => $opt, 'actif' => true),
            array('id' => 'ab', 'nom' => 'Lot Anti-Buée & Nettoyant', 'compo' => $ab, 'actif' => true),
        ),
        'plv' => array(
            array('id' => 'presentoir', 'nom' => 'Présentoir de comptoir', 'desc' => 'Pour présenter les 8 couleurs près de la caisse', 'prix' => null),
            array('id' => 'kit', 'nom' => 'Kit PLV vitrine', 'desc' => 'Supports de vitrine aux couleurs BH Insight', 'prix' => null),
        ),
    );
}
function bh_catalogue()
{
    $c = json_decode((string) bh_reglage('catalogue', ''), true);
    return is_array($c) ? array_merge(bh_catalogue_defaut(), $c) : bh_catalogue_defaut();
}
/* Articles qu'un client peut commander, prix calculés par le serveur (jamais repris de la page) */
function bh_articles()
{
    $c = bh_catalogue();
    $g = bh_gammes();
    $carton = max(1, (int) $c['carton']);
    $prix = array();
    $a = array();
    foreach (bh_flacons() as $f) {
        $pu = (int) $c['prix'][$f['g']];
        $prix[$f['id']] = $pu;
        $a['unite:' . $f['id']] = array('key' => 'unite:' . $f['id'], 'type' => 'unite', 'g' => $f['g'], 'gamme' => $g[$f['g']], 'nom' => $f['nom'], 'ton' => $f['ton'], 'img' => $f['id'],
            'pu' => $pu, 'nb' => 1, 'label' => $f['nom'] . ' · flacon', 'detail' => $g[$f['g']] . ' · 30 ml');
        $a['carton:' . $f['id']] = array('key' => 'carton:' . $f['id'], 'type' => 'carton', 'g' => $f['g'], 'gamme' => $g[$f['g']], 'nom' => $f['nom'], 'ton' => $f['ton'], 'img' => $f['id'],
            'pu' => $pu * $carton, 'nb' => $carton, 'label' => $f['nom'] . ' · carton de ' . $carton, 'detail' => $g[$f['g']] . ' · ' . $carton . ' flacons de 30 ml');
    }
    $noms = array(); foreach (bh_flacons() as $f) $noms[$f['id']] = $f;
    foreach ($c['lots'] as $l) {
        if (empty($l['actif'])) continue;
        $pu = 0; $nb = 0; $parts = array(); $imgs = array();
        foreach ((array) $l['compo'] as $id => $q) {
            $q = (int) $q;
            if ($q <= 0 || !isset($prix[$id])) continue;
            $pu += $prix[$id] * $q; $nb += $q;
            $parts[] = $q . ' ' . $noms[$id]['nom'];
            if (count($imgs) < 2) $imgs[] = $id;
        }
        if (!$nb) continue;
        $a['lot:' . $l['id']] = array('key' => 'lot:' . $l['id'], 'type' => 'lot', 'g' => '', 'gamme' => 'Lot assorti', 'nom' => $l['nom'], 'ton' => isset($noms[$imgs[0]]) ? $noms[$imgs[0]]['ton'] : 'v-orange',
            'img' => $imgs[0], 'img2' => isset($imgs[1]) ? $imgs[1] : '', 'pu' => $pu, 'nb' => $nb, 'label' => $l['nom'], 'detail' => $nb . ' flacons : ' . implode(', ', $parts));
    }
    foreach ($c['plv'] as $p) {
        if ($p['prix'] === null || $p['prix'] === '' || (int) $p['prix'] <= 0) continue;
        $a['plv:' . $p['id']] = array('key' => 'plv:' . $p['id'], 'type' => 'plv', 'g' => '', 'gamme' => 'Présentoirs et PLV', 'nom' => $p['nom'], 'ton' => 'v-orange', 'img' => '',
            'pu' => (int) $p['prix'], 'nb' => 0, 'label' => $p['nom'], 'detail' => (string) $p['desc']);
    }
    return $a;
}
/* Panier envoyé par la page ({ clé: quantité }) → lignes figées avec les prix du moment */
function bh_lignes_panier($panier)
{
    $articles = bh_articles();
    $lignes = array();
    if (!is_array($panier)) return $lignes;
    foreach ($panier as $cle => $q) {
        $q = (int) $q;
        if ($q <= 0 || $q > 9999 || !isset($articles[$cle])) continue;
        $x = $articles[$cle];
        $lignes[] = array('key' => $cle, 'type' => $x['type'], 'label' => $x['label'], 'detail' => $x['detail'], 'ton' => $x['ton'],
            'pu' => $x['pu'], 'q' => $q, 'nb' => $x['nb'], 'total' => $x['pu'] * $q);
        if (count($lignes) >= 60) break;
    }
    return $lignes;
}
function bh_totaux($lignes)
{
    $ht = 0; $flacons = 0;
    foreach ($lignes as $l) { $ht += (int) $l['total']; $flacons += (int) $l['nb'] * (int) $l['q']; }
    $tva = (int) round($ht * BH_TVA / 100);
    return array('ht' => $ht, 'tva' => $tva, 'ttc' => $ht + $tva, 'flacons' => $flacons);
}
function bh_eur($c) { return number_format($c / 100, 2, ',', ' ') . ' €'; }

/* ------------------------------------------------------------------ émetteur des factures (RVF) */
function bh_emetteur()
{
    /* Vendeur : RVF (RCS Rennes 907 839 898, SAS au capital de 1 000 €, d'après le registre vérifié le 23/11/2026).
       Adresse : Boulogne-Billancourt, à la demande du propriétaire (23/11/2026), à la place du siège encore inscrit à Cesson-Sévigné.
       Tout est modifiable dans Réglages ; les mentions du bas sont recomposées à partir de ces champs. */
    $d = array(
        'raison' => 'RVF', 'forme' => 'SAS', 'capital' => '1 000 €', 'rcs_ville' => 'Rennes', 'siren' => '907 839 898', 'tva' => 'FR 72 907 839 898',
        'adresse' => '6 rue de la Pyramide', 'cp' => '92100', 'ville' => 'Boulogne-Billancourt', 'pays' => 'France',
        'email' => BH_EMAIL_BH_DEFAUT,
        'delai' => 30,
        'escompte' => 'néant',
        'penalites' => 'En cas de retard de paiement, pénalités exigibles sans rappel au taux d’intérêt de la BCE majoré de 10 points, et indemnité forfaitaire pour frais de recouvrement de 40 € (article L441-10 du Code de commerce).',
        'conditions' => '',
    );
    $r = json_decode((string) bh_reglage('emetteur', ''), true);
    /* une seule fois : une adresse de Cesson-Sévigné déjà enregistrée dans Réglages passe à Boulogne-Billancourt (propriétaire, 23/11/2026) */
    if (is_array($r) && isset($r['adresse']) && stripos($r['adresse'], 'Bas-Village') !== false && !bh_reglage('migration_boulogne')) {
        $r['adresse'] = $d['adresse']; $r['cp'] = $d['cp']; $r['ville'] = $d['ville'];
        bh_reglage_ecrire('emetteur', json_encode($r, JSON_UNESCAPED_UNICODE));
        bh_reglage_ecrire('migration_boulogne', '1');
    }
    $e = is_array($r) ? array_merge($d, $r) : $d;
    $e['delai'] = max(0, min(60, (int) $e['delai']));
    $e['mentions'] = implode(' · ', array_filter(array($e['raison'], trim($e['forme'] . ($e['capital'] !== '' ? ' au capital de ' . $e['capital'] : '')),
        $e['siren'] !== '' ? 'RCS ' . trim($e['rcs_ville'] . ' ' . $e['siren']) : '', $e['tva'] !== '' ? 'TVA ' . $e['tva'] : '')));
    return $e;
}
function bh_email_bh() { $e = bh_reglage('email_bh', ''); return bh_email_valide($e) ? $e : BH_EMAIL_BH_DEFAUT; }

/* ------------------------------------------------------------------ commandes, bons de livraison et factures */
$GLOBALS['BH_ETATS'] = array('recue' => 'Reçue', 'preparation' => 'En préparation', 'expediee' => 'Expédiée', 'annulee' => 'Annulée');
function bh_etat_texte($e) { return isset($GLOBALS['BH_ETATS'][$e]) ? $GLOBALS['BH_ETATS'][$e] : $e; }
function bh_date_fr($d) { return $d ? date('d.m.Y', strtotime($d)) : ''; }
function bh_siren($siret)
{
    $n = preg_replace('/\D/', '', (string) $siret);
    return strlen($n) >= 9 ? substr($n, 0, 3) . ' ' . substr($n, 3, 3) . ' ' . substr($n, 6, 3) : '';
}
function bh_client_bloc($c)
{
    return array('numero' => (string) $c['numero'], 'societe' => (string) $c['societe'], 'enseigne' => (string) $c['enseigne'],
        'nom' => trim($c['prenom'] . ' ' . $c['nom']), 'adresse' => (string) $c['adresse'], 'cp' => (string) $c['cp'], 'ville' => (string) $c['ville'],
        'pays' => (string) $c['pays'], 'tva' => (string) $c['tva'], 'siret' => (string) $c['siret'], 'siren' => bh_siren($c['siret']), 'email' => (string) $c['email'], 'tel' => (string) $c['tel']);
}
/* Contenu du bon de livraison, figé à l'expédition (une adresse modifiée ensuite ne change pas un bon déjà émis) */
function bh_bl_contenu($cmd, $client)
{
    return array('type' => 'bl', 'numero' => $cmd['numero'], 'commande' => $cmd['numero'], 'date_commande' => bh_date_fr($cmd['creee']),
        'expediee' => bh_date_fr($cmd['expediee']), 'reference' => (string) $cmd['reference'], 'colis' => (string) $cmd['colis'],
        'emetteur' => bh_emetteur(), 'client' => bh_client_bloc($client), 'livraison' => json_decode($cmd['livraison'], true),
        'lignes' => json_decode($cmd['lignes'], true), 'flacons' => (int) $cmd['flacons']);
}
/* Facture émise à l'expédition, numérotée à la suite, figée (déclencheurs de la base) */
function bh_facturer($cmd, $client)
{
    $deja = bh_un('SELECT * FROM factures WHERE commande = ?', array($cmd['numero']));
    if ($deja) return $deja;
    $num = bh_numero_facture();
    $quand = $cmd['expediee'] ?: bh_maintenant();
    $em = bh_emetteur();
    $d = array('type' => 'facture', 'numero' => $num, 'emise' => bh_date_fr($quand),
        'echeance' => date('d.m.Y', strtotime($quand) + 86400 * (int) $em['delai']), 'delai' => (int) $em['delai'], 'operation' => 'Livraison de biens', 'commande' => $cmd['numero'], 'date_commande' => bh_date_fr($cmd['creee']),
        'reference' => (string) $cmd['reference'], 'expediee' => bh_date_fr($cmd['expediee']),
        'emetteur' => $em, 'client' => bh_client_bloc($client), 'livraison' => json_decode($cmd['livraison'], true),
        'lignes' => json_decode($cmd['lignes'], true), 'ht' => (int) $cmd['ht'], 'tva' => (int) $cmd['tva'], 'ttc' => (int) $cmd['ttc'], 'taux' => BH_TVA,
        'flacons' => (int) $cmd['flacons']);
    bh_exec('INSERT INTO factures (numero, commande, client_id, emise, ttc, donnees) VALUES (?, ?, ?, ?, ?, ?)',
        array($num, $cmd['numero'], $cmd['client_id'], $quand, (int) $cmd['ttc'], json_encode($d, JSON_UNESCAPED_UNICODE)));
    return bh_un('SELECT * FROM factures WHERE numero = ?', array($num));
}
/* Vue d'une commande pour les pages (client et BH Insight) */
function bh_commande_vue($c, $complet = false)
{
    $f = bh_un('SELECT numero FROM factures WHERE commande = ?', array($c['numero']));
    $v = array('numero' => $c['numero'], 'etat' => $c['etat'], 'etat_texte' => bh_etat_texte($c['etat']),
        'creee' => bh_date_fr($c['creee']), 'creee_heure' => $c['creee'] ? date('H:i', strtotime($c['creee'])) : '',
        'preparee' => bh_date_fr($c['preparee']), 'expediee' => bh_date_fr($c['expediee']), 'annulee' => bh_date_fr($c['annulee']),
        'reference' => (string) $c['reference'], 'note' => (string) $c['note'], 'colis' => (string) $c['colis'],
        'lignes' => json_decode($c['lignes'], true), 'livraison' => json_decode($c['livraison'], true),
        'ht' => (int) $c['ht'], 'tva' => (int) $c['tva'], 'ttc' => (int) $c['ttc'], 'flacons' => (int) $c['flacons'],
        'facture' => $f ? $f['numero'] : '', 'bl' => $c['etat'] === 'expediee');
    if ($complet) $v['note_interne'] = (string) $c['note_interne'];
    return $v;
}

/* ------------------------------------------------------------------ e-mails */
function bh_entete_utf8($texte)
{
    if (!preg_match('/[^\x20-\x7E]/', $texte)) return $texte;
    $mots = array(); $bloc = '';
    foreach (preg_split('//u', $texte, -1, PREG_SPLIT_NO_EMPTY) as $c) {
        if (strlen($bloc . $c) > 42) { $mots[] = '=?UTF-8?B?' . base64_encode($bloc) . '?='; $bloc = ''; }
        $bloc .= $c;
    }
    $mots[] = '=?UTF-8?B?' . base64_encode($bloc) . '?=';
    return implode(' ', $mots);
}
function bh_h($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
/* Envoi d'un e-mail HTML (avec sa version texte), aux couleurs de BH Insight ; renvoie true si l'hébergement l'a accepté.
   $m : texte simple, ou tableau : 'pour' ('client' ou 'bh'), 'etiquette', 'titre', 'texte' (paragraphes séparés par une ligne vide),
   'lignes' (array(array(libellé, valeur))), 'citation', 'bouton' (array(libellé, adresse)), 'apres'. */
function bh_mail($a, $sujet, $m, $repondre = '', $de = 'BH Insight Pro')
{
    if (!bh_email_valide($a)) return false;
    if (!is_array($m)) $m = array('texte' => (string) $m);
    $m = array_merge(array('pour' => 'client', 'etiquette' => '', 'titre' => '', 'texte' => '', 'lignes' => array(), 'citation' => '', 'bouton' => null, 'apres' => ''), $m);
    if ($m['pour'] === 'client' && !$repondre) $repondre = bh_email_bh();
    $nl = "\r\n";
    $b = 'bh-' . bin2hex(random_bytes(10));
    $corps = '--' . $b . $nl . 'Content-Type: text/plain; charset=UTF-8' . $nl . 'Content-Transfer-Encoding: base64' . $nl . $nl
        . chunk_split(base64_encode(str_replace("\n", "\r\n", bh_mail_texte($m))), 76, $nl)
        . '--' . $b . $nl . 'Content-Type: text/html; charset=UTF-8' . $nl . 'Content-Transfer-Encoding: base64' . $nl . $nl
        . chunk_split(base64_encode(bh_mail_html($m, $sujet)), 76, $nl)
        . '--' . $b . '--' . $nl;
    $entetes = array('From: ' . bh_entete_utf8($de) . ' <' . BH_EXPEDITEUR . '>', 'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $b . '"', 'X-Mailer: BH Insight Pro');
    if ($repondre && bh_email_valide($repondre)) $entetes[] = 'Reply-To: ' . $repondre;
    return @mail($a, bh_entete_utf8($sujet), $corps, implode($nl, $entetes), '-f' . BH_EXPEDITEUR);
}
function bh_mail_texte($m)
{
    $t = array();
    if ($m['titre']) $t[] = $m['titre'];
    if ($m['texte']) $t[] = $m['texte'];
    foreach ($m['lignes'] as $l) $t[] = $l[0] . ' : ' . $l[1];
    if ($m['citation']) $t[] = $m['citation'];
    if ($m['bouton']) $t[] = $m['bouton'][0] . ' : ' . $m['bouton'][1];
    if ($m['apres']) $t[] = $m['apres'];
    $e = bh_emetteur();
    $t[] = "—\nBH Insight · " . ($m['pour'] === 'bh' ? 'avis automatique de la plateforme' : 'le service client') . "\n" . BH_URL_SITE . ' · ' . BH_URL_PRO . "\nBH Insight est une marque de " . $e['mentions'];
    return implode("\n\n", $t);
}
function bh_mail_html($m, $sujet)
{
    $police = "font-family:'Bricolage Grotesque',Arial,Helvetica,sans-serif;";
    $mono = "font-family:'Space Mono','Courier New',monospace;";
    $par = '';
    foreach (preg_split("/\n\s*\n/", (string) $m['texte']) as $p) if (trim($p) !== '')
        $par .= '<p style="margin:0 0 14px;' . $police . 'font-size:16px;line-height:24px;color:#0A1E3C">' . nl2br(bh_h(trim($p))) . '</p>';
    $lignes = '';
    if ($m['lignes']) {
        $lignes = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 18px;border-collapse:separate;border:1px solid #E8DDCB;border-radius:14px;background:#FFF8EF">';
        foreach ($m['lignes'] as $l) $lignes .= '<tr><td style="padding:10px 16px;' . $mono . 'font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#56667D">' . bh_h($l[0]) . '</td>'
            . '<td style="padding:10px 16px;' . $police . 'font-size:15px;font-weight:700;color:#0A1E3C;text-align:right">' . bh_h($l[1]) . '</td></tr>';
        $lignes .= '</table>';
    }
    $cit = $m['citation'] ? '<div style="margin:0 0 18px;padding:14px 16px;border-left:4px solid #FF7A00;background:#FFF8EF;' . $police . 'font-size:14px;line-height:21px;color:#0A1E3C;white-space:pre-line">' . bh_h($m['citation']) . '</div>' : '';
    $bouton = $m['bouton'] ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 18px"><tr><td style="border-radius:999px;background:#FF7A00">'
        . '<a href="' . bh_h($m['bouton'][1]) . '" style="display:inline-block;padding:14px 26px;' . $police . 'font-size:15px;font-weight:800;color:#0A1E3C;text-decoration:none;border-radius:999px">' . bh_h($m['bouton'][0]) . ' →</a></td></tr></table>' : '';
    $apres = $m['apres'] ? '<p style="margin:0;' . $police . 'font-size:13px;line-height:20px;color:#56667D">' . bh_h($m['apres']) . '</p>' : '';
    $e = bh_emetteur();
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . bh_h($sujet) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#FFF8EF">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFF8EF"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;border-radius:24px;overflow:hidden;background:#FFFFFF">'
        . '<tr><td style="padding:26px 30px;background:#0A1E3C"><img src="' . BH_URL_PRO . 'img/logo-clair.png" width="150" height="32" alt="BH Insight" style="display:block;width:150px;height:auto;border:0;color:#FFF8EF;font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:800">'
        . '<p style="margin:10px 0 0;' . $mono . 'font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#A9B8CB">' . ($m['pour'] === 'bh' ? 'Espace BH Insight' : 'Espace pro') . '</p></td></tr>'
        . '<tr><td style="height:5px;line-height:5px;font-size:0;background:#FF7A00">&nbsp;</td></tr>'
        . '<tr><td style="padding:30px 30px 24px">'
        . ($m['etiquette'] ? '<p style="margin:0 0 8px;' . $mono . 'font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8A4A00">( ' . bh_h($m['etiquette']) . ' )</p>' : '')
        . ($m['titre'] ? '<h1 style="margin:0 0 16px;' . $police . 'font-size:28px;line-height:32px;font-weight:800;color:#0A1E3C">' . bh_h($m['titre']) . '</h1>' : '')
        . $par . $lignes . $cit . $bouton . $apres
        . '</td></tr>'
        . '<tr><td style="padding:18px 30px;background:#0A1E3C;' . $police . 'font-size:12px;line-height:19px;color:#C6D2E0">'
        . ($m['pour'] === 'bh' ? 'Avis automatique de la plateforme BH Insight Pro.' : 'Une question ? Répondez simplement à cet e-mail.')
        . '<br><a href="' . BH_URL_SITE . '" style="color:#FF7A00;text-decoration:none">bhinsight.fr</a> · <a href="' . BH_URL_PRO . '" style="color:#FF7A00;text-decoration:none">pro.bhinsight.fr</a>'
        . '<br>BH Insight est une marque de ' . bh_h($e['mentions']) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}
