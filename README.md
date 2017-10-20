# MIXMO !


## Install :

 * clone repo
 * Build node image with Dockerfile/node/Dockerfile
 * copy env.sample => .env
 * change env file 
 * update your hosts file
 * up dockers => run bash in mixmo-php

```php
cd src
php composer.phar install
```


## Stack
### Backend
 * PHP
   * Silex
   * Twig
 * Node JS
   * ExpressJS
   * SocketIO
   * GraphQL
   * Gulp
   * Sass
   
### Frontend
 * Foundation for site
 * ZeptoJS

## Notes


```php
$time_start = microtime(true);
$app['monolog']->info(sprintf("before getNodeQuery : '%f'",microtime(true) - $time_start));
```

-----

## Mixmo is a quick letters game.
The letter tiles are all in the middle of the table face down.
Each player receives 6 letters.
At the signal, all players turn their tiles and try to make crosswords with them.
When a player has used all his letters he says Mixmo
Each player picks two more letters and the game goes on until the all face-down letters are used.

[Mixmo website](http://mixmo.fr/MIXMO-jeu-de-lettres.htm)
