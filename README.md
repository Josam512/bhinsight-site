# Essayage virtuel de lentilles de couleur — copie de référence

Version autonome de l'outil d'essayage en direct, figée le 22 novembre 2026 (version 5).
Elle est indépendante de tout site : un seul fichier HTML, sans serveur ni dépendance en ligne.

- **Fichier prêt à l'emploi** : `dist/essayage-lentilles.html` (8,7 Mo, tout est embarqué).
- **En ligne** : https://josam512.github.io/bhinsight-site/essayage-lentilles.html

## Ce que fait l'outil

- Ouvre la caméra (ou une photo importée), suit le visage et pose une lentille de couleur sur chaque iris, en direct.
- Trois gammes : Air Optix Colors (Alcon, 12 teintes), FreshLook ColorBlends (Alcon, 12 teintes), SofLens Natural Colors (Bausch + Lomb, 10 teintes).
- Fonctionne sur téléphone Android, iPhone et ordinateur. La caméra exige une adresse en HTTPS (ou l'ouverture du fichier en local sur ordinateur).
- Aucune image ne quitte l'appareil : tout le calcul se fait dans le navigateur.

## Reconstruire le fichier

```
python3 build.py
```

Python 3 suffit, sans module à installer. Le résultat est écrit dans `dist/essayage-lentilles.html`.

## Organisation

| Chemin | Rôle |
|---|---|
| `src/template.html` | Toute la page : style, interface, nuanciers, rendu des lentilles, gestion caméra |
| `src/reference-autotest.jpg` | Image de test du moteur (photo publique NASA), jamais affichée |
| `vendor/mediapipe-tasks-vision-0.10.21/` | Moteur MediaPipe (JavaScript + WebAssembly), licence Apache 2.0 |
| `vendor/face_landmarker.task` | Modèle de suivi du visage et des iris (Google MediaPipe) |
| `build.py` | Assemble le tout en un seul fichier |

## Points à connaître avant de modifier

- **Nuanciers** : tableau `BRANDS` dans `src/template.html`. Chaque teinte a trois couleurs : `base`, `inner` (près de la pupille), `limbal` (anneau externe). Les valeurs sont approchées d'après les nuanciers publics, les fabricants ne publient pas de valeurs colorimétriques.
- **Moteur** : calcul sur processeur (CPU) par défaut. Le calcul sur puce graphique (GPU) ne détectait aucun visage sur un téléphone Android testé. Un autotest au démarrage et une rotation automatique des stratégies couvrent les autres cas.
- **Rendu** : l'image caméra et les lentilles sont peintes dans le même canvas à partir de la même image, pour éviter tout décalage.
- **Chargeur MediaPipe** : il efface sa fabrique après usage. Elle est sauvegardée dans `window.__MPFactory` pour pouvoir créer un second moteur.
- **Attribut `hidden`** : la feuille de style contient `[hidden]{display:none!important}`. Sans elle, les panneaux masqués restent affichés.
