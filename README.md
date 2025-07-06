
# ianseo-ui-simplifier

> 🇫🇷 Module personnalisable pour ianseo afin de simplifier son interface en masquant certains menus et en améliorant l’expérience utilisateur.  
> 🇬🇧 Customizable module for ianseo to simplify its user interface by hiding some menus and improving usability.

---

## ✨ Fonctionnalités

- Masque des menus ou sections pour les utilisateurs.
- S’intègre dans le répertoire `Modules/Custom` pour rester intact lors des mises à jour de ianseo.
- Compatible avec d’autres modules personnalisés.

---

## 📥 Installation

1. Téléchargez ou clonez ce dépôt.
2. Copiez le dossier `ianseo-ui-simplifier` **et** le fichier `menu.php` fournis dans ce dépôt dans le dossier :  

   - sous Windows/Mac : `htdocs/Modules/Custom/`
   - sous Linux : `ianseo/Modules/Custom/`

### 🗂️ Arborescence attendue :

```
htdocs/
└── Modules/
    └── Custom/
        ├── ianseo-ui-simplifier/
        │   ├── App/
        │   │   └── correspondance.csv
        │   │   └── settings.php
        │   └── menu.php
        ├── menu.php
        ├── LICENSE
        └── README.md
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

### Installation
1. Download or clone this repository.
2. Copy the folder `ianseo-ui-simplifier` **and** the file `menu.php` to:
   - on Windows/Mac: `htdocs/Modules/Custom/`
   - on Linux: `ianseo/Modules/Custom/`

### If another custom module already uses `menu.php`
Open the existing `menu.php` and add:
```php
include 'ianseo-ui-simplifier/menu.php';
```
