# PayPro - Payroll Management System

Systeme de gestion de paie complet pour PME, avec gestion des employes, presences, primes, retenues et bulletins de paie.

## Stack technique

- **Backend** : PHP 8.4
- **Base de donnees** : SQLite (fichier `payroll.db`, cree automatiquement)
- **Frontend** : HTML5, CSS3, JavaScript
- **Serveur** : Nginx + PHP-FPM
- **CI/CD** : GitHub Actions (deploiement automatique sur push vers `master`)

## Fonctionnalites

- Authentification avec roles (admin / employe)
- Tableau de bord avec statistiques
- Gestion des employes (CRUD, types permanent/temporel)
- Grilles salariales, primes et retenues configurables
- Pointage des presences (clock-in / clock-out)
- Traitement de la paie mensuel automatique
- Generation et impression de bulletins de paie
- Rapports par mois, employe et departement
- Demandes de conge avec notifications WhatsApp
- Protection CSRF sur tous les formulaires

## Installation locale

```bash
# Prerequis : PHP 8.x avec extensions sqlite3 et pdo_sqlite
php -S localhost:8000
```

Ouvrir http://localhost:8000

**Identifiants par defaut :**
- Utilisateur : `admin`
- Mot de passe : `SecureAdmin2024!`

## Production

L'application est deployee sur **https://payrollsys.duckdns.org**

### Deploiement automatique (CI/CD)

Chaque push sur la branche `master` declenche un deploiement automatique via GitHub Actions :

1. Checkout du code
2. Copie des fichiers vers le serveur via SCP
3. Mise a jour des permissions

**Secrets GitHub requis :**
- `SERVER_HOST` : IP du serveur
- `SERVER_USER` : Utilisateur SSH
- `SSH_PRIVATE_KEY` : Cle privee SSH (koursa_deploy)
- `SERVER_SUDO_PASS` : Mot de passe sudo

### Infrastructure serveur

- **Serveur** : Ubuntu 24.04, Nginx, PHP 8.4-FPM
- **Domaine** : payrollsys.duckdns.org
- **SSL** : Certbot (Let's Encrypt)
- **Deploiement** : `/var/www/payrollsys`

## Structure du projet

```
payrollsys/
├── .github/workflows/deploy.yml   # CI/CD pipeline
├── includes/
│   ├── sidebar.php                # Menu lateral partage
│   ├── header.php                 # En-tete partage
│   └── pagination.php             # Composant de pagination
├── uploads/                       # Photos de profil
├── index.php                      # Page de connexion
├── dashboard.php                  # Tableau de bord admin
├── employees.php                  # Gestion des employes
├── salary.php                     # Grilles, primes, retenues
├── attendance.php                 # Presences et pointage
├── payroll.php                    # Traitement de la paie
├── payslips.php                   # Bulletins de paie
├── reports.php                    # Rapports
├── my_profile.php                 # Espace employe
├── config.php                     # Configuration
├── db.php                         # Connexion et schema BD
├── style.css                      # Styles
└── script.js                      # JavaScript
```

## Securite

- Protection CSRF sur tous les formulaires et appels AJAX
- Mots de passe hashes avec `password_hash()`
- Requetes preparees (PDO) contre les injections SQL
- Controle d'acces par role (admin vs employe)
- Fichiers sensibles bloques par Nginx (`.db`, `config.php`, `includes/`)
- Echappement HTML (`htmlspecialchars`) contre le XSS
