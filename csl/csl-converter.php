<?php
/**
 *
 * User: digfish
 * Date: 23-03-2019
 * Time: 12:12
 */

/**
 * Class CslConverter
 * converts the bibtex entries into different formats, e.g. raw JSON (as-it-is) or into CSL JSON, ready to be used by citeproc
 */

include_once dirname( __DIR__ ) . "/" . "NewBibtexConverter.php";

class CslConverter implements NewBibtexConverter {

	static $entryMap = array(
		"article" => "article",

		"proceedings"   => "book",
		"inproceedings" => "book",
		"book"          => "book",
		"manual"        => "book",
		"periodical"    => "book",

		"booklet" => "pamphlet",

		"inbook"       => "chapter",
		"incollection" => "chapter",

		"mastersthesis" => "thesis",
		"phdthesis"     => "thesis",

		"techreport" => "report",

		"patent" => "patent",

		"electronic" => "webpage",

		"misc"  => "article",
		"other" => "article",

		"standard" => "legislation",

		"unpublished" => "manuscript"
	);

	// Map from CSL field to one or more bibtex fields
	// If the mapped value is a:
	// - a string: direct mapping
	// - an array of arrays ($types, key_true, key_false): if bibtex type is in $types,
	// maps from $key_true, otherwise from $key_false. If the $key_xxx is null,
	// then tries
	static $fieldMap = array(
		"publisher-place" => "address",

		"event-place" => "location",

		"author" => "author",

		"editor" => "editor", // contained-editor, collection-editor

		"edition" => "edition",

		"publisher" => array(
			array( array( "techreport" ), "institution" ),
			array( array( "thesis" ), "school" ),
			"institution",
			"organization"
		),

		/*        "title" => array(
					array(array("inbook"), "chapter", "title"),
				),*/
		"title"     => "title",

		"doi" => "doi",

		"note"     => "note",
		"annote"   => "annote",
		"keywords" => "keyword",
		"status"   => "status",
		"accessed" => "accessed",

		"volume" => "volume",
		"number" => "issue", // number

		"pages" => "number-of-pages",

		"URL" => "url",

	);


	var $data;

	public function __construct( $data = array() ) {
		$this->data = $data;
	}

	public function setOneEntry() {
		$this->entry = $this->data;
	}

	public function setEntries() {
		$this->entries = $this->data;
	}


	public function toJson( $json_filename = '', $format = 'csl' ) {
		if ( empty( $json_filename ) ) {
			throw new Exception( "No JSON filename specified" );
		}

		if ( empty( $this->entries ) ) {
			throw new Exception( "No entries to convert to Json!" );
		}

		call_user_func( array( $this, "to" . ucfirst( $format ) . "Json" ), $json_filename );
	}

	protected function toRawJson( $json_filename ) {
		file_put_contents( $json_filename, json_encode( $this->data, JSON_PRETTY_PRINT ) );
	}

	protected function toCslJson( $json_filename ) {
		$csl_formatted = $this->convert();
		file_put_contents( $json_filename, json_encode( $csl_formatted, JSON_PRETTY_PRINT ) );
	}

	protected function convertToCslEntry( $entry ) {
		$cslEntry = new stdClass();
		if ( empty( $entry['cite'] ) ) {
			$entry_id = md5( $entry['bibtex'] );
		} else {
			$entry_id = $entry['cite'];
		}
		$cslEntry->id = $entry_id;

		$cslTypeField = self::$entryMap[ $entry['entrytype'] ];

		$cslEntry->type   = ( empty( $cslTypeField ) ) ? $entry['entrytype'] : $cslTypeField;
		$cslEntry->title  = $entry['title'];
		$cslEntry->author = array();

		if ( is_object( $entry['author'] ) ) {
			foreach ( $entry['author']->creators as $creator ) {
				$cslEntry->author[] = $this->toCslName( $creator );
			}
		}

		if ( ! empty( $entry['year'] ) ) {
			$cslEntry->issued = $this->toCslDate( $entry['year'] );
		}
		if ( ! empty( $entry['isbn'] ) ) {
			$cslEntry->ISBN = $entry['isbn'];
		}
		if ( ! empty( $entry['address'] ) ) {
			$cslEntry->{"event-place"} = $entry['address'];
		}
		if ( ! empty( $entry['mendeley-tags'] ) ) {
			$cslEntry->tags = $entry['mendeley-tags'];
		}
		if ( ! empty( $entry['url'] ) ) {
			$cslEntry->URL = $entry['url'];
		}
		if ( ! empty( $entry['publisher'] ) ) {
			$cslEntry->publisher = $entry['publisher'];
		}
		if ( ! empty( $entry['pages'] ) ) {
			$cslEntry->{"number-of-pages"} = $entry['pages'];
		}

		$cslEntry->editor = array();
		if ( ! empty( $entry['editor'] ) && is_object( $entry['editor'] ) ) {
			foreach ( $entry['editor']->creators as $creator ) {
				$cslEntry->editor[] = $this->toCslName( $creator );
			}
		}

		if ( ! empty( $entry['edition'] ) ) {
			$cslEntry->edition = $entry['edition'];
		}

		return $cslEntry;
	}

