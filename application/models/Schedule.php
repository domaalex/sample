<?php

class Application_Model_Schedule {

    const POINTS_PER_WIN = 3;
    const POINTS_PER_DRAWN = 1;

    protected $_dbTable = array();

    public function setDbTable($dbTable) {
        if (is_string($dbTable)) {
            $dbTable = new $dbTable();
        }
        if (!$dbTable instanceof Zend_Db_Table_Abstract) {
            throw new Exception('Invalid table data gateway provided');
        }
        $name = get_class($dbTable);
        $this->_dbTable[$name] = $dbTable;

        return $this;
    }

    protected function _getDbTable($name = 'Application_Model_DbTable_Schedule') {
        if (!isset($this->_dbTable[$name])) {
            $this->setDbTable($name);
        }

        return $this->_dbTable[$name];
    }

    public function getDbTable() {

        return $this->_getDbTable('Application_Model_DbTable_Schedule');
    }

    public function find($val) {
        $result = $this->getDbTable()->find($val);
        if (0 == count($result)) {
            return;
        }

        return $row = $result->current();
    }

    public function fetchAll() {
        $resultSet = $this->getDbTable()->fetchAll();

        return $resultSet;
    }

    public function save($data, $id = null) {
        if (null === $id) {
            $lastInsertId = $this->getDbTable()->insert($data);

            return $lastInsertId;
        } else {
            $this->getDbTable()->update($data, array('id = ?' => $id));
        }
    }

    public function getCurrentRound() {
        $round = 0;

        $select = $this->getDbTable()->select();
        $select->setIntegrityCheck(false);
        $select->from(array('s' => 'schedules'), array('round'))
                ->where('is_played = ?', 1)
                ->order('round DESC');
        $result = $this->getDbTable()->fetchRow($select);

        if ($result && $result->round) {
            $round = $result->round;
        }
        return $round;
    }

    public function generateSchedule() {

        $model_team = new Application_Model_Team();
        $teams = $model_team->fetchAll();

        $total_rounds = 2 * count($teams) - 2;

        // generate pairs
        $cnt_teams = count($teams);
        $pairs = self::generate_pairs_for_schedule($cnt_teams);
        if ($pairs) {
            foreach ($pairs as $key => $pair) {
                $generated_schedule_game_1 = array(
                    'team_owner_id' => $pair['first_game_pair'][0],
                    'team_guest_id' => $pair['first_game_pair'][1],
                    'round' => $pair['round']
                );
                $this->save($generated_schedule_game_1);

                $generated_schedule_game_1 = array(
                    'team_owner_id' => $pair['first_game_pair'][1],
                    'team_guest_id' => $pair['first_game_pair'][0],
                    'round' => $total_rounds - $pair['round'] + 1
                );
                $this->save($generated_schedule_game_1);

                $generated_schedule_game_2 = array(
                    'team_owner_id' => $pair['second_game_pair'][0],
                    'team_guest_id' => $pair['second_game_pair'][1],
                    'round' => $pair['round']
                );
                $this->save($generated_schedule_game_2);

                $generated_schedule_game_2 = array(
                    'team_owner_id' => $pair['second_game_pair'][1],
                    'team_guest_id' => $pair['second_game_pair'][0],
                    'round' => $total_rounds - $pair['round'] + 1
                );
                $this->save($generated_schedule_game_2);
            }
        }
    }

    public static function generate_pairs_for_schedule($cnt_teams) {
        $result_pairs = array();

        for ($k = 1; $k <= $cnt_teams; $k++) {
            $ids[] = $k;
        }
        $combinations = array();
        $num_ids = count($ids);

        for ($i = 0; $i < $num_ids; $i++) {
            for ($j = $i + 1; $j < $num_ids; $j++) {
                $combinations[] = array($ids[$i], $ids[$j]);
            }
        }

        shuffle($combinations);
        $round = 1;
        foreach ($combinations as $key => $value) {
            foreach ($combinations as $k => $v) {
                if ($k > $key) {
                    if (count(array_unique(array_merge($value, $v))) == count($ids)) {
                        $result_pairs[] = array('first_game_pair' => $v, 'second_game_pair' => $value, 'round' => $round);
                        $round++;
                    }
                }
            }
        }

        return $result_pairs;
    }

