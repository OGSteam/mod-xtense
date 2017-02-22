# Xtense Callbacks

##Introduction

Introduits dans Xtense 2, les appels (nommés Callbacks en anglais) sont l'envoi des données reçues par Xtense aux mods de OGSpy. A chaque réception de données par le mod Xtense, il appellera les mods enregistrés pour leur envoyer les données.
Lors de cet appel, Xtense inclut un fichier spécial du mod, dans lequel il exécutera une fonction définie par l'appel.

##Enregistrement de la Callback

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

Exemple:

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
* ranking

###Sommaire des paramètres

La liste ci-dessous représente une vue "raccourcie" de la variable contenant les données envoyée aux fonctions d'appel.
La syntaxe est un peu particulière, tous les index ayant un type "array #" sont des tableaux avec des index numériques.
Soit il y a un nombre à la suite du #, ce qui signifie que c'est un tableau avec X lignes, soit une plage notifiée comme {1,n}.

### Code d'appel et données retournées

## Pages

### overview
```
    (array #9)
    [coords] (array #3)
        [0] (int) galaxie
        [1] (int) système
        [2] (int) ligne
    [planet_name] (string)
    [planet_type] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON
    [fields] (int) cases max de la planète
    [temperature_min] (int) température min
    [temperature_max] (int) température max
    [ressources] (array #5)
        [metal] (int)
        [cristal] (int)
        [deut] (int)
        [antimater] (int)
        [energy] (int)
    [ogame_timestamp] (int) Game time
    [boostExt] (array #2)
        [uuid] (int)
        [temps] (int)
```
### buildings

* Mine Page:
```
    (array #4)
    [coords] (array #3)
        [0] (int) galaxie
        [1] (int) système
        [2] (int) ligne
    [planet_name] (string)
    [planet_type] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON
    [M] => niveau (int)
    [C] => niveau (int)
    [D] => niveau (int)
    [CES] => niveau (int)
    [CEF] => niveau (int)
    [SAT] => niveau (int)
    [HM] => niveau (int)
    [HC] => niveau (int)
    [HD] => niveau (int)
```
* Installations Page:
```
    (array #13)
    [coords] (array #3)
        [0] (int) galaxie
        [1] (int) système
        [2] (int) ligne
    [planet_name] (string)
    [planet_type] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON
    [UdR] => niveau (int)
    [CSp] => niveau (int)
    [Lab] => niveau (int)
    [DdR] => niveau (int)
    [Silo] => niveau (int)
    [UdN] => niveau (int)
    [Ter] => niveau (int)
    [BaLu] => niveau (int) // For Moon Only
    [Pha] => niveau (int) // For Moon Only
    [PoSa] => niveau (int) // For Moon Only
```
### research
```
    (array #19)
    [coords] (array #3)
        [0] (int) galaxie
        [1] (int) système
        [2] (int) ligne
    [planet_name] (string)
    [planet_type] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON
    [NRJ] => niveau (int)
    [Laser] => niveau (int)
    [Ions] => niveau (int)
    [Hyp] => niveau (int)
    [Plasma] => niveau (int)
    [RC] => niveau (int)
    [RI] => niveau (int)
    [PH] => niveau (int)
    [Esp] => niveau (int)
    [Ordi] => niveau (int)
    [Astrophysique] => niveau (int)
    [RRI] => niveau (int)
    [Graviton] => niveau (int)
    [Armes] => niveau (int)
    [Bouclier] => niveau (int)
    [Protection] => niveau (int)
```
### fleet
```
    (array #16)
    [coords] (array #3)
        [0] (int) galaxie
        [1] (int) système
        [2] (int) ligne
    [planet_name] (string)
    [planet_type] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON
    [CLE] => (int)
    [CLO] => (int)
    [CR] => (int)
    [VB] => (int)
    [TRA] => (int)
    [BMD] => (int)
    [DST] => (int)
    [EDLM] => (int)
    [PT] => (int)
    [GT] => (int)
    [VC] => (int)
    [REC] => (int)
    [SE] => (int)
```
### defence
```
    (array #16)
    [coords] (array #3)
        [0] (int) galaxie
        [1] (int) système
        [2] (int) ligne
    [planet_name] (string)
    [planet_type] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON
    [LM] => (int)
    [LLE] => (int)
    [LLO] => (int)
    [CG] => (int)
    [AI] => (int)
    [LP] => (int)
    [PB] => (int)
    [GB] => (int)
    [MIC] => (int)
    [MIP] => (int)
```
### ranking
```
    (array #3)
    [offset] (int)
    [type1] (int) Ally or Player
    [type2] (int) Type Points Buildings Research Fleet
    [type3] (int) Sub Type for fleets
    [time] (int)
    [n] (array {1,100}) For players
        [player_id] (int)
        [player_name] (string)
        [ally_id] (int)        
        [ally_tag] (string)
        [points] (int)
        [nb_spacecraft] (int)
or for ally :
    [n] (array {1,100}) For alliance
        [ally_id] (int)
        [ally_tag] (string)
        [members] (int)        
        [points] (int)
        [mean] (int)
```
### system
```
    (array #3)
    [galaxy] (int)
    [system] (int)
    [row] (array #12)
        [player_id] (int)
        [planet_name] (string)
        [planet_id] (int)
        [moon_id] (int)
        [moon] (int) défini par les constantes TYPE_PLANET ou TYPE_MOON Ogame
        [player_name] (string)
        [status] (string)
        [ally_id] (int)
        [ally_tag] (string)
        [debris] (array #2)
            [metal] (int) Ogame
            [cristal] (int) Ogame
        [activity] (string) au format du jeu, * ou 37mn par exemple Ogame
        [activityMoon] (string) au format du jeu, * ou 37mn par exemple Ogame
```
### ally_list
```
    (array #2)
    [tag] (string)
    [n] (array #4{1,n} )
        [pseudo] (string)
        [points] (int)
        [coords] (string)
        [rang] (string)
```
##Messages

