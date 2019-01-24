<html>
	<head>
		<title>Ordinance Disposal</title>
		<script src="https://cdn.tudedude.me/jquery/jquery-2.2.4.min.js"></script>
		<script src="js/game.js"></script>
		<link rel="stylesheet" type="text/css" href="css/game.css"/>
	</head>
	<body>
		<div class="loadingVeneer"><div class="loadingText">Loading...</div></div>
		<div class="titlePanel">Ordinance Disposal</div>
		<div class="scorePanel"><div class="scoreText">Flags: <span id="flags">0</span>&nbsp;&nbsp;&nbsp;&nbsp;Time: <span id="time">0</span></div></div>
		<div class="gamePanel"><div class="gridWrapper"><div class="gameGrid"></div></div></div>
		<div class="gameMenu">
			<div class="menuPanel">
				<div class="menuHeader">Start Game</div>
				<div class="gbutton" id="easy">Easy</div>
				<div class="gbutton" id="medium">Medium</div>
				<div class="gbutton" id="hard">Hard</div>
			</div>
		</div>
		<div class="resultsPanel" style="display: none;">
			<div class="resultsHeader">Results</div>
			<div class="correctFlags">Correct flags: <span id="flagStat">0</span>/<span id="flagMax">0</span></div>
			<div class="timeStats">Time elapsed: <span id="timeStat">0</span></div>
			<div id="succFail" class="fail">Failure</div>

			<div class="gbutton" id="tryAgain">Try Again?</div>
		</div>
	</body>
</html>