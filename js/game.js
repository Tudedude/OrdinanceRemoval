var gameRunning = false;
var timerInterval = 0;
var time = 0;
var flags = 0;
var maxFlags = 0;
var lastUpdate = "";

$(function(){
	
	setTimeout(() => {

		$('head').append('<style id="dynamicCSS" type="text/css">.squareLabel{font-size: 48px;}.square{cursor: pointer;}</style>');

		$(".gameGrid").hide();

		$(".scorePanel").css("height", Math.floor($(".titlePanel").outerHeight()));
		if(window.innerWidth <= 600){
			var gameHeight = $("body").outerHeight() - ($(".titlePanel").outerHeight() + $(".scorePanel").outerHeight());
			$(".gamePanel").css("height", gameHeight);
			$(".gamePanel").css("margin-top", $(".scorePanel").outerHeight());
		}else{
			$(".gamePanel").css("height", ($(window).outerHeight() - ($(".titlePanel").outerHeight()))+3);
		}

		var pos = $(".gamePanel").position();
		var mt = $(".gamePanel").css("margin-top");
		$(".gameMenu").css("top", pos.top + parseInt(mt.substring(0, mt.length-2)));
		$(".gameMenu").css("height", $(".gamePanel").height());

		resizeGrid();

		$(window).resize(() => {
			if(window.innerWidth <= 600){
				var gameHeight = $("body").outerHeight() - ($(".titlePanel").outerHeight() + $(".scorePanel").outerHeight());
				$(".gamePanel").css("height", gameHeight);
				$(".gamePanel").css("margin-top", $(".scorePanel").outerHeight());
			}else{
				$(".gamePanel").css("margin-top", 0);
				$(".scorePanel").css("height", Math.floor($(".titlePanel").outerHeight()));
				$(".gamePanel").css("height", ($(window).outerHeight() - ($(".titlePanel").outerHeight()))+3);
			}
			var pos = $(".gamePanel").position();
			var mt = $(".gamePanel").css("margin-top");
			$(".gameMenu").css("top", pos.top + parseInt(mt.substring(0, mt.length-2)));
			$(".gameMenu").css("height", $(".gamePanel").height());
			
			resizeGrid();

		});

		$("#easy").click(() => {
			startGame(0);
		});

		$("#medium").click(() => {
			startGame(1);
		});

		$("#hard").click(() => {
			startGame(2);
		});

		$("#tryAgain").click(backToMenu);

		setTimeout(() => {$(".loadingVeneer").css("display", "none")}, 250);
	}, 500);
});

var startGame = function(difficulty){
	var width = 0;
	var height = 0;
	var bombs = 0;

	if(difficulty == 0){
		width = 10;
		height = 8;
		bombs = 10;
	}else if(difficulty == 1){
		width = 18;
		height = 14;
		bombs = 40;
	}else if(difficulty = 2){
		width = 24;
		height = 20;
		bombs = 100;
	}

	flags = bombs;
	maxFlags = flags;

	$(".gameMenu").hide();
	$(".menuPanel").hide();

	$.getJSON("game.php?a=gB&w=" + width + "&h=" + height + "&b=" + bombs, (data) => {
		updateBoard(data);

		$(".gameGrid").show();

		gameRunning = true;
		timerInterval =  setInterval(() => {
			time++;
			$("#time").text(time);
		}, 1000);
	});
}

var updateBoard = function(data){

	console.log(data);

	$(".gameGrid").css("grid-template-columns", "repeat(" + data.width + ", 20px)");
	$(".gameGrid").css("grid-template-rows", "repeat(" + data.height + ", 20px)");

	$(".gameGrid").html("");

	var board = data.board;
	var odd = 0;
	var oddRow = 0;
	for(var y = 0; y < board.length; y++){
		odd = oddRow;
		for(var x = 0; x < board[y].length; x++){

			var id = "x" + x + "y" + y;
			var classes = "square " + (odd == 1 ? "odd" : "even");
			var label = "";
			if(board[y][x].isClicked == true){
				classes += " clicked";
				var bombsNearby = board[y][x].bombsInRange;
				if(bombsNearby == undefined){
					bombsNearby = "B";
					classes += " bomb";
				}
				label += "<div class=\"squareLabel\" id=\"" + id + "\">" + bombsNearby + "</div>";
			}
			if(board[y][x].isFlagged == true) classes += " flagged";
			$(".gameGrid").append("<div class=\"" + classes + "\" id=\"" + id + "\">" + label + "</div>");
			$("#" + id).click(clickSquare);
			$("#" + id).contextmenu(flagSquare);

			if(odd == 1)odd = 0;
			else odd = 1;
		}
		if(oddRow == 1)oddRow = 0;
		else oddRow = 1;
	}

	resizeGrid();

	if(data.lost == true || data.won == true){
		gameRunning = false;
		clearInterval(timerInterval);
		timerInterval = 0;
		endGame(data);
	}

	lastUpdate = data;
	$("#flags").text(lastUpdate.flags);
}

