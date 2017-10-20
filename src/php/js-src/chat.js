chatMessages = $("#chat-messages");

function chatListener(data) {
    var time = moment().format('H:mm');
    var type = data.type || "message";
    chatMessages.append('<dd class="' + type + '"><em class="time">'+time+'</em> <b class="label">< ' + data.label + ' ></b><span class="content">' + data.message + '</span></dd>');
    $('#log').scrollTop($('#log')[0].scrollHeight);
}

function sendMessage(gameId, userId, userLabel, message) {
    var data = $.param({"gameId": gameId, "userId": userId, "name": userLabel, "message": message});
    $.post('/chat', data);
}


$(document).ready(function () {
    var id = $('#userId').val();
    var userLabel = $('#userEmail').val();
    var gameId = $('#gameId').val();


    $('#chat-input').keypress(function (e) {
        var key = e.which;
        if (key == 13)  // the enter key code
        {
            var message = $(this).val();
            sendMessage(gameId, id, userLabel, message);
            $(this).val('');
        }
    });

    socket.on('chat' + gameId, chatListener);
});