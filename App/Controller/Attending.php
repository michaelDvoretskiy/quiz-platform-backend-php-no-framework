<?php
namespace App\Controller;

use App\Middleware\DataGetter;
use App\Middleware\RightsChecker;
use App\Model\User;
use App\Utils\Request;
use App\Utils\Response;

class Attending {
    static function getMyAwailableAttendings() {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        $discipline = RightsChecker::getMyAvailableAttendings($userData['id']);

        if ($discipline) {
            $discipline['thumb'] = DataGetter::getThombnailPath($discipline['disc_id']);
        }
        return Response::json($discipline);
    }

    static function setMyCurrentAttending($params = []) {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }

        $pairId = $params['pairId'];
        $discipline = RightsChecker::getMyAvailableAttendings($userData['id']);
        if (!$discipline || $discipline['id'] != $pairId) {
            Response::status(403);
            return Response::json(['error' => 'This attending is forbidden']);
        }

        $attendingData = Request::getBody();
        DataGetter::setMyAttending($pairId, $discipline['student_id'], $attendingData['attended']);
        return Response::json([
            'post_title' => $discipline['post_title'],
            'description' => $discipline['description']
        ]);
    }

    static function getMyAttendingDiscList() {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        $attendings = RightsChecker::getMyAttendingDiscList($userData['id']);
        return Response::json($attendings);
    }

    static function getAllAttendings() {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        if (!RightsChecker::isUserAdmin($userData['id'])) {
            Response::status(403);
            return Response::json(['error' => 'Forbidden']);
        }
        $attendings = DataGetter::getAllAttendings();
        return Response::json($attendings);
    }

    static function getAllCurrentAttendings() {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        if (!RightsChecker::isUserAdmin($userData['id'])) {
            Response::status(403);
            return Response::json(['error' => 'Forbidden']);
        }

        $disciplines = DataGetter::getAllCurrentAttendings();

        return Response::json($disciplines);
    }

    static function setApproved($params = []) {
        $token = Request::getToken();
        $userData = User::checkToken($token);
        if (!$userData) {
            Response::status(401);
            return Response::json(['error' => 'authentication failed']);
        }
        if (!RightsChecker::isUserAdmin($userData['id'])) {
            Response::status(403);
            return Response::json(['error' => 'Forbidden']);
        }

        $attendingId = $params['attendingId'];
        $atudentId = $params['studentId'];
        $attendingData = Request::getBody();
        $res = DataGetter::ApproveAttending($attendingId, $atudentId, $attendingData['type']);

        return Response::json($res);
    }
}
