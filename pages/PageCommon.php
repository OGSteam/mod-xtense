<?php


class PageCommon
{
    public function checkUserRights($rights)
    {

        global $user_data, $io;

        if (!$user_data['grant'][$rights]) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => $rights
            ));
            $io->status(0);
        }
    }

        protected function  __construct() { }
}