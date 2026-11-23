<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class ResearchsHandler extends AbstractHandler
{
    public function handle(array $data): void
    {
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }

        $coordinates = filter_var($data['planet']['coords']);
        $planetName = filter_var($data['planet']['name']);
        $planetType = filter_var($data['planet']['type']);
        $researches = $data['researchs'];
        if (!isset($coordinates, $planetName, $planetType)) {
            throw new UnexpectedValueException('Researchs: Missing Planet Details');
        }

        $coordinates = Check::coords($coordinates);
        $planetType = ((int)$planetType === TYPE_PLANET) ? TYPE_PLANET : TYPE_MOON;
        if (!isset($this->userData['player_id']) || $this->userData['player_id'] <= 0) {
            throw new UnexpectedValueException('Researchs: Player ID not available');
        }

        $playerId = (int)$this->userData['player_id'];
        $fields = [];
        $values = [];
        $updates = [];
        foreach ($this->database['labo'] as $code) {
            if (isset($researches[$code])) {
                $fields[] = "`$code`";
                $values[] = (int)$researches[$code];
                $updates[] = "`$code` = " . (int)$researches[$code];
            }
        }

        if ($fields !== []) {
            $this->db->sql_query("INSERT INTO " . TABLE_USER_TECHNOLOGY . "
                (`player_id`, " . implode(', ', $fields) . ")
                VALUES ({$playerId}, " . implode(', ', $values) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updates));
        }

        $this->setPageUpdatedResponse('labo', $coordinates);
        $this->registerCallback('research', ['research' => $researches]);
        $this->logAction('research');
    }

    public function getType(): string
    {
        return 'researchs';
    }

    public function getRequiredGrant(): string
    {
        return 'empire';
    }
}
