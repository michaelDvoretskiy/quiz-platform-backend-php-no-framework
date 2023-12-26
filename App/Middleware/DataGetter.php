<?php
namespace App\Middleware;

use Illuminate\Database\Capsule\Manager as Data;

class DataGetter {
    const PICT_BASE_PATH = '/wp-content/uploads/';

    public static function getUserPersonName($userId) {
        $results = Data::select("SELECT name FROM marks_students where user_id = :id", [
                'id' => $userId
            ]
        );
        if (!$results) {
            return '';
        }
        return $results[0]->name;
    }

    public static function getThombnailPath($postId) {
        $results = Data::select("SELECT wp.ID, wpm2.meta_value
            FROM edu_posts wp
                INNER JOIN edu_postmeta wpm
                    ON (wp.ID = wpm.post_id AND wpm.meta_key = '_thumbnail_id')
                INNER JOIN edu_postmeta wpm2
                    ON (wpm.meta_value = wpm2.post_id AND wpm2.meta_key = '_wp_attached_file')
            where wp.id = :id", [
                'id' => $postId
            ]
        );
        if (!$results) {
            return false;
        }
        return self::PICT_BASE_PATH . $results[0]->meta_value;
    }

    public static function getTestContent($userId, $testId) {
        $results = self::getReadyTestContent($userId, $testId);
        if (!$results || !$results[0]->res_details) {
            self::setTestContent($userId, $testId);
            $results = self::getReadyTestContent($userId, $testId);
        }
        $testCurrentState = unserialize($results[0]->res_details);
        $testCurrentState = self::prepareTestContent($testCurrentState);
        $testCurrentState = self::getTestQuestions($testId, $testCurrentState);

        $testCurrentAnswers = false;
        if ($results[0]->answers) {
            $testCurrentAnswers = unserialize($results[0]->answers);
        }

        $testCheckedAnswers = false;
        if ($results[0]->checked_answers) {
            $testCheckedAnswers = unserialize($results[0]->checked_answers);
        }

        return [
            'test' => $testCurrentState,
            'answers' => $testCurrentAnswers,
            'checked_answers' =>$testCheckedAnswers
        ];
    }

    public static function mergeTestAnswers($answersAndRes, $newAnswers) {
        $testContent = $answersAndRes['res'];
        $currentAnswers = $answersAndRes['answers'];
        foreach ($newAnswers as $key=>$answer) {
            $currentAnswers[$key] = $answer;
        }
        return self::excludeRedunduntAnswewrs($testContent, $currentAnswers);
    }

    public static function updateAnswers($userId, $testId, $answers) {
        Data::table("ng_test_results")
            ->where('test_id', $testId)
            ->where('user_id', $userId)
            ->update([
                'answers' => serialize($answers)
            ]);
    }

    private static function excludeRedunduntAnswewrs($testContent, $currentAnswers) {
        foreach($currentAnswers as $key=>$curAnswerArr) {
            foreach($curAnswerArr as $curAnswer) {
                if (!isset($testContent[$key]) || !isset($testContent[$key]['answers'][$curAnswer])) {
                    unset($currentAnswers[$key]);
                }
            }
        }
        return $currentAnswers;
    }

    private static function getTestQuestions($testId, $testCurrentState) {
        $sql = "select q.id q_id, q.name q_name, a.id a_id, a.name a_name, a.right_flg  
                from ng_test_questions q inner join ng_test_answers a on q.id = a.quest_id 
                where test_id = :test_id";
        $results = Data::select($sql, ['test_id' => $testId]);
        $allQuestions = [];
        foreach($results as $row) {
            $allQuestions[$row->q_id]['name'] = $row->q_name;
            $allQuestions[$row->q_id]['answers'][$row->a_id] = $row->a_name;
        }
        foreach($testCurrentState as $key=>$elem) {
            $testCurrentState[$key]['question']['name'] = $allQuestions[$elem['question']['id']]['name'];
            foreach($testCurrentState[$key]['answers'] as $ansKey=>$answer) {
                $testCurrentState[$key]['answers'][$ansKey]['name'] = $allQuestions[$elem['question']['id']]['answers'][$answer['id']];
            }
        }
        return $testCurrentState;
    }

    private static function prepareTestContent($testContent) {
        foreach($testContent as $key=>$question) {
            if (count($testContent[$key]['right_answers']) > 1) {
                $testContent[$key]['multiple_answer'] = 1;
            } else {
                $testContent[$key]['multiple_answer'] = 0;
            }
            unset($testContent[$key]['right_answers']);
        }
        return $testContent;
    }

    private static function setTestContent($userId, $testId) {
        $sql = "select q.q_number, q.q_id, a.id a_id, a.right_flg flg
            from
            (select q.q_number, (select id from ng_test_questions where test_id = $testId and q_number = q.q_number order by rand() limit 0, 1) q_id
            from (select distinct q_number from ng_test_questions where test_id = $testId) q) q inner join ng_test_answers a on q.q_id = a.quest_id
            order by q.q_number, rand()";
        $results = Data::select($sql, ['test_id' => $testId]);
        $content = [];
        foreach ($results as $row) {
            $content[$row->q_number]['question'] = ['id' => $row->q_id];
            $content[$row->q_number]['answers'][$row->a_id] = ['id' => $row->a_id];
            if ($row->flg == 1) {
                $content[$row->q_number]['right_answers'][] = $row->a_id;
            }
        }

        $sql = "select ifnull(max(try_number),0)+1 try_number from ng_test_results_hystory where test_id = :test_id and user_id = :user_id";
        $results = Data::select($sql, [
            'test_id' => $testId,
            'user_id' => $userId
        ]);
        $tryNumber = 1;
        if ($results && $results[0]->try_number) {
            $tryNumber = $results[0]->try_number;
        }

        Data::table("ng_test_results")->upsert(
            [
                'user_id' => $userId,
                'test_id' => $testId,
                'begin_date' => new \DateTime(),
                'res_details' => serialize($content),
                'try_number' => $tryNumber
            ],
            ['user_id', 'test_id'], ['begin_date', 'res_details', 'try_number']
        );
    }

    private static function getReadyTestContent($userId, $testId) {
        $sql = "select ifnull(res_details,'') res_details, ifnull(answers, '') answers, 
            ifnull(checked_answers, '') checked_answers 
            from ng_test_results where test_id = :testId and user_id = :userId";
        $results = Data::select($sql, [
            'testId' => $testId,
            'userId' => $userId
        ]);
        return $results;
    }

    public static function finishTest($test) {
        if (!is_null($test['finish_date']) || !is_null($test['result']) || !is_null($test['finish_status'])) {
            return ['error' => 'This test is already finished !'];
        }
        $beginTime = new \DateTime($test['begin_date']);
        $finishTime = new \DateTime();
        $diff = date_diff($beginTime,$finishTime);
        $test['finish_date'] = $finishTime->format('Y-m-d h:i:s');
        $minutes = ($diff->days * 24 + $diff->h) * 60 + $diff->i;
        if ($minutes > $test['duration']) {
            $test['finish_status'] = 1; //тест просрочен
            $test['result'] = 0;
        } else {
            $answers = unserialize($test['answers']);
            $res = unserialize($test['res']);
            $resultQuerstions = 0;
            $allQuestions = 0;
            foreach ($res as $key=>$question) {
                $test['checked_answer'][$key] = 0;
                if(isset($answers[$key]) && !is_null($answers[$key])) {
                    $diff1 = array_diff($question['right_answers'], $answers[$key]);
                    $diff2 = array_diff($answers[$key], $question['right_answers']);
                    if (!$diff1 && !$diff2) {
                        $test['checked_answer'][$key] = 1;
                        $resultQuerstions++;
                    }
                }
                $allQuestions++;
            }

            //пороговый тест
            if ($test['type'] == 1) {
                if ($resultQuerstions >= $test['min_result']) {
                    $test['finish_status'] = 2; //тест пройден
                    $test['result'] = $test['points'];
                } else {
                    $test['finish_status'] = 3; //тест не пройден
                    $test['result'] = 0;
                }
            }

            //накопительный тест
            if ($test['type'] == 2) {
                if ($resultQuerstions >= $test['min_result']) {
                    $points = round( ($resultQuerstions / $allQuestions) * $test['points'], 0 );
                    $test['finish_status'] = 2; //тест пройден
                    $test['result'] = $points;
                } else {
                    $test['finish_status'] = 3; //тест не пройден
                    $test['result'] = 0;
                }
            }
        }
        Data::table("ng_test_results")
            ->where('id', $test['rid'])
            ->update([
                'finish_status' => $test['finish_status'], //просрочен
                'result' => $test['result'],
                'finish_date' => $test['finish_date'],
                'checked_answers' => serialize($test['checked_answer'])
            ]);

        unset($test['answers']);
        unset($test['res']);

        //добавить запись баллов в нужную работу, если тест пройден
        if ($test['finish_status'] == 2) {

        }

        return $test;
    }

    public static function annulTest($test) {
        if (is_null($test['finish_date']) || is_null($test['result']) || is_null($test['finish_status'])) {
            return ['error' => 'This test is not finished !'];
        }
        if ($test['try_number'] >= $test['max_tries']) {
            return ['error' => 'This was the last try !'];
        }
        Data::connection()
            ->statement('insert into ng_test_results_hystory(`test_id`, `user_id`, `begin_date`, 
                    `finish_date`, `result`, `res_details`, `finish_status`, `answers`, `try_number`, 
                    `checked_answers`) 
                    select `test_id`, `user_id`, `begin_date`, 
                    `finish_date`, `result`, `res_details`, `finish_status`, `answers`, `try_number`, 
                    `checked_answers` from ng_test_results where id = :id', [
                'id' => $test['rid']
            ]);
        Data::connection()
            ->statement('delete from ng_test_results where id = :id', [
                'id' => $test['rid']
            ]);
        return ['result' => 'annuled successfully'];
    }

    public static function setMyAttending($pairId, $studentId, $attendingFlg = true) {
        if ($attendingFlg) {
            Data::table("ng_pairs_attending")->upsert(
                [
                    'pair_id' => $pairId,
                    'student_id' => $studentId,
                    'status' => 1
                ],
                ['user_id', 'student_id'], ['pair_id', 'student_id', 'status']
            );
        } else {
            Data::connection()
                ->statement('delete from ng_pairs_attending where pair_id=:pair_id and student_id=:student_id', [
                    'pair_id' => $pairId,
                    'student_id' => $studentId
                ]);
        }
    }

    public static function getAllAttendings() {
        $sql = "select l.disc_id, d.post_title, s.id sid, s.name sname, p.description, ifnull(pa.status, 0) s 
            from ng_pairs p inner join marks_list l on p.list_id = l.id 
            inner join marks_list_students ls on l.id = ls.list_id
            inner join marks_students s on ls.stud_id = s.id
            inner join edu_posts d on l.disc_id = d.id
            left join ng_pairs_attending pa on p.id=pa.pair_id and s.id = pa.student_id
            where l.is_active = 1
            order by d.post_title, s.name, p.begin_date";
        $results = Data::select($sql);
        $disciplines = []; $discipline = []; $students = []; $student = [];
        $old_disc_id = 0; $old_student_id = 0; $allPairs = 0; $attendedPairs = 0;
        foreach ($results as $row) {
            if ($row->sid != $old_student_id) {
                if ($old_student_id != 0 ) {
                    $student['all_pairs'] = $allPairs;
                    $student['attended_pairs'] = $attendedPairs;
                    $students[] = $student;
                }
                $student = [
                    'sid' => $row->sid,
                    'sname' => $row->sname
                ];
                $allPairs = 0; $attendedPairs = 0;
            }
            if ($row->disc_id != $old_disc_id) {
                if ($old_disc_id !=0 ) {
                    $discipline['students'] = $students;
                    $disciplines[] = $discipline;
                }
                $discipline = [
                    'disc_id' => $row->disc_id,
                    'post_title' => $row->post_title,
                    'thumb' => DataGetter::getThombnailPath($row->disc_id),
                    'students' => []
                ];
                $students = [];
            }
            $old_disc_id = $row->disc_id;
            $old_student_id = $row->sid;
            $allPairs++;
            if ($row->s == 2) {
                $attendedPairs++;
            }
            $student['pairs'][] = [
                'description' => $row->description,
                'status' => $row->s
            ];
        }
        if ($old_student_id !=0 ) {
            $student['all_pairs'] = $allPairs;
            $student['attended_pairs'] = $attendedPairs;
            $students[] = $student;
        }
        if ($old_disc_id !=0 ) {
            $discipline['students'] = $students;
            $disciplines[] = $discipline;
        }
        return $disciplines;
    }

    public static function getAllCurrentAttendings() {
        $sql = "select distinct p.id, l.disc_id, d.post_title, p.id pid, p.description, s.id sid, s.name sname,
            ifnull(pa.status, 0) s, ifnull(pa.id,0) attending_id
            from ng_pairs p inner join marks_list l on p.list_id = l.id 
            inner join marks_list_students ls on l.id = ls.list_id
            inner join marks_students s on ls.stud_id = s.id
            inner join edu_posts d on l.disc_id = d.id
            left join ng_pairs_attending pa on p.id=pa.pair_id and s.id = pa.student_id
            where now() between begin_date and end_date and l.is_active = 1
            order by d.post_title, s.name";
        $results = Data::select($sql);


        $attendings = []; $attending = [];
        $old_id = 0;
        foreach ($results as $row) {
            if ($row->disc_id != $old_id) {
                if ($old_id !=0 ) {
                    $attendings[] = $attending;
                }
                $attending = [
                    'disc_id' => $row->disc_id,
                    'post_title' => $row->post_title,
                    'thumb' => DataGetter::getThombnailPath($row->disc_id),
                    'description' => $row->description,

                ];
            }
            $old_id = $row->disc_id;
            $attending['students'][] = [
                'student' => $row->sname,
                'sid' => $row->sid,
                'pid' => $row->pid,
                'status' => $row->s,
                'attending_id' => $row->attending_id
            ];
        }
        if ($old_id != 0 ) {
            $attendings[] = $attending;
        }
        return $attendings;

//        $disciplines = []; $discipline = []; $students = []; $student = [];
//        $old_disc_id = 0; $old_student_id = 0;
//        foreach ($results as $row) {
//            if ($row->sid != $old_student_id) {
//                if ($old_student_id != 0 ) {
//                    $students[] = $student;
//                }
//                $student = [
//                    'sid' => $row->sid,
//                    'sname' => $row->sname
//                ];
//                $allPairs = 0; $attendedPairs = 0;
//            }
//            if ($row->disc_id != $old_disc_id) {
//                if ($old_disc_id !=0 ) {
//                    $discipline['students'] = $students;
//                    $disciplines[] = $discipline;
//                }
//                $discipline = [
//                    'disc_id' => $row->disc_id,
//                    'post_title' => $row->post_title,
//                    'thumb' => DataGetter::getThombnailPath($row->disc_id),
//                    'students' => []
//                ];
//                $students = [];
//            }
//            $old_disc_id = $row->disc_id;
//            $old_student_id = $row->sid;
//            $student['pairs'][] = [
//                'description' => $row->description,
//                'status' => $row->s
//            ];
//        }
//        if ($old_student_id !=0 ) {
//            $student['all_pairs'] = $allPairs;
//            $student['attended_pairs'] = $attendedPairs;
//            $students[] = $student;
//        }
//        if ($old_disc_id !=0 ) {
//            $discipline['students'] = $students;
//            $disciplines[] = $discipline;
//        }
//        return $disciplines;
    }

    public static function ApproveAttending($pairId, $studentId, $type) {
        if ($type == 1) {
            Data::table("ng_pairs_attending")->upsert(
                [
                    'pair_id' => $pairId,
                    'student_id' => $studentId,
                    'status' => 2
                ],
                ['pair_id', 'student_id'], ['pair_id', 'student_id', 'status']
            );
        } elseif ($type == 2) {
            Data::table("ng_pairs_attending")->upsert(
                [
                    'pair_id' => $pairId,
                    'student_id' => $studentId,
                    'status' => 3
                ],
                ['pair_id', 'student_id'], ['pair_id', 'student_id', 'status']
            );
        }
    }
}
