<?php


class Overview extends PageCommon
{
    private $planet_name;
    private $ressources;
    private $temperature_min;
    private $temperature_max;
    private $fields;
    private $coords;
    private $planet_type;
    private $ogame_timestamp;
    private $off_commandant;
    private $off_amiral;
    private $off_ingenieur;
    private $off_geologue;
    private $off_technocrate;
    private $userclass;
    private $player_details;
    private $uni_details;


    public function  __construct($toolbar_info) {

        parent::__construct();

        global $user_data;
        if ($this->checkDataRequirements() ){
            $this->checkUserRights( 'empire');
            $this->filterContent();
            $this->saveData();
            $this->publishDataToMods();
            //log :
            add_log('overview', array('coords' => $this->coords, 'planet_name' => $this->planet_name, 'toolbar' => $toolbar_info));
        }
    }

    private function checkDataRequirements(){
        if (isset($pub_coords,
                  $pub_planet_name,
                  $pub_planet_type,
                  $pub_fields,
                  $pub_temperature_min,
                  $pub_temperature_max,
                  $pub_ressources,
                  $pub_playerdetails,
                  $pub_unidetails)) {

            return true;
        }
        else {
                return false;
        }
    }

    private function filterContent() {
        $this->player_details = filter_var_array($_POST['playerdetails'], [
            'player_name'   => FILTER_SANITIZE_STRING,
            'player_id'   => FILTER_SANITIZE_STRING,
            'playerclass_explorer'   => FILTER_SANITIZE_STRING,
            'playerclass_miner'   => FILTER_SANITIZE_STRING,
            'playerclass_warrior'   => FILTER_VALIDATE_INT,
            'player_officer_commander'   => FILTER_VALIDATE_INT,
            'player_officer_amiral'   => FILTER_VALIDATE_INT,
            'player_officer_engineer'   => FILTER_VALIDATE_INT,
            'player_officer_geologist'   => FILTER_VALIDATE_INT,
            'player_officer_technocrate'   => FILTER_VALIDATE_INT
        ]);

        $this->uni_details = filter_var_array($_POST['unidetails'], [
                'uni_version'   => FILTER_SANITIZE_STRING,
                'uni_url'   => FILTER_SANITIZE_STRING,
                'uni_lang'   => FILTER_SANITIZE_STRING,
                'uni_name'   => FILTER_SANITIZE_STRING,
                'uni_time'   => FILTER_VALIDATE_INT,
                'uni_speed'   => FILTER_VALIDATE_INT, // speed_uni
                'uni_speed_fleet'   => FILTER_VALIDATE_INT,
                'uni_donut_g'   => FILTER_VALIDATE_INT,
                'uni_donut_s'   => FILTER_VALIDATE_INT
            ]
        );

        $this->planet_name = filter_var($_POST['planet_name'], FILTER_SANITIZE_STRING);
        $this->ressources = filter_var_array($_POST['ressources'], FILTER_VALIDATE_INT);
        $this->temperature_min = filter_var($_POST['temperature_min'], FILTER_VALIDATE_INT);
        $this->temperature_max = filter_var($_POST['temperature_max'], FILTER_VALIDATE_INT);
        $this->fields = filter_var($_POST['fields'], FILTER_VALIDATE_INT);

        $this->coords = Check::coords($_POST['coords']);
        $this->planet_type = ((int)$_POST['planet_type'] == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);
        $this->ogame_timestamp = $this->uni_details['uni_time'];
        $this->player_details['playerclass_miner'] == 1 ? $this->userclass = 'COL' : $this->player_details['playerclass_warrior'] == 1 ? $this->userclass = 'GEN' : $this->player_details['playerclass_explorer'] == 1 ? $this->userclass = 'EXP' : $this->userclass = 'none' ;
        $this->off_commandant = $this->player_details['player_officer_commander'];
        $this->off_amiral = $this->player_details['player_officer_amiral'];
        $this->off_ingenieur = $this->player_details['player_officer_engineer'];
        $this->off_geologue = $this->player_details['player_officer_geologist'];
        $this->off_technocrate = $this->player_details['player_officer_technocrate'];
    }

    private function saveData()
    {
        global $db,$user_data,$io;
        //Officers
        $db->sql_query("UPDATE " . TABLE_USER. " SET `user_class` = '$this->userclass', `off_commandant` = '$this->off_commandant', `off_amiral` = '$this->off_amiral', `off_ingenieur` = '$this->off_ingenieur', `off_geologue` = '$this->off_geologue', `off_technocrate` = '$this->off_technocrate'" );

        //Uni Speed
        $unispeed = $this->uni_details['uni_speed'];
        $db->sql_query("UPDATE " . TABLE_CONFIG. " SET `config_value` = '$unispeed' WHERE `config_name` = 'speed_uni' " );
        generate_config_cache();

        //boosters
        if (isset($pub_boostExt)) {
            $boosters = update_boosters($pub_boostExt, $this->ogame_timestamp); /*Merge des différents boosters*/
            $boosters = booster_encode($boosters); /*Conversion de l'array boosters en string*/
        } else
            $boosters = booster_encodev(0, 0, 0, 0, 0, 0, 0, 0); /* si aucun booster détecté*/

        //Empire
        $home = home_check($this->planet_type, $this->coords);
        if ($home[0] == 'full') {
            $io->set(array('type' => 'home full'));
            $io->status(0);
        } else {
            if ($home[0] == 'update') {
                $db->sql_query('UPDATE ' . TABLE_USER_BUILDING . ' SET `planet_name` = "' . $this->planet_name . '", `fields` = ' . $this->fields . ', `boosters` = "' . $boosters . '", `temperature_min` = ' . $this->temperature_min . ', `temperature_max` = ' . $this->temperature_max . '  WHERE `planet_id` = ' . $home['id'] . ' AND `user_id` = ' . user_data['user_id']);
            } else {
                $db->sql_query('INSERT INTO ' . TABLE_USER_BUILDING . ' (`user_id`, `planet_id`, `coordinates`, `planet_name`, `fields`, `boosters`, `temperature_min`, `temperature_max`) VALUES (' . $user_data['user_id'] . ', ' . $home['id'] . ', "' . $this->coords . '", "' . $this->planet_name . '", ' . $this->fields . ', "' . $boosters . '", ' . $this->temperature_min . ', ' . $this->temperature_max . ')');
            }

            $io->set(array(
                'type' => 'home updated',
                'page' => 'overview',
                'planet' => $this->coords
            ));
        }


    }

    private function publishDataToMods()
    {
       global $call;
        // Appel fonction de callback
        $call->add('overview', array(
            'coords' => explode(':', $this->coords),
            'planet_type' => $this->planet_type,
            'planet_name' => $this->planet_name,
            'fields' => $this->fields,
            'temperature_min' => $this->temperature_min,
            'temperature_max' => $this->temperature_max,
            'ressources' => $this->ressources
        ));
    }
}