    public function getWeekResults($next_round = 1) {
        $result = array();

        if (isset($next_round) && !empty($next_round)) {

            $select = $this->getDbTable()->select();
            $select->setIntegrityCheck(false);
            $select->from(array('s' => 'schedules'), array('team_owner_id', 'team_guest_id', 'team_owner_goal_cnt', 'team_guest_goal_cnt'))
                    ->joinLeft(array('t1' => 'teams'), 't1.id = s.team_owner_id', array('t1.title as team_owner_title'))
                    ->joinLeft(array('t2' => 'teams'), 't2.id = s.team_guest_id', array('t2.title as team_guest_title'))
                    ->where('s.round = ?', $next_round);

            $result = $this->getDbTable()->fetchAll($select)->toArray();
        }

        return $result;
    }

    public function generateGameResults($next_round = 1) {
        $result = array();

        if (isset($next_round) && !empty($next_round)) {

            $select = $this->getDbTable()->select();
            $select->setIntegrityCheck(false);
            $select->from(array('s' => 'schedules'), array('s.id', 'team_owner_id', 'team_guest_id'))
                    ->joinLeft(array('t1' => 'teams'), 't1.id = s.team_owner_id', array('t1.level as team_owner_level'))
                    ->joinLeft(array('t2' => 'teams'), 't2.id = s.team_guest_id', array('t2.level as team_guest_level'))
                    ->where('s.round = ?', $next_round);
            $planned_games = $this->getDbTable()->fetchAll($select)->toArray();


            if ($planned_games) {
                foreach ($planned_games as $key => $game) {
                    $generated_game_results = array(
                        'team_owner_goal_cnt' => $this->generateGoalsCnt(true, $game['team_owner_level'], $game['team_owner_id'], $next_round),
                        'team_guest_goal_cnt' => $this->generateGoalsCnt(false, $game['team_guest_level'], $game['team_guest_id'], $next_round),
                        'is_played' => 1
                    );
                    $this->save($generated_game_results, $game['id']);
                }
            }
        }

        return $result;
    }

    public function generateGoalsCnt($is_owner, $level, $team_id, $round) {
        // кол-во забитых мячей
        // Бонус если играют на своём поле
        // бонус если продлевают беспроигрышную серию
        // бонус за уровень команды
        $points = 0;
        if ($is_owner) {
            $points +=10; // add 10 points for home match
        }

        $points += mt_rand($level, 16 + $level);
        $points += mt_rand(0, 20);

        $previous_result = $this->getPreviousResult($team_id, $round);
        if ($previous_result) {
            if ($previous_result['d']) {
                $points += 10;
            } else if ($previous_result['w']) {
                $points += 20;
            }
        }

        return ceil($points / 15);
    }

    public function getPreviousResult($team_id, $round) {
        $result = '';

        if (isset($round) && $round > 1) {
            $result = $this->getTeamStatistics($team_id, $round - 1);
        }

        return $result;
    }