	protected function toCslName( $bibAuthor ) {
		$cslAuthor                            = new stdClass();
		$cslAuthor->family                    = $bibAuthor['surname'];
		$cslAuthor->given                     = $bibAuthor['firstname'];
		$cslAuthor->suffix                    = '';
		$cslAuthor->{"dropping-particle"}     = $bibAuthor['initials'];
		$cslAuthor->{"non-dropping-particle"} = $bibAuthor['prefix'];

		return $cslAuthor;
	}

	public function toCslDate( $bibDate ) {
		$cslDate      = new stdClass();
		$date_matches = preg_split( "/\-/", $bibDate );
		//var_dump($date_matches);
		/*        if (count($date_matches) > 0) {
					$cslDate->year = $date_matches[0];
				}
				if (count($date_matches) > 1) {
					$cslDate->month = $date_matches[1];
				}
				if (count($date_matches) > 2) {
					$cslDate->day = $date_matches[2];
				}*/

		//$cslDate->raw = $bibDate;
		$cslDate->{"date-parts"} = array( $date_matches );

		return $cslDate;
	}

	protected function convertEntries( $entries = array() ) {
		if ( func_num_args() == 0 ) {
			$entries = $this->entries;
		}
		$cslData = array();
		foreach ( $entries as $entry ) {
			//$cslData[] = $this->convertToCslEntry($entry);
			$cslData[] = $this->_convertEntry( $entry );
		}

		return $cslData;
	}

	public function convert( $bibtex_entries = array() ) {
		if ( empty( $bibtex_entries ) && ! empty( $this->data ) ) {
			$bibtex_entries = $this->data;
		}
		if ( is_object( $bibtex_entries ) ) {
			$this->data = (array) $bibtex_entries;
			$this->setOneEntry();
		} else if ( is_array( $bibtex_entries ) ) {
			$this->data = $bibtex_entries;
			$this->setEntries();
		}

		if ( isset( $this->entries ) ) {
			return $this->convertEntries( $this->entries );
		} else if ( isset( $this->entry ) ) {
			//return $this->convertToCslEntry( $this->entry );
			return $this->_convertEntry( $this->entry );
		}
	}

	function import( $bibtex_entry, $csl_entry, &$key, &$dest_key ) {
		if ( array_key_exists( $key, $bibtex_entry ) ) {
			$value = $bibtex_entry[ $key ];

			if ( is_object( $value ) ) {
				$csl_entry->$dest_key = $value->toCSL();

				return true;
			}

			$csl_entry->$dest_key = &$value;

			return true;
		}

		return false;
	}


	function _convertEntry( $bibtex_entry ) {
		$type            = static::$entryMap[ $bibtex_entry["entrytype"] ];
		$csl_entry       = new stdClass();
		$csl_entry->type = $type;

		foreach ( self::$fieldMap as $dest_key => $keys ) {
			if ( is_array( $keys ) ) {
				foreach ( $keys as $from ) {
					if ( is_array( $from ) ) {
						if ( count( $from ) > 3 ) {
							$key = array_search( $type, $from[0] ) ? $from[1] : $from[2];
							if ( $key && $this->import( $bibtex_entry, $csl_entry, $key, $dest_key ) ) {
								break;
							}
						}
					} else if ( $this->import( $bibtex_entry, $csl_entry, $from, $dest_key ) ) {
						break;
					}
				}
			} else {
				$this->import( $bibtex_entry, $csl_entry, $keys, $dest_key );
			}
		}

		if ( ! isset( $bibtex_entry['cite'] ) ) {
			$csl_entry->id = md5( $bibtex_entry['bibtex'] );
		} else {
			$csl_entry->id = $bibtex_entry['cite'];
		}

		// Handles dates

		if ( isset( $bibtex_entry['year'] ) ) {
			$csl_entry->issued = $this->toCslDate( $bibtex_entry['year'] );
		}

		return $csl_entry;

	}


	/**
	 * Set a global variable
	 */
	function setGlobal( $name, $value ) {
		// TODO: Implement setGlobal() method.
	}
}
