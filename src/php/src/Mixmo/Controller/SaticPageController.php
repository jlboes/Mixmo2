<?php

namespace Mixmo\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Mixmo\Service\QueryService;

/**
 * @Package Mixmo\Controller
 */
class SaticPageController
{
    public function faqAction(Request $request, Application $app)
    {
        return $app['twig']->render('faq.twig');
    }

    public function scoreAction(Request $request, Application $app)
    {
        $query = '{"query":"{allUsers(orderBy:victoryCounter_DESC, first:10){id,email,victoryCounter,gameCounter}}"}';
        $result =json_decode($app['queryService']->getGraphQLDirectQuery($query));
        $players = $result->data->allUsers;
        return $app['twig']->render('score.twig', array("players" =>$players));
    }
}