    public function getTeamsStatistics($teams) {
        $team_statistics = array();

        $forecast = array();
        $points_max = 0;
        $points_to_finish = 0;
        foreach ($teams as $key => $team) {
            $team_statistics[$team->id] = $this->getTeamStatistics($team->id);
            $team_statistics[$team->id]['title'] = $team->title;

            if ($team_statistics[$team->id]['pts'] > $points_max) {
                $points_max = $team_statistics[$team->id]['pts'];
            }
            $forecast[$team->id] = $team_statistics[$team->id]['pts'];
            $points_to_finish = $team_statistics[$team->id]['points_to_finish'];
        }

///////////////здесь подсчитывается статистика
        $sum_forecast = 0;
        foreach ($forecast as $key => $value) {
            if ($value + $points_to_finish > $points_max) {
                $sum_forecast += ($value + $points_to_finish - $points_max);
            }
        }
        if ($sum_forecast > 0) {
            foreach ($forecast as $key => $value) {
                if ($value + $points_to_finish <= $points_max) {
                    $team_statistics[$key]['forecast'] = 0;
                } else {
                    $team_statistics[$key]['forecast'] = ceil(($value + $points_to_finish - $points_max) * 100 / $sum_forecast);
                }
            }
        }
//////////////////

        usort($team_statistics, function ($a, $b) {
            if ($a['pts'] == $b['pts']) {
                return 0;
            }
            return ($a['pts'] < $b['pts']) ? 1 : -1;
        });

        return $team_statistics;
    }

    public function getTeamStatistics($team_id, $round = null) {

        $team_statistics = array(
            'pts' => 0,
            'p' => 0,
            'w' => 0,
            'd' => 0,
            'l' => 0,
            'gf' => 0,
            'ga' => 0,
            'gd' => 0,
            'points_to_finish' => 18,
            'forecast' => 0
        );

        if (isset($team_id) && !empty($team_id)) {

            $select = $this->getDbTable()->select();
            $select->setIntegrityCheck(false);
            $select->from(array('s' => 'schedules'), array('team_owner_id', 'team_guest_id', 'team_owner_goal_cnt', 'team_guest_goal_cnt'))
                    ->where(' (s.team_owner_id = ?', $team_id)
                    ->orWhere('s.team_guest_id = ?)', $team_id)
                    ->where('s.is_played != ?', 0);

            if ($round) {
                $select->where('s.round = ?', $round)
                        ->orWhere('s.round = ?', $round);
            }

            $result = $this->getDbTable()->fetchAll($select)->toArray();

            if ($result) {

                foreach ($result as $key => $value) {
                    $team_statistics['p'] ++;

                    if ($value['team_owner_goal_cnt'] == $value['team_guest_goal_cnt']) {
                        $team_statistics['d'] ++;

                        $team_statistics['gf'] += $value['team_owner_goal_cnt'];
                        $team_statistics['ga'] += $value['team_guest_goal_cnt'];
                    } else if ($value['team_owner_goal_cnt'] > $value['team_guest_goal_cnt']) {
                        if ($team_id == $value['team_owner_id']) {
                            $team_statistics['w'] ++;
                            $team_statistics['gf'] += $value['team_owner_goal_cnt'];
                            $team_statistics['ga'] += $value['team_guest_goal_cnt'];
                        } else {
                            $team_statistics['l'] ++;
                            $team_statistics['gf'] += $value['team_guest_goal_cnt'];
                            $team_statistics['ga'] += $value['team_owner_goal_cnt'];
                        }
                    } else {
                        if ($team_id == $value['team_owner_id']) {
                            $team_statistics['l'] ++;
                            $team_statistics['gf'] += $value['team_owner_goal_cnt'];
                            $team_statistics['ga'] += $value['team_guest_goal_cnt'];
                        } else {
                            $team_statistics['w'] ++;
                            $team_statistics['gf'] += $value['team_guest_goal_cnt'];
                            $team_statistics['ga'] += $value['team_owner_goal_cnt'];
                        }
                    }
                }

                $team_statistics['gd'] = $team_statistics['gf'] - $team_statistics['ga'];
                $team_statistics['pts'] = self::POINTS_PER_WIN * $team_statistics['w'] + self::POINTS_PER_DRAWN * $team_statistics['d'];


                $team_statistics['points_to_finish'] = (6 - ($key + 1)) * self::POINTS_PER_WIN;
            }
        }
        return $team_statistics;
    }

}
