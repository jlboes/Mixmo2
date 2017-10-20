function startMixmoListener(data) {
    $('#mixmo').hide();
    $('.loading-game').show();
}

function resetingListener(data) {
    $('#resetGame').attr('disabled', true);
    $('#mixmo').hide();
    $('.loading-game').show();
}


/**
 * @credits https://davidwalsh.name/javascript-debounce-function
 * @param func
 * @param wait
 * @param immediate
 * @returns function
 */
function debounce(func, wait, immediate) {
	var timeout;
	return function() {
		var context = this, args = arguments;
		var later = function() {
			timeout = null;
			if (!immediate) func.apply(context, args);
		};
		var callNow = immediate && !timeout;
		clearTimeout(timeout);
		timeout = setTimeout(later, wait);
		if (callNow) func.apply(context, args);
	};
}

function mixmoListener(data) {
    // display letters
    $.each(data.letters, function () {
        var letter = $(this)[0];
        if ($('td[data-id="' + letter.id + '"]').length == 0) {
            var joker = letter.joker?'1':'';
            var gangster = letter.gangster?'1':'';
            $("#letters-row").append("<td data-id='" + letter.id + "' data-joker='" + joker + "' data-gangster='" + gangster + "' class='letterTile notOnBoard'><div class='board-tile text-center'>" + letter.value + "</div></td>");
        }
    });
    $('.loading-game').hide();
    $('#mixmo').show();

    var percent = 100 - data.turnLeft / data.turns * 100;
    $("#turnLeft").attr('aria-valuenow', percent);

    if(percent > 80){
        $("#turnLeft").removeClass('success');
        $("#turnLeft").removeClass('warning');
        $("#turnLeft").addClass('alert');
    }
    else if (percent > 60){
        $("#turnLeft").removeClass('success');
        $("#turnLeft").removeClass('alert');
        $("#turnLeft").addClass('warning');
    }

    $(".progress-meter").css('width', percent + '%');
    $(".progress-meter-text").text(data.turnLeft);
}

function startListener() {
    $('#startGame').hide();
    $('#resetGame').attr('disabled', true);
    $('.loading-game').show();
}

function startedListener() {
    $('.loading-game').hide();
    $('#resetGame').removeAttr('disabled');
    $("#mixmo").show();
    $.get('/mixmo/started');
    localStorage.clear();
}

function resetListener() {
    window.location.reload();
}

function endGameListener(data) {
    $('#winnerName').text(data)
    $('.winnerRow').show();
    $('#mixmo').attr('disabled', true);
}


function mixmoGameListener() {

}

function mixmo() {
	console.log('disabled: ' + $('#mixmo').attr('disabled'));
	if ($('#mixmo').attr('disabled')) {
		console.log("In mixmo() | Button is disabled ! No event handling.");		
		return;
	}
    var id = $('#userId').val();
    $.get('/mixmo/' + id);
}

function startGame() {
    var pathname = window.location.pathname;
    $.get(pathname + '/start');
}

function quitGame() {
    var id = $('#userId').val();
    var pathname = window.location.pathname;
    var url = pathname + '/' + id + '/quit';
    window.location.href = url;
}

function resetGame() {
    var id = $('#userId').val();
    var pathname = window.location.pathname;
    var url = pathname + '/' + id + '/reset';
    $.get(url);
}

function selectLetter(tile) {
    $('.letterTile').removeClass('letterTileSelected');
    tile.addClass('letterTileSelected');
}

function chooseLetter(tile) {
    if (tile.attr('data-joker') == 1) {
        $('#offCanvasGangster').foundation('close');
        $('#offCanvasJoker').foundation('open');
    }
    else if (tile.attr('data-gangster') == 1) {
        $('#offCanvasJoker').foundation('close');
        $('#offCanvasGangster').foundation('open');
    }
}

function jokerLetterSelect() {
    var letter = $('td[data-joker="1"]').filter('.letterTileSelected');
    var value = $(this).text();
    specialLetterSelect("JOKER", letter, value);
}
function gangsterLetterSelect() {
    var letter = $('td[data-gangster="1"]').filter('.letterTileSelected');
    var value = $(this).text();
    specialLetterSelect("BANDIT", letter,value);
}

function specialLetterSelect(title, letter,value) {
    letter.html(getRibbon(title, value));
    hideAllLetterSelectors();
    letter.removeClass('letterTileSelected');
    updateStorage(letter);
    validate();
}

