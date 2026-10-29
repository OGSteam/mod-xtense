<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class CombatReportHandler extends AbstractHandler
{
    private const FLEET_TECHNOLOGIES = [
        202 => 'PT', 203 => 'GT', 204 => 'CLE', 205 => 'CLO', 206 => 'CR',
        207 => 'VB', 208 => 'VC', 209 => 'REC', 210 => 'SE', 211 => 'BMD',
        212 => 'SAT', 213 => 'DST', 214 => 'EDLM', 215 => 'TRA', 217 => 'FOR',
        218 => 'FAU', 219 => 'ECL',
    ];

    private const DEFENSE_TECHNOLOGIES = [
        401 => 'LM', 402 => 'LLE', 403 => 'LLO', 404 => 'CG', 405 => 'AI',
        406 => 'LP', 407 => 'PB', 408 => 'GB', 502 => 'MIC', 503 => 'MIP',
    ];

    public function handle(array $data): void
    {
        $reportType = $data['_xtense_request_type'] ?? 'rc';
        $rawPayload = $data['_xtense_raw_payload'] ?? json_encode($data);
        unset($data['_xtense_request_type'], $data['_xtense_raw_payload']);
        $api = filter_var($data['ogapilnk'] ?? '', FILTER_DEFAULT) ?: '';

        if (!isset($data['timestamp'])) {
            throw new UnexpectedValueException('Combat Report: Timestamp not sent');
        }
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }

        $this->registerCallback('rc', ['json' => $rawPayload, 'api' => $api]);
        $timestamp = (int)$data['timestamp'];
        $existing = $this->db->sql_fetch_row($this->db->sql_query(
            "SELECT `id_rc` FROM " . TABLE_PARSEDRC . " WHERE `dateRC` = '{$timestamp}'"
        ));
        if (isset($existing[0])) {
            $this->io->set(['type' => $reportType]);
            $this->logAction($reportType);
            return;
        }

        $coordinates = $this->parseCoordinates($data['coordinates'])['coords'];
        $rounds = $data['combatRounds'] ?? [];
        $winner = match ($data['result']['winner'] ?? null) {
            'draw' => 'N',
            'attacker' => 'A',
            'defender' => 'D',
            default => throw new UnexpectedValueException('Combat Report: Result not found'),
        };

        $loot = $this->resourceAmounts($data['result']['loot']['resources'] ?? []);
        $debris = $this->resourceAmounts($data['result']['debris']['resources'] ?? []);
        $losses = ['attacker' => 0, 'defender' => 0];
        foreach ($data['result']['totalValueOfUnitsLost'] ?? [] as $loss) {
            if (isset($losses[$loss['side'] ?? ''])) {
                $losses[$loss['side']] = (int)($loss['value'] ?? 0);
            }
        }
        $moonChance = (int)($data['result']['moonCreation']['chance'] ?? 0);

        $defenderObject = $data['defenderSpaceObject'] ?? [];
        $objectType = $this->db->sql_escape_string((string)($defenderObject['type'] ?? ''));
        $parsedCoordinates = $this->parseCoordinates($coordinates);
        $objectResult = $this->db->sql_query("SELECT id FROM " . TABLE_USER_BUILDING . "
            WHERE galaxy = {$parsedCoordinates['galaxy']} AND system = {$parsedCoordinates['system']}
                AND row = {$parsedCoordinates['row']} AND type = '{$objectType}'
            ORDER BY last_update DESC LIMIT 1");
        $objectRow = $this->db->sql_fetch_row($objectResult);
        $astroObjectId = isset($objectRow[0]) ? (int)$objectRow[0] : null;
        if ($astroObjectId === null) {
            throw new UnexpectedValueException("Combat Report: Astro object not found for coordinates {$coordinates} with type {$objectType}");
        }

        $this->db->sql_query("INSERT INTO " . TABLE_PARSEDRC . " (
            `dateRC`, `astro_object_id`, `nb_rounds`, `victoire`, `pertes_A`, `pertes_D`,
            `gain_M`, `gain_C`, `gain_D`, `debris_M`, `debris_C`, `lune`
        ) VALUES (
            {$timestamp}, {$astroObjectId}, " . count($rounds) . ", '{$winner}', {$losses['attacker']}, {$losses['defender']},
            {$loot['metal']}, {$loot['crystal']}, {$loot['deuterium']}, {$debris['metal']}, {$debris['crystal']}, {$moonChance}
        )");
        $reportId = (int)$this->db->sql_insertid();

        $combatants = $this->indexCombatants($data['fleets'] ?? []);
        foreach ($rounds as $roundNumber => $round) {
            $statistics = $this->roundStatistics($round['statistics'] ?? []);
            $this->db->sql_query("INSERT INTO " . TABLE_PARSEDRCROUND . " (
                `id_rc`, `numround`, `attaque_tir`, `attaque_puissance`, `defense_bouclier`,
                `attaque_bouclier`, `defense_tir`, `defense_puissance`
            ) VALUES (
                {$reportId}, " . (int)$roundNumber . ", {$statistics['attacker']['hits']}, {$statistics['attacker']['strength']},
                {$statistics['defender']['absorbedDamage']}, {$statistics['attacker']['absorbedDamage']},
                {$statistics['defender']['hits']}, {$statistics['defender']['strength']}
            )");
            $roundId = (int)$this->db->sql_insertid();
            if ($roundId === 0) {
                throw new UnexpectedValueException("Combat Report: Failed to insert round {$roundNumber}");
            }

            foreach ($round['fleets'] ?? [] as $fleet) {
                $this->insertRoundFleet($roundId, $astroObjectId, $fleet, $combatants);
            }
        }

        $this->io->set(['type' => $reportType]);
        $this->logAction($reportType);
    }

    private function resourceAmounts(array $resources): array
    {
        $amounts = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
        foreach ($resources as $resource) {
            $name = $resource['resource'] ?? '';
            if (array_key_exists($name, $amounts)) {
                $amounts[$name] = (int)($resource['amount'] ?? 0);
            }
        }
        return $amounts;
    }

    private function indexCombatants(array $fleets): array
    {
        $combatants = ['attacker' => [], 'defender' => []];
        foreach ($fleets as $fleet) {
            $side = $fleet['side'] ?? '';
            if (!isset($combatants[$side])) {
                continue;
            }
            $research = ['armor' => 0, 'weapon' => 0, 'shield' => 0];
            foreach ($fleet['combatResearchPercentage'] ?? [] as $technology) {
                $key = match ((int)($technology['id'] ?? 0)) {
                    109 => 'armor',
                    110 => 'weapon',
                    111 => 'shield',
                    default => null,
                };
                if ($key !== null) {
                    $research[$key] = (int)($technology['percentage'] ?? 0);
                }
            }
            $player = $fleet['player'] ?? [];
            $combatants[$side][(string)($player['name'] ?? '')] = $research;
        }
        return $combatants;
    }

    private function roundStatistics(array $statistics): array
    {
        $result = [
            'attacker' => ['hits' => 0, 'strength' => 0, 'absorbedDamage' => 0],
            'defender' => ['hits' => 0, 'strength' => 0, 'absorbedDamage' => 0],
        ];
        foreach ($statistics as $stat) {
            $side = $stat['side'] ?? '';
            if (!isset($result[$side])) {
                continue;
            }
            foreach (['hits', 'strength', 'absorbedDamage'] as $field) {
                $result[$side][$field] = (int)($stat[$field] ?? 0);
            }
        }
        return $result;
    }

    private function insertRoundFleet(int $roundId, int $astroObjectId, array $fleet, array $combatants): void
    {
        $side = $fleet['side'] ?? '';
        if (!in_array($side, ['attacker', 'defender'], true) || !isset($fleet['player'])) {
            return;
        }

        $player = $fleet['player'];
        $playerName = $this->db->sql_escape_string((string)($player['name'] ?? 'Unknown'));
        $playerId = (int)($player['id'] ?? -1);
        $research = $combatants[$side][(string)($player['name'] ?? '')] ?? ['armor' => 0, 'weapon' => 0, 'shield' => 0];
        $units = $side === 'attacker'
            ? $this->database['fleet']
            : array_values(array_unique(array_merge($this->database['fleet'], ['SAT', 'FOR'], $this->database['defense'])));
        $mapping = self::FLEET_TECHNOLOGIES + ($side === 'defender' ? self::DEFENSE_TECHNOLOGIES : []);
        $unitCounts = array_fill_keys($units, 0);

        foreach ($fleet['technologies'] ?? [] as $technology) {
            $technologyId = (int)($technology['technologyId'] ?? 0);
            $unitCode = $mapping[$technologyId] ?? null;
            if ($unitCode !== null && array_key_exists($unitCode, $unitCounts)) {
                $unitCounts[$unitCode] = (int)($technology['remaining'] ?? 0);
            }
        }

        $unitValues = [];
        foreach ($units as $unit) {
            $unitValues[] = in_array($unit, ['PB', 'GB'], true)
                ? (int)($unitCounts[$unit] > 0)
                : $unitCounts[$unit];
        }
        $table = $side === 'attacker' ? TABLE_ROUND_ATTACK : TABLE_ROUND_DEFENSE;
        $columns = $side === 'attacker'
            ? '`id_rcround`, `player`, `astro_object_id`, `Armes`, `Bouclier`, `Protection`'
            : '`id_rcround`, `player_id`, `astro_object_id`, `Armes`, `Bouclier`, `Protection`';
        $playerValue = $side === 'attacker' ? "'{$playerName}'" : (string)$playerId;
        $unitColumns = '`' . implode('`, `', $units) . '`';
        $this->db->sql_query("INSERT INTO {$table} ({$columns}, {$unitColumns}) VALUES (
            {$roundId}, {$playerValue}, {$astroObjectId}, {$research['weapon']}, {$research['shield']}, {$research['armor']}, " . implode(', ', $unitValues) . ')');
    }

    public function getType(): string
    {
        return 'rc';
    }

    public function getRequiredGrant(): string
    {
        return 'messages';
    }
}
