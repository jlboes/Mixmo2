
function createRoom() {
    var name = $('#createRoomName').val();
    if (name != "") {
        $.get('/createRoom/' + name, function (gameId) {
            if (gameId == "NOK") {
                window.location.reload();
            }
            window.location.href = gameId;
        });
    }
}

// When document is ready
$(document).ready(function () {
    $('#createRoom').on('click', createRoom);
});