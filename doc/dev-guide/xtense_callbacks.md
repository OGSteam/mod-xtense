# Xtense Callbacks

##Introduction

Introduits dans Xtense 2, les appels (nommés Callbacks en anglais) sont l'envoi des données reçues par Xtense aux mods de OGSpy. A chaque réception de données par le mod Xtense, il appellera les mods enregistrés pour leur envoyer les données.
Lors de cet appel, Xtense inclut un fichier spécial du mod, dans lequel il exécutera une fonction définie par l'appel.
II. Enregistrement

L'enregistrement est nécessaire pour recevoir des appels. A chaque enregistrement correspond un type d'appel et une fonction, vous ne pouvez pas définir pour un même appel plusieurs types de données à renvoyer.

Structure de la table MySQL stockant ces appels:

    `prefix des tables` xtense_callbacks
    `id` int(3) : id de l'appel
    `mod_id` int(3) : id de votre mod
    `function` varchar(30) : nom de la fonction à appeler
    `type` enum() : type d'appel.
    `active` int(1) : determine le status de l'appel

Le champ `id` possédant une auto incrémentation, il ne faut pas le mettre dans la requête tout comme le champ `active` qui est par défaut à 1.
Lors d'un ajout, vérifiez si un enregistrement identique n'existe pas déjà dans la table.

Exemple

    INSERT INTO ogspy_xtense_callbacks (mod_id, function, type) VALUES (1, 'prout_galaxy_import', 'system')

##Fichiers

###Structure des fichiers

Lors des appels, Xtense inclura le fichier _xtense.php qui doit être dans le dossier de votre mod.
Ce fichier doit respecter une certaine hiérarchie :
La verification de la constante IN_SPYOGAME pour éviter les includes non sécurisées
La présence d'une variable, nommée $xtense_version qui contiendra la version minimum du plugin pour laquelle ce fichier est correct. Si la révision du plugin Xtense est plus vielle que celle de votre fichier, l'appel sera ignoré
Les fonctions appelées doivent commencer par le nom de votre mod pour éviter tout problème avec des fonctions définies plusieurs fois (ex: import_system qui peut être définie dans deux mods différents)
Un nombre d'includes le plus faible possible pour éviter une surcharge trop importante mais aussi pour éviter des problèmes de surdéfinition (fonctions, constantes)

###Contexte de développement

Il faut savoir que Xtense est maintenant totalement dissocié de OGSpy, aucun fichier de OGSpy n'est inclus (si ce n'est id.php). Donc toutes les fonctions, classes, constantes disponibles sont celles de Xtense. A noter que vos fichiers sont inclus depuis une fonction de Xtense (comme les mods). Voici une liste des variables globales utiles aux mods :
$server_config : Contient toutes les directives de configuration de l'OGSpy contenues dans la table ogspy_config
$user : Tableau avec les informations sur l'utilisateur actuellement connecté (pseudo, id, grand [tableau des droits pour Xtense (valeur 0 ou 1), index: system, ranking, empire, messages])
$db : Instance de la classe MySQL utilisée dans Xtense
$database : Tableau contenant les codes MySQL de l'espace personnel des joueurs

Structure

    $database = array(
        'buildings' => array('M', 'C', 'D', 'CES', 'CEF', 'UdR', 'UdN', 'CSp', 'HM', 'HC', 'HD', 'Lab', 'Ter', 'Silo', 'BaLu', 'Pha', 'PoSa'),
        'labo' => array('Esp', 'Ordi', 'Armes', 'Bouclier', 'Protection', 'NRJ', 'Hyp', 'RC', 'RI', 'PH', 'Laser', 'Ions', 'Plasma', 'RRI', 'Graviton', 'Expeditions'),
        'defense' => array('LM', 'LLE', 'LLO', 'CG', 'LP', 'AI', 'PB', 'GB', 'MIC', 'MIP'), 
        'fleet' => array('PT', 'GT', 'CLE', 'CLO', 'CR', 'VB', 'VC', 'RE', 'SE', 'BM', 'SS', 'DE', 'EDLM', 'TR')
    );

##Arguments des fonctions d'appels

Chaque fonction ne reçoit qu'un seul paramètre : un tableau (souvent sur plusieurs dimensions) contenant les données utiles aux mods. Les données envoyées sont spécifiques à chaque type d'appel

###Types d'appels

Voici une liste de tous les types d'appels que vous pouvez utiliser:

* system
* spy
* ennemy_spy
* rc
* rc_cdr
* msg
* ally_msg
* expedition
* ally_list
* overview
* buildings
* research
* fleet
* defense
* ranking_player_points
* ranking_player_fleet
* ranking_player_research
* ranking_ally_points
* ranking_ally_fleet
* ranking_ally_research

###Sommaire des paramètres

La liste ci-dessous représente une vue "raccourcie" de la variable contenant les données envoyée aux fonctions d'appel.
La syntaxe est un peu particulière, tous les index ayant un type "array #" sont des tableaux avec des index numériques. Soit il y a un nombre à la suite du #, ce qui signifie que c'est un tableau avec X lignes, soit une plage qui signifie que la taille du tableau varie suivant cette plage. Un index avec un type "array" est un tableau avec comme index des chaînes de caractères.

