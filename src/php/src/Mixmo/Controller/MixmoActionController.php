<?php

namespace Mixmo\Controller;

use Mixmo\Service\QueryService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Package Mixmo\Controller
 */
class MixmoActionController
{

    public function indexAction(Request $request, Application $app)
    {
        // check user
        $userId = $request->get("id");

        $user = $app['session']->get('user');
        if ($userId != $user["id"]) {
            return new Response("NOK");
        }

        //@todo check mixmo is valid
        // getGameId
        $game = $app['session']->get('game');
        $gameId = $game["id"];
        $app['queryService']->getNodeQuery("/mixmo/" . $gameId . "/started/" . $user["email"]);

        if ($this->isEndGame($userId, $app)) {
            $this->updateVictoryCounter($app, $userId, $gameId);
            $app['queryService']->getNodeQuery("/game/" . $gameId . "/end/" . $user["email"]);
            return new Response("OK");
        }

        $this->processMixmo($userId, $gameId, $app);

        // call mixmo event
        $app['queryService']->getNodeQuery("/mixmo/" . $gameId);
        return new Response("OK");
    }

    public function gameStartedAction(Request $request, Application $app)
    {
        $game = $app['session']->get('game');
        $gameId = $game["id"];
        //@todo add protection
        $app['queryService']->getNodeQuery("/mixmo/" . $gameId);
        return new Response("OK");
    }


    public function gridsAction(Request $request, Application $app)
    {
        $playerId = $request->get("id");
        $gameId = $request->get("gameid");
        $query = '{"query":"{User(id:\"' . $playerId . '\"){letterStorage}}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($query);
        $resultDecoded = json_decode($result);
        $letters = $resultDecoded->data->User->letterStorage;
        $jsonLetters = json_encode($letters);
        $jsonLettersAsArray = json_decode($jsonLetters, true);

        $gridOk = false;
        $min_x = 10000;
        $min_y = 10000;
        $max_x = 0;
        $max_y = 0;

        foreach ($jsonLettersAsArray as $letter) {
            $x = $letter["x"];
            $y = $letter["y"];
            $letterArray[$x][$y] = $letter["letter"];

            $intx = intval($x);
            $inty = intval($y);
            $min_x = $min_x > $intx ? $intx : $min_x;
            $min_y = $min_y > $inty ? $inty : $min_y;
            $max_x = $max_x < $intx ? $intx : $max_x;
            $max_y = $max_y < $inty ? $inty : $max_y;
        }

        return $app['twig']->render('miniGrid.twig', array(
            "letters" => $letterArray,
            "max_x" => $max_x,
            "max_y" => $max_y,
            "min_x" => $min_x,
            "gridOk" => $gridOk,
            "min_y" => $min_y
        ));
    }

    protected function processMixmo($userId, $gameId, $app)
    {

        // Get next two letters for each player in game
        $query = '{"query":"{User(id:\"' . $userId . '\"){game(filter:{id:\"' . $gameId . '\"}){id, turnLeft, players{id, email, letters(filter:{isOpen: false}, first:2){id, value, isOpen, gangster}}}}}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($query);

        $resultDecoded = json_decode($result);
        $players = $resultDecoded->data->User->game->players;
        $turnLeft = $resultDecoded->data->User->game->turnLeft;

        $letterIds = array();
        $gangster = null;
        foreach ($players as $player) {
            foreach ($player->letters as $letter) {
                if ($letter->gangster) {
                    $gangster = array(
                        "player" => $player,
                        "letter" => $letter
                    );
                }
                array_push($letterIds, $letter->id);
            }
        }

        $letterQueries = "";
        foreach ($letterIds as $id) {
            $letterQueries .= 'letter' . $id . ' :updateLetter(id:\"' . $id . '\", isOpen: true){id,value}';
        }

        $turnLeft--;
        $turnLeftQuery = 'turnLeft :updateGame(id:\"' . $gameId . '\", turnLeft: ' . $turnLeft . '){id}';

        $mutations = '{"query":"mutation { ' . $letterQueries . ' ' . $turnLeftQuery . '}"}';
        $resultQueryUpdateLetter = $app['queryService']->getGraphQLDirectQuery($mutations);

        // Notify chat users/players when someone has got the gangster letter
        if ($gangster) {
            $data = array(
                "gameId" => $gameId,
                "command" => "gangster",
                "name" => "gansgter",
                "message" => "\$Gangster\$ en possession de " . $gangster["player"]->email . " !"
            );
            $app['queryService']->getPostNodeQuery('/chat', $data);
        }

    }

