<?php
namespace Deployer;

require 'recipe/symfony.php';

// --------------------------
// modification des paramètres par défaut
// --------------------------

// on demande à inclure les packages de dev
set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader');

// ---------------------------------------------------------------------------
// Paramètres de notre application
// ---------------------------------------------------------------------------
set("env_database", "mysql://CurieSeb:JhhD7Dr4@127.0.0.1:3306/BoucherieV2?serverVersion=mariadb-10.3.34");


// ---------------------------------------------------------------------------
// Paramètres de connexion au serveur distant
// ---------------------------------------------------------------------------

// Nom du fichier contenant la clé SSH permettant de s'authentifier auprès
// du serveur distant (va chercher dans votre répertoire local ~/.ssh de
// l'utilisateur courant).
// Généralement requis pour se connecter à un serveur mais pas nécessaire
// pour se connecter à notre VM Kourou.
// set('ssh_key_filename', 'nom_du_fichier_contenant_la_cle_ssh.pem');

// Adresse du serveur distant (adresse IP ou DNS public)
// set('remote_server_url','adresse_ip_ou_dns_public_du_serveur');
// TODO 
set('remote_server_url','sebastienjeandel-server.eddi.cloud');

// Nom du compte utilisateur sur le serveur distant/
// C'est cet utilisateur qui exécutera les commandes distantes.
// set('remote_server_user','nom_utilisateur_distant');
// TODO
set('remote_server_user','student');

// ---------------------------------------------------------------------------
// Paramètres de déploiement spécifiques à notre projet
// ---------------------------------------------------------------------------

// Répertoire cible (sur le serveur distant) où le code source sera déployé
// => le répertoire sera créé s'il n'existe pas
// TODO
set('remote_server_target_repository', '/var/www/html/boucherieV1');

// Adresse du dépôt Github contenant le code source du projet 
// TODO
set('repository', 'git@github.com:SebastienJEANDEL/boucherieV1.git');

// Nom de la branche à déployer
// TODO branch
set('repository_target_branch', 'master');

// ---------------------------------------------------------------------------
// Autres paramètres concernant le déploiement
// ---------------------------------------------------------------------------

// [Optional]
// Ce paramètre permet d'avoir le retour de la commande "git clone"
set('git_tty', true); 

// On ne veut pas envoyer de statistiques à Deployer.org (même de façon anonyme)
set('allow_anonymous_stats', false);

// Nombre de releases à conserver (5 par défaut, -1 pour illimité)
// TODO
set('keep_releases', 3);

// ---------------------------------------------------------------------------
// Définition des paramètres de déploiement pour le serveur de 'production'
// ---------------------------------------------------------------------------

host('prod')
    // On précise l'adresse du serveur distant.
    // Les doubles accolades {{my_parameter}} permettent de récupérer
    // la valeur d'un paramètre défini avec set('my_parameter','my_value');
    ->set('hostname', '{{remote_server_url}}')
    // Précise le chemin absolu (sur la machine distante) du répertoire
    // dans lequel le code sera déployé.
    // par exemple : /var/www/html/mywebsite
    ->set('deploy_path', '{{remote_server_target_repository}}')
    // Si la branche n'est pas spécifiée, Deployer utilise le nom de la branche
    // actuelle du dépôt Git local dans lequel on se trouve.
    ->set('branch', '{{repository_target_branch}}')
    // Précise le nom de l'utilisateur (sur la machine distante) qui sera utilisé
    // pour établir la connexion SSH et exécuter les commandes.
    ->set('remote_user', '{{remote_server_user}}');
    // Chemin du fichier (sur votre machine locale) contenant la clé SSH permettant
    // d'établir la connexion SSH.
    // Généralement requis pour se connecter à un serveur mais pas nécessaire
    // pour se connecter à notre VM Kourou.
    // ->set('identity_file','~/.ssh/{{ssh_key_filename}}')

// ---------------------------------------------------------------------------
// Définition des tâches (tasks)
// ---------------------------------------------------------------------------

desc('Création de la base de données');
task('init:database', function() {
    run('{{bin/console}} doctrine:database:create');
});

desc('Supression base de données');
task('init:database:drop', function() {
    run('{{bin/console}} doctrine:database:drop --if-exists --no-interaction --force');
});


desc("Création des fixtures");
task('init:fixtures', function () {
    // comme la commande fixture nous pose la question si OUI ou NON on vide la base de données
    // et que l'on ne peut pas intéragir, on ajoute un "yes | " pour pré-répondre à la question
    run('yes | {{bin/console}} doctrine:fixtures:load');
});

// TODO
desc('écraser le .env.local PUIS écrire les paramètres de PROD');
task('init:config:write:prod', function() {
    // {{remote_server_target_repository}} == '/var/www/html/oflix
    run('echo "APP_ENV=prod" > {{remote_server_target_repository}}/shared/.env.local');
    run('echo "DATABASE_URL={{env_database}}" >> {{remote_server_target_repository}}/shared/.env.local');
});

// TODO
desc('écraser le .env.local PUIS écrire les paramètres de DEV');
task('init:config:write:dev', function() {
    run('echo "APP_ENV=dev" > {{remote_server_target_repository}}/shared/.env.local');
    run('echo "DATABASE_URL={{env_database}}" >> {{remote_server_target_repository}}/shared/.env.local');
});

desc('Deploy project');
task('first_deploy', [

    // https://deployer.org/docs/7.x/recipe/common#deployprepare
    'deploy:prepare',

    // on écrit notre fichier .env.local
    'init:config:write:dev',

    // https://deployer.org/docs/7.x/recipe/deploy/vendors#deployvendors
    'deploy:vendors',

    // https://deployer.org/docs/7.x/recipe/symfony#deploycacheclear
    'deploy:cache:clear',

    // au cas où il existe la BDD
    'init:database:drop',

    // on crée la base de donnée
    'init:database',

    // https://deployer.org/docs/7.x/recipe/symfony#databasemigrate
    'database:migrate',

    // on lance les fixtures
    'init:fixtures',

    // on écrit notre fichier .env.local
    'init:config:write:prod',

    

    // https://deployer.org/docs/7.x/recipe/common#deploypublish
    'deploy:publish'
]);

task('prod_update', [
    // https://deployer.org/docs/7.x/recipe/common#deployprepare
    'deploy:prepare',

    // https://deployer.org/docs/7.x/recipe/deploy/vendors#deployvendors
    'deploy:vendors',

    // https://deployer.org/docs/7.x/recipe/symfony#deploycacheclear
    'deploy:cache:clear',

    // https://deployer.org/docs/7.x/recipe/symfony#databasemigrate
    'database:migrate',
    
    // https://deployer.org/docs/7.x/recipe/common#deploypublish
    'deploy:publish'
]);

// Facultatif, en cas d'échec du déploiement on force la suppression
// du fichier 'deploy.lock' présent dans le répertoire '.dep' qui sert
// d'indicateur de 'déploiement en cours'
after('deploy:failed', 'deploy:unlock');