function dropLetter() {
    var letter = $('.letterTileSelected');
    if (letter.length == 0 || !($(this).hasClass('dropable'))) {
        return;
    }
    if (letter.length > 1) {
        console.error("multiple letters are selected");
        letter.removeClass('letterTileSelected');
        letter.removeClass('isValid');
        letter.removeClass('isNotValid');
        return;
    }
    moveLetter(letter, $(this));

    updateStorage($(this))
    validate();
}

function updateStorage(letter) {
    var letterValue = "";
    if (letter.attr('data-joker') == true || letter.attr('data-gangster') == true) {
        letterValue = letter.find('.letterValue').text().trim();
    }
    else {
        letterValue = letter.find(".board-tile").text().trim();
    }

    localStorage.setItem(letter.attr('data-id'), JSON.stringify({
        x: letter.attr('data-x'),
        y: letter.attr('data-y'),
        letter: letterValue,
        id: letter.attr('data-id'),
        joker: letter.attr('data-joker'),
        gangster: letter.attr('data-gangster')
    }));
}

function moveFromBoardToLetterBag() {
    var letter = $('.letterTileSelected');
    if (letter.length == 0 || letter.hasClass('notOnBoard')) {
        return;
    }
    var tr = $('#letters-row').append('<td></td>');
    var lastTd = tr.find('td').last();

    lastTd.addClass('notOnBoard');
    moveLetter(letter, lastTd);
    if (lastTd.attr('data-joker') == true) {
        lastTd.text("*");
    }
    else if (lastTd.attr('data-joker') == true) {
        lastTd.text("$");
    }
    localStorage.removeItem(lastTd.attr('data-id'));
    validate();
}

function validate() {
    var validationWorker = new Worker('js/validationWorker.js');
    var dataAsArray = new Array();    
    var letters = getLetters();
    for(var key in letters) {
        dataAsArray[key] = JSON.stringify(letters[key]);
    }
    var data = {"letters": dataAsArray, "dictionary": dictionary};

    validationWorker.postMessage(data);
    validationWorker.onmessage = function (e) {
    	var isOk = !(e.data && e.data.nokIds && e.data.nokIds.length);
    	onValidationResult({ success : isOk, data : e.data});
  	
    	handleEfficientCaptureGridScreenshot();
    }
}

function onValidationResult(result)
{
	console.log("In onValidationResult() | result.success : " + result.success);
	if (!!result.success) {
		$('#mixmo').removeAttr('disabled');
	} else {
		$('#mixmo').attr('disabled', true);	
	}
	
	visualFeedBack(result.data);
    conectivityCheck();
}


function onConnectivityCheckResult(result)
{
	console.log("In onConnectivityCheckResult() | result.success : " + result.success);
	var lettersBoardIsEmpty = $('#letters-row td[data-id]').length == 0; 
	console.log("In onConnectivityCheckResult() | lettersBoardIsEmpty : " + lettersBoardIsEmpty);	
	var isSuccess = result.success && lettersBoardIsEmpty;
	if (isSuccess) {
        console.log("grid ok");
        $('#board-table').removeClass('boardError');  		
		
	} else {
		// Toggle disabled state of mixmo button
		$('#mixmo').attr('disabled', true);
		
        console.log("grid not ok");
        // Add a red border on the entire board if not OK
        $('#board-table').addClass('boardError'); 
	}	
}


function visualFeedBack(data) {
    $("td").removeClass('isValid');
    $("td").removeClass('isNotValid');
    var ok = data.okIds;
    ok.forEach(function (id) {
        $('td[data-id="' + id + '"]').addClass('isValid');
    });
    var nok = data.nokIds;
    nok.forEach(function (id) {
        $('td[data-id="' + id + '"]').addClass('isNotValid');
    });
}

function organize() {
    $('.letterTile').each(function () {
        var id = $(this).attr('data-id');
        var item = localStorage.getItem(id);
        if (item != null) {
            var data = $.parseJSON(item);
            var x = data.x;
            var y = data.y;
            var node = $('td[data-x="' + x + '"][data-y="' + y + '"]');
            moveLetter($(this), node);
        }
    })
}

