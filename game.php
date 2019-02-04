<?php
/**
 * OrdinanceRemoval PHP back-end
 * 
 * This script communicates with the game client
 * via JSON transactions and carries out all game
 * actions.
 *
 * @author Carson Faatz <me@tudedude.me>
 * @version 1.0.0
 */

// prevent accessing without GET arguments
if(!isset($_GET)){
	die('{}');
}

// include GameBoard and Square classes
require_once('./GameBoard.php');

// start user session, containing game board and data
session_start();

// prevent accessing without action argument
if(!isset($_GET['a'])){
	die('{}');
}

// retrieve session board if it is set
if(isset($_SESSION['board'])){
  	$sessionBoard = unserialize($_SESSION['board']);
}

// action == getBoard
if($_GET['a'] == "gB"){
  	
  	// if width, height, and bomb count are set, create new board with the specified parameters
  	if(isset($_GET['w']) && isset($_GET['h']) && isset($_GET['b'])){
	
		// if seed is set, initialize board with specified seed
	  	if(isset($_GET['s'])){
	  		$b = new GameBoard(intval($_GET['w']), intval($_GET['h']), intval($_GET['b']), intval($_GET['s']));
	  	}else{
	  		$b = new GameBoard(intval($_GET['w']), intval($_GET['h']), intval($_GET['b']));
	  	}

	  	// calculate bombs in range for all squares  after board is created
	  	foreach($b->boardData as $row){
			foreach($row as $square){
				if(!$square->isBomb){
					$square->bombsInRange = $b->calculateBombsInRange($square->x, $square->y);
				}
			}
		}

		// set session board to the generated board
	  	$_SESSION['board'] = serialize($b);

	  	// return the new board state with JSON
	  	die($b->boardToJson());

	// if no parameters are set and a session board is set, return the session board's state
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
	// if no session board exists or if the required parameters aren't there, exit
	if(!isset($sessionBoard) || !isset($_GET['x']) || !isset($_GET['y'])){
		die('{}');
	}

	// prevent access with invalid parameters
	if(!(is_numeric($_GET['x']) && is_numeric($_GET['y']))){
		die($b->boardToJson());
	}

	// initialize parameters
	$b = $sessionBoard;
	$x = intval($_GET['x']);
	$y = intval($_GET['y']);

	// if click is outside of board, do nothing
	if($x < 0 || $x >= $b->width || $y < 0 || $y >= $b->height){
		die($b->boardToJson());
	}

	// retrieve object for clicked square
	$sq = $b->getSquare($x, $y);

	// if it is already clicked or flagged, do nothing
	if($sq->isClicked || $sq->isFlagged){
		die($b->boardToJson());
	}

	// uh-oh, its a bomb
	if($sq->isBomb){

		// if no squares have been clicked, re-arrange bombs to prevent one-click losses
		if($b->firstClick){

			// keep looping until a spot is found
			$spotFound = false;
			$spot = array(0, 0);

			while(!$spotFound){
				// if it is a bomb, keep moving
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
					// found a spot that is not a bomb
					$spotFound = true;
				}
			}

			// remove the clicked bomb location
			for($i = 0; $i < sizeof($b->bombLocations); $i++){
				$loc = $b->bombLocations[$i];
				if($loc[0] == $x && $loc[1] == $y){
					array_splice($b->bombLocations, $i, 1);
				}
			}

			// add new bomb location to array
			array_push($b->bombLocations, $spot);

			// update square properties
			$b->getSquare($spot[0], $spot[1])->isBomb = true;
			$sq->isBomb = false;

			// re-calculate bomb distances for all squares
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

			// carry out normal click functions
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
			// oof, the user lost
			$sq->isClicked = true;
			$bData = $b->boardToJson(false);

			// reveal data and inform the client that the game is lost
			$bData['lost'] = true;
			$bData['bombClicked'] = array($x, $y);
			$bData['bombLocations'] = $b->bombLocations;

			// un-set session board and return the final board state
			$_SESSION['board'] = null;
			unset($_SESSION['board']);
			die(json_encode($bData));
		}
	}else{
		// set square to clicked
		$sq->isClicked = true;

		// if square has no bombs near it, flood-search for other open squares
		if($sq->bombsInRange == 0){
			$revealed = flood($sq->x, $sq->y, $b);
			foreach($revealed as $rsq){
				$_rsq = $b->getSquare($rsq[0], $rsq[1]);

				// remove flags set on cleared squares
				if($_rsq->isFlagged){
					$_rsq->isFlagged = false;
					$b->flags++;
				}
				// click all cleared squares
				$_rsq->isClicked = true;
			}
		}
	}

	// if no flags are remaining on click, game could be completed
	if($b->flags == 0){

		// assume game is completed successfully until we find otherwise
		$finished = true;

		// search through board for un-clicked or un-flagged squares
		for($_y = 0; $_y < sizeof($b->boardData); $_y++){
			
			for($_x = 0; $_x < sizeof($b->boardData[$_y]); $_x++){
				
				if(!$b->boardData[$_y][$_x]->isClicked && !$b->boardData[$_y][$_x]->isFlagged){
					
					// if any squares are untouched, game is not finished
					$finished = false;
				}
			}
		}
		// if the game is finished, signal that to the client and destroy the session board
		if($finished){

			$bData = $b->boardToJson(false);
			$bData['won'] = true;
			$bData['bombLocations'] = $b->bombLocations;
			$_SESSION['board'] = null;
			unset($_SESSION['board']);
			die(json_encode($bData));
		}
	}

	// track that a move has been made
	$b->firstClick = false;

	// re-serialize board and set to session variable
	$_SESSION['board'] = serialize($b);

	// return with new board state
	die($b->boardToJson());

// action == flag
}else if($_GET['a'] == "fl"){

	// if no session board is active and/or invalid parameters are set, prevent access
	if(!isset($sessionBoard) || !isset($_GET['x']) || !isset($_GET['y'])){
		die('{}');
	}

	// prevent access with invalid parameters
	if(!(is_numeric($_GET['x']) && is_numeric($_GET['y']))){
		die($b->boardToJson());
	}

	$b = $sessionBoard;
	$x = intval($_GET['x']);
	$y = intval($_GET['y']);

	// if click is outside of board, do nothing
	if($x < 0 || $x >= $b->width || $y < 0 || $y >= $b->height){
		die($b->boardToJson());
	}

	// get object for clicked square
	$sq = $b->getSquare($x, $y);

	// if the square is already clicked, do nothing
	if($sq->isClicked){
		die($b->boardToJson());
	}

	// if square is flagged, remove flag and add to flag count
	if($sq->isFlagged){
		$sq->isFlagged = false;
		$b->flags++;

	// if square isn't flagged, flag it and remove from flag count
	}else if($b->flags >= 0){
		$sq->isFlagged = true;
		$b->flags--;
	}

	// if no flags are remaining, game could be finished
	if($b->flags == 0){

		// assume game is completed successfully until we find otherwise
		$finished = true;

		// search through board for un-clicked or un-flagged squares
		for($_y = 0; $_y < sizeof($b->boardData); $_y++){

			for($_x = 0; $_x < sizeof($b->boardData[$_y]); $_x++){

				if(!$b->boardData[$_y][$_x]->isClicked && !$b->boardData[$_y][$_x]->isFlagged){

					// if any squares are untouched, game is not finished
					$finished = false;
				}
			}
		}

		// if the game is finished, signal that to the client and destroy the session board
		if($finished){

			$bData = $b->boardToJson(false);
			$bData['won'] = true;
			$bData['bombLocations'] = $b->bombLocations;
			$_SESSION['board'] = null;
			unset($_SESSION['board']);
			die(json_encode($bData));
		}
	}

	// serialize new board state and return it to the client
	$_SESSION['board'] = serialize($b);

	die($b->boardToJson());
}

