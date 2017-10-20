<?php

namespace Mixmo\Controller;

use Mixmo\Service\QueryService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Package Mixmo\Controller
 */
class ChatActionController
{
    private static $COMMANDS = [
        "/players" => "getPlayers"
    ];

    public function indexAction(Request $request, Application $app)
    {

        $gameId = $request->get("gameId");
        $userId = $request->get("id");
        $type = $request->get("type");
        $name = $request->get("name");
        $message = $request->get("message");

        if(array_key_exists($message, SELF::$COMMANDS)){
            $type = "command";
            $callback = SELF::$COMMANDS[$message];
            $message = $this->$callback($app, $gameId);
        }

        $data = array(
            "gameId" => urlencode($gameId),
            "userId" => urlencode($userId),
            "type" => urlencode($type),
            "name" => urlencode($name),
            "message" => urlencode(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'))
        );

        $app['queryService']->getPostNodeQuery('/chat', $data);
        return new Response("OK");
    }


    protected function getPlayers($app, $gameId){
        $query = '{"query":"{Game(id:\"'.$gameId.'\"){players{email}}}"}';
        $result = json_decode($app['queryService']->getGraphQLDirectQuery($query));
        $playerNames=array();
        foreach ($result->data->Game->players as $player){
            array_push($playerNames, $player->email);
        }

        return join(" - ", $playerNames);
    }
}