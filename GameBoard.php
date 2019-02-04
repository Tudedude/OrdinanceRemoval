<?php
class GameBoard{
	public $boardData;
	public $boardSeed;
	public $width, $height, $bombs, $flags;
	public $bombLocations;
	public $firstClick;

	function __construct($w, $h, $b, $s = null){

		$this->width = $w;
		$this->height = $h;
		$this->bombs = $b;
		$this->flags = $b;

		if($s == null){
			$this->boardSeed = rand();
		}else{
			$this->boardSeed = $s;
		}

		$this->boardData = array();

		srand($this->boardSeed);

		for($_y = 0; $_y < $this->height; $_y++){

			$this->boardData[$_y] = array();

			for($_x = 0; $_x < $this->width; $_x++){

				$this->boardData[$_y][$_x] = new GameSquare($_x, $_y, false, false, false);

			}

		}

		$_bombs = array();

		for($i = 0; $i < $b; $i++){
			$finished = false;
			$x = 0;
			$y = 0;
			while(!$finished){
				$x = rand(0, $this->width-1);
				$y = rand(0, $this->height-1);
				$finished = true;

				foreach($_bombs as $bomb){
					if($bomb[0] == $x && $bomb[1] == $y){
						$finished = false;
					}
				}
			}
			$_bombs[] = array($x, $y);
		}

		$this->bombLocations = $_bombs;

		foreach($this->bombLocations as $bombLoc){
			$this->getSquare($bombLoc[0], $bombLoc[1])->isBomb = true;
		}

		foreach($this->boardData as $row){
			foreach($row as $square){
				if(!$square->isBomb){
					$square->bombsInRange = $this->calculateBombsInRange($square->x, $square->y);
				}
			}
		}

		$this->firstClick = true;

	}

	function calculateBombsInRange($x, $y){
		$bombs = 0;

		$coords = array(array($x-1, $y-1), array($x-1, $y), array($x-1, $y+1), array($x, $y-1),
						array($x, $y+1), array($x+1, $y-1), array($x+1, $y), array($x+1, $y+1));

		foreach($coords as $coord){
			if($coord[0] < 0 || $coord[0] >= $this->width)continue;
			if($coord[1] < 0 || $coord[1] >= $this->height)continue;
			$square = $this->getSquare($coord[0], $coord[1]);
			if($square->isBomb)$bombs++;
		}

		return $bombs;

	}

	function boardToJson($stringify = true){

		$data = array();
		$data['width'] = $this->width;
		$data['height'] = $this->height;
		$data['bombs'] = $this->bombs;
		$data['flags'] = $this->flags;
		$data['boardSeed'] = $this->boardSeed;
		$data['board'] = $this->boardData;
		
		for($x = 0; $x < $this->width; $x++){

			for($y = 0; $y < $this->height; $y++){

				$square = $data['board'][$y][$x];
				$obj = array();
				$obj['isClicked'] = $square->isClicked;
				$obj['isFlagged'] = $square->isFlagged;
				if($square->isClicked && !$square->isBomb){
					$obj['bombsInRange'] = $square->bombsInRange;
				}

				$data['board'][$y][$x]= $obj;

			}

		}

		if($stringify){
			return json_encode($data);
		}else{
			return $data;
		}

	}

	function seedToNum($seed){
		preg_replace("/([a-zA-Z]+)/", "", $seed);
		$len = strlen(PHP_INT_MAX . '')-1;
		if(strlen($seed) > $len){
			$seed = substr($seed, 0, $len);
		}
		return intval($seed);
	}

	function getSquare($x, $y){
		return $this->boardData[$y][$x];
	}
}

class GameSquare{
	public $x, $y;
	public $isBomb;
	public $isFlagged;
	public $isClicked;
	public $bombsInRange;

	function __construct($xC, $yC, $iB, $iF, $iC){
		$this->x = $xC;
		$this->y = $yC;
		$this->isBomb = $iB;
		$this->isFlagged = $iF;
		$this->isClicked = $iC;
	}
}
?>