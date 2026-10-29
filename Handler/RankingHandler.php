<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class RankingHandler extends AbstractHandler
{
    public function handle(array $data): void
    {
        $type1 = (string)filter_var($data['type1'] ?? '', FILTER_DEFAULT);
        $type2 = (string)filter_var($data['type2'] ?? '', FILTER_DEFAULT);
        $type3 = (string)filter_var($data['type3'] ?? '', FILTER_DEFAULT);
        $offset = filter_var($data['offset'] ?? null, FILTER_SANITIZE_NUMBER_INT);
        $date = filter_var($data['time'] ?? null, FILTER_DEFAULT);
        $entries = $data['n'] ?? null;

        if (!in_array($type1, ['player', 'ally'], true) || $type2 === '' || $offset === false || $entries === null || $date === false) {
            throw new UnexpectedValueException('Rankings: Incomplete Ranking');
        }
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }
        if (((int)$offset - 1) % 100 !== 0) {
            throw new UnexpectedValueException('Ranking: Offset not found');
        }
        if (!is_array($entries)) {
            throw new UnexpectedValueException('Rankings: Incomplete Ranking');
        }

        $table = $this->getTargetTable($type1, $type2, $type3);
        $timestamp = (int)($GLOBALS['timestamp'] ?? time());
        $senderId = (int)$this->userData['id'];
        $processed = [];
        $values = [];
        $isPlayerMilitary = $type1 === 'player' && $table === TABLE_RANK_PLAYER_MILITARY;

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entry['rank'] = (int)($entry['rank'] ?? 0);
            $entry['points'] = (int)($entry['points'] ?? 0);

            if ($type1 === 'player') {
                $entry['player_name'] = filter_var($entry['player_name'] ?? '', FILTER_DEFAULT);
                $entry['ally_tag'] = filter_var($entry['ally_tag'] ?? '', FILTER_DEFAULT);
                $entry['ally_id'] = !empty($entry['ally_id']) ? (int)$entry['ally_id'] : -1;
                $entry['player_id'] = !empty($entry['player_id']) ? (int)$entry['player_id'] : -1;

                if ($entry['player_id'] > 0 && $entry['player_name'] !== '') {
                    $playerName = $this->db->sql_escape_string($entry['player_name']);
                    $this->db->sql_query("INSERT INTO " . TABLE_GAME_PLAYER . " (id, name, ally_id, datadate)
                        VALUES ({$entry['player_id']}, '{$playerName}', {$entry['ally_id']}, " . time() . ")
                        ON DUPLICATE KEY UPDATE name = VALUES(name), ally_id = VALUES(ally_id), datadate = VALUES(datadate)");
                }
                if ($entry['ally_id'] > 0 && $entry['ally_tag'] !== '') {
                    $allyTag = $this->db->sql_escape_string($entry['ally_tag']);
                    $this->db->sql_query("INSERT INTO " . TABLE_GAME_ALLY . " (id, tag, datadate)
                        VALUES ({$entry['ally_id']}, '{$allyTag}', " . time() . ")
                        ON DUPLICATE KEY UPDATE tag = VALUES(tag), datadate = VALUES(datadate)");
                }

                $playerName = $this->db->sql_escape_string($entry['player_name']);
                $allyTag = $this->db->sql_escape_string($entry['ally_tag']);
                $rowValues = "({$timestamp}, {$entry['rank']}, '{$playerName}', {$entry['player_id']}, '{$allyTag}', {$entry['ally_id']}, {$entry['points']}, {$senderId}";
                if ($isPlayerMilitary) {
                    $rowValues .= ', ' . (int)($entry['nb_spacecraft'] ?? 0);
                }
                $values[] = $rowValues . ')';
            } else {
                $allyTag = $this->db->sql_escape_string(filter_var($entry['ally_tag'] ?? '', FILTER_DEFAULT));
                $allyName = $this->db->sql_escape_string(filter_var($entry['ally'] ?? '', FILTER_DEFAULT));
                if (!isset($entry['ally_id'], $entry['points'], $entry['mean'], $entry['members'])) {
                    throw new UnexpectedValueException('Ranking Ally: Missing alliance data');
                }
                $allyId = (int)$entry['ally_id'];
                $points = (int)$entry['points'];
                $mean = (int)$entry['mean'];
                $members = (int)$entry['members'];

                if ($allyId > 0 && $allyTag !== '' && $allyName !== '') {
                    $this->db->sql_query("INSERT INTO " . TABLE_GAME_ALLY . " (id, name, tag, datadate)
                        VALUES ({$allyId}, '{$allyName}', '{$allyTag}', " . time() . ")
                        ON DUPLICATE KEY UPDATE name = VALUES(name), tag = VALUES(tag), datadate = VALUES(datadate)");
                }
                $values[] = "({$timestamp}, {$entry['rank']}, '{$allyTag}', {$allyId}, {$points}, {$senderId}, {$members}, {$mean})";
            }

            $processed[] = $entry;
        }

        if ($values !== []) {
            if ($type1 === 'player') {
                $columns = '`datadate`, `rank`, `player`, `player_id`, `ally`, `ally_id`, `points`, `sender_id`';
                if ($isPlayerMilitary) {
                    $columns .= ', `nb_spacecraft`';
                }
                $this->db->sql_query("REPLACE INTO {$table} ({$columns}) VALUES " . implode(',', $values));
            } else {
                $this->db->sql_query("REPLACE INTO {$table} (datadate, rank, ally, ally_id, points, sender_id, number_member, points_per_member) VALUES " . implode(',', $values));
                $this->db->sql_query("UPDATE " . TABLE_USER . " SET rank_imports = rank_imports + " . count($values) . " WHERE id = {$senderId}");
            }
        }

        $callbackType2 = $type2 === 'fleet' ? $type2 . $type3 : $type2;
        $this->registerCallback('ranking_' . $type1 . '_' . $callbackType2, [
            'data' => $processed,
            'offset' => (int)$offset,
            'time' => $timestamp,
        ]);
        $this->io->set(['type' => 'ranking', 'type1' => $type1, 'type2' => $callbackType2, 'offset' => (int)$offset]);
        update_statistic('rankimports', 100);
        $this->logAction('ranking', [
            'type1' => $type1,
            'type2' => $callbackType2,
            'offset' => (int)$offset,
            'time' => $timestamp,
        ]);
    }

    private function getTargetTable(string $type1, string $type2, string $type3): string
    {
        if ($type1 === 'player') {
            return match ($type2) {
                'points' => TABLE_RANK_PLAYER_POINTS,
                'economy' => TABLE_RANK_PLAYER_ECO,
                'research' => TABLE_RANK_PLAYER_TECHNOLOGY,
                'fleet' => match ($type3) {
                    '' => TABLE_RANK_PLAYER_MILITARY,
                    '5' => TABLE_RANK_PLAYER_MILITARY_BUILT,
                    '6' => TABLE_RANK_PLAYER_MILITARY_DESTRUCT,
                    '4' => TABLE_RANK_PLAYER_MILITARY_LOOSE,
                    '7' => TABLE_RANK_PLAYER_HONOR,
                    default => throw new OutOfRangeException('Ranking Player: Unexpected Ranking type for type3: ' . $type3),
                },
                default => throw new UnexpectedValueException('Ranking Player: Unexpected Ranking type for type2: ' . $type2),
            };
        }

        return match ($type2) {
            'points' => TABLE_RANK_ALLY_POINTS,
            'economy' => TABLE_RANK_ALLY_ECO,
            'research' => TABLE_RANK_ALLY_TECHNOLOGY,
            'fleet' => match ($type3) {
                '' => TABLE_RANK_ALLY_MILITARY,
                '5' => TABLE_RANK_ALLY_MILITARY_BUILT,
                '6' => TABLE_RANK_ALLY_MILITARY_DESTRUCT,
                '4' => TABLE_RANK_ALLY_MILITARY_LOOSE,
                '7' => TABLE_RANK_ALLY_HONOR,
                default => throw new OutOfRangeException('Ranking Ally: Unexpected Ranking type for type3: ' . $type3),
            },
            default => throw new UnexpectedValueException('Ranking Ally: Unexpected Ranking type for type2: ' . $type2),
        };
    }

    public function getType(): string
    {
        return 'ranking';
    }

    public function getRequiredGrant(): string
    {
        return 'ranking';
    }
}
