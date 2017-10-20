<?php

namespace Mixmo\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Mixmo\Service\QueryService;
use Mixmo\Service\MixmoService;

/**
 * @Package Mixmo\Controller
 */
class MixmoPageController
{

    public function indexAction(Request $request, Application $app)
    {

        $user = $app['session']->get('user');
        $userId = $user["id"];
        $gameId = $request->get("id");
        $app['session']->set('game', array('id' => $gameId));


        $query = '{"query":"{Game(id:\"' . $gameId . '\"){id,label,isOpen,turns,turnLeft,playerList: players{id,email},players(filter:{id:\"' . $userId . '\"}){email,letters(filter:{isOpen: true}){id,value,joker,gangster}}}}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($query);

        // join game query
        $resultDecoded = json_decode($result);
        $isOpen = $resultDecoded->data->Game->isOpen;
        $playerList = $resultDecoded->data->Game->playerList;

        $userAlredyInRoom = false;
        // prevent foreach error
        if (!is_array($playerList) && !is_object($playerList)) {
            $app['session']->remove('game');
            return $app->redirect('/');
        }
        foreach ($playerList as $player) {
            if ($player->id == $userId) {
                $userAlredyInRoom = true;
            }
        }
        if (!$userAlredyInRoom) {
            if ((bool)$isOpen && count($playerList) < 5) {
                $this->removeAllLetterFromPlayer($userId, $app);
                $queryAddPlayerToGame = '{"query":"mutation {addToPLAYERS(playersUserId:\"' . $userId . '\", gameGameId:\"' . $gameId . '\"){playersUser{email}}}"}';
                $resultQueryAddPlayerToGame = $app['queryService']->getGraphQLDirectQuery($queryAddPlayerToGame);
                $resultDecoded = json_decode($resultQueryAddPlayerToGame);
                $data = array(
                    "gameId" => $gameId,
                    "command" => "playerJoined",
                    "name" => "[Admin] : ",
                    "message" => $resultDecoded->data->addToPLAYERS->playersUser->email . " à rejoins la partie !"
                );
                $app['queryService']->getPostNodeQuery('/chat', $data);
            } else {
                $app['session']->remove('game');
                return $app->redirect('/');
            }
        }

        $isNotEnded = true;
        if ($resultDecoded->data->Game->turnLeft < 0) {
            $isNotEnded = false;
        }

        $arrayResult = json_decode($result, true);
        return $app['twig']->render('mixmo.twig', array("user" => $user, "gameData" => $result, "gameDataArray" => $arrayResult, "isOpen" => $isOpen, "isNotEnded" => $isNotEnded));
    }

    public function startAction(Request $request, Application $app)
    {

        $gameId = $request->get("id");
        $app['queryService']->getNodeQuery('/game/' . $gameId . '/start');

        // new random on letters
        $letters = MixmoService::getRandomLetters();

        // get list of users
        $query = '{"query":"{Game(id:\"' . $gameId . '\"){players{id, email}}}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($query);
        $resultDecoded = json_decode($result);
        $players = $resultDecoded->data->Game->players;
        $nbLetters = strlen($letters);
        $nbPlayers = count($players);
        $nbLetterToDispatch = $nbLetters / $nbPlayers;
        $lettersArray = str_split($letters);
        $nbTurns = ($nbLetterToDispatch - 6) / 2;

        $gangster = null;

        // add all letters to users
        foreach ($players as $player) {
            $lettersArrayForUser = array();
            for ($i = 0; $i < $nbLetterToDispatch; $i++) {
                $char = array_shift($lettersArray);
                array_push($lettersArrayForUser, $char);
            }
            $letterResults = $this->setLetter($player, $lettersArrayForUser);
            $letterQueries = $letterResults["queries"];
            $gangster = $letterResults["gangster"];
            $playerQueries = $this->setPlayerGameCounter($player->id, $app);


            $query = '{"query":"mutation {' . $letterQueries . ' ' . $playerQueries . '}"}';
            $app['queryService']->getGraphQLDirectQuery($query);
        }

        // flag game as closed
        $this->closeGame($gameId, $nbTurns, $app);

        // send node request to dispatch event
        $app['queryService']->getNodeQuery("/mixmo");

        // users listen on event then request available letters
        $app['queryService']->getNodeQuery('/game/' . $gameId . '/started');

        // Notify chat users/players when someone has got the gangster letter
        if (null != $gangster) {
            $data = array(
                "gameId" => $gameId,
                "command" => "gangster",
                "name" => "gansgter",
                "message" => "\$Gangster\$ en possession de " . $gangster["player"]->email . " !"
            );
            $app['queryService']->getPostNodeQuery('/chat', $data);
        }

        return "OK";
    }


