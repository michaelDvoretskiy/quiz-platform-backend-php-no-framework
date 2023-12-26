<?php
namespace App\Controller;

use App\Middleware\DataGetter;
use App\Middleware\RightsChecker;
use App\Model\User;
use App\Utils\Request;
use App\Utils\Response;

class Tests {
    static function getDiscList() {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        $res = RightsChecker::getUserDisciplinesWithTests($userData['id']);
        $disciplines = [];
        foreach($res as $key=>$elem) {
            $disciplines[$key] = json_decode(json_encode($elem),true);
            $disciplines[$key]['thumb'] = DataGetter::getThombnailPath($elem->id);
        }
        return Response::json($disciplines);
    }

    static function getDiscTests($params = []) {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        $dysc = RightsChecker::getOneDisciplineById($userData['id'], $params['discId']);
        if (!$dysc) {
            Response::status(404);
            return Response::json(['error' => 'Dyscipline is not found']);
        }
        $tests = RightsChecker::getUserTestsByDisc($userData['id'], $params['discId']);
        return Response::json([
            'dysc' => $dysc, 'tests' => $tests
        ]);
    }

    static function readOne($params = []) {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        $testId = $params['testId'];
        $test = RightsChecker::getTestHead($userData['id'], $testId);
        if (!$test) {
            Response::status(403);
            return Response::json(['error' => 'The test is forbidden']);
        }
        $content = DataGetter::getTestContent($userData['id'], $testId);
        if ($test->res_status == 0) {
            $test = RightsChecker::getTestHead($userData['id'], $testId);
        }
        return Response::json(['test' => $test, 'content' => $content]);
    }

    static function putAnswers($params = []) {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        $testId = $params['testId'];
        $answersData = Request::getBody();
        $answersAndRes = RightsChecker::getTestUserAnswers($userData['id'], $testId);
        if (!$answersAndRes) {
            Response::status(403);
            return Response::json(['error' => 'The test is forbidden']);
        }
        $answers = DataGetter::mergeTestAnswers($answersAndRes, $answersData['answers']);
        DataGetter::updateAnswers($userData['id'], $testId, $answers);
        $answersAndRes = RightsChecker::getTestUserAnswers($userData['id'], $testId);
        return Response::json(['answers' => $answersAndRes['answers']]);
    }

    static function finishTest($params = []) {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        $testId = $params['testId'];
        $test = RightsChecker::getTestForFinishing($userData['id'], $testId);
        if (!$test) {
            Response::status(403);
            return Response::json(['error' => 'The test is forbidden']);
        }
        $res = DataGetter::finishTest($test);
        return Response::json(['result' => $res]);
    }

    static function annulTest($params = []) {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        $testId = $params['testId'];
        $test = RightsChecker::getTestForFinishing($userData['id'], $testId);
        if (!$test) {
            Response::status(403);
            return Response::json(['error' => 'The test is forbidden']);
        }
        $res = DataGetter::annulTest($test);
        return Response::json($res);
    }
}
