# bhinsight-site

Version compilée (prête à servir) du site **bhinsight.fr**.

- Code source : dépôt `Josam512/lens-cleanse-design` (React + Vite).
- Ce dépôt est relié au dossier `bhinsight` de l'hébergement web OVH ; chaque mise à jour de la branche `main` peut y être déployée depuis l'espace client OVH (Multisite > Déployer Git).

Pour mettre à jour : dans le dépôt source, `npm run build`, puis remplacer le contenu de ce dépôt par celui du dossier `dist/` (en gardant `.htaccess` et ce README).
