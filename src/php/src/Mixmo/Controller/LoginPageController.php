<?php

namespace Mixmo\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Mixmo\Service\QueryService;

/**
 * @Package Mixmo\Controller
 */
class LoginPageController
{
    
    public function indexAction(Request $request, Application $app)
    {
        return $app['twig']->render('login.twig');
    }

    public function loginAction(Request $request, Application $app)
    {
        $email = $request->get('email');
        $password = $request->get('password');
        $gameId = $request->get('gameId');
        
        $result = json_decode($app['queryService']->getNodeQuery('/login/' . $email . '/' . $password));
        $app['logger']->debug(__METHOD__.' | result : ' . var_export($result, true));
        
        // @TODO: Handle (display of) error when logging user in.
        $hasError = isset($result->errors[0]);
        if ($hasError) 
        {
        	$errorEntry = $result->errors[0];
	        $errorCode = $errorEntry->code;
	        $errorMessage = $errorEntry->message;
	        $app['session']->getFlashBag()->add("login", $errorMessage);
	        $app['logger']->debug(__METHOD__.' | errorCode : ' . $errorCode .', errorMessage : ' . $errorMessage);
	        return $app->redirect('/login');
        }
        
        $user = $result->data->signinUser->user;
        $app['session']->set(
        		'user',
        		array (
        				'id' 	=> $user->id,
        				'email' => $user->email
        		)
			);
        
        if($user->game->id != NULL)
        {
            $app['session']->set('game', array('id' => $user->game->id));
        }
                
        return $app->redirect('/');
    }
    
    public function signupAction(Request $request, Application $app)
    {
    	$email 		= $request->get('email');
    	$password 	= $request->get('password');

    	$result = json_decode($app['queryService']->getNodeQuery('/signup/' . $email . '/' . $password));
    	$app['logger']->debug(__METHOD__.' | result : ' . var_export($result, true));
    	
    	// @TODO: Handle (display of) error when creating user.
    	$hasError = isset($result->errors[0]);
    	if ($hasError)
    	{
    		$errorEntry = $result->errors[0];
    		$errorCode = $errorEntry->code;
    		$errorMessage = $errorEntry->message;
    		$app['session']->getFlashBag()->add("signup", $errorMessage);
    		$app['logger']->debug(__METHOD__.' | errorCode : ' . $errorCode .', errorMessage : ' . $errorMessage);
    		return $app->redirect('/login');
    	}
    	
    	$user = $result->data->createUser;   	   	  
    	$app['session']->set(
    			'user', 
    			array (
    					'id' 	=> $user->id,
    					'email' => $user->email    					
    			)
    		);
    	
    	return $app->redirect('/');
    }

}
