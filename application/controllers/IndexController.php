<?php

class IndexController extends Zend_Controller_Action {

    public function init() {
        parent::init();
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext
                ->addActionContext('nextweek', 'json')
                ->initContext('json');
    }

    public function indexAction() {
        $is_finish = false;

        $model_team = new Application_Model_Team();
        $teams = $model_team->fetchAll();

        $model_schedule = new Application_Model_Schedule();
        $round = $model_schedule->getCurrentRound();

        if (empty($round)) {
            //генерация расписания игр. согласно полученному расписанию и будет вестись подсчёт результатов
            $model_schedule->generateSchedule(); 
        }

        // получение данных для заполнения таблицы с результатами
        $team_statistics = $model_schedule->getTeamsStatistics($teams);

        $total_rounds = 2 * count($teams) - 2;

        if ($round >= $total_rounds) {
            $is_finish = true;
        }

        $this->view->teams = $teams;
        $this->view->round = $round;
        $this->view->team_statistics = $team_statistics;
        $this->view->is_finish = $is_finish;
    }

    public function nextweekAction() {
        $data = $this->getRequest()->getPost();

        if (isset($data['nextweek'])) {
            $model_team = new Application_Model_Team();
            $teams = $model_team->fetchAll();

            $model_schedule = new Application_Model_Schedule();
            $round = $model_schedule->getCurrentRound();
            $next_round = $round + 1;
            $total_rounds = 2 * count($teams) - 2;

            // в зависимости от выбранной кнопки - либо выводим следующий шаг, либо проигрываем все матчи
            if ($data['nextweek'] == 'fast') {
                $this->getResultsForRestRounds($total_rounds, $teams);
            } else if ($data['nextweek'] == 'slow') {
                $this->getResultsForRestRounds($next_round, $teams);
            }
        } else {
            $this->view->content = '';
        }
    }

    public function getResultsForRestRounds($finish_round, $teams) {
        $model_schedule = new Application_Model_Schedule();

        $round = $model_schedule->getCurrentRound();
        $next_round = $round + 1;
        $total_rounds = 2 * count($teams) - 2;

        $content = '';
        do {
            $is_finish = false;
            if ($next_round <= $total_rounds) {
                //генерация счёта
                $model_schedule->generateGameResults($next_round);

                $team_statistics = $model_schedule->getTeamsStatistics($teams);

                // данные для заполнения матчей этой недели
                $week_results = $model_schedule->getWeekResults($next_round);

                if ($next_round >= $total_rounds) {
                    $is_finish = true;
                }

                $this->view->teams = $teams;
                $this->view->round = $next_round;
                $this->view->team_statistics = $team_statistics;
                $this->view->week_results = $week_results;
                $this->view->is_finish = $is_finish;

                $content = $content . $this->view->render('index/template/index.phtml');
            } else {
                $this->view->is_finish = true;
            }

            $this->view->content = $content;
            unset($this->view->teams);
            unset($this->view->round);
            unset($this->view->team_statistics);
            unset($this->view->week_results);


            $next_round ++;
        } while ($next_round <= $finish_round);
    }

}
