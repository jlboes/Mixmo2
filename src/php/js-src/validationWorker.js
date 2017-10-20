importScripts('underscore-min.js');

onmessage = function (message) {
    var storedData = message.data.letters;
    var dictionary = message.data.dictionary;

    var matrix = getHorizontalMatrix(storedData);
    var hWords = getWords(matrix);


    var matrix = getVerticalMatrix(storedData);
    var vWords = getWords(matrix);

    // validate()
    var okIds = Array();
    var nokIds = Array();
    var words = hWords.concat(vWords);
    words.forEach(function (element) {
        var wordToValidate = element.word;
        var index = _.indexOf(dictionary,wordToValidate, true);
        if(index >= 0){
            okIds.push(element.ids);
        }
        else
        {
            nokIds.push(element.ids);
        }
    });
    //
    postMessage({"okIds":_.flatten(okIds), "nokIds":_.flatten(nokIds)});
    close();
};

function getHorizontalMatrix(storedData) {
    var matrix = {};
    for (var k in storedData) {
        if (k == "debug") {
            continue;
        }
        var letterData = JSON.parse(storedData[k]);
        if (matrix[letterData.x] == undefined) {
            matrix[letterData.x] = {};
        }
        matrix[letterData.x][letterData.y] = letterData;
    }
    return matrix;
}

function getVerticalMatrix(storedData) {
    var matrix = {};
    for (var k in storedData) {
        if (k == "debug") {
            continue;
        }
        var letterData = JSON.parse(storedData[k]);
        if (matrix[letterData.y] == undefined) {
            matrix[letterData.y] = {};
        }
        matrix[letterData.y][letterData.x] = letterData;  // here add letter object instead
    }
    return matrix;
}

function getWords(matrix) {
    var words = Array();
    var wordObj = {word:"",ids:[]};

    for (var xKey in matrix) {
        var breakyKey = Object.keys(matrix[xKey])[0];

        for (var yKey in matrix[xKey]) {
            if (yKey - breakyKey <= 1) {
                var letterObj = matrix[xKey][yKey];
                wordObj.word += letterObj.letter;
                wordObj.ids.push(letterObj.id);
            }
            else {
                if (wordObj.word.length > 1) {
                    words.push(wordObj);
                }
                wordObj = {word:"",ids:[]};
                var letterObj = matrix[xKey][yKey];
                wordObj.word += letterObj.letter;
                wordObj.ids.push(letterObj.id);
            }
            breakyKey = yKey;
        }
        if (wordObj.word.length > 1) {
            words.push(wordObj);
        }
        wordObj = {word:"",ids:[]};
    }

    return words;
}

// @todo
// word should be an object {word:"azerty", ids[123, 456, 789]}
// response with json with status and id