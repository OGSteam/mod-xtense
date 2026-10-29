<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class AllyListHandler extends AbstractHandler
{
    public function handle(array $data): void
    {
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }
        if (!isset($data['tag'], $data['allyList'])) {
            throw new UnexpectedValueException('Allylist: Missing Value');
        }

        $tag = filter_var($data['tag']);
        $members = [];
        foreach ($data['allyList'] as $member) {
            if (!isset($member['player'], $member['points'], $member['rank'], $member['coords'])) {
                throw new UnexpectedValueException('Allylist: Missing Ally Detailed list');
            }
            $members[] = [
                'pseudo' => filter_var($member['player']),
                'points' => filter_var($member['points'], FILTER_SANITIZE_NUMBER_INT),
                'coords' => explode(':', $member['coords']),
                'rang' => filter_var($member['rank'], FILTER_SANITIZE_NUMBER_INT),
            ];
        }

        $this->registerCallback('ally_list', ['list' => $members, 'tag' => $tag]);
        $this->io->set(['type' => 'ally_list', 'tag' => $tag]);
        $this->logAction('ally_list', ['tag' => $tag]);
    }

    public function getType(): string
    {
        return 'ally_list';
    }

    public function getRequiredGrant(): string
    {
        return 'ranking';
    }
}