###Exemple

| Code PHP  | Equivalent  |
|-----------|-------------|
|   |   |

Code PHP	Equivalent

array
[data] (array #2)
array
[name] (string)
[ally] (string)
Code d'appels	Données envoyées
system	array
    [galaxy] (int)
    [system] (int)
    [data] (array #15)
array
    [planet_name] (string)
    [moon] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON Ogame
    [player_name] (string)
    [status] (string)
    [ally_tag] (string)
    [debris] (array)
    [metal] (int) Ogame
    [cristal] (int) Ogame
    [activity] (string) au format du jeu, * ou 37mn par exemple Ogame

spy	array #~
array
[coords] (array #3)
[0] (int) galaxie
[1] (int) système
[2] (int) ligne
[content] (string
[time] (int)
ennemy_spy	array #~
array
[from] (array #3)
[0] (int) galaxie
[1] (int) système
[2] (int) ligne
[to] (array #3)
[0] (int) galaxie
[1] (int) système
[2] (int) ligne
[proba] (int)
[time] (int)
rc	array
[content] (string) le contenu brut de la page du RC, entre les balises <body>
rc_cdr	array #~
array
[nombre] (int)
[coords] (array #3)
[0] (int) galaxie
[1] (int) système
[2] (int) ligne
[M_reco] (int) Métal récupéré 
[C_reco] (int) Cristal récupéré 
[M_total] (int) Metal dans le CdR 
[C_total] (int) Cristal dans le CdR 
[time] (int)
msg	array #~
array
[coords] (array #3)
[0] (int) galaxie
[1] (int) système
[2] (int) ligne
[from] (string)
[subject] (string)
[message] (string)
[time] (int)
ally_msg	array #~
array
[from] (string)
[tag] (string)
[message] (string)
[time] (int)
expedition	array #~
array
[coords] (array #3)
[0] (int) galaxie
[1] (int) système
[2] (int) ligne
[content] (string)
[time] (int
ally_list	array
[tag] (string)
[list] (array #~)
array
[pseudo] (string)
[points] (int)
[coords] (string)
[rang] (string)
overview	array
[coords] (array #3)
[0] (int) galaxie
[1] (int) système
[2] (int) ligne
[planet_name] (string)
[planet_type] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON
[fields] (int) cases max de la planète
[temp] (int) température max
buildings	array
[coords] (array #3)
[0] (int) galaxie
[1] (int) système
[2] (int) ligne
[planet_name] (string)
[planet_type] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON
[buildings] (array)
Tableau associatif contenant en index le code des batiments présents sur la planète ainsi que leur niveau. Comme tel : 
[code] => niveau (int)
research	array
[research] (array)
Tableau associatif contenant en index le code des recherches présentes sur la planète ainsi que leur niveau. Comme tel : 
[code] => niveau (int)
fleet	array
[coords] (array #3)
[0] (int) galaxie
[1] (int) système
[2] (int) ligne
[planet_name] (string)
[planet_type] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON
[fleet] (array)
Tableau associatif contenant en index le code des vaisseaux présents sur la planète ainsi que leur nombre. Comme tel : 
[code] => nombre (int)
defense	array
[coords] (array #3)
[0] (int) galaxie
[1] (int) système
[2] (int) ligne
[planet_name] (string)
[planet_type] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON
[defense] (array)
Tableau associatif contenant en index le code des defenses présentes sur la planète ainsi que leur nombre. Comme tel : 
[code] => nombre (int)
ranking_ally_points
ranking_ally_fleet
ranking_ally_research	array
[offset] (int)
[time] (int)
[data] (array #1-100)
array
[ally_tag] (string)
[members] (int) Ogame
[points] (int)
[mean] (int) Ogame
[ally_id] (int) E-Univers
ranking_player_points
ranking_player_fleet
ranking_player_research	array
[offset] (int)
[time] (int)
[data] (array #1-100)
array
[player_name] (string)
[ally_tag] (string)
[points] (int)
[player_id] (int) E-Univers
[ally_id] (int) E-Univers

##Fonctions usuelles

Le fichier de fonctions de xtense étant inclut vous pouvez avoir accès à ces fonctions. Voici quelques unes qui pourraient vous être utiles
quote ( string $str )
Une amélioration de add_slashes. Cette fonction rajoute des antislash si les magic_quotes_gpc ne sont pas activées. A utiliser pour chaque requête MySQL.
dump ( mixed $var [, mixed $var [, $... ]] )
Amélioration de la fonction var_dump() pour les renvois de Xtense. Elle formate les données pour qu'elles soient affichables depuis la fenêtre de debug
get_microtime ()
Retourne le timestamp UNIX courant avec les millisecondes
check_coords ( string $coords [, int $expedition] )
Cette fonction renvoi true ou false si les coordonnées sont correctes (prend en charge la config OGSpy pour le nombre de systèmes et de galaxies). Si $expedition est à 1 elle vérifie si les coordonnées sont celles d'une expédition