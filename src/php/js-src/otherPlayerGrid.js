function showPlayerGrid() {
    // Get mini grid html letter with ajax
    var playerId = $('#gridPlayerId').val();
    var gameId = $('#gameId').val();
    $.get('/grids/' + gameId + '/' + playerId, function (dataGrid) {
        $('.loading-grid').hide();
        $('#playerGridView').html(dataGrid);
    });
}

$(document).ready(function () {
    $('.loading-grid').hide();
    $('#gridGoBtn').on('click', showPlayerGrid);
});
