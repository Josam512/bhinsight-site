/* BH Insight Pro — espace client (pro.bhinsight.fr) : comportements de toutes les pages.
   Chaque page porte data-page sur <body>. Tout passe par api.php ; le panier reste dans le navigateur jusqu'à l'envoi.
   Les textes venant du serveur sont toujours insérés comme texte (jamais comme HTML). */
'use strict';
(function () {
  var PANIER = 'bh-pro-panier';
  var page = document.body.getAttribute('data-page');
  var IMG = function (id) { return 'img/' + id + '.webp'; };

  /* ---------------------------------------------------------------- outils */
  function h(tag, a) {
    var e = document.createElement(tag);
    if (a) Object.keys(a).forEach(function (k) {
      var v = a[k];
      if (v === null || v === undefined || v === false) return;
      if (k === 'class') e.className = v;
      else if (k === 'text') e.textContent = v;
      else if (k === 'svg') e.innerHTML = v;          /* icônes fixes de ce fichier seulement */
      else if (k === 'style') e.style.cssText = v;
      else if (k.slice(0, 2) === 'on') e.addEventListener(k.slice(2), v);
      else e.setAttribute(k, v === true ? '' : v);
    });
    for (var i = 2; i < arguments.length; i++) ajouter(e, arguments[i]);
    return e;
  }
  function ajouter(e, x) {
    if (x === null || x === undefined || x === false) return;
    if (Array.isArray(x)) { x.forEach(function (y) { ajouter(e, y); }); return; }
    e.append(x instanceof Node ? x : String(x));
  }
  function $(s) { return document.querySelector(s); }
  function fl(n) { return n + (n > 1 ? ' flacons' : ' flacon'); }
  function vider(e) { while (e.firstChild) e.removeChild(e.firstChild); return e; }
  function eur(c) { return window.BH_DOC ? window.BH_DOC.eur(c) : (c / 100).toFixed(2).replace('.', ',') + ' €'; }
  function param(n) { return new URLSearchParams(location.search).get(n) || ''; }
  function api(action, donnees, extra) {
    var url = 'api.php?a=' + encodeURIComponent(action) + (extra || '');
    var o = { credentials: 'same-origin', headers: { 'X-BH': '1' } };
    if (donnees !== undefined) { o.method = 'POST'; o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(donnees); }
    return fetch(url, o).then(function (r) {
      return r.json().catch(function () { return { ok: false, erreur: 'Réponse inattendue du serveur.' }; }).then(function (j) {
        if (r.status === 401 && page !== 'connexion' && page !== 'inscription') { aLaConnexion(); throw new Error('session'); }
        if (!j.ok) throw new Error(j.erreur || 'Erreur.');
        return j;
      });
    }, function () { throw new Error('Connexion au serveur impossible : vérifiez votre accès à Internet.'); });
  }
  function aLaConnexion() { location.href = 'index.html?retour=' + encodeURIComponent(location.pathname.split('/').pop() + location.search); }
  function alerte(zone, msg, type) {
    vider(zone);
    if (msg) zone.append(h('p', { class: 'alerte' + (type ? ' ' + type : ''), role: type === 'ok' ? 'status' : 'alert', text: msg }));
  }
  function toast(msg) {
    var t = h('div', { class: 'toast', role: 'status', text: msg });
    document.body.append(t);
    setTimeout(function () { t.remove(); }, 2600);
  }
  function lirePanier() { try { var p = JSON.parse(localStorage.getItem(PANIER) || '{}'); return p && typeof p === 'object' ? p : {}; } catch (e) { return {}; } }
  function ecrirePanier(p) { try { localStorage.setItem(PANIER, JSON.stringify(p)); } catch (e) { /* stockage indisponible */ } majBadge(); }
  function nbLignes() { var p = lirePanier(); return Object.keys(p).filter(function (k) { return p[k] > 0; }).length; }
  function champs(form) {
    var o = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name) return;
      if (el.type === 'checkbox') o[el.name] = el.checked;
      else if (el.type === 'radio') { if (el.checked) o[el.name] = el.value; }
      else o[el.name] = el.value;
    });
    return o;
  }
  function remplir(form, x) {
    Object.keys(x || {}).forEach(function (k) { var el = form.elements[k]; if (el && el.type !== 'checkbox' && el.type !== 'radio' && x[k] !== null && typeof x[k] !== 'object') el.value = x[k]; });
  }
  function occupe(btn, oui) { if (!btn) return; btn.disabled = oui; btn.setAttribute('aria-busy', oui ? 'true' : 'false'); }

  /* ---------------------------------------------------------------- en-tête */
  var ICO = {
    catalogue: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h6v3H9zM8 6h8l1 4v10a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V10z"/></svg>',
    panier: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/></svg>',
    historique: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    message: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>',
    compte: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>'
  };
  var badgePanier = null;
  function majBadge() {
    if (!badgePanier) return;
    var n = nbLignes();
    badgePanier.textContent = String(n);
    badgePanier.hidden = !n;
  }
  function entete(actif) {
    var zone = $('#tete');
    if (!zone) return;
    badgePanier = h('span', { class: 'badge-n', 'aria-label': 'articles' });
    var liens = [['catalogue', 'Commander', 'catalogue.html'], ['panier', 'Panier', 'panier.html'], ['historique', 'Historique', 'historique.html'], ['message', 'Message', 'message.html'], ['compte', 'Mon compte', 'compte.html']];
    vider(zone).append(h('nav', { class: 'nav', 'aria-label': 'Espace pro' },
      h('a', { class: 'nav-brand', href: 'accueil.html', 'aria-label': 'BH Insight Pro, accueil' }, h('img', { src: 'img/logo-clair.png', alt: 'BH Insight' }), h('span', { class: 'nav-tag mono', text: 'Pro' })),
      h('div', { class: 'nav-links' }, liens.map(function (l) {
        return h('a', { class: 'nav-link' + (l[0] === actif ? ' is-on' : ''), href: l[2], 'aria-current': l[0] === actif ? 'page' : null },
          h('span', { svg: ICO[l[0]], style: 'display:inline-flex' }), l[1], l[0] === 'panier' ? badgePanier : null);
      }))));
    majBadge();
  }
  function exiger() {
    return api('etat').then(function (j) {
      if (!j.connecte) { aLaConnexion(); throw new Error('session'); }
      document.body.classList.remove('verif');
      return j.client;
    });
  }

  /* ---------------------------------------------------------------- catalogue (commun au catalogue, au panier et à l'accueil) */
  var catalogue = null;
  function chargerCatalogue() {
    if (catalogue) return Promise.resolve(catalogue);
    return api('catalogue').then(function (j) { catalogue = j; catalogue.index = {}; j.articles.forEach(function (a) { catalogue.index[a.key] = a; }); return catalogue; });
  }
  function totaux(p) {
    var ht = 0, flacons = 0, lignes = [];
    Object.keys(p).forEach(function (k) {
      var a = catalogue.index[k], q = p[k];
      if (!a || !(q > 0)) return;
      ht += a.pu * q; flacons += a.nb * q;
      lignes.push({ a: a, q: q, total: a.pu * q });
    });
    var tva = Math.round(ht * catalogue.taux / 100);
    return { lignes: lignes, ht: ht, tva: tva, ttc: ht + tva, flacons: flacons };
  }
  function visuel(a, grand) {
    if (a.type === 'plv') return grand ? h('span', { class: 'plv' }, ['opt-orange', 'ab-lagon', 'ab-rose', 'opt-vert'].map(function (id) { return h('img', { src: IMG(id), alt: '' }); })) : h('span', { class: 'vign-plv', text: 'PLV' });
    if (a.type === 'lot') return grand ? h('span', { class: 'duo' }, h('img', { src: IMG(a.img), alt: '' }), a.img2 ? h('img', { src: IMG(a.img2), alt: '' }) : null)
      : h('span', { class: 'vign ' + a.ton }, h('img', { src: IMG(a.img), alt: '' }), a.img2 ? h('img', { src: IMG(a.img2), alt: '' }) : null);
    return grand ? h('img', { src: IMG(a.img), alt: a.gamme + ', flacon ' + a.nom.toLowerCase() }) : h('span', { class: 'vign ' + a.ton }, h('img', { src: IMG(a.img), alt: '' }));
  }
  function compteur(q, moins, plus, nom) {
    return h('div', { class: 'qty' },
      h('button', { type: 'button', 'aria-label': 'Retirer un · ' + nom, onclick: moins, text: '−' }),
      h('span', { 'aria-live': 'polite', text: String(q) }),
      h('button', { type: 'button', 'aria-label': 'Ajouter un · ' + nom, onclick: plus, text: '+' }));
  }

  /* ---------------------------------------------------------------- pages */
  var PAGES = {};

  PAGES.connexion = function () {
    var f = $('#f-connexion'), zone = $('#msg'), retour = param('retour');
    var cible = /^[a-z-]+\.html(\?[\w=&%.-]*)?$/.test(retour) ? retour : 'accueil.html';
    api('etat').then(function (j) { if (j.connecte) location.replace(cible); }).catch(function () {});
    f.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var b = f.querySelector('[type=submit]'); occupe(b, true); alerte(zone, '');
      api('connexion', champs(f)).then(function () { location.href = cible; }).catch(function (e) { alerte(zone, e.message); occupe(b, false); });
    });
    var fo = $('#f-oubli');
    $('#voir-oubli').addEventListener('click', function () { fo.hidden = !fo.hidden; if (!fo.hidden) fo.elements.email.value = f.elements.email.value; });
    fo.addEventListener('submit', function (ev) {
      ev.preventDefault();
      api('oubli', champs(fo)).then(function () { alerte(zone, 'Si un compte ouvert existe à cette adresse, un e-mail vient de partir avec un lien valable une heure.', 'ok'); fo.hidden = true; })
        .catch(function (e) { alerte(zone, e.message); });
    });
    var m = /reinit=([a-f0-9]{48})/.exec(location.hash);
    if (m) {
      var fr = $('#f-reinit'); fr.hidden = false; f.hidden = true;
      fr.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var x = champs(fr);
        if (x.mdp !== x.mdp2) { alerte(zone, 'Les deux mots de passe ne sont pas identiques.'); return; }
        api('reinit', { jeton: m[1], mdp: x.mdp }).then(function () {
          history.replaceState(null, '', 'index.html'); fr.hidden = true; f.hidden = false;
          alerte(zone, 'Mot de passe enregistré : vous pouvez vous connecter.', 'ok');
        }).catch(function (e) { alerte(zone, e.message); });
      });
    }
  };

  PAGES.inscription = function () {
    var f = $('#f-inscription'), zone = $('#msg');
    var autre = f.elements.autre_livraison, bloc = $('#bloc-livraison');
    autre.addEventListener('change', function () { bloc.hidden = !autre.checked; });
    f.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var b = f.querySelector('[type=submit]'); occupe(b, true); alerte(zone, '');
      api('inscription', champs(f)).then(function () { $('#etape-form').hidden = true; $('#etape-merci').hidden = false; window.scrollTo(0, 0); })
        .catch(function (e) { alerte(zone, e.message); occupe(b, false); });
    });
  };

  PAGES.accueil = function () {
    exiger().then(function (c) {
      entete('');
      $('#bonjour').textContent = c.prenom || c.societe;
      $('#qui').textContent = '( ' + (c.enseigne || c.societe) + ' · n° client ' + c.numero + ' )';
      if (c.factures_nouvelles) { $('#factures-nouvelles').hidden = false; $('#nb-factures').textContent = c.factures_nouvelles + (c.factures_nouvelles > 1 ? ' factures nouvelles' : ' facture nouvelle'); }
      chargerCatalogue().then(function () {
        var t = totaux(lirePanier());
        $('#panier-titre').textContent = t.lignes.length ? t.lignes.length + (t.lignes.length > 1 ? ' articles' : ' article') : 'Vide';
        $('#panier-sous').textContent = t.lignes.length ? fl(t.flacons) + ' · ' + eur(t.ht) + ' HT' : 'Rien en attente';
      });
      api('commandes').then(function (j) {
        var d = j.commandes[0];
        if (!d) { $('#derniere').textContent = 'Aucune commande pour le moment'; return; }
        $('#derniere').textContent = 'Dernière commande ' + d.numero;
        var ch = $('#derniere-etat'); ch.hidden = false; ch.textContent = d.etat_texte;
        ch.className = 'chip ' + ({ recue: 'chip-o', preparation: 'chip-b', expediee: 'chip-v', annulee: 'chip-r' })[d.etat];
      });
    }).catch(function () {});
  };

  PAGES.catalogue = function () {
    var etat = { onglet: 'unite', gamme: 'toutes' };
    var ONGLETS = [['unite', 'Au flacon'], ['carton', 'Au carton'], ['lot', 'Lots assortis'], ['plv', 'Présentoirs & PLV']];
    var FILTRES = [['toutes', 'Toutes'], ['opt', 'Nettoyant Optique'], ['ab', 'Anti-Buée & Nettoyant']];
    function changer(k, d) {
      var p = lirePanier();
      p[k] = Math.max(0, Math.min(9999, (p[k] || 0) + d));
      if (!p[k]) delete p[k];
      ecrirePanier(p); dessiner();
    }
    function dessiner() {
      var p = lirePanier();
      var art = catalogue.articles;
      var tabs = vider($('#onglets'));
      ONGLETS.forEach(function (o) {
        var n = art.filter(function (a) { return a.type === o[0] && p[a.key]; }).length;
        tabs.append(h('button', { class: 'tab' + (etat.onglet === o[0] ? ' is-on' : ''), type: 'button', role: 'tab', 'aria-selected': etat.onglet === o[0] ? 'true' : 'false',
          onclick: function () { etat.onglet = o[0]; dessiner(); } }, o[1], n ? h('span', { class: 'badge-n', text: String(n) }) : null));
      });
      var avecFiltres = etat.onglet === 'unite' || etat.onglet === 'carton';
      var fz = vider($('#filtres'));
      if (avecFiltres) {
        fz.append(h('span', { class: 'mono mute', style: 'font-size:11px;letter-spacing:.14em;text-transform:uppercase;margin-right:4px', text: 'Gamme' }));
        FILTRES.forEach(function (f) { fz.append(h('button', { class: 'filtre' + (etat.gamme === f[0] ? ' is-on' : ''), type: 'button', 'aria-pressed': etat.gamme === f[0] ? 'true' : 'false', onclick: function () { etat.gamme = f[0]; dessiner(); }, text: f[1] })); });
      } else {
        fz.append(h('p', { class: 'mute', style: 'margin:0;font-size:15px', text: etat.onglet === 'lot' ? 'Des lots prêts à poser, au prix des flacons qu’ils contiennent.' : 'Pour mettre la gamme en valeur dans votre magasin.' }));
      }
      var g = vider($('#grille'));
      var liste = art.filter(function (a) { return a.type === etat.onglet && (!avecFiltres || etat.gamme === 'toutes' || a.g === etat.gamme); });
      if (!liste.length) g.append(h('p', { class: 'card vide', style: 'grid-column:1/-1', text: etat.onglet === 'plv' ? 'Les présentoirs arrivent bientôt : leur tarif n’est pas encore fixé. Écrivez-nous si vous en voulez un.' : 'Rien pour le moment.' }));
      liste.forEach(function (a) {
        var q = p[a.key] || 0;
        g.append(h('article', { class: 'prod ' + a.ton },
          h('div', { class: 'prod-vis' }, visuel(a, true), a.type === 'carton' ? h('span', { class: 'carton-tag', text: '× ' + a.nb }) : null),
          h('div', null, h('p', { class: 'prod-gamme', text: a.gamme }), h('h3', { class: 'prod-name', style: 'margin-top:4px' }, h('span', { class: 'pastille' }), a.nom),
            h('p', { class: 'mute', style: 'margin:6px 0 0;font-size:13px', text: a.type === 'carton' ? 'Carton de ' + a.nb + ' flacons · ' + eur(a.pu / a.nb) + ' le flacon' : a.detail })),
          h('div', { class: 'prod-foot' }, h('span', { class: 'prix' }, eur(a.pu), ' ', h('small', { text: 'HT / ' + ({ unite: 'flacon', carton: 'carton', lot: 'lot', plv: 'pièce' })[a.type] })),
            compteur(q, function () { changer(a.key, -1); }, function () { changer(a.key, 1); }, a.nom))));
      });
      var t = totaux(p);
      $('#nb-flacons').textContent = fl(t.flacons);
      $('#barre-total').textContent = t.lignes.length ? fl(t.flacons) + ' · ' + eur(t.ht) + ' HT' : 'Panier vide';
      var lz = vider($('#resume'));
      if (!t.lignes.length) lz.append(h('p', { class: 'mute', style: 'margin:0;font-size:14px', text: 'Ajoutez des flacons, des cartons ou des lots : votre panier se remplit ici.' }));
      t.lignes.forEach(function (l) { lz.append(h('div', { class: 'ligne ' + l.a.ton }, h('span', null, h('span', { class: 'pastille', style: 'margin-right:6px' }), l.q + ' × ' + l.a.label), h('b', { text: eur(l.total) }))); });
      $('#ht').textContent = eur(t.ht); $('#tva').textContent = eur(t.tva); $('#ttc').textContent = eur(t.ttc);
      $('#taux').textContent = 'TVA ' + catalogue.taux + ' %';
      $('#aller-panier').classList.toggle('btn-line', !t.lignes.length);
      $('#aller-panier').classList.toggle('btn-o', !!t.lignes.length);
    }
    exiger().then(function () {
      entete('catalogue');
      return chargerCatalogue();
    }).then(function (c) {
      var o = { opt: 'Nettoyant Optique', ab: 'Anti-Buée & Nettoyant' };
      $('#tarifs').textContent = o.opt + ' ' + eur(c.prix.opt) + ' HT · ' + o.ab + ' ' + eur(c.prix.ab) + ' HT · flacons de 30 ml';
      if (param('onglet')) etat.onglet = param('onglet');
      dessiner();
    }).catch(function (e) { if (e.message !== 'session') alerte($('#msg'), e.message); });
  };

  PAGES.panier = function () {
    var client = null;
    function changer(k, d, zero) {
      var p = lirePanier();
      p[k] = zero ? 0 : Math.max(0, Math.min(9999, (p[k] || 0) + d));
      if (!p[k]) delete p[k];
      ecrirePanier(p); dessiner();
    }
    function dessiner() {
      var p = lirePanier();
      /* articles devenus indisponibles : retirés du panier */
      Object.keys(p).forEach(function (k) { if (!catalogue.index[k]) delete p[k]; });
      var t = totaux(p);
      var corps = vider($('#lignes'));
      t.lignes.forEach(function (l) {
        corps.append(h('tr', { class: l.a.ton },
          h('td', null, h('div', { style: 'display:flex;align-items:center;gap:14px' }, visuel(l.a, false), h('span', null, h('span', { class: 'fort', style: 'display:block', text: l.a.label }), h('span', { class: 'sous', text: l.a.detail })))),
          h('td', { class: 'num', text: eur(l.a.pu) }),
          h('td', null, compteur(l.q, function () { changer(l.a.key, -1); }, function () { changer(l.a.key, 1); }, l.a.label)),
          h('td', { class: 'num fort', text: eur(l.total) }),
          h('td', null, h('button', { class: 'suppr', type: 'button', 'aria-label': 'Retirer ' + l.a.label, onclick: function () { changer(l.a.key, 0, true); },
            svg: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>' }))));
      });
      $('#vide').hidden = !!t.lignes.length;
      $('#tableau').hidden = !t.lignes.length;
      $('#flacons').textContent = String(t.flacons);
      $('#ht').textContent = eur(t.ht); $('#tva').textContent = eur(t.tva); $('#ttc').textContent = eur(t.ttc);
      $('#taux').textContent = 'TVA ' + catalogue.taux + ' %';
      $('#envoyer').disabled = !t.lignes.length;
    }
    exiger().then(function (c) {
      client = c; entete('panier');
      var l = c.livraison || { societe: c.enseigne || c.societe, adresse: c.adresse, cp: c.cp, ville: c.ville };
      $('#adr-nom').textContent = l.societe || c.societe;
      $('#adr-ligne').textContent = (l.adresse || '') + ' · ' + (l.cp || '') + ' ' + (l.ville || '');
      return chargerCatalogue();
    }).then(function () {
      dessiner();
      var f = $('#f-commande');
      Array.prototype.forEach.call(f.elements.livraison, function (r) { r.addEventListener('change', function () { $('#autre-adresse').hidden = f.elements.livraison.value !== 'autre'; }); });
      f.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var x = champs(f);
        x.autre_adresse = x.livraison === 'autre';
        x.panier = lirePanier();
        var b = $('#envoyer'); occupe(b, true); alerte($('#msg'), '');
        api('commander', x).then(function (j) {
          ecrirePanier({});
          $('#etape-panier').hidden = true; $('#etape-envoyee').hidden = false;
          $('#num-envoye').textContent = j.numero; $('#ht-envoye').textContent = eur(j.ht) + ' HT · ' + eur(j.ttc) + ' TTC';
          $('#suivre').setAttribute('href', 'historique.html?n=' + encodeURIComponent(j.numero));
          window.scrollTo(0, 0);
        }).catch(function (e) { if (e.message !== 'session') alerte($('#msg'), e.message); occupe(b, false); });
      });
    }).catch(function (e) { if (e.message !== 'session') alerte($('#msg'), e.message); });
  };

  PAGES.historique = function () {
    var cmds = [], ouvert = param('n');
    function dessiner() {
      var q = ($('#cherche').value || '').trim().toLowerCase();
      var z = vider($('#liste'));
      var liste = cmds.filter(function (c) { return !q || (c.numero + ' ' + c.reference + ' ' + c.colis).toLowerCase().indexOf(q) >= 0; });
      if (!liste.length) { z.append(h('p', { class: 'vide', text: cmds.length ? 'Aucune commande ne correspond.' : 'Aucune commande pour le moment.' })); return; }
      liste.forEach(function (c) {
        var etape = { annulee: 0, recue: 1, preparation: 2, expediee: 3 }[c.etat];
        var sous = c.etat === 'expediee' ? 'Le ' + c.expediee + (c.colis ? ' · colis ' + c.colis : '') : c.etat === 'annulee' ? 'Le ' + c.annulee : c.etat === 'preparation' ? 'Depuis le ' + c.preparee : 'Le ' + c.creee + ' à ' + c.creee_heure;
        var estOuvert = ouvert === c.numero;
        var ligne = h('div', { class: 'cmd' + (estOuvert ? ' cmd-surbrillance' : ''), id: 'c-' + c.numero },
          h('div', null, h('span', { class: 'ref', text: c.numero }), h('span', { class: 'sous', text: c.creee + ' · ' + (c.reference || 'sans référence') })),
          h('div', { text: fl(c.flacons) }),
          h('div', { class: 'fort', text: eur(c.ht) + ' HT' }),
          h('div', { class: etape === 0 ? 'etat-ann' : '' },
            h('span', { class: 'etat' }, h('span', { class: 'etat-pts' + (etape === 3 ? ' fini' : '') }, [1, 2, 3].map(function (i) { return h('i', { class: etape >= i ? 'on' : '' }); })), h('span', { class: 'etat-txt', text: c.etat_texte })),
            h('span', { class: 'sous', text: sous })),
          h('div', { class: 'cmd-act' },
            h('button', { class: 'btn btn-line btn-sm', type: 'button', 'aria-expanded': estOuvert ? 'true' : 'false', onclick: function () { ouvert = estOuvert ? '' : c.numero; dessiner(); }, text: estOuvert ? 'Fermer' : 'Détail' }),
            h('button', { class: 'btn btn-line btn-sm', type: 'button', onclick: function () { recommander(c); }, text: 'Recommander' }),
            c.bl ? h('a', { class: 'btn btn-navy btn-sm', href: 'bl.html?n=' + encodeURIComponent(c.numero), text: 'Bon de livraison' }) : null,
            c.facture ? h('a', { class: 'btn btn-o btn-sm', href: 'facture.html?n=' + encodeURIComponent(c.facture), text: 'Facture' }) : null,
            h('a', { class: 'btn btn-line btn-sm', href: 'message.html?n=' + encodeURIComponent(c.numero), text: 'Réclamation' })),
          estOuvert ? h('div', { class: 'detail' },
            h('div', null, h('p', { class: 'eyebrow mono', style: 'margin-bottom:10px', text: '( Articles )' }),
              h('ul', null, c.lignes.map(function (l) { return h('li', null, h('span', { text: l.q + ' × ' + l.label }), h('b', { text: eur(l.total) })); })),
              h('div', { class: 'tot', style: 'margin-top:12px;font-size:14px' }, h('div', null, h('span', { text: 'Total HT' }), h('b', { text: eur(c.ht) })), h('div', { class: 'mute' }, h('span', { text: 'TVA' }), h('span', { text: eur(c.tva) })), h('div', null, h('span', { text: 'Total TTC' }), h('b', { text: eur(c.ttc) })))),
            h('div', null, h('p', { class: 'eyebrow mono', style: 'margin-bottom:10px', text: '( Suivi )' }),
              h('div', { class: 'frise' },
                h('div', { class: 'on' }, h('i'), h('span', null, h('b', { text: 'Reçue' }), ' le ' + c.creee + ' à ' + c.creee_heure)),
                c.etat === 'annulee' ? h('div', { class: 'on' }, h('i'), h('span', null, h('b', { text: 'Annulée' }), ' le ' + c.annulee)) : [
                  h('div', { class: c.preparee ? 'on' : '' }, h('i'), h('span', null, h('b', { text: 'En préparation' }), c.preparee ? ' le ' + c.preparee : '')),
                  h('div', { class: c.expediee ? 'on' : '' }, h('i'), h('span', null, h('b', { text: 'Expédiée' }), c.expediee ? ' le ' + c.expediee + (c.colis ? ' · colis ' + c.colis : '') : ''))]),
              h('p', { class: 'mute', style: 'margin:14px 0 0;font-size:14px' }, 'Livraison : ', [c.livraison.societe, c.livraison.adresse, (c.livraison.cp || '') + ' ' + (c.livraison.ville || '')].filter(Boolean).join(', ')),
              c.note ? h('p', { class: 'mute', style: 'margin:6px 0 0;font-size:14px', text: 'Votre note : ' + c.note }) : null)) : null);
        z.append(ligne);
      });
    }
    function recommander(c) {
      chargerCatalogue().then(function () {
        var p = lirePanier(), manque = 0;
        c.lignes.forEach(function (l) { if (catalogue.index[l.key]) p[l.key] = (p[l.key] || 0) + l.q; else manque++; });
        ecrirePanier(p);
        toast(manque ? 'Ajouté au panier (' + manque + ' article(s) plus disponible(s))' : 'Ajouté au panier');
        setTimeout(function () { location.href = 'panier.html'; }, 700);
      }).catch(function (e) { toast(e.message); });
    }
    exiger().then(function () { entete('historique'); return api('commandes'); }).then(function (j) {
      cmds = j.commandes; dessiner();
      $('#cherche').addEventListener('input', dessiner);
      if (ouvert) { var e = document.getElementById('c-' + ouvert); if (e) e.scrollIntoView({ block: 'center' }); }
    }).catch(function (e) { if (e.message !== 'session') alerte($('#msg'), e.message); });
  };

  PAGES.compte = function () {
    var onglets = { infos: $('#p-infos'), factures: $('#p-factures'), mdp: $('#p-mdp') };
    function montrer() {
      var o = (location.hash || '#infos').slice(1); if (!onglets[o]) o = 'infos';
      Object.keys(onglets).forEach(function (k) { onglets[k].hidden = k !== o; var a = document.querySelector('[data-onglet="' + k + '"]'); if (a) { a.classList.toggle('is-on', k === o); a.setAttribute('aria-current', k === o ? 'page' : 'false'); } });
    }
    window.addEventListener('hashchange', montrer);
    exiger().then(function (c) {
      entete('compte'); montrer();
      var f = $('#f-infos');
      remplir(f, c);
      $('#num-client').textContent = c.numero; $('#email-client').textContent = c.email;
      var autre = f.elements.autre_livraison, bloc = $('#bloc-livraison');
      if (c.livraison) { autre.checked = true; bloc.hidden = false; Object.keys(c.livraison).forEach(function (k) { if (f.elements['l_' + k]) f.elements['l_' + k].value = c.livraison[k]; }); }
      autre.addEventListener('change', function () { bloc.hidden = !autre.checked; });
      f.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var b = f.querySelector('[type=submit]'); occupe(b, true);
        api('compte', champs(f)).then(function () { alerte($('#msg-infos'), 'Informations enregistrées.', 'ok'); }).catch(function (e) { alerte($('#msg-infos'), e.message); }).then(function () { occupe(b, false); });
      });
      var fm = $('#f-mdp');
      fm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var x = champs(fm);
        if (x.nouveau !== x.nouveau2) { alerte($('#msg-mdp'), 'Les deux nouveaux mots de passe ne sont pas identiques.'); return; }
        api('mdp', x).then(function () { fm.reset(); alerte($('#msg-mdp'), 'Mot de passe changé.', 'ok'); }).catch(function (e) { alerte($('#msg-mdp'), e.message); });
      });
      return api('factures');
    }).then(function (j) {
      var z = vider($('#liste-factures'));
      if (!j.factures.length) { z.append(h('p', { class: 'vide', text: 'Aucune facture pour le moment : elles arrivent ici dès l’expédition de vos commandes.' })); return; }
      z.append(h('div', { class: 'tbl-box' }, h('table', { class: 'tbl', style: 'min-width:560px' },
        h('thead', null, h('tr', null, h('th', { text: 'Facture' }), h('th', { text: 'Date' }), h('th', { text: 'Commande' }), h('th', { class: 'num', text: 'Total TTC' }), h('th', null, h('span', { class: 'sr', text: 'Ouvrir' })))),
        h('tbody', null, j.factures.map(function (f) {
          return h('tr', null, h('td', null, h('span', { class: 'ref', text: f.numero }), f.nouvelle ? h('span', { class: 'chip chip-o', style: 'margin-left:8px', text: 'Nouvelle' }) : null),
            h('td', { text: f.emise }), h('td', { text: f.commande }), h('td', { class: 'num fort', text: eur(f.ttc) }),
            h('td', { class: 'num' }, h('a', { class: 'btn btn-line btn-sm', href: 'facture.html?n=' + encodeURIComponent(f.numero), text: 'Ouvrir' })));
        })))));
    }).catch(function (e) { if (e.message !== 'session') alerte($('#msg-infos'), e.message); });
    $('#deconnexion').addEventListener('click', function () {
      api('deconnexion', {}).catch(function () {}).then(function () { try { localStorage.removeItem(PANIER); } catch (e) { /* rien */ } location.href = 'index.html'; });
    });
  };

  PAGES.message = function () {
    exiger().then(function () { entete('message'); return api('commandes'); }).then(function (j) {
      var sel = $('#commande');
      j.commandes.forEach(function (c) { sel.append(h('option', { value: c.numero, text: c.numero + ' · ' + c.creee + ' · ' + c.etat_texte })); });
      if (param('n')) sel.value = param('n');
      var f = $('#f-message');
      f.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var b = f.querySelector('[type=submit]'); occupe(b, true);
        api('message', champs(f)).then(function () { f.reset(); alerte($('#msg'), 'Message envoyé : BH Insight vous répond par e-mail.', 'ok'); })
          .catch(function (e) { alerte($('#msg'), e.message); }).then(function () { occupe(b, false); });
      });
    }).catch(function (e) { if (e.message !== 'session') alerte($('#msg'), e.message); });
  };

  function pageDoc(action) {
    exiger().then(function () { entete(action === 'facture' ? 'compte' : 'historique'); return api(action, undefined, '&n=' + encodeURIComponent(param('n'))); })
      .then(function (j) { vider($('#feuille')).append(window.BH_DOC.rendre(j.doc, 'img/logo-sombre.png')); })
      .catch(function (e) { if (e.message !== 'session') alerte($('#msg'), e.message); });
    $('#imprimer').addEventListener('click', function () { window.print(); });
  }
  PAGES.bl = function () { pageDoc('bl'); };
  PAGES.facture = function () { pageDoc('facture'); };

  if (PAGES[page]) PAGES[page]();
})();
