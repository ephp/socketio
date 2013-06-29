var io = require('socket.io').listen(8080);

io.sockets.on('connection', function(socket) {
	
	console.log('user connected');

	socket.on('message', function(data){
		socket.broadcast.emit('broadcast-action', data);
	});
});