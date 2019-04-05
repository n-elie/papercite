<?php

require_once 'PARSECREATORS.php';

/**
 * A list of creators (e.g., authors, editors)
 */
class PaperciteBibtexCreators {
  function __construct(&$creators) {
    $this->creators = &$creators;
  }
  function count() {
    return sizeof($this->creators);
  }

  static function parse($authors) {
      $parseCreators = new PaperciteParseCreators();
      $creators = $parseCreators->parse($authors);
      foreach($creators as &$cArray) {
        $cArray = array(
  		      "surname" => trim($cArray[2]),
  		      "firstname" => trim($cArray[0]),
  		      "initials" => trim($cArray[1]),
  		      "prefix" => trim($cArray[3])
  		      );
        unset($cArray);
      }
      return new PaperciteBibtexCreators($creators);
  }

	function toCSL() {
		// dropping-particle, non-dropping-particle
		$authors = array();
		foreach ( $this->creators as $c ) {
			$author           = array();
			$author['given']  = $c["firstname"];
			$author['family'] = $c["surname"];
			$author['suffix'] = isset( $c['suffix'] ) ? $c["suffix"] : "";
			$authors[]        = (object) $author;
		}

		return $authors;
	}


	public function __toString() {
		return join( "; ", $this->toArray() );
	}

	public function toArray( $complete = false ) {
		$creators = $this->creators;
		$toks     = array();
		foreach ( $creators as $creator ) {
			if ( $complete ) {
				$toks[] = "{$creator['surname']}, ${creator['prefix']} {$creator['firstname']} {$creator['initials']}";
			} else {
				$toks[] = "{$creator['surname']}, {$creator['firstname']}";

			}
		}

		return $toks;
	}

}

/**
 * A page range
 */
class PaperciteBibtexPages {
  function __construct($start, $end) {
    $this->start = (int)$start;
    $this->end = (int)$end;
  }
  function count() {
    return ($this->start ? 1 : 0) + ($this->end ? 1 : 0);
  }

	function toCSL() {
		$c = $this->count();
		if ( $c == 1 ) {
			return $this->start;
		}

		return $this->start . "-" . $this->end;
	}

	public function __toString() {
		// TODO: Implement __toString() method.
		//return "pages {$this->start} : {$this->end}";
		return $this->toCSL();
	}



	public function __toString() {
		// TODO: Implement __toString() method.
		//return "pages {$this->start} : {$this->end}";
		return $this->toCSL();
	}
}

?>
