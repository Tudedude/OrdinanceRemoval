<?php
if(!isset($_GET)){
	die('{}');
}
require_once('./GameBoard.php');
session_start();

if(!isset($_GET['a'])){
	die('{}');
}

if(isset($_SESSION['board'])){
  	$sessionBoard = unserialize($_SESSION['board']);
}

// action == getBoard
if($_GET['a'] == "gB"){
  	
  	if(isset($_GET['w']) && isset($_GET['h']) && isset($_GET['b'])){
	
	  	if(isset($_GET['s'])){
	  		$b = new GameBoard(intval($_GET['w']), intval($_GET['h']), intval($_GET['b']), intval($_GET['s']));
	  	}else{
	  		$b = new GameBoard(intval($_GET['w']), intval($_GET['h']), intval($_GET['b']));
	  	}


	  	foreach($b->boardData as $row){
			foreach($row as $square){
				if(!$square->isBomb){
					$square->bombsInRange = $b->calculateBombsInRange($square->x, $square->y);
				}
			}
		}

	  	$_SESSION['board'] = serialize($b);

	  	die($b->boardToJson());

  	}else if(isset($sessionBoard)){

  		foreach($sessionBoard->boardData as $row){
			foreach($row as $square){
				if(!$square->isBomb){
					$square->bombsInRange = $sessionBoard->calculateBombsInRange($square->x, $square->y);
				}
			}
		}

  		die($sessionBoard->boardToJson());

  	}

// action == click
}else if($_GET['a'] == "cl"){
	if(!isset($sessionBoard) || !isset($_GET['x']) || !isset($_GET['y'])){
		die('{}');
	}

	$b = $sessionBoard;
	$x = intval($_GET['x']);
	$y = intval($_GET['y']);

	if($x < 0 || $x >= $b->width || $y < 0 || $y >= $b->height){
		die($b->boardToJson());
	}

	$sq = $b->getSquare($x, $y);

	if($sq->isClicked || $sq->isFlagged){
		die($b->boardToJson());
	}

	if($sq->isBomb){
		if($b->firstClick){
			$spotFound = false;
			$spot = array(0, 0);
			while(!$spotFound){
				if($b->getSquare($spot[0], $spot[1])->isBomb){
					$spot[0]++;
					if($spot[0] >= $b->width){
						$spot[0] = 0;
						$spot[1]++;
					}
					if($spot[1] >= $b->height){
						$spot[1] = 0;
					}
				}else{
					$spotFound = true;
				}
			}
			for($i = 0; $i < sizeof($b->bombLocations); $i++){
				$loc = $b->bombLocations[$i];
				if($loc[0] == $x && $loc[1] == $y){
					array_splice($b->bombLocations, $i, 1);
				}
			}
			array_push($b->bombLocations, $spot);
			$b->getSquare($spot[0], $spot[1])->isBomb = true;
			$sq->isBomb = false;
			for($_x = 0; $_x < $b->width; $_x++){
				for($_y = 0; $_y < $b->height; $_y++){
					$_sq = $b->getSquare($_x, $_y);
					if($_sq->isBomb){
						unset($_sq->bombsInRange);
					}else{
						$_sq->bombsInRange = $b->calculateBombsInRange($_x, $_y);
					}
				}
			}
			$sq->isClicked = true;
			if($sq->bombsInRange == 0){
				$revealed = flood($sq->x, $sq->y, $b);
				foreach($revealed as $rsq){
					$_rsq = $b->getSquare($rsq[0], $rsq[1]);
					if($_rsq->isFlagged){
						$_rsq->isFlagged = false;
						$b->flags++;
					}
					$_rsq->isClicked = true;
				}
			}

		}else{
			$sq->isClicked = true;
			$bData = $b->boardToJson(false);
			$bData['lost'] = true;
			$bData['bombClicked'] = array($x, $y);
			$bData['bombLocations'] = $b->bombLocations;
			$_SESSION['board'] = null;
			unset($_SESSION['board']);
			die(json_encode($bData));
		}
	}else{
		$sq->isClicked = true;
		if($sq->bombsInRange == 0){
			$revealed = flood($sq->x, $sq->y, $b);
			foreach($revealed as $rsq){
				$_rsq = $b->getSquare($rsq[0], $rsq[1]);
				if($_rsq->isFlagged){
					$_rsq->isFlagged = false;
					$b->flags++;
				}
				$_rsq->isClicked = true;
			}
		}
	}

	if($b->flags == 0){
		$finished = true;
		for($_y = 0; $_y < sizeof($b->boardData); $_y++){
			for($_x = 0; $_x < sizeof($b->boardData[$_y]); $_x++){
				if(!$b->boardData[$_y][$_x]->isClicked && !$b->boardData[$_y][$_x]->isFlagged){
					$finished = false;
				}
			}
		}
		if($finished){
			$bData = $b->boardToJson(false);
			$bData['won'] = true;
			$bData['bombLocations'] = $b->bombLocations;
			$_SESSION['board'] = null;
			unset($_SESSION['board']);
			die(json_encode($bData));
		}
	}

	$b->firstClick = false;

	$_SESSION['board'] = serialize($b);

	die($b->boardToJson());
}else if($_GET['a'] == "fl"){
	if(!isset($sessionBoard) || !isset($_GET['x']) || !isset($_GET['y'])){
		die('{}');
	}

	$b = $sessionBoard;
	$x = intval($_GET['x']);
	$y = intval($_GET['y']);

	$sq = $b->getSquare($x, $y);

	if($sq->isClicked){
		die($b->boardToJson());
	}

	if($sq->isFlagged){
		$sq->isFlagged = false;
		$b->flags++;
	}else if($b->flags >= 0){
		$sq->isFlagged = true;
		$b->flags--;
	}

	if($b->flags == 0){
		$finished = true;
		for($_y = 0; $_y < sizeof($b->boardData); $_y++){
			for($_x = 0; $_x < sizeof($b->boardData[$_y]); $_x++){
				if(!$b->boardData[$_y][$_x]->isClicked && !$b->boardData[$_y][$_x]->isFlagged){
					$finished = false;
				}
			}
		}
		if($finished){
			$bData = $b->boardToJson(false);
			$bData['won'] = true;
			$bData['bombLocations'] = $b->bombLocations;
			$_SESSION['board'] = null;
			unset($_SESSION['board']);
			die(json_encode($bData));
		}
	}

	$_SESSION['board'] = serialize($b);

	die($b->boardToJson());
}

