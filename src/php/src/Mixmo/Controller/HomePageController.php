<?php

namespace Mixmo\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Mixmo\Service\QueryService;

/**
 * @Package Mixmo\Controller
 */
class HomePageController
{

    public function indexAction(Request $request, Application $app)
    {
        $sessionUser = $app['session']->get('user');
        if (!empty($sessionUser)) {
            $user = $app['session']->get('user');
            $games = json_decode($app['queryService']->getNodeQuery('/games'),true);
            return $app['twig']->render('home.twig', array("user" => $user['id'], "games" => $games["data"]["allGames"]));
        }

        return $app->redirect('/login');
    }

    public function createRoomAction(Request $request, Application $app)
    {

        $roomName = $request->get("name");
        if (empty($roomName)) {
            return new Response("NOK");
        }

        $query = '{"query":"mutation {createGame(isOpen:true, label: \"' . $roomName . '\"){id}}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($query);
        $resultDecoded = json_decode($result);

        //@todo handle errors
        $id = $resultDecoded->data->createGame->id;
        return new Response($id);
    }
}
