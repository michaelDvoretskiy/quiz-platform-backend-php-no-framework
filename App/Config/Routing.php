<?php
namespace App\Config;

use App\Utils\Response;

class Routing {
    static function dispatch() {
        $dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) {
            $r->addRoute('POST', '/login[/]', 'App\Controller\Auth::login');
            $r->addRoute('POST', '/check-token[/]', 'App\Controller\Auth::checkToken');
            $r->addRoute('GET', '/disciplines[/]', 'App\Controller\Tests::getDiscList');
            $r->addRoute('GET', '/test-list/{discId}[/]', 'App\Controller\Tests::getDiscTests');
            $r->addGroup('/test-one', function (\FastRoute\RouteCollector $r) {
                $r->addRoute('GET', '/read/{testId}[/]', 'App\Controller\Tests::readOne');
                $r->addRoute('POST', '/accept-answers/{testId}[/]', 'App\Controller\Tests::putAnswers');
                $r->addRoute('POST', '/finish/{testId}[/]', 'App\Controller\Tests::finishTest');
                $r->addRoute('POST', '/annul/{testId}[/]', 'App\Controller\Tests::annulTest');
            });
            $r->addGroup('/attending', function (\FastRoute\RouteCollector $r) {
                $r->addRoute('GET', '/current[/]', 'App\Controller\Attending::getMyAwailableAttendings');
                $r->addRoute('POST', '/current-set/{pairId}[/]', 'App\Controller\Attending::setMyCurrentAttending');
                $r->addRoute('GET', '/list[/]', 'App\Controller\Attending::getMyAttendingDiscList');
            });
            $r->addGroup('/attending-admin', function (\FastRoute\RouteCollector $r) {
                $r->addRoute('POST', '/approve/{attendingId}/{studentId}[/]', 'App\Controller\Attending::setApproved');
                $r->addRoute('GET', '/list[/]', 'App\Controller\Attending::getAllAttendings');
                $r->addRoute('GET', '/current[/]', 'App\Controller\Attending::getAllCurrentAttendings');
            });
        });

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Authorization, Content-Type,Accept, Origin");
            exit(0);
        }

        // Fetch method and URI from somewhere
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        $uri = preg_replace("/^\/app\/api/","",$uri);
        
        // Strip query string (?foo=bar) and decode URI
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);
        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);
        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                // ... 404 Not Found
                Response::status(404);
                return Response::json(['error' => '404 Not Found1']);
                break;
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                // ... 405 Method Not Allowed
                Response::status(404);
                return Response::json(['error' => '404 Not Found2']);
                break;
            case \FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                return $handler($vars);
                break;
        }
    } 
} 
