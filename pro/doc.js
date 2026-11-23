/* BH Insight Pro — bon de livraison et facture (feuille A4), communs à l'espace client et à l'espace BH Insight.
   Le contenu vient du serveur, figé à l'expédition ; tout texte est inséré comme texte (jamais comme HTML). */
'use strict';
window.BH_DOC = (function () {
  function h(tag, a) {
    var e = document.createElement(tag);
    if (a) Object.keys(a).forEach(function (k) {
      var v = a[k];
      if (v === null || v === undefined || v === false) return;
      if (k === 'class') e.className = v;
      else if (k === 'text') e.textContent = v;
      else if (k === 'style') e.style.cssText = v;
      else e.setAttribute(k, v);
    });
    for (var i = 2; i < arguments.length; i++) ajouter(e, arguments[i]);
    return e;
  }
  function ajouter(e, x) {
    if (x === null || x === undefined || x === false) return;
    if (Array.isArray(x)) { x.forEach(function (y) { ajouter(e, y); }); return; }
    e.append(x instanceof Node ? x : String(x));
  }
  function eur(c) {
    var n = (Math.round(c) / 100).toFixed(2).split('.');
    return n[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ',' + n[1] + ' €';
  }
  function lignesAdresse(x) {
    if (!x) return [];
    return [x.societe, x.enseigne && x.enseigne !== x.societe ? x.enseigne : '', x.nom, x.adresse, ((x.cp || '') + ' ' + (x.ville || '')).trim(), x.pays && x.pays !== 'France' ? x.pays : '']
      .filter(Boolean).map(function (t, i) { return i === 0 ? h('b', { text: t }) : t; });
  }
  function avecSauts(liste) { var out = []; liste.forEach(function (t, i) { if (i) out.push(h('br')); out.push(t); }); return out; }

  function rendre(d, logo) {
    var facture = d.type === 'facture';
    var em = d.emetteur || {};
    var cl = d.client || {};
    var titre = facture ? 'Facture' : 'Bon de livraison';
    document.title = (facture ? 'Facture ' + d.numero : 'BL ' + d.commande) + ' - ' + (cl.societe || '') + ' - BH Insight';
    var haut = h('div', { class: 'doc-top' },
      h('div', null, h('img', { src: logo, alt: 'BH Insight', style: 'height:30px;width:auto' }), h('p', { class: 'doc-marque', text: 'BH Insight est une marque de ' + (em.raison || 'RVF') })),
      h('div', { class: 'doc-titre' }, h('h1', { text: titre }), h('p', null, 'N° ', h('b', { text: facture ? d.numero : d.commande })),
        h('p', { text: facture ? 'Émise le ' + d.emise : 'Expédiée le ' + (d.expediee || '') })));
    var siege = [em.adresse, ((em.cp || '') + ' ' + (em.ville || '')).trim(), em.pays && em.pays !== 'France' ? em.pays : ''].filter(Boolean);
    var vendeur = h('div', { class: 'doc-bloc' }, h('h2', { text: facture ? 'Vendeur' : 'Expéditeur' }),
      h('p', null, avecSauts([h('b', { text: em.raison || 'RVF' }),
        facture && em.forme ? em.forme + (em.capital ? ' au capital de ' + em.capital : '') : ''].concat(siege,
        [facture && em.siren ? 'RCS ' + ((em.rcs_ville || '') + ' ' + em.siren).trim() : '', facture && em.tva ? 'TVA intracom. : ' + em.tva : '', em.email]).filter(Boolean))));
    var adrClient = [cl.adresse, cl.cp, cl.ville].join('|').toLowerCase();
    var liv = d.livraison || {};
    var livreAilleurs = facture && liv.adresse && [liv.adresse, liv.cp, liv.ville].join('|').toLowerCase() !== adrClient;
    var client = facture
      ? h('div', { class: 'doc-bloc' }, h('h2', { text: 'Client' }), h('p', null, avecSauts(lignesAdresse(cl).concat(
          [cl.numero ? 'N° client : ' + cl.numero : '', cl.siren ? 'SIREN : ' + cl.siren : (cl.siret ? 'SIRET : ' + cl.siret : ''), cl.tva ? 'TVA intracom. : ' + cl.tva : ''].filter(Boolean)))),
          livreAilleurs ? h('p', { style: 'margin-top:8px;padding-top:8px;border-top:1px solid #E3D8C5' }, avecSauts([h('b', { text: 'Livré à' })].concat(lignesAdresse(liv).slice(0)))) : null)
      : h('div', { class: 'doc-bloc' }, h('h2', { text: 'Livré à' }), h('p', null, avecSauts(lignesAdresse(d.livraison).concat([cl.numero ? 'N° client : ' + cl.numero : ''].filter(Boolean)))));
    var refs = facture
      ? [['Facture', d.numero], ['Émise le', d.emise], ['Échéance', d.echeance || '—'], ['Commande', d.commande + (d.reference ? ' · réf. ' + d.reference : '')]]
      : [['Commande', d.commande], ['Commandée le', d.date_commande], ['Votre référence', d.reference || '—'], ['N° de colis', d.colis || '—']];
    var grille = h('div', { class: 'doc-refs' }, refs.map(function (r) { return h('div', null, h('span', { text: r[0] }), h('b', { text: r[1] })); }));
    var tete = facture ? ['Désignation', 'Qté', 'Prix unitaire HT', 'Total HT'] : ['Désignation', 'Qté', 'Flacons'];
    var corps = (d.lignes || []).map(function (l) {
      var des = h('td', null, h('span', { class: 'doc-pastille ' + (l.ton || '') }), h('b', { text: l.label }), h('br'), h('span', { style: 'color:#56667D', text: l.detail || '' }));
      return facture
        ? h('tr', null, des, h('td', { class: 'num', text: String(l.q) }), h('td', { class: 'num', text: eur(l.pu) }), h('td', { class: 'num', text: eur(l.total) }))
        : h('tr', null, des, h('td', { class: 'num', text: String(l.q) }), h('td', { class: 'num', text: l.nb ? String(l.nb * l.q) : '—' }));
    });
    var table = h('table', { class: 'doc-tbl' }, h('thead', null, h('tr', null, tete.map(function (t, i) { return h('th', { class: i ? 'num' : null, text: t }); }))), h('tbody', null, corps));
    var totaux = facture
      ? h('div', { class: 'doc-total' }, h('table', null,
          h('tr', null, h('td', { text: 'Total HT' }), h('td', { class: 'num', text: eur(d.ht) })),
          h('tr', null, h('td', { text: 'TVA ' + d.taux + ' %' }), h('td', { class: 'num', text: eur(d.tva) })),
          h('tr', { class: 'g' }, h('td', { text: 'Total TTC' }), h('td', { class: 'num', text: eur(d.ttc) }))))
      : h('div', { class: 'doc-total' }, h('table', null, h('tr', { class: 'g' }, h('td', { text: 'Total' }), h('td', { class: 'num', text: d.flacons + (d.flacons > 1 ? ' flacons' : ' flacon') }))));
    var paiement = d.echeance ? (d.delai ? 'Paiement à ' + d.delai + ' jours à compter de la livraison, au plus tard le ' + d.echeance + '.' : 'Paiement à réception, le ' + d.echeance + '.') : '';
    var conditions = facture ? h('div', { style: 'display:flex;flex-direction:column;gap:5px' },
      h('p', { class: 'doc-texte' }, h('b', { text: 'Date de livraison : ' }), (d.expediee || d.emise) + (d.operation ? ' · ' : ''), d.operation ? h('b', { text: 'Opération : ' }) : null, d.operation ? d.operation.toLowerCase() : ''),
      paiement ? h('p', { class: 'doc-texte', text: paiement }) : null,
      h('p', { class: 'doc-texte', text: 'Escompte pour paiement anticipé : ' + (em.escompte || 'néant') + '.' }),
      em.penalites ? h('p', { class: 'doc-texte', text: em.penalites }) : null,
      em.conditions ? h('p', { class: 'doc-texte', text: em.conditions }) : null) : null;
    var pied = h('div', { class: 'doc-pied' }, h('span', { text: (em.mentions || '') + (siege.length ? ' · Siège : ' + siege.join(', ') : '') }),
      h('span', { text: 'BH Insight est une marque de ' + (em.raison || 'RVF') + ' · bhinsight.fr · pro.bhinsight.fr' }));
    return h('article', { class: 'doc' }, haut, h('div', { class: 'doc-bande' }), h('div', { class: 'doc-blocs' }, vendeur, client), grille, table, totaux, conditions, pied);
  }
  return { rendre: rendre, eur: eur };
})();