    protected function isEndGame($userId, $app)
    {

        $query = '{"query":"{User(id:\"' . $userId . '\"){letters(filter:{isOpen:false}){id}}}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($query);

        $resultDecoded = json_decode($result);
        $closedLetters = $resultDecoded->data->User->letters;
        if (count($closedLetters) == 0) {
            return true;
        }
        return false;
    }

    protected function updateVictoryCounter($app, $userId, $gameId)
    {
        $query = '{"query":"{User(id:\"' . $userId . '\"){victoryCounter}}"}';
        $result = json_decode($app['queryService']->getGraphQLDirectQuery($query));
        $victories = $result->data->User->victoryCounter;
        $victories++;

        $query = '{"query":"mutation{updateUser(id:\"' . $userId . '\", victoryCounter:' . $victories . '){id}}"}';
        $app['queryService']->getGraphQLDirectQuery($query);

        $turnLeftQuery = 'turnLeft :updateGame(id:\"' . $gameId . '\", turnLeft: -1){id}';
        $mutation = '{"query":"mutation { ' . $turnLeftQuery . '}"}';
        $app['queryService']->getGraphQLDirectQuery($mutation);
    }


    public function gridSaveAction(Request $request, Application $app)
    {
        $nickName = $request->get("name");
        $identifier = $request->get("identifier");
        $result = null;

        //Get the base-64 string from data
        $imgData = $request->get('gridImgData');
        $app["logger"]->debug(__METHOD__ . ' | gridImgData : ' . var_export($request->get('gridImgData'), true));


        //*
        $json = addslashes('"' . addslashes(json_encode($imgData, JSON_FORCE_OBJECT)) . '"');
        $query = '{"query":"mutation{updateUser(id:\"' . $identifier . '\", letterStorage: ' . $json . '){id}}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($query);
        //*/
        $app["logger"]->debug(__METHOD__ . ' | gridImgData(json_encode) : ' . $json);

        $resultCode = 200;

        /*
        if (null != $nickName && null != $imgData) {
            $filteredData = substr($imgData, strpos($imgData, ',') + 1);

            //Decode the string
            $unencodedData = base64_decode($filteredData);

            // Normalise filename
            $targetFileName = $this->buildNormalizedFileName($nickName);
            $targetFileExt = "png";
            //Save the image
            $basePath = "/data/src/www/images";
            file_put_contents($basePath . $targetFileName .'.' . $targetFileExt, $unencodedData);

            $result = array(
                "success" => true,
                "fileName" => $targetFileName .'.' . $targetFileExt
            );
            $resultCode = 200;
        } else {
            $result = array(
                    "success" => false,
            );
            $resultCode = 500;
        }
        //*/

        return $app->json($result, $resultCode);
    }

    /**
     * e.g.
     * '/etc/hosts/@Álix Ãxel likes - beer?!.jpg' --> etc_hosts_alix_axel_likes_beer.jpg
     */
    private function buildNormalizedFileName($sourceFileName, $slug = '-', $extra = null)
    {
        return $this->slugify($sourceFileName, $slug, $extra);
    }

    /**
     * @credits http://stackoverflow.com/a/5860054/646281
     */
    private function slugify($string, $slug = '-', $extra = null)
    {
        return strtolower(trim(preg_replace('~[^0-9a-z' . preg_quote($extra, '~') . ']+~i', $slug, $this->removeAccents($string)), $slug));
    }

    /**
     * @credits http://stackoverflow.com/a/5860054/646281
     */
    private function removeAccents($string) // normalizes (romanization) accented chars
    {
        if (strpos($string = htmlentities($string, ENT_QUOTES, 'UTF-8'), '&') !== false) {
            $string = html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i', '$1', $string), ENT_QUOTES, 'UTF-8');
        }

        return $string;
    }
}
