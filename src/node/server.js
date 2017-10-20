var CONF = {
    IO: {HOST: '0.0.0.0', PORT: 9090},
    GRAPHQL: {HOST: process.env.GRAPHQL_ENDPOINT},
    ROLLBAR: {TOKEN: process.env.ROLLBAR_KEY, ENVIRONMENT: process.env.ROLLBAR_ENV},
    EXPRESS: {HOST: '0.0.0.0', PORT: 26300}
};

var request = require('request');
var cors = require('cors');
var app = require('express')();
var http = require('http').Server(app);
var io = require('socket.io')(CONF.IO.PORT);
var bodyParser = require('body-parser');
var rollbar = require("rollbar");


app.use(bodyParser.json()); // support json encoded bodies
app.use(bodyParser.urlencoded({extended: true})); // support encoded bodies
app.use(cors());
app.use(rollbar.errorHandler(CONF.ROLLBAR.TOKEN, {
    environment: CONF.ROLLBAR.ENVIRONMENT
}));

app.get('/mixmo/:gameId/started/:userName', function (req, res) {
    var gameId = req.params.gameId;
    io.sockets.emit('mixmoStarted' + gameId);
    var data = {"type": "mixmo", "label": "mixmo", "message": "Mixmo de " + req.params.userName + " !"};
    io.sockets.emit('chat' + gameId, data);
    res.sendStatus(200);
});

app.get('/mixmo/:gameId', function (req, res) {
    var query = {json: {"query": "{Game(id: \"" + req.params.gameId + "\"){turns, turnLeft, players{id,letters(filter:{isOpen: true}){id,value,joker,gangster}}}}"}};
    makePostCall(res, req, "/mixmo", query, function (error, response, body) {
        var players = body.data.Game.players;
        var turns = body.data.Game.turns;
        var turnLeft = body.data.Game.turnLeft;
        for (var i = 0; i < players.length; i++) {
            io.sockets.emit('mixmo' + players[i].id, {
                "letters": players[i].letters,
                "turns": turns,
                "turnLeft": turnLeft
            });
        }
    });

});

app.get('/games', function (req, res) {
    var query = {json: {"query": "query {allGames (filter:{isOpen:true}) {id, label, players{id,email}}}"}};
    makePostCall(res, req, "/games", query);
});

app.get('/games/:id/:user', function (req, res) {
    var query = {json: {"query": "query {allGames {id, label}}"}};
    makePostCall(res, req, "/games/:id/:user", query);
});

app.get('/game/:id/start', function (req, res) {
    io.sockets.emit('start' + req.params.id);
    res.sendStatus(200);
});
app.get('/game/:id/started', function (req, res) {
    io.sockets.emit('started' + req.params.id);
    res.sendStatus(200);
});
app.get('/game/:id/reset', function (req, res) {
    io.sockets.emit('reset' + req.params.id);
    res.sendStatus(200);
});
app.get('/game/:id/resetEvent', function (req, res) {
    io.sockets.emit('reseting' + req.params.id);
    res.sendStatus(200);
});
app.get('/game/:id/end/:winner', function (req, res) {
    io.sockets.emit('end' + req.params.id, req.params.winner);
    res.sendStatus(200);
});
app.get('/grids/:gameId/:playerId', function (req, res) {
    var data = {"type":"getInfos", player: req.params.playerId};
    io.sockets.emit('grids' + req.params.gameId, data);
    res.sendStatus(200);
});
app.post('/grids', function (req, res) {

    var data = {"type":"showInfos", view:req.body.view, player: req.body.userId};
    io.sockets.emit('grids' + req.body.gameId, data);
    res.sendStatus(200);
});

app.post('/chat', function (req, res) {
    var data = {"type": req.body.command, "label": req.body.name, "message": req.body.message};
    io.sockets.emit('chat' + req.body.gameId, data);
    res.sendStatus(200);
});


app.get('/login/:email/:password', function (req, res) {
    var query = {json: {"query": 'mutation {signinUser(email: {email: "' + req.params.email + '", password: "' + req.params.password + '" }) {user{id,email,game{id}}}}'}};
    makePostCall(res, req, "/login", query);
});

app.get('/signup/:email/:password', function (req, res) {
    var query = {json: {"query": 'mutation {createUser(authProvider: { email: { email: "' + req.params.email + '", password: "' + req.params.password + '" }}) {id,email}}'}};
    makePostCall(res, req, "/signup", query);
});

http.listen(CONF.EXPRESS.PORT, CONF.EXPRESS.HOST);


function makePostCall(res, req, title, queryJson, callBack) {
    try {
        request.post(
            CONF.GRAPHQL.HOST, queryJson,
            function (error, response, body) {
                try {
                    if (!error && response.statusCode == 200) {
                        if (callBack && (typeof callBack == "function")) {
                            callBack(error, response, body)
                        }
                        res.json(body);
                    }
                    else {
                        logError(title, response, body);
                    }
                }
                catch (e) {
                    rollbar.handleErrorWithPayloadData(e, {level: "warning", custom: {title: title}});
                }
            });
    }
    catch (e) {
        rollbar.handleErrorWithPayloadData(e, {level: "warning", custom: {title: title}}, req);
    }
}

function logError(title, response, body) {
    var errorData = {level: "warning", "custom": {"satus": response.statusCode, "response": response, "body": body}};
    rollbar.reportMessageWithPayloadData(title, errorData);
}