function flood($x, $y, $board){
	$visited = array();
	for($_y = 0; $_y < $board->height; $_y++){
		$visited[$_y] = array();
		for($_x = 0; $_x < $board->width; $_x++){
			$visited[$_y][$_x] = false;
		}
	}
	$visited[$y][$x] = true;
	$reveal = array();
	$queue = array();
	array_push($queue, new SearchNode($x-1, $y-1, $x, $y));
	array_push($queue, new SearchNode($x, $y-1, $x, $y));
	array_push($queue, new SearchNode($x+1, $y-1, $x, $y));
	array_push($queue, new SearchNode($x+1, $y, $x, $y));
	array_push($queue, new SearchNode($x+1, $y+1, $x, $y));
	array_push($queue, new SearchNode($x, $y+1, $x, $y));
	array_push($queue, new SearchNode($x-1, $y+1, $x, $y));
	array_push($queue, new SearchNode($x-1, $y, $x, $y));
	while(count($queue) > 0){
		$node = $queue[0];
		$nX = $node->x;
		$nY = $node->y;
		if($nX < 0 || $nX >= $board->width || $nY < 0 || $nY >= $board->height){
			array_shift($queue);
			continue;
		}else{
			if($visited[$nY][$nX] == false){
				$visited[$nY][$nX] = true;
				if($board->getSquare($nX, $nY)->bombsInRange !== 0 && isset($board->getSquare($nX, $nY)->bombsInRange)){
					array_push($reveal, array($nX, $nY));
					array_shift($queue);
					continue;
				}else if($board->getSquare($nX, $nY)->bombsInRange == 0 && isset($board->getSquare($nX, $nY)->bombsInRange)){
					array_push($reveal, array($nX, $nY));
				}
				array_push($queue, new SearchNode($nX-1, $nY-1, $nX, $nY));
				array_push($queue, new SearchNode($nX, $nY-1, $nX, $nY));
				array_push($queue, new SearchNode($nX+1, $nY-1, $nX, $nY));
				array_push($queue, new SearchNode($nX+1, $nY, $nX, $nY));
				array_push($queue, new SearchNode($nX+1, $nY+1, $nX, $nY));
				array_push($queue, new SearchNode($nX, $nY+1, $nX, $nY));
				array_push($queue, new SearchNode($nX-1, $nY+1, $nX, $nY));
				array_push($queue, new SearchNode($nX-1, $nY, $nX, $nY));

				array_shift($queue);
			}else{
				array_shift($queue);
				continue;
			}
		}
	}
	return $reveal;
}

die('{}');

class SearchNode{
	public $x, $y;
	public $sourceX, $sourceY;

	function __construct($_x, $_y, $_sourceX, $_sourceY){
		$this->x = $_x;
		$this->y = $_y;
		$this->sourceX = $_sourceX;
		$this->sourceY = $_sourceY;
	}
}
?>