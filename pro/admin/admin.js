/* BH Insight Pro — espace BH Insight (pro.bhinsight.fr/admin) : comportements de toutes les pages.
   Chaque page porte data-page sur <body>. Tout passe par api.php. Les textes venant du serveur sont insérés comme texte. */
'use strict';
(function () {
  var page = document.body.getAttribute('data-page');

  /* ---------------------------------------------------------------- outils */
  function h(tag, a) {
    var e = document.createElement(tag);
    if (a) Object.keys(a).forEach(function (k) {
      var v = a[k];
      if (v === null || v === undefined || v === false) return;
      if (k === 'class') e.className = v;
      else if (k === 'text') e.textContent = v;
      else if (k === 'svg') e.innerHTML = v;
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
  /* insère des éléments, des listes ou rien (null) — contrairement à append, qui écrirait « null » */
  function poser(e) { for (var i = 1; i < arguments.length; i++) ajouter(e, arguments[i]); return e; }
  function vider(e) { while (e.firstChild) e.removeChild(e.firstChild); return e; }
  function eur(c) { return window.BH_DOC.eur(c); }
  function param(n) { return new URLSearchParams(location.search).get(n) || ''; }
  function api(action, donnees, extra) {
    var o = { credentials: 'same-origin', headers: { 'X-BH': '1' } };
    if (donnees !== undefined) { o.method = 'POST'; o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(donnees); }
    return fetch('api.php?a=' + encodeURIComponent(action) + (extra || ''), o).then(function (r) {
      return r.json().catch(function () { return { ok: false, erreur: 'Réponse inattendue du serveur.' }; }).then(function (j) {
        if (r.status === 401 && page !== 'connexion') { location.href = 'index.html?retour=' + encodeURIComponent(location.pathname.split('/').pop() + location.search); throw new Error('session'); }
        if (!j.ok) throw new Error(j.erreur || 'Erreur.');
        return j;
      });
    }, function () { throw new Error('Connexion au serveur impossible.'); });
  }
  function alerte(zone, msg, type) { vider(zone); if (msg) zone.append(h('p', { class: 'alerte' + (type ? ' ' + type : ''), role: type === 'ok' ? 'status' : 'alert', text: msg })); }
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
    Object.keys(x || {}).forEach(function (k) { var el = form.elements[k]; if (el && el.type !== 'checkbox' && x[k] !== null && typeof x[k] !== 'object') el.value = x[k]; });
  }
  function occupe(b, oui) { if (b) { b.disabled = oui; b.setAttribute('aria-busy', oui ? 'true' : 'false'); } }
  function centimes(t) { var n = parseFloat(String(t).replace(/\s/g, '').replace('€', '').replace(',', '.')); return isNaN(n) ? null : Math.round(n * 100); }
  function prixTexte(c) { return c === null || c === undefined ? '' : (c / 100).toFixed(2).replace('.', ','); }
  var CHIP = { recue: 'chip-o', preparation: 'chip-b', expediee: 'chip-v', annulee: 'chip-r' };
  var CLIENT_ETATS = { attente: ['En attente', 'chip-o'], actif: ['Ouvert', 'chip-v'], suspendu: ['Suspendu', 'chip-r'], refuse: ['Refusé', 'chip-r'] };

  /* ---------------------------------------------------------------- en-tête */
  function entete(actif, badges) {
    var z = $('#tete'); if (!z) return;
    var liens = [['commandes', 'Commandes', 'commandes.html'], ['clients', 'Clients', 'clients.html'], ['catalogue', 'Catalogue et prix', 'catalogue.html'],
      ['factures', 'Factures', 'factures.html'], ['messages', 'Messages', 'messages.html'], ['reglages', 'Réglages', 'reglages.html']];
    vider(z).append(h('nav', { class: 'nav nav-gestion', 'aria-label': 'Espace BH Insight' },
      h('a', { class: 'nav-brand', href: 'commandes.html', 'aria-label': 'Espace BH Insight, commandes' }, h('img', { src: '../img/logo-clair.png', alt: 'BH Insight' }), h('span', { class: 'nav-tag mono', text: 'Gestion' })),
      h('div', { class: 'nav-links' }, liens.map(function (l) {
        var n = badges ? badges[l[0]] : 0;
        return h('a', { class: 'nav-link' + (l[0] === actif ? ' is-on' : ''), href: l[2], 'aria-current': l[0] === actif ? 'page' : null }, l[1], n ? h('span', { class: 'badge-n', text: String(n) }) : null);
      }), h('button', { class: 'nav-sortir', type: 'button', text: 'Sortir', onclick: function () { api('deconnexion', {}).catch(function () {}).then(function () { location.href = 'index.html'; }); } }))));
  }
  function exiger(actif) {
    return api('etat').then(function (j) {
      if (!j.connecte) { location.href = 'index.html?retour=' + encodeURIComponent(location.pathname.split('/').pop() + location.search); throw new Error('session'); }
      document.body.classList.remove('verif');
      entete(actif, j.badges);
      return j;
    });
  }
  function echec(zone) { return function (e) { if (e.message !== 'session') alerte(zone || $('#msg'), e.message); }; }

  var PAGES = {};

  /* ---------------------------------------------------------------- connexion, installation, mot de passe oublié */
  PAGES.connexion = function () {
    var zone = $('#msg'), retour = param('retour');
    var cible = /^[a-z-]+\.html(\?[\w=&%.-]*)?$/.test(retour) ? retour : 'commandes.html';
    var blocs = ['#f-installer', '#f-connexion', '#f-secours', '#f-reinit'];
    function montrer(s) { blocs.forEach(function (b) { $(b).hidden = b !== s; }); }
    var m = /reinit=([a-f0-9]{48})/.exec(location.hash);
    api('etat').then(function (j) {
      if (j.connecte && !m) { location.replace(cible); return; }
      montrer(m ? '#f-reinit' : j.installe ? '#f-connexion' : '#f-installer');
    }).catch(function (e) { alerte(zone, e.message); });
    function envoyer(form, action, verif, ok) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var x = champs(form);
        if (verif && x.mdp !== x.mdp2) { alerte(zone, 'Les deux mots de passe ne sont pas identiques.'); return; }
        var b = form.querySelector('[type=submit]'); occupe(b, true); alerte(zone, '');
        api(action, ok ? ok(x) : x).then(function () { location.href = cible; }).catch(function (e) { alerte(zone, e.message); occupe(b, false); });
      });
    }
    envoyer($('#f-installer'), 'installer', true);
    envoyer($('#f-connexion'), 'connexion', false);
    envoyer($('#f-secours'), 'reinit', true);
    envoyer($('#f-reinit'), 'reinit', true, function (x) { return { jeton: m ? m[1] : '', mdp: x.mdp }; });
    $('#oubli').addEventListener('click', function () {
      api('oubli', {}).then(function (j) { alerte(zone, 'Un lien valable une heure vient de partir à ' + j.adresse + '.', 'ok'); }).catch(function (e) { alerte(zone, e.message); });
    });
    $('#voir-secours').addEventListener('click', function () { montrer('#f-secours'); });
    Array.prototype.forEach.call(document.querySelectorAll('[data-retour-connexion]'), function (b) { b.addEventListener('click', function () { montrer('#f-connexion'); }); });
  };

  /* ---------------------------------------------------------------- commandes */
  PAGES.commandes = function () {
    var etat = param('etat') || 'recue', q = '';
    var ONGLETS = [['recue', 'À traiter'], ['preparation', 'En préparation'], ['expediee', 'Expédiées'], ['annulee', 'Annulées'], ['', 'Toutes']];
    function charger() {
      api('commandes', undefined, '&etat=' + encodeURIComponent(etat) + '&q=' + encodeURIComponent(q)).then(function (j) {
        var t = vider($('#onglets'));
        ONGLETS.forEach(function (o) {
          var n = o[0] ? (j.compte[o[0]] || 0) : Object.keys(j.compte).reduce(function (s, k) { return s + j.compte[k]; }, 0);
          t.append(h('button', { class: 'tab' + (etat === o[0] ? ' is-on' : ''), type: 'button', 'aria-pressed': etat === o[0] ? 'true' : 'false', onclick: function () { etat = o[0]; charger(); } }, o[1], n ? h('span', { class: 'badge-n', text: String(n) }) : null));
        });
        var z = vider($('#liste'));
        if (!j.commandes.length) { z.append(h('p', { class: 'vide', text: 'Aucune commande ici.' })); return; }
        z.append(h('div', { class: 'tbl-box' }, h('table', { class: 'tbl' },
          h('thead', null, h('tr', null, ['Commande', 'Client', 'Articles', 'Total HT', 'État'].map(function (x, i) { return h('th', { class: i === 3 ? 'num' : null, text: x }); }))),
          h('tbody', null, j.commandes.map(function (c) {
            return h('tr', null,
              h('td', null, h('a', { class: 'ref lien', href: 'commande.html?n=' + encodeURIComponent(c.numero), text: c.numero }), h('span', { class: 'sous', text: c.creee + ' à ' + c.creee_heure + (c.reference ? ' · ' + c.reference : '') })),
              h('td', null, h('span', { class: 'fort', text: c.client.societe }), h('span', { class: 'sous', text: 'n° ' + c.client.numero + ' · ' + c.client.ville })),
              h('td', null, c.lignes.length + ' ligne' + (c.lignes.length > 1 ? 's' : '') + ' · ' + fl(c.flacons)),
              h('td', { class: 'num fort', text: eur(c.ht) }),
              h('td', null, h('span', { class: 'chip ' + CHIP[c.etat], text: c.etat_texte }), c.facture ? h('span', { class: 'sous', text: 'Facture ' + c.facture }) : null));
          })))));
      }).catch(echec());
    }
    exiger('commandes').then(function () {
      var f = $('#f-cherche');
      f.addEventListener('submit', function (ev) { ev.preventDefault(); q = f.elements.q.value.trim(); if (q) etat = ''; charger(); });
      charger();
    }).catch(echec());
  };

  PAGES.commande = function () {
    var n = param('n');
    function dessiner(j) {
      var c = j.commande, k = j.client;
      $('#titre').textContent = c.numero;
      $('#sous-titre').textContent = 'Reçue le ' + c.creee + ' à ' + c.creee_heure + (c.reference ? ' · réf. client : ' + c.reference : '');
      var ch = $('#etat'); ch.className = 'chip ' + CHIP[c.etat]; ch.textContent = c.etat_texte;
      poser(vider($('#lignes')), c.lignes.map(function (l) {
        return h('tr', { class: l.ton }, h('td', null, h('span', { class: 'pastille', style: 'margin-right:8px' }), h('span', { class: 'fort', text: l.label }), h('span', { class: 'sous', text: l.detail })),
          h('td', { class: 'num', text: eur(l.pu) }), h('td', { class: 'num', text: String(l.q) }), h('td', { class: 'num', text: l.nb ? String(l.nb * l.q) : '—' }), h('td', { class: 'num fort', text: eur(l.total) }));
      }));
      $('#t-ht').textContent = eur(c.ht); $('#t-tva').textContent = eur(c.tva); $('#t-ttc').textContent = eur(c.ttc); $('#t-flacons').textContent = fl(c.flacons);
      poser(vider($('#client')), h('a', { class: 'fort lien', href: 'client.html?id=' + k.id, text: k.societe }), h('br'),
        'N° client ' + k.numero, h('br'), (k.prenom + ' ' + k.nom).trim(), h('br'), h('a', { class: 'lien', href: 'mailto:' + k.email, text: k.email }), k.tel ? [h('br'), k.tel] : null);
      var l = c.livraison;
      poser(vider($('#livraison')), [l.societe, l.nom, l.adresse, (l.cp || '') + ' ' + (l.ville || ''), l.pays && l.pays !== 'France' ? l.pays : ''].filter(Boolean).map(function (t, i) { return i ? [h('br'), t] : h('b', { text: t }); }));
      $('#note-client').hidden = !c.note; $('#note-client-txt').textContent = c.note;
      var f = $('#f-suivi');
      f.elements.colis.value = c.colis; f.elements.note_interne.value = c.note_interne;
      $('#bloc-date').hidden = c.etat === 'expediee' || c.etat === 'annulee';
      var act = vider($('#actions'));
      function bouton(txt, cls, action, confirmer) {
        return h('button', { class: 'btn ' + cls, type: 'button', text: txt, onclick: function (ev) {
          if (confirmer && ev.currentTarget.getAttribute('data-sur') !== '1') { ev.currentTarget.setAttribute('data-sur', '1'); ev.currentTarget.textContent = confirmer; return; }
          maj(action, ev.currentTarget);
        } });
      }
      if (c.etat === 'recue') act.append(bouton('Passer en préparation', 'btn-navy', 'preparer'));
      if (c.etat === 'recue' || c.etat === 'preparation') act.append(bouton('Expédier · BL et facture', 'btn-o', 'expedier', 'Confirmer l’expédition ?'));
      if (c.etat === 'recue' || c.etat === 'preparation') act.append(bouton('Annuler la commande', 'btn-line', 'annuler', 'Vraiment annuler ?'));
      if (c.etat === 'annulee' || c.etat === 'preparation') act.append(bouton(c.etat === 'annulee' ? 'Rétablir' : 'Remettre en « reçue »', 'btn-line', 'retablir'));
      poser(vider($('#docs')), c.bl ? h('a', { class: 'btn btn-navy btn-sm', href: 'bl.html?n=' + encodeURIComponent(c.numero), text: 'Bon de livraison' }) : null,
        c.facture ? h('a', { class: 'btn btn-o btn-sm', href: 'facture.html?n=' + encodeURIComponent(c.facture), text: 'Facture ' + c.facture }) : null,
        !c.bl && !c.facture ? h('p', { class: 'mute', style: 'margin:0;font-size:14px', text: 'Le bon de livraison et la facture sont édités à l’expédition.' }) : null);
      var mz = vider($('#reclamations'));
      $('#bloc-reclamations').hidden = !j.messages.length;
      j.messages.forEach(function (m) { mz.append(h('div', { class: 'card pad', style: 'background:#FFF8EF' }, h('p', { class: 'sous', style: 'margin:0 0 6px', text: m.cree + (m.traite ? ' · traité' : ' · à traiter') }), h('p', { style: 'margin:0;white-space:pre-line', text: m.texte }))); });
    }
    function maj(action, btn) {
      var f = $('#f-suivi'), x = champs(f);
      x.numero = n; x.action = action;
      occupe(btn, true); alerte($('#msg'), '');
      api('commande_maj', x).then(function () { return api('commande', undefined, '&n=' + encodeURIComponent(n)); }).then(function (j) {
        dessiner(j);
        alerte($('#msg'), { preparer: 'Commande en préparation.', expedier: 'Commande expédiée : bon de livraison et facture édités.', annuler: 'Commande annulée.', retablir: 'Commande rétablie.', enregistrer: 'Enregistré.' }[action] || 'Enregistré.', 'ok');
      }).catch(echec()).then(function () { occupe(btn, false); });
    }
    exiger('commandes').then(function () { return api('commande', undefined, '&n=' + encodeURIComponent(n)); }).then(function (j) {
      dessiner(j);
      $('#f-suivi').addEventListener('submit', function (ev) { ev.preventDefault(); maj('enregistrer', ev.submitter || $('#f-suivi [type=submit]')); });
    }).catch(echec());
  };

  /* ---------------------------------------------------------------- clients */
  PAGES.clients = function () {
    exiger('clients').then(function () { return api('clients'); }).then(function (j) {
      var z = vider($('#liste'));
      var attente = j.clients.filter(function (c) { return c.etat === 'attente'; });
      $('#bloc-attente').hidden = !attente.length;
      poser(vider($('#attente')), attente.map(function (c) {
        return h('a', { class: 'card pad', href: 'client.html?id=' + c.id, style: 'display:flex;flex-direction:column;gap:6px' }, h('span', { class: 'chip chip-o', style: 'align-self:flex-start', text: 'À valider' }),
          h('b', { text: c.societe }), h('span', { class: 'sous', text: c.cp + ' ' + c.ville + ' · demande du ' + c.cree }), h('span', { class: 'sous', text: (c.prenom + ' ' + c.nom).trim() + ' · ' + c.email }));
      }));
      var autres = j.clients.filter(function (c) { return c.etat !== 'attente'; });
      if (!autres.length) { z.append(h('p', { class: 'vide', text: 'Aucun client ouvert pour le moment.' })); return; }
      z.append(h('div', { class: 'tbl-box' }, h('table', { class: 'tbl' },
        h('thead', null, h('tr', null, ['N°', 'Client', 'Ville', 'Contact', 'Commandes', 'État'].map(function (x) { return h('th', { text: x }); }))),
        h('tbody', null, autres.map(function (c) {
          var e = CLIENT_ETATS[c.etat] || [c.etat, 'chip-n'];
          return h('tr', null, h('td', { class: 'fort', text: c.numero || '—' }), h('td', null, h('a', { class: 'lien fort', href: 'client.html?id=' + c.id, text: c.societe }), c.enseigne ? h('span', { class: 'sous', text: c.enseigne }) : null),
            h('td', { text: c.cp + ' ' + c.ville }), h('td', null, (c.prenom + ' ' + c.nom).trim(), h('span', { class: 'sous', text: c.email })),
            h('td', null, String(c.commandes), h('span', { class: 'sous', text: eur(c.ht) + ' HT' })), h('td', null, h('span', { class: 'chip ' + e[1], text: e[0] })));
        })))));
    }).catch(echec());
  };

  PAGES.client = function () {
    var id = param('id');
    var f = $('#f-client');
    function dessiner(j) {
      var c = j.client;
      $('#titre').textContent = c.societe || 'Nouveau client';
      $('#sous-titre').textContent = c.id ? ('Inscrit le ' + c.cree + (c.valide ? ' · ouvert le ' + c.valide : '') + ' · ' + c.commandes + ' commande(s) · ' + eur(c.ht) + ' HT') : 'Compte créé par BH Insight';
      remplir(f, c);
      f.elements.etat.value = c.etat || 'actif';
      $('#livraison').textContent = c.livraison ? 'Livraison : ' + [c.livraison.societe, c.livraison.adresse, c.livraison.cp + ' ' + c.livraison.ville].filter(Boolean).join(', ') : '';
      $('#bloc-commandes').hidden = !c.id;
      if (j.commandes) {
        var z = vider($('#commandes'));
        if (!j.commandes.length) z.append(h('p', { class: 'vide', text: 'Aucune commande.' }));
        j.commandes.forEach(function (x) {
          z.append(h('a', { class: 'ligne card pad', href: 'commande.html?n=' + encodeURIComponent(x.numero), style: 'margin-bottom:8px' },
            h('span', null, h('b', { text: x.numero }), ' · ' + x.creee + ' · ', h('span', { class: 'chip ' + CHIP[x.etat], text: x.etat_texte })), h('b', { text: eur(x.ht) + ' HT' })));
        });
      }
    }
    exiger('clients').then(function () {
      if (!id) { dessiner({ client: { etat: 'actif', pays: 'France' } }); $('#mdp-aide').textContent = 'Mot de passe du compte (8 caractères ou plus), à communiquer au client.'; return null; }
      return api('client', undefined, '&id=' + encodeURIComponent(id)).then(dessiner);
    }).then(function () {
      f.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var x = champs(f); x.id = id ? parseInt(id, 10) : 0;
        var b = f.querySelector('[type=submit]'); occupe(b, true);
        api('client_maj', x).then(function (j) {
          if (!id) { location.replace('client.html?id=' + j.client.id); return; }
          f.elements.mdp.value = '';
          return api('client', undefined, '&id=' + encodeURIComponent(id)).then(function (k) { dessiner(k); alerte($('#msg'), 'Enregistré.' + (x.etat === 'actif' ? ' Le client est prévenu par e-mail à l’ouverture de son compte.' : ''), 'ok'); });
        }).catch(echec()).then(function () { occupe(b, false); });
      });
      Array.prototype.forEach.call(document.querySelectorAll('[data-etat]'), function (b) {
        b.addEventListener('click', function () { f.elements.etat.value = b.getAttribute('data-etat'); f.requestSubmit ? f.requestSubmit() : f.querySelector('[type=submit]').click(); });
      });
    }).catch(echec());
  };

  /* ---------------------------------------------------------------- catalogue et prix */
  PAGES.catalogue = function () {
    var cat = null, flacons = [];
    function lotBloc(l) {
      var d = h('div', { class: 'card pad lot', style: 'display:flex;flex-direction:column;gap:14px' },
        h('div', { class: 'grid2' }, h('div', { class: 'field' }, h('label', { text: 'Nom du lot' }), h('input', { class: 'input', 'data-k': 'nom', value: l.nom || '', maxlength: '60' })),
          h('label', { class: 'coche', style: 'align-self:end' }, h('input', { type: 'checkbox', 'data-k': 'actif', checked: l.actif !== false ? true : null }), 'Proposé aux clients')),
        h('div', { class: 'lot-grille' }, flacons.map(function (f) {
          return h('div', { class: 'field ' + f.ton }, h('label', null, h('span', { class: 'pastille', style: 'margin-right:6px' }), f.nom), h('input', { class: 'mini', type: 'number', min: '0', max: '500', inputmode: 'numeric', 'data-f': f.id, value: String((l.compo || {})[f.id] || 0) }));
        })),
        h('div', { style: 'display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap' }, h('span', { class: 'mute lot-total' }),
          h('button', { class: 'btn btn-line btn-sm', type: 'button', text: 'Retirer ce lot', onclick: function () { d.remove(); } })));
      d.setAttribute('data-id', l.id || '');
      function total() {
        var n = 0, c = 0;
        d.querySelectorAll('[data-f]').forEach(function (i) { var q = parseInt(i.value, 10) || 0; var f = flacons.filter(function (x) { return x.id === i.getAttribute('data-f'); })[0]; n += q; c += q * (centimes($('#p-' + f.g).value) || 0); });
        d.querySelector('.lot-total').textContent = n + ' flacons · ' + eur(c) + ' HT';
      }
      d.addEventListener('input', total); setTimeout(total);
      return d;
    }
    function plvBloc(p) {
      var d = h('div', { class: 'card pad plv-l grid2' },
        h('div', { class: 'field' }, h('label', { text: 'Nom' }), h('input', { class: 'input', 'data-k': 'nom', value: p.nom || '', maxlength: '60' })),
        h('div', { class: 'field' }, h('label', { text: 'Prix HT (€) · vide = pas encore proposé' }), h('input', { class: 'input', 'data-k': 'prix', inputmode: 'decimal', value: prixTexte(p.prix), placeholder: 'Prix à définir' })),
        h('div', { class: 'field', style: 'grid-column:1/-1' }, h('label', { text: 'Description' }), h('input', { class: 'input', 'data-k': 'desc', value: p.desc || '', maxlength: '120' })),
        h('div', null, h('button', { class: 'btn btn-line btn-sm', type: 'button', text: 'Retirer', onclick: function () { d.remove(); } })));
      d.setAttribute('data-id', p.id || '');
      return d;
    }
    exiger('catalogue').then(function () { return api('catalogue'); }).then(function (j) {
      cat = j.catalogue; flacons = j.flacons;
      $('#p-opt').value = prixTexte(cat.prix.opt); $('#p-ab').value = prixTexte(cat.prix.ab); $('#carton').value = String(cat.carton);
      var lz = $('#lots'); cat.lots.forEach(function (l) { lz.append(lotBloc(l)); });
      var pz = $('#plvs'); cat.plv.forEach(function (p) { pz.append(plvBloc(p)); });
      $('#ajout-lot').addEventListener('click', function () { lz.append(lotBloc({ nom: '', compo: {}, actif: true })); });
      $('#ajout-plv').addEventListener('click', function () { pz.append(plvBloc({ nom: '', desc: '', prix: null })); });
      ['#p-opt', '#p-ab'].forEach(function (s) { $(s).addEventListener('input', function () { lz.dispatchEvent(new Event('input')); lz.querySelectorAll('.lot').forEach(function (d) { d.dispatchEvent(new Event('input')); }); }); });
      $('#f-catalogue').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var x = { prix: { opt: centimes($('#p-opt').value), ab: centimes($('#p-ab').value) }, carton: parseInt($('#carton').value, 10), lots: [], plv: [] };
        lz.querySelectorAll('.lot').forEach(function (d) {
          var compo = {}; d.querySelectorAll('[data-f]').forEach(function (i) { compo[i.getAttribute('data-f')] = parseInt(i.value, 10) || 0; });
          x.lots.push({ id: d.getAttribute('data-id'), nom: d.querySelector('[data-k=nom]').value, actif: d.querySelector('[data-k=actif]').checked, compo: compo });
        });
        pz.querySelectorAll('.plv-l').forEach(function (d) {
          var p = d.querySelector('[data-k=prix]').value.trim();
          x.plv.push({ id: d.getAttribute('data-id'), nom: d.querySelector('[data-k=nom]').value, desc: d.querySelector('[data-k=desc]').value, prix: p === '' ? null : centimes(p) });
        });
        var b = ev.submitter || $('#f-catalogue [type=submit]'); occupe(b, true);
        api('catalogue', x).then(function () { alerte($('#msg'), 'Catalogue enregistré : les clients voient les nouveaux prix dès maintenant. Les commandes déjà passées gardent leurs prix.', 'ok'); window.scrollTo(0, 0); })
          .catch(echec()).then(function () { occupe(b, false); });
      });
    }).catch(echec());
  };

  /* ---------------------------------------------------------------- factures, messages, réglages */
  PAGES.factures = function () {
    exiger('factures').then(function () { return api('factures'); }).then(function (j) {
      var z = vider($('#liste'));
      if (!j.factures.length) { z.append(h('p', { class: 'vide', text: 'Aucune facture pour le moment : elles sont émises à l’expédition des commandes.' })); return; }
      var total = j.factures.reduce(function (s, f) { return s + f.ttc; }, 0);
      $('#total').textContent = j.factures.length + ' facture(s) · ' + eur(total) + ' TTC';
      z.append(h('div', { class: 'tbl-box' }, h('table', { class: 'tbl' },
        h('thead', null, h('tr', null, ['Facture', 'Date', 'Client', 'Commande', 'Total TTC', ''].map(function (x, i) { return h('th', { class: i === 4 ? 'num' : null, text: x }); }))),
        h('tbody', null, j.factures.map(function (f) {
          return h('tr', null, h('td', { class: 'ref', text: f.numero }), h('td', { text: f.emise }), h('td', { text: f.societe }),
            h('td', null, h('a', { class: 'lien', href: 'commande.html?n=' + encodeURIComponent(f.commande), text: f.commande })), h('td', { class: 'num fort', text: eur(f.ttc) }),
            h('td', { class: 'num' }, h('a', { class: 'btn btn-line btn-sm', href: 'facture.html?n=' + encodeURIComponent(f.numero), text: 'Ouvrir' })));
        })))));
    }).catch(echec());
  };

  PAGES.messages = function () {
    function charger() {
      api('messages').then(function (j) {
        var z = vider($('#liste'));
        if (!j.messages.length) { z.append(h('p', { class: 'vide', text: 'Aucun message.' })); return; }
        j.messages.forEach(function (m) {
          z.append(h('article', { class: 'card pad', style: 'display:flex;flex-direction:column;gap:10px;margin-bottom:12px' + (m.traite ? ';opacity:.65' : '') },
            h('div', { style: 'display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px' },
              h('span', null, h('a', { class: 'fort lien', href: 'client.html?id=' + m.client_id, text: m.societe }), ' · ' + m.cree, m.commande ? [' · ', h('a', { class: 'lien', href: 'commande.html?n=' + encodeURIComponent(m.commande), text: 'commande ' + m.commande })] : null),
              h('span', { class: 'chip ' + (m.traite ? 'chip-v' : 'chip-o'), text: m.traite ? 'Traité' : (m.commande ? 'Réclamation' : 'À traiter') })),
            h('p', { style: 'margin:0;white-space:pre-line', text: m.texte }),
            h('div', { style: 'display:flex;flex-wrap:wrap;gap:8px' },
              h('a', { class: 'btn btn-navy btn-sm', href: 'mailto:' + m.email + '?subject=' + encodeURIComponent('BH Insight' + (m.commande ? ' · commande ' + m.commande : '')), text: 'Répondre' }),
              h('button', { class: 'btn btn-line btn-sm', type: 'button', text: m.traite ? 'Remettre à traiter' : 'Marquer traité', onclick: function () { api('message_traite', { id: m.id, traite: !m.traite }).then(charger).catch(echec()); } }))));
        });
      }).catch(echec());
    }
    exiger('messages').then(charger).catch(echec());
  };

  PAGES.reglages = function () {
    var f = $('#f-reglages');
    exiger('reglages').then(function () { return api('reglages'); }).then(function (j) {
      f.elements.email_bh.value = j.email_bh;
      Object.keys(j.emetteur).forEach(function (k) { var el = f.elements['e_' + k]; if (el) el.value = j.emetteur[k]; });
      f.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var x = champs(f), em = {};
        Object.keys(x).forEach(function (k) { if (k.slice(0, 2) === 'e_') em[k.slice(2)] = x[k]; });
        var b = f.querySelector('[type=submit]'); occupe(b, true);
        api('reglages', { email_bh: x.email_bh, emetteur: em }).then(function () { alerte($('#msg'), 'Réglages enregistrés. Ils s’appliquent aux prochaines factures ; celles déjà émises ne changent pas.', 'ok'); })
          .catch(echec()).then(function () { occupe(b, false); });
      });
      var fm = $('#f-mdp');
      fm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var x = champs(fm);
        if (x.nouveau !== x.nouveau2) { alerte($('#msg-mdp'), 'Les deux nouveaux mots de passe ne sont pas identiques.'); return; }
        api('mdp', x).then(function () { fm.reset(); alerte($('#msg-mdp'), 'Mot de passe changé. Vos autres sessions sont fermées.', 'ok'); }).catch(echec($('#msg-mdp')));
      });
    }).catch(echec());
  };

  function pageDoc(action) {
    exiger('commandes').then(function () { return api(action, undefined, '&n=' + encodeURIComponent(param('n'))); })
      .then(function (j) { vider($('#feuille')).append(window.BH_DOC.rendre(j.doc, '../img/logo-sombre.png')); $('#retour').setAttribute('href', 'commande.html?n=' + encodeURIComponent(j.doc.commande)); })
      .catch(echec());
    $('#imprimer').addEventListener('click', function () { window.print(); });
  }
  PAGES.bl = function () { pageDoc('bl'); };
  PAGES.facture = function () { pageDoc('facture'); };

  if (PAGES[page]) PAGES[page]();
})();