function moveLetter(from, to) {
    to.attr('data-id', from.attr('data-id'));
    to.attr('data-joker', from.attr('data-joker'));
    to.attr('data-gangster', from.attr('data-gangster'));
    to.addClass('letterTile');
    to.removeClass('dropable');

    if (from.attr('data-joker') == true) {
        from.find('.ribbon').remove();
        to.html(getRibbon("JOKER",from.text()));
    }
    else if (from.attr('data-gangster') == true) {
        from.find('.ribbon').remove();
        to.html(getRibbon("BANDIT",from.text()));
    }
    else {
        to.html(from.html());
    }


    if (from.hasClass('notOnBoard')) {
        from.remove();
    }
    else {
        from.html('<div class="board-tile"></div>');
        from.attr('data-id', '');
        from.attr('data-joker', '');
        from.attr('data-gangster', '');
        from.removeClass('letterTile');
        from.removeClass('letterTileSelected');
        from.removeClass('isValid');
        from.removeClass('isNotValid');
        from.addClass('dropable');
    }
}

function getLetters()
{
    var items = {};
    var keys = Object.keys(localStorage);
    for(var i = 0, key; key = keys[i]; i++) {
        if (key == "debug") {
            continue;
        }    
        var dataItem = localStorage.getItem(key);
        items[key] = JSON.parse(dataItem);
    }
    return items;	
}

function moveBoard() {

    var incrH = parseInt($(this).attr("data-x"));
    var incrV = parseInt($(this).attr("data-y"));

    // Old boundaries
    var letters = getLetters();
    var min_X = parseInt(_.min(_.pluck(letters, 'x')));
    var max_X = parseInt(_.max(_.pluck(letters, 'x')));
    var min_Y = parseInt(_.min(_.pluck(letters, 'y')));
    var max_Y = parseInt(_.max(_.pluck(letters, 'y')));
    console.log("Current boundaries (minx, maxx, miny, maxy) : " + min_X +',' + max_X + ', ' + min_Y + ', ' + max_Y);
    
    // Potential new boundaries    
    var new_min_X = min_X + incrV;
    var new_max_X = max_X + incrV;
    var new_min_Y = min_Y + incrH;
    var new_max_Y = max_Y + incrH;
    
    // Do we allow
    var isWithinBoundaries = new_min_X >= 0 
    				&& new_max_X <= 40 
    				&& new_min_Y >= 0 
    				&& new_max_Y <= 40;
    				
    if (!isWithinBoundaries) {
    	console.log("Move not allowed ! Reached boundaries !");
        console.log("Potential new boundaries (minx, maxx, miny, maxy) : " + new_min_X +',' + new_max_X + ', ' + new_min_Y + ', ' + new_max_Y);
    	return;
    }
    
    $('[data-id]:not([data-id=""]):not(".notOnBoard")').each(function () {
        console.log("row length"+$('#letters-row').find('td').length);
        var tr = $('#letters-row').append('<td><div class="board-tile text-center"></div></td>');
        var lastTd = tr.find('td').last();

        lastTd.addClass('notOnBoard');
        moveLetter($(this), lastTd);
    });
    
    var letters = getLetters();
    for (var key in letters) {
        var data = letters[key];
        var y = parseInt(data.y);
        var x = parseInt(data.x);
        data.y = y + incrH;
        data.x = x + incrV;
        localStorage.setItem(key, JSON.stringify(data));
    }

    organize();
    validate();
}

function conectivityCheck() {
    var matrix = {};
    var vertex;
    var letterCount = 0;
    
    var letters = getLetters();
    for (var key in letters) {
        var data = letters[key];
        if (matrix[data.x] == undefined) {
            matrix[data.x] = {};
        }
        matrix[data.x][data.y] = false;
        vertex = {x: data.x, y: data.y};
        letterCount++;
    }
    explore(matrix, vertex);

    var isOk = letterCount > 1 && !!isConnected(matrix);    
    onConnectivityCheckResult({success : isOk, data : {}});
}

function explore(matrix, vertex) {
    if (vertex == undefined || matrix[vertex.x] == undefined || matrix[vertex.x][vertex.y] == undefined || matrix[vertex.x][vertex.y] == true) {
        return;
    }
    matrix[vertex.x][vertex.y] = true;
    var plusX = (parseInt(vertex.x)+ 1);
    var plusY = (parseInt(vertex.y)+ 1);
    var minusX = (parseInt(vertex.x)- 1);
    var minusY = (parseInt(vertex.y)- 1);
    explore(matrix, {x: plusX, y: vertex.y});
    explore(matrix, {x: minusX, y: vertex.y});
    explore(matrix, {x: vertex.x, y: plusY});
    explore(matrix, {x: vertex.x, y: minusY});
}