/**
 * Find squares with 0 bombs nearby in a
 * classic Minewseeper-style flood-fill pattern. 
 * Returns an array of all squares to be clicked.
 * @author Carson Faatz<me@tudedude.me>
 * 
 * @param int $x x coordinate to start search from
 * @param int $y y coordinate to start search from
 * @param GameBoard $board the board to search on
 * @return array
 */
function flood($x, $y, $board){

	// track visited squares to prevent redundancy
	$visited = array();

	// populate array with false values
	for($_y = 0; $_y < $board->height; $_y++){
		$visited[$_y] = array();
		for($_x = 0; $_x < $board->width; $_x++){
			$visited[$_y][$_x] = false;
		}
	}

	// mark origin as visited
	$visited[$y][$x] = true;

	// track clicked squares and queue of search directions
	$reveal = array();
	$queue = array();

	// queue search trees in all 8 directions
	array_push($queue, new SearchNode($x-1, $y-1, $x, $y));
	array_push($queue, new SearchNode($x, $y-1, $x, $y));
	array_push($queue, new SearchNode($x+1, $y-1, $x, $y));
	array_push($queue, new SearchNode($x+1, $y, $x, $y));
	array_push($queue, new SearchNode($x+1, $y+1, $x, $y));
	array_push($queue, new SearchNode($x, $y+1, $x, $y));
	array_push($queue, new SearchNode($x-1, $y+1, $x, $y));
	array_push($queue, new SearchNode($x-1, $y, $x, $y));

	// continue looping until the queue has emptied
	while(count($queue) > 0){

		// track variables from first search node in queue
		$node = $queue[0];
		$nX = $node->x;
		$nY = $node->y;

		// if node is out of the board dimensions, discard it and continue
		if($nX < 0 || $nX >= $board->width || $nY < 0 || $nY >= $board->height){
			array_shift($queue);
			continue;
		}

		// if node has been visited discard it and continue
		if($visited[$nY][$nX] == true){
			array_shift($queue);
			continue;
		}

		// mark node as visited
		$visited[$nY][$nX] = true;

		// if node has bombs in range, end tree and add to clicked array, then move to next node
		if(isset($board->getSquare($nX, $nY)->bombsInRange) && $board->getSquare($nX, $nY)->bombsInRange !== 0){
			array_push($reveal, array($nX, $nY));
			array_shift($queue);
			continue;
		}

		// square has no bombs in range, add to clicked list
		if(isset($board->getSquare($nX, $nY)->bombsInRange) && $board->getSquare($nX, $nY)->bombsInRange == 0){
			array_push($reveal, array($nX, $nY));
		}

		// queue new nodes in all 8 directions
		array_push($queue, new SearchNode($nX-1, $nY-1, $nX, $nY));
		array_push($queue, new SearchNode($nX, $nY-1, $nX, $nY));
		array_push($queue, new SearchNode($nX+1, $nY-1, $nX, $nY));
		array_push($queue, new SearchNode($nX+1, $nY, $nX, $nY));
		array_push($queue, new SearchNode($nX+1, $nY+1, $nX, $nY));
		array_push($queue, new SearchNode($nX, $nY+1, $nX, $nY));
		array_push($queue, new SearchNode($nX-1, $nY+1, $nX, $nY));
		array_push($queue, new SearchNode($nX-1, $nY, $nX, $nY));

		// remove searched node from queue
		array_shift($queue);
	}

	// return list of clicked nodes
	return $reveal;
}

// if no parameters are specified, prevent access
die('{}');

/**
 * A class to easily track information for search nodes
 * in the flood function.
 *
 * @see flood()
 */
class SearchNode{

	/**
	 * x,y coordinate of the node's search target
	 * @var int
	 */
	public $x, $y;

	/**
	 * x,y coordinates of the node's search origin
	 * @var int
	 */
	public $sourceX, $sourceY;

	// assign variables
	function __construct($_x, $_y, $_sourceX, $_sourceY){
		$this->x = $_x;
		$this->y = $_y;
		$this->sourceX = $_sourceX;
		$this->sourceY = $_sourceY;
	}
}
?>