    // add all letters to users
    // make six letters available
    // save users
    protected function setLetter($player, $letters)
    {
        $index = 0;
        $letterQueries = "";
        $playerId = $player->id;
        $gangsterMarker = null;
        foreach ($letters as $letter) {
            $isOpen = "false";
            if ($index < 6) {
                $isOpen = "true";
            }

            $joker = "false";
            if ($letter == "*") {
                $joker = "true";
            }
            $gangster = "false";
            if (MixmoService::CHAR_GANGSTER == $letter) {
                $gangster = "true";
                if ($isOpen === "true") {
                    $gangsterMarker = array(
                        "player" => $player
                    );
                }
            }
            $letterQueries .= 'create' . $index . ' : createLetter(userId:\"' . $playerId . '\", value:\"' . $letter . '\", isOpen: ' . $isOpen . ', joker: ' . $joker . ', gangster: ' . $gangster . '){id,isOpen,value}';
            $index++;
        }

        return array("queries" => $letterQueries, "gangster" => $gangsterMarker);

    }

    protected function setPlayerGameCounter($playerId, $app)
    {
        $query = '{"query":"{User(id:\"' . $playerId . '\"){gameCounter}}"}';
        $result = json_decode($app['queryService']->getGraphQLDirectQuery($query));
        $games = $result->data->User->gameCounter;
        $games++;

        $query = 'create' . $playerId . ' : updateUser(id:\"' . $playerId . '\", gameCounter:' . $games . '){id}';
        return $query;
    }

    protected function closeGame($gameId, $nbTurns, $app)
    {
        $query = '{"query":"mutation{updateGame(id:\"' . $gameId . '\", isOpen: false, turns:' . $nbTurns . ', turnLeft:' . $nbTurns . '){id}}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($query);
    }

    public function quitAction(Request $request, Application $app)
    {
        $gameId = $request->get("gameId");
        $userId = $request->get("id");

        $user = $app['session']->get('user');
        if ($userId != $user["id"]) {
            return new Response("NOK");
        }

        //@todo handle errors
        $this->removeAllLetterFromPlayer($userId, $app);
        $this->removePlayerFromGame($userId, $gameId, $app);
        $app['session']->remove('game');

        return $app->redirect('/');
    }

    protected function removeAllLetterFromPlayer($userId, $app)
    {
        $queryLetters = '{"query":"{User(id: \"' . $userId . '\"){letters{id}}}"}';
        $resultQueryLetters = json_decode($app['queryService']->getGraphQLDirectQuery($queryLetters));
        $letterIds = $resultQueryLetters->data->User->letters;

        $query = "";
        foreach ($letterIds as $letter) {
            $query .= 'delete' . $letter->id . ' : deleteLetter(id:\"' . $letter->id . '\"){id}';
        }

        $masterQuery = '{"query":"mutation {' . $query . '}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($masterQuery);
    }

    protected function removePlayerFromGame($userId, $gameId, $app)
    {
        $query = '{"query":"mutation{removeFromPLAYERS(playersUserId: \"' . $userId . '\", gameGameId: \"' . $gameId . '\"){gameGame{id, players{id}}}}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($query);

        $resultDecoded = json_decode($result);
        $players = $resultDecoded->data->removeFromPLAYERS->gameGame->players;

        // remove room if no player inside
        if (count($players) <= 0) {
            $query = '{"query":"mutation { deleteGame(id: \"' . $gameId . '\"){id}}"}';
            $result = $app['queryService']->getGraphQLDirectQuery($query);
        }
    }


    public function resetAction(Request $request, Application $app)
    {
        $gameId = $request->get("gameId");
        $app['queryService']->getNodeQuery('/game/' . $gameId . '/resetEvent');

        $playersQuery = '{"query":"{Game(id:\"' . $gameId . '\"){players{id, letters{id}}}}"}';
        $resultPlayersQuery = json_decode($app['queryService']->getGraphQLDirectQuery($playersQuery));

        $players = $resultPlayersQuery->data->Game->players;

        // remove all letter from all users
        $this->removeAllLetterFromPlayerList($players, $app);
        // open game
        $queryOpenGame = '{"query":"mutation{updateGame(id:\"' . $gameId . '\", isOpen : true, turns: null, turnLeft: null){isOpen}}"}';
        $app['queryService']->getGraphQLDirectQuery($queryOpenGame);


        $app['queryService']->getNodeQuery('/game/' . $gameId . '/reset');
        return "OK";
    }

    protected function removeAllLetterFromPlayerList($players, $app)
    {
        $query = "";
        foreach ($players as $player) {
            $playerId = $player->id;
            $letters = $player->letters;
            foreach ($letters as $letter) {
                $query .= 'delete' . $letter->id . ' : deleteLetter(id:\"' . $letter->id . '\"){id}';
            }
        }
        $masterQuery = '{"query":"mutation {' . $query . '}"}';
        $result = $app['queryService']->getGraphQLDirectQuery($masterQuery);
    }
}
