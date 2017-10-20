<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/rollbar/rollbar/src/rollbar.php';

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Mixmo\Silex\Application;
use Mixmo\Service\QueryService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use PHPProm\Integration\SilexSetup;


$app = new Silex\Application();

/******************************/
/*** Registering Providers ***/
/******************************/
// Template
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views',
));
// Session
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../monolog.log',
    'monolog.level' => getenv("LOGGER_LEVEL")
));
/******************************/
/*** End Registering Providers ***/
/******************************/


/******************************/
/*** Registering Service ***/
/******************************/
$app['queryService'] = function ($app) {
    return new QueryService($app);
};
/******************************/
/*** End Registering Service ***/
/******************************/

/**********************************************/
/*** Registering Javascript Variables 		***/
/**********************************************/
$app['graphql_endpoint'] 	= getenv("GRAPHQL_ENDPOINT");
$app['nodejs_endpoint'] 	= getenv("NODEJS_ENDPOINT");
$app['socketio_url'] 		= getenv("SOCKETIO_URL");
$app['enable_browsersync'] 	= getenv("ENABLE_BROWSERSYNC") == 'true' ? true : false;
/**********************************************/
/*** End Registering Javascript Variables 	***/
/**********************************************/

$app->before(function ($request) {
    $request->getSession()->start();

    // ROLLBAR
    $config = array(
        'access_token' => getenv("ROLLBAR_KEY"),
    	'environment' => getenv("ROLLBAR_ENV")
    );
    Rollbar::init($config);

    $sessionUser = $request->getSession()->get('user');
	$noAuthRoutes = array(
			"/faq",
			"/contact",
			"/scores",
			"/login",
			"/signup",
			"/metrics",
			"/graphql",
	);

	$requireAuth = !in_array($request->getRequestUri(), $noAuthRoutes);
    if ($requireAuth)
    {
        if(empty($sessionUser))
        {
            throw new AccessDeniedHttpException("require auth...");
        }


        $game = $request->getSession()->get('game');
        if($game && isset($game['id']))
        {
            $gameId = $game["id"];
            if($request->getRequestUri() != "/".$gameId && !strstr($request->getRequestUri(), "quit")
                && !strstr($request->getRequestUri(), "chat")
                && !strstr($request->getRequestUri(), "reset")
                && !strstr($request->getRequestUri(), "mixmo")
                && !strstr($request->getRequestUri(), "start")
                && !strstr($request->getRequestUri(), "grids")
                && !strstr($request->getRequestUri(), "createRoom"))
            {
                return new RedirectResponse('/'.$gameId);
            }
        }
    }

});

$app->error(function (\Exception $e) use ($app) {
    if ($e instanceof AccessDeniedHttpException) {
        return $app->redirect('/login');
    }
});


$app->get('/faq', 'Mixmo\Controller\SaticPageController::faqAction');
$app->get('/scores', 'Mixmo\Controller\SaticPageController::scoreAction');
$app->get('/graphql', 'Mixmo\Controller\SaticPageController::graphqlAction');

$app->get('/createRoom/{name}', 'Mixmo\Controller\HomePageController::createRoomAction');
$app->get('/games', 'Mixmo\Controller\HomePageController::getRoomsAction');

$app->get('/login', 'Mixmo\Controller\LoginPageController::indexAction');
$app->post('/login', 'Mixmo\Controller\LoginPageController::loginAction');
$app->post('/signup', 'Mixmo\Controller\LoginPageController::signupAction');


$app->get('/{gameId}/{id}/quit', 'Mixmo\Controller\MixmoPageController::quitAction');
$app->get('/{gameId}/{id}/reset', 'Mixmo\Controller\MixmoPageController::resetAction');
$app->get('/mixmo/started', 'Mixmo\Controller\MixmoActionController::gameStartedAction');
$app->get('/mixmo/{id}', 'Mixmo\Controller\MixmoActionController::indexAction');
$app->post('/grids/save/{name}', 'Mixmo\Controller\MixmoActionController::gridSaveAction');
$app->get('/grids/get/{name}', 'Mixmo\Controller\MixmoActionController::gridGetAction');
$app->get('/grids/{gameid}/{id}', 'Mixmo\Controller\MixmoActionController::gridsAction');
$app->post('/grids', 'Mixmo\Controller\MixmoActionController::gridsPostAction');

$app->post('/chat', 'Mixmo\Controller\ChatActionController::indexAction');


$app->get('/emit/{id}/{message}', function ($id, $message) use ($app) {
    $s = curl_init();
    curl_setopt($s, CURLOPT_URL, "http://localhost:26300/emit/{$id}/{$message}");
    curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
    $content = curl_exec($s);
    $status = curl_getinfo($s, CURLINFO_HTTP_CODE);
    curl_close($s);

    return new Response($content, $status);
});


/*
 |--------------------------------------------------------------------------
 | START | PHP Prometheus setup
 |--------------------------------------------------------------------------
 */
$storage = new PHPProm\Storage\Redis('mixmoredis');
$silexPrometheusSetup = new PHPProm\Integration\SilexSetup();
$metricsAction = $silexPrometheusSetup->setupAndGetMetricsRoute($app, $storage);
$app->get('/metrics', $metricsAction);


/*
 |--------------------------------------------------------------------------
 | END | PHP Prometheus setup
 |--------------------------------------------------------------------------
 */

$app->get('/{id}/start', 'Mixmo\Controller\MixmoPageController::startAction');
$app->get('/{id}', 'Mixmo\Controller\MixmoPageController::indexAction');
$app->get('/', 'Mixmo\Controller\HomePageController::indexAction');

return $app;
