
# ianseo-ui-simplifier

> 🇫🇷 Module personnalisable pour ianseo afin de simplifier son interface en masquant certains menus et en améliorant l’expérience utilisateur.  
>   
> 🇬🇧 Customizable module for ianseo to simplify its user interface by hiding some menus and improving usability.

---

## ✨ Fonctionnalités

- Masque des menus ou sections pour les utilisateurs.
- S’intègre dans le répertoire `Modules/Custom` pour rester intact lors des mises à jour de ianseo.
- Compatible avec d’autres modules personnalisés.
- Mise à jour du module et des pré-configurations recommandées via l'interface utilisateur.
- Les pré-configurations peuvent être adaptées.
- Compatibilité multilingue.

---

## 📥 Installation

1. Téléchargez ou clonez ce dépôt. (Bouton vert en haut à droite de la page `<> Code` puis `Download ZIP`)
2. Copiez le dossier `ianseo-ui-simplifier` **et** le fichier `menu.php` fournis dans ce dépôt dans le dossier :  

   - sous Windows/Mac : `htdocs/Modules/Custom/`
   - sous Linux : `ianseo/Modules/Custom/`
      *sous Linux, executez la commande :
     ```bash
     sudo chmod -R 777 /opt/ianseo/Modules/Custom/ianseo-ui-simplifier/
     ```
     Cela permettra les mises à jours via le module lui-même.

### 🗂️ Arborescence attendue :

```
htdocs/
├── index.php
├── Common/
│   ├── Menu.php
│   └── Templates/
│       └── head.php
└── Modules/
    └── Custom/
        ├── menu.php               ← inclut le module ianseo-ui-simplifier
        └── ianseo-ui-simplifier/
            ├── menu.php           ← logique d’injection & de chargement
            └── App/
                ├── settings.php         ← page de config / UI
                ├── download_csv.php     ← export CSV (menus.csv / elements.csv)
                ├── ultra-basique.css.php
                ├── ultra-basique.js.php
                ├── menus.csv
                ├── elements.csv
                └── Languages/           ← traductions par langue
                    ├── en.php
                    ├── fr.php
                    ├── it.php
                    ├── es.php
                    ├── de.php
                    └── … (autres codes ISO)
```

---

## 🧩 Compatibilité avec d’autres modules

Si un autre module personnalisé utilise déjà `Modules/Custom/menu.php`, ne remplacez pas ce fichier.

Dans ce cas, ouvrez le fichier existant et ajoutez à la fin la ligne suivante :

```php
include 'ianseo-ui-simplifier/menu.php';
```

Cela permet à plusieurs modules de coexister en toute sécurité.

---

## 📝 English

### Features
- Hides some menus or sections for users.
- Installs in `Modules/Custom` and survives ianseo updates.
- Compatible with other custom modules.
- Recommended module and pre-configuration updates via the user interface.
- The pre-configurations can be adapted.
- Multilingual compatibility.

### Installation
1. Download or clone this repository.
2. Copy the folder `ianseo-ui-simplifier` **and** the file `menu.php` to:
   - on Windows/Mac: `htdocs/Modules/Custom/`
   - on Linux: `ianseo/Modules/Custom/`
     *Under Linux, run the command :
     ```bash
     sudo chmod -R 777 /opt/ianseo/Modules/Custom/ianseo-ui-simplifier/
     ```
     This will enable updates via the module itself.

### If another custom module already uses `menu.php`
Open the existing `menu.php` and add:
```php
include 'ianseo-ui-simplifier/menu.php';
```