function isConnected(matrix) {
    for (var level1 in matrix) {
        for (var level2 in matrix[level1]) {
            if (matrix[level1][level2] == false) {
                return false;
            }
        }
    }
    return true;
}

function getRibbon(text, letter) {
    var html = '<div class="board-tile text-center"><div class="ribbon"><div class="ribbon-stitches-top"></div><strong class="ribbon-content"><h1>'+text+'</h1></strong><div class="ribbon-stitches-bottom"></div></div>' +
        '<span class="letterValue">'+letter+'</span></div>';
    return html;
}

function hideSelectLetter() {
    if ($(this).attr('data-joker') != 1) {
        $('#offCanvasJoker').foundation('close');
    }
    if ($(this).attr('data-gangster') != 1) {
        $('#offCanvasGangster').foundation('close');
    }
}
 function hideAllLetterSelectors() {
     $('#offCanvasJoker').foundation('close');
     $('#offCanvasGangster').foundation('close');
 }

var dictionary;
// doesn't need to wait for the DOM
var dictionaryWorker = new Worker('js/dictionaryDbWorker.js');
dictionaryWorker.onmessage = function (e) {
    console.log('Message received from worker');
    dictionary = e.data;
    validate();
};

function uploadLocalLetters() {
	
	
}

function captureGridScreenshot() {
	
    var userName = $('#userEmail').val();	
    var userId = $('#userId').val();	
	var gridSaveUrl = "/grids/save/" + userName;
    
	/*
    $('#board-table').html2canvas({
        onrendered: function(canvas) {
            //Set hidden field's value to image data (base-64 string)
            $('#gridImgData').val(canvas.toDataURL("image/png"));
            var form = document.forms.namedItem("gridImgDataForm");
            var oData = new FormData(form);
            var oReq = new XMLHttpRequest();

            oReq.open("POST", gridSaveUrl, true);
            oReq.onload = function(oEvent) {
                if (oReq.status == 200) {
                	console.log("In captureGridScreenshot() | Screenshot uploaded !");
                } else {
                    console.error("In captureGridScreenshot() | Error " + oReq.status + " occurred when trying to upload your file.");
                }
            };
            oReq.send(oData);
        }
    });
    //*/
	
    $.post({
    	  url: gridSaveUrl,
    	  data: $.param({
    		identifier : userId,
      		gridImgData : getLetters(),
      	}),
	});

}

var handleEfficientCaptureGridScreenshot = debounce(captureGridScreenshot, 2000);

$(document).ready(function () {

    $('.loading-game').hide();
    organize();
    $('#startGame').on('click', startGame);
    $('#quitGame').on('click', quitGame);
    $('#resetGame').on('click', resetGame);
    $('#mixmo').on('click', mixmo);

    $(document).on('click', '.moveBoardBtn', moveBoard);

    if (Foundation.MediaQuery.is("small only")) {
        $(document).on('click', '.letterTile', function () {
            if($(this).hasClass('letterTileSelected')){
                chooseLetter($(this));
            }
            else {
                selectLetter($(this));
            }
        });
    }
    else {
        $(document).on('dblclick', '.letterTile', function(){chooseLetter($(this));});
        $(document).on('click', '.letterTile', function(){selectLetter($(this));});
    }

    $(document).on('click', '.dropable', dropLetter);
    $(document).on('click', '.letters-container', moveFromBoardToLetterBag);
    $(document).on('click', '.joker-letter-select', jokerLetterSelect);
    $(document).on('click', '.gangster-letter-select', gangsterLetterSelect);
    $(document).on('click', 'td', hideSelectLetter);

    var id = $('#userId').val();
    var gameId = $('#gameId').val();

    socket.on('mixmoStarted' + gameId, startMixmoListener);
    socket.on('mixmo' + id, mixmoListener);
    socket.on('start' + gameId, startListener);
    socket.on('started' + gameId, startedListener);
    socket.on('reset' + gameId, resetListener);
    socket.on('reseting' + gameId, resetingListener);
    socket.on('end' + gameId, endGameListener);

});
