<?php
namespace App\Middleware;

use Illuminate\Database\Capsule\Manager as Data;

class RightsChecker {

    const ADMINS = [1];

    public function userByTokenCanPassTest($token, $testId) {
        $results = Data::select("select id, user_email from edu_users 
            where id in(select user_id from ng_user_token where token = :token)", [
            'token' => $token
        ]);
        if (!$results) {
            return false;
        }
        return [
            'username' => $results[0]->user_email
        ];

    }

    public static function getUserDisciplinesWithTests($userId) {
        $sql = "select id, post_title title
            from edu_posts 
            where id in(
            SELECT m.meta_value 
            FROM edu_posts p inner join edu_postmeta m on p.id = m.post_id
            inner join ng_tests t on p.id = t.work_id
            where p.post_type = 'acme_practical_work' and m.meta_key = 'practical_work_discipline_id' and t.act_flg = 1
            )
            and id in(
            select disc_id
            from marks_list 
            where is_active = 1 and 
            (
            teacher_id in (select p.id from edu_posts p inner join edu_postmeta m on p.id = m.post_id
            where p.post_type = 'chnu_marks_teachers' and m.meta_key = 'chnu_teacher_user_id' and m.meta_value = $userId) 
            or 
            id in(select list_id from marks_students s inner join marks_list_students ls on s.id = ls.stud_id where user_id = $userId ) )
            or $userId in (".implode(",", RightsChecker::ADMINS).")
            )
            order by post_title";
        $results = Data::select($sql);
        return $results;
    }

    public static function getOneDisciplineById($userId, $discId) {
        $sql = "select id, post_title title
            from edu_posts 
            where id = $discId and post_type = 'acme_discipline' 
            and id in(
            select disc_id
            from marks_list 
            where is_active = 1 and 
            (
            teacher_id in (select p.id from edu_posts p inner join edu_postmeta m on p.id = m.post_id
            where p.post_type = 'chnu_marks_teachers' and m.meta_key = 'chnu_teacher_user_id' and m.meta_value = $userId) 
            or 
            id in(select list_id from marks_students s inner join marks_list_students ls on s.id = ls.stud_id where user_id = $userId ) )
            or $userId in (".implode(",", RightsChecker::ADMINS).")
            )";
        $results = Data::select($sql);
        return $results[0];
    }

    public static function getUserTestsByDisc($userId, $discId) {
        $sql = "SELECT t.id, t.name, t.description, t.type, t.min_result, t.points, r.result, r.finish_status,
                case when r.res_details is null then 0 when r.res_details is not null and r.finish_status is null then 1 else 2 end res_status
                FROM edu_posts p inner join edu_postmeta m on p.id = m.post_id
                inner join ng_tests t on p.id = t.work_id
                left join (select test_id, result, finish_status, res_details from ng_test_results where user_id = $userId) r on t.id = r.test_id
                where p.post_type = 'acme_practical_work' and m.meta_key = 'practical_work_discipline_id' and t.act_flg = 1
                and m.meta_value in(
	                select disc_id
                    from marks_list 
                    where disc_id = $discId and is_active = 1 and 
                    (
                    teacher_id in (select p.id from edu_posts p inner join edu_postmeta m on p.id = m.post_id
                    where p.post_type = 'chnu_marks_teachers' and m.meta_key = 'chnu_teacher_user_id' and m.meta_value = $userId) 
                    or 
                    id in(select list_id from marks_students s inner join marks_list_students ls on s.id = ls.stud_id where user_id = $userId )
                    or $userId in (".implode(",", RightsChecker::ADMINS).") 
                    )
                )
                order by t.ord";
        $results = Data::select($sql);
        return $results;
    }

    public static function getTestHead($userId, $testId) {
        $sql = "SELECT t.id, t.name, t.description, t.type, t.min_result, t.points, r.result, 
                r.finish_status, r.begin_date, t.duration, r.try_number, m.meta_value disc_code,
                case when r.res_details is null then 0 when r.res_details is not null and r.finish_status is null then 1 else 2 end res_status 
                FROM edu_posts p inner join edu_postmeta m on p.id = m.post_id
                inner join ng_tests t on p.id = t.work_id
                left join (select test_id, result, finish_status, res_details, begin_date, try_number from ng_test_results where user_id = $userId) r on t.id = r.test_id
                where t.id = $testId and p.post_type = 'acme_practical_work' and m.meta_key = 'practical_work_discipline_id' and t.act_flg = 1
                and m.meta_value in(
                    select disc_id
                    from marks_list 
                    where is_active = 1 and 
                    (
                        teacher_id in (select p.id from edu_posts p inner join edu_postmeta m on p.id = m.post_id
                                       where p.post_type = 'chnu_marks_teachers' and m.meta_key = 'chnu_teacher_user_id' and m.meta_value = $userId) 
                        or 
                        id in(select list_id from marks_students s inner join marks_list_students ls on s.id = ls.stud_id where user_id = $userId )
                        or $userId in (".implode(",", RightsChecker::ADMINS).") 
                    )
                )";
        $results = Data::select($sql);
        if (!$results) {
            return false;
        }
        return $results[0];
    }

    public static function getTestUserAnswers($userId, $testId) {
        $sql = "select ifnull(answers, '') answers, res_details res from ng_test_results 
            where test_id = :testId and user_id = :userId";
        $results = Data::select($sql, [
            'testId' => $testId,
            'userId' => $userId,
        ]);
        if (!$results) {
            return false;
        }
        if (!$results[0]->answers) {
            $ans = [];
        } else {
            $ans = unserialize($results[0]->answers);
        }
        if (!$results[0]->res) {
            $res = [];
        } else {
            $res = unserialize($results[0]->res);
        }
        return [
            'answers' => $ans,
            'res' => $res
        ];
    }

    public static function getTestForFinishing($userId, $testId) {
        $sql = "select r.id rid, ifnull(r.answers, '') answers, r.res_details res,  r.finish_date, r.result, r.finish_status,
            t.work_id, t.type, t.min_result, t.points, t.duration, r.begin_date, t.max_tries, r.try_number
            from ng_test_results r inner join ng_tests t on r.test_id = t.id
            where test_id = :testId and user_id = :userId ";
        $results = Data::select($sql, [
            'testId' => $testId,
            'userId' => $userId,
        ]);
        if (!$results) {
            return false;
        }
        return json_decode(json_encode($results[0]),true);
    }

    public static function getMyAvailableAttendings($userId) {
        $sql = "select distinct p.id, l.disc_id, d.post_title, p.description, s.id student_id,
            ifnull(pa.status, 0) status
            from ng_pairs p inner join marks_list l on p.list_id = l.id 
            inner join marks_list_students ls on l.id = ls.list_id
            inner join marks_students s on ls.stud_id = s.id
            inner join edu_posts d on l.disc_id = d.id
            left join ng_pairs_attending pa on p.id=pa.pair_id and s.id = pa.student_id
            where now() between begin_date and end_date and l.is_active = 1 and s.user_id = :userId";
        $results = Data::select($sql, [
            'userId' => $userId
        ]);
        if (!$results) {
            return false;
        }
        return json_decode(json_encode($results[0]),true);
    }

    public static function getMyAttendingDiscList($userId) {
        $sql = "select l.disc_id, d.post_title, p.description, ifnull(pa.status, 0) s
            from ng_pairs p inner join marks_list l on p.list_id = l.id 
            inner join marks_list_students ls on l.id = ls.list_id
            inner join marks_students s on ls.stud_id = s.id
            inner join edu_posts d on l.disc_id = d.id
            left join ng_pairs_attending pa on p.id=pa.pair_id and s.id = pa.student_id
            where l.is_active = 1
            and s.user_id = :userId
            order by d.post_title, p.begin_date";
        $results = Data::select($sql, [
            'userId' => $userId
        ]);
        $attendings = []; $attending = [];
        $old_disc_id = 0;
        foreach ($results as $row) {
            if ($row->disc_id != $old_disc_id) {
                if ($old_disc_id !=0 ) {
                    $attendings[] = $attending;
                }
                $attending = [
                    'disc_id' => $row->disc_id,
                    'post_title' => $row->post_title,
                    'thumb' => DataGetter::getThombnailPath($row->disc_id)
                ];
            }
            $old_disc_id = $row->disc_id;
            $attending['pairs'][] = [
                'description' => $row->description,
                'status' => $row->s
            ];
        }
        if ($old_disc_id != 0 ) {
            $attendings[] = $attending;
        }
        return $attendings;
    }

    public static function isUserAdmin($userId) {
        $sql = "SELECT count(id) c FROM `ng_user_roles` where user_id = :userId and role = 'ADMIN'";
        $results = Data::select($sql, [
            'userId' => $userId
        ]);
        if ($results[0]->c > 0) {
            return true;
        }
        return false;
    }
}