var backToMenu = function(){
	$(".gameGrid").hide();
	$(".gameMenu").show();
	$(".menuPanel").show();
	$("#flags").text("0");
	$("#time").text("0");
	$(".resultsPanel").hide();
}

var endGame = function(data){
	var bombLocations = data.bombLocations;
	var ids = [];
	var correctFlags = 0;
	if(data.lost == true){
		for(var i = 0; i < bombLocations.length; i++){
			var bomb = bombLocations[i];
			if(bomb[0] == data.bombClicked[0] && bomb[1] == data.bombClicked[1])continue;
			var id = "x" + bomb[0] + "y" + bomb[1];
			if(data.board[bomb[1]][bomb[0]].isFlagged == true){
				$("#" + id).addClass("correctFlag");
				correctFlags++;
			}else{
				ids[ids.length] = id;
				$("#" + id).css("transition", "all 0.2s ease-in-out");
			}
		}
		$(".flagged:not(.correctFlag)").each((index, element) => {
			$(element).addClass("incorrectFlag");
		});
	}else{
		$(".flagged").each((index, element) => {
			$(element).addClass("correctFlag");
			correctFlags++;
		});
	}
	$('#dynamicCSS').text($('#dynamicCSS').text().replace(/(cursor: pointer)/g, "cursor: default"));
	revealBomb(ids, (10 + (2500/ids.length)), () => {
		console.log("Done revealing");
		setTimeout(() => {
			$(".gameMenu").show();
			$("#flagStat").text(correctFlags);
			$("#flagMax").text(maxFlags);
			$("#timeStat").text(time);
			if(data.won == true){
				$("#succFail").removeClass("fail");
				$("#succFail").addClass("succ");
				$("#succFail").text("Success!");
			}
			if(data.lost == true){
				$("#succFail").addClass("fail");
				$("#succFail").removeClass("succ");
				$("#succFail").text("Failure");
			}
			$(".resultsPanel").show(250);
			time = 0;
		}, 500);
	});
}

var revealBomb = function(ids, delay, callback){
	if(ids.length > 0){
		var id = ids[0];
		$("#" + id).addClass("clicked");
		setTimeout(() => {
			$("#" + id).html("<div class=\"squareLabel\" id=\"" + id + "\">B</div>");
			$("#" + id).addClass("bomb");
			revealBomb(ids, delay, callback);
		}, delay);
		ids.shift();
	}else{
		callback();
	}
}

var resizeGrid = function(){
	$(".gridWrapper").css("height", $(".gamePanel").height());
	$(".gridWrapper").css("width", $(".gamePanel").width());
	$(".gridWrapper").css("grid-template-columns", $(".gameMenu").width());
	$(".gridWrapper").css("grid-template-rows", $(".gameMenu").height());

	var gridRows = $(".gameGrid").css("grid-template-rows").split(" ").length;
	var gridColumns = $(".gameGrid").css("grid-template-columns").split(" ").length;
	var gridRatio = gridColumns/gridRows;

	var gridWidth = $(".gridWrapper").innerWidth();
	var gridHeight = $(".gridWrapper").innerHeight();
	var screenRatio = gridWidth/gridHeight;
	
	if(gridRatio == screenRatio || gridRatio > screenRatio){
		// width of grid will collide first or they are even
		var squareSize = gridWidth / gridColumns;
		console.log(squareSize);
		$(".gameGrid").css("grid-template-columns", "repeat(" + gridColumns + ", " + squareSize + "px)");
		$(".gameGrid").css("grid-template-rows", "repeat(" + gridRows + ", " + squareSize + "px)");
	}else if(gridRatio < screenRatio){
		// height of grid will collide first
		var squareSize = gridHeight / gridRows;
		console.log(squareSize);
		$(".gameGrid").css("grid-template-columns", "repeat(" + gridColumns + ", " + squareSize + "px)");
		$(".gameGrid").css("grid-template-rows", "repeat(" + gridRows + ", " + squareSize + "px)");
	}

	var fontSize = Math.floor(squareSize / 1.8);
	$('#dynamicCSS').text('.squareLabel{font-size: ' + fontSize + 'px;}.square{cursor: pointer;}');
}

var clickSquare = function(){
	if(!gameRunning){
		return;
	}
	var id = $(this).attr("id").split("y");

	$.getJSON("game.php?a=cl&x=" + id[0].substring(1) + "&y=" + id[1], (data) => {
		updateBoard(data);
	});
}

var flagSquare = function(event){
	event.preventDefault();
	if(!gameRunning){
		return;
	}
	var id = $(this).attr("id").split("y");

	$.getJSON("game.php?a=fl&x=" + id[0].substring(1) + "&y=" + id[1], (data) => {
		updateBoard(data);
	});
}