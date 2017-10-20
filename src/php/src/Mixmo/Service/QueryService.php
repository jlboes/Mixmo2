<?php

namespace Mixmo\Service;

use Rollbar;
use Silex\Application as SilexApplication;
use \Ubench as Ubench;

class QueryService
{

    private static $graphQLHost;
    
    private static $nodeJsHost; 

    private $logger;

    private $bench;
    
    /**
     * QueryService constructor.
     */
    public function __construct(SilexApplication $app)
    {
        $this->logger 		= $app["logger"];
        self::$graphQLHost 	= $app["graphql_endpoint"];
        self::$nodeJsHost 	= $app["nodejs_endpoint"];
        $this->bench 		= new Ubench();
        $this->logger->debug(sprintf("graphQLHost : %s, nodeJsHost : %s", self::$graphQLHost, self::$nodeJsHost));
    }


    /**
     * @see http://stackoverflow.com/questions/16920291/post-request-with-json-body
     */
    public function getGraphQLDirectQuery($graphqlQuery)
    {
    	// Start bench
    	$this->bench->start();
        
        // Setup cURL
        $ch = curl_init(static::$graphQLHost);
        curl_setopt_array($ch, array(
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => $graphqlQuery
        ));

        // Send the request
        $response = curl_exec($ch);
        $this->bench->end();
        
        // Check for errors
        if ($response === false) {
            Rollbar::report_exception(curl_error($ch));
        }

        $benchArgs = array(
        		'BENCH',
        		__METHOD__,
        		$graphqlQuery,
        		$this->bench->getTime(true),
        	); 
        $this->logger->info('|'.join('|', $benchArgs).'|');
        return $response;
    }

    public function getNodeQuery($route)
    {  	
    	// Start bench
    	$this->bench->start();
    	
        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, static::$nodeJsHost.$route);
        curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
        
        $content = curl_exec($s);
        $status = curl_getinfo($s, CURLINFO_HTTP_CODE);

        // End bench and log data
        $this->bench->end();
        $benchArgs = array(
        		'BENCH',
        		__METHOD__,
        		$route,
        		$this->bench->getTime(true),
        );
        $this->logger->info('|'.join('|', $benchArgs).'|');
        
        // Check for errors
        if ($content === false) {
            Rollbar::report_exception(curl_error($s));
        }

        curl_close($s);

        return $content;
    }

    public function getPostNodeQuery($route, $data)
    {
    	// Start bench
    	$this->bench->start();
    	
        //url-ify the data for the POST
        foreach($data as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');

        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, static::$nodeJsHost.$route);
        curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($s, CURLOPT_POST, count($data));
        curl_setopt($s, CURLOPT_POSTFIELDS, $fields_string);

        $content = curl_exec($s);       
        $status = curl_getinfo($s, CURLINFO_HTTP_CODE);
        curl_close($s);
        
        // End bench and log data
        $this->bench->end();
        $benchArgs = array(
        		'BENCH',
        		__METHOD__,
        		$route,
        		$this->bench->getTime(true),
        );
        $this->logger->info('|'.join('|', $benchArgs).'|');
        
        $this->logger->debug(sprintf("curl | route : %s, status : %s, content : %s", $route, $status, var_export($content, true)));

        // Check for errors
        if ($content === false) {
            Rollbar::report_exception(curl_error($s));
        }

        return $content;
    }
}