### cr
```
    (array #)
    [date] (int)
    [win] {D,A,N}
    [count] (int) Nb rounds
    [result]
        [a_lost] (int)
        [d_lost] (int)
        [win_metal] (int) galaxie
        [win_cristal] (int) système
        [win_deut] (int) ligne
        [deb_metal] (int) ligne
        [deb_cristal] (int) ligne
    [moon] (int)
    [moonprob] (int)
    [rounds]
        [rnd][n][a_nb]
        [rnd][n][a_shoot]
        [rnd][n][d_bcl]
        [rnd][n][d_nb]
        [rnd][n][d_shoot]
        [rnd][n][a_bcl]
    [n]
        [player] (string)
        [coords] (string)
        [type] A /D (Attacker or Defender)
        [weapons]
            [arm] (int)
            [bcl] (int)
            [coq] (int)
        [content] ???????? // A compléter au debugger
    [rawdata]
        [xxxxxxx]  // A compléter au debugger
    [ogapilnk]
```
### rc_cdr
```
    (array #7)
    [nombre] (int) // Nb recycleurs
    [coords] (string)
    [M_recovered] (int) Métal récupéré
    [C_recovered] (int) Cristal récupéré
    [M_total] (int) Metal dans le CdR
    [C_total] (int) Cristal dans le CdR
    [date] (int)
```
### expedition
```
    (array #3)
    [coords] (string)
    [content] (string)
    [time] (int)
```
### ally_msg
```
    (array #4)
    [from] (string)
    [tag] (string)
    [message] (string)
```
### msg (player_msg)
```
    (array #4)
    [from] (string)
    [coords] (string)
    [subject] (string)
    [message] (string)
```
### spy
```
    (array #3)
    [planetName] (string)
    [coords] (string)
    [content] (string)
    [time] (int)
    ['metal'] (int)
    ['cristal'] (int)
    ['deuterium'] (int)
    ['energie'] (int)
```    
#### buildings
```
     ['M'] (int) Level
     ['C'] (int) Level
     ['D'] (int) Level
     ['CES'] (int) Level
     ['CEF'] (int) Level
     ['UdR'] (int) Level
     ['UdN'] (int) Level
     ['CSp'] (int) Level
     ['Sat'] (int) Level
     ['HM'] (int) Level
     ['HC'] (int) Level
     ['HD'] (int) Level
     ['CM'] (int) Level
     ['CC'] (int) Level
     ['CD'] (int) Level
     ['Lab'] (int) Level
     ['Ter'] (int) Level
     ['DdR'] (int) Level
     ['Silo'] (int) Level
     ['BaLu'] (int) Level
     ['Pha'] (int) Level
     ['PoSa'] (int) Level
```     
#### researchs
```
     ['Esp'] (int) Level
     ['Ordi'] (int) Level
     ['Armes'] (int) Level
     ['Bouclier'] (int) Level
     ['Protection'] (int) Level
     ['NRJ'] (int) Level
     ['Hyp'] (int) Level
     ['RC'] (int) Level
     ['RI'] (int) Level
     ['PH'] (int) Level
     ['Laser'] (int) Level
     ['Ions'] (int) Level
     ['Plasma'] (int) Level
     ['RRI'] (int) Level
     ['Astrophysique'] (int) Level
     ['Graviton']
```
#### fleets
```
     ['PT'] (int) Level
     ['GT'] (int) Level
     ['CLE'] (int) Level
     ['CLO'] (int) Level
     ['CR'] (int) Level
     ['VB'] (int) Level
     ['VC'] (int) Level
     ['REC'] (int) Level
     ['SE'] (int) Level
     ['BMD'] (int) Level
     ['SAT'] (int) Level
     ['DST'] (int) Level
     ['EDLM'] (int) Level
     ['TRA']
```  
#### defense
```
     ['LM'] (int) Level
     ['LLE'] (int) Level
     ['LLO'] (int) Level
     ['CG'] (int) Level
     ['AI'] (int) Level
     ['LP'] (int) Level
     ['PB'] (int) Level
     ['GB'] (int) Level
     ['MIC'] (int) Level
     ['MIP'] (int) Level
```
### ennemy_spy:
```
    (array #3)release-notes
    [from] (string)
    [to] (string)
    [proba] (int)
```