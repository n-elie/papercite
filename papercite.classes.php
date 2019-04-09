<?php
/*  Copyright 2012-18  Benjamin Piwowarski  (email : benjamim@bpiwowar.net)

    Contributors:
    - Michael Schreifels: auto-bibshow and no processing in post lists options
    - Stefan Aiche: group by year option
    - Łukasz Radliński: bug fixes & handling polish characters
    - Some parts of the code come from bib2html (version 0.9.3) written by
    Sergio Andreozzi.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/** Main Class Papercite moved to this file. All the papercite old class methods
 * with no dependencies on Wordpress has been moved, and some reimplemented to not
 *   depend on Wordpress
 * to this file
 * User: digfish
 */


define( 'PAPERCITE_CUR_DIR', __DIR__ );
if ( ! defined( 'PAPERCITE_CONTENT_DIR' ) ) {
	define( 'PAPERCITE_CONTENT_DIR', dirname( __DIR__ ) );
}
define( 'CSL_STYLES_LOCATION', "vendor/citation-style-language/styles-distribution" );

include_once( "lib/BibTex_pear.php" );
include_once( "lib/BibTex_osbib.php" );
include_once( "papercite_helpers.php" );



include_once( PAPERCITE_CUR_DIR . "/bib2tpl/tpl_converter.php" );
include_once( PAPERCITE_CUR_DIR . "/csl/csl-converter.php" );
include_once( PAPERCITE_CUR_DIR . "/csl/citeproc-renderer.php" );


/**
 * Get string with author name(s) and make regex of it.
 * String with author or a list of authors (passed as parameter to papercite) in the following format:
 * -a1|a2|..|an   - publications including at least one of these authors
 * -a1&a2&..&an   - publications including all of these authors
 *
 * @param unknown $authors - string parsed from papercite after tag: "author="
 */
class PaperciteAuthorMatcher {
	function __construct( $authors ) {
		// Each element of this array is alternative match
		$this->filters = array();

		if ( ! isset( $authors ) || empty( $authors ) ) {
		} elseif ( ! is_string( $authors ) ) {
			echo "Warning: cannot parse option \"authors\", this is specified by string!<br>";// probably useless..
			// string contains both: & and | => this is not supported
		} else {
			require_once( dirname( __FILE__ ) . "/lib/bibtex_common.php" );
			foreach ( preg_split( "-\\|-", $authors ) as $conjonction ) {
				$this->filters[] = PaperciteBibtexCreators::parse( $conjonction );
			}
		}
	}

	function matches( &$entry ) {
		$ok       = true;
		$eAuthors = &$entry["author"];
		foreach ( $this->filters as &$filter ) {
			foreach ( $filter->creators as $author ) {
				$ok = false;
				foreach ( $eAuthors->creators as $eAuthor ) {
					if ( $author["surname"] === $eAuthor["surname"] ) {
						$ok = true;
						break;
					}
				}
				// Author was not found in publication
				if ( ! $ok ) {
					break;
				}
			}
			// Everything was OK
			if ( $ok ) {
				break;
			}
		}

		return $ok;
	}
}

class Papercite {


	var $parse = false;


	// List of publications for those citations
	var $bibshows = array();

	// Our caches (bibtex files and formats)
	var $cache = array();

	// Array of arrays of current citations
	var $cites = array();

	// Global replacements for cited keys
	var $keys = array();
	var $keyValues = array();

	// bibshow options stack
	var $bibshow_options = array();
	var $bibshow_tpl_options = array();

	// Global counter for unique references of each
	// displayed citation (used by bibshow)
	var $citesCounter = 0;

	// Global counter for unique reference of each
	// displayed citation
	var $counter = 0;


	var $options;

	//! Add an error message
	var $error_messages = array();

	static $bibtex_parsers = array( "pear" => "Pear parser", "osbib" => "OSBiB parser" );

	// Names of the options that can be set
	static $option_names = array(
		"format",
		"timeout",
		"file",
		"bibshow_template",
		"bibtex_template",
		"bibtex_parser",
		"use_db",
		"auto_bibshow",
		"use_media",
		"use_files",
		"skip_for_post_lists",
		"process_titles",
		"checked_files",
		"show_links",
		"highlight",
		"ssl_check",
		"format_type"
	);

	// Default value of options
	static $default_options =
		array(
			"format"              => "ieee",
			"group"               => "none",
			"order"               => "desc",
			"sort"                => "none",
			"key_format"          => "numeric",
			"bibtex_template"     => "default-bibtex",
			"bibshow_template"    => "default-bibshow",
			"bibtex_parser"       => "osbib",
			"use_db"              => false,
			"auto_bibshow"        => false,
			"use_media"           => false,
			"use_files"           => true,
			"skip_for_post_lists" => false,
			"group_order"         => "",
			"timeout"             => 3600,
			"process_titles"      => true,
			"checked_files"       => array( array( "pdf", "pdf", "", "pdf", "application/pdf" ) ),
			"show_links"          => true,
			"highlight"           => "",
			"ssl_check"           => false,
			"filters"             => array(),
			"format_type"         => 'tpl'
		);


	function init() {


		// Get general preferences & page wise preferences
		if ( empty( $this->options ) ) {
			$this->options = self::$default_options;
		}
		/*		$this->lastCommand = '';
				$this->lastCommandOptions = array();*/

	}

	/**
	 * Check if a matching file exists, and add it to the bibtex if so
	 *
	 * @param $entry key
	 * @param $types An array of couples (folder, extension)
	 */
	function checkFiles( &$entry, $options ) {

		if ( ! isset( $entry['cite'] ) ) {
			return;
		}
		$id = strtolower( preg_replace( "@[/:]@", "-", $entry["cite"] ) );
		foreach ( $options["checked_files"] as &$type ) {
			// 0. field, 1. folder, 2. suffix, 3. extension, 4. mime-type
			if ( sizeof( $type ) == 3 ) {
				$type[3] = $type[2];
				$type[2] = "";
				$type[4] = "";
			}
			$file = $this->getDataFile( "$id$type[2]", $type[3], $type[1], $type[4], $options );
			if ( $file ) {
				$entry[ $type[0] ] = $file[1];
			}
		}
	}

	static function array_get( $array, $key, $defaultValue ) {
		return array_key_exists( $key, $array ) ? $array[ $key ] : $defaultValue;
	}

	static function startsWith( $haystack, $needle ) {
		return ! strncmp( $haystack, $needle, strlen( $needle ) );
	}


	/** Returns true if papercite uses a database backend */
	function useDb() {
		return $this->options["use_db"];
	}


	// Get the options to forward to bib2tpl
	function getBib2TplOptions( $options ) {
		return array(
			"anonymous-whole" => true, // for compatibility in the output
			"group"           => $options["group"],
			"group_order"     => $options["group_order"],
			"sort"            => $options["sort"],
			"order"           => $options["order"],
			"key_format"      => $options["key_format"],
			"limit"           => papercite::array_get( $options, "limit", 0 ),
			"highlight"       => $options["highlight"]
		);
	}


	public function formatBibliographyItems( $bibTexEntries, $format, $options = array(), $format_type = 'tpl' ) {
		if ( func_num_args() < 3 ) {
			$options = $this->options;
		}
		if ( func_num_args() > 3 ) {
			$options['format_type'] = $format_type;
			$options['format']      = $format;
		}
		$bib2tplOptions = $this->getBib2TplOptions( $options );
		$bib_html       = $this->showEntries( $bibTexEntries, $bib2tplOptions, false, $options["bibtex_template"], $format, "bibtex", $options );

		return $bib_html;
	}

	/**
	 * Returns the content of a file (disk or post data)
	 *
	 * @see Papercite::getDataFile
	 */
	static function getContent( $relfile, $ext, $folder, $mimetype, $options, $use_files = false ) {

		// Normal behavior
		$data = self::getDataFile( $relfile, $ext, $folder, $mimetype, $options, $use_files );
		if ( $data ) {
			return file_get_contents( $data[0] );
		}

		return false;
	}

	/**
	 * Show a set of entries
	 *
	 * @param $refs
	 * @param $bib2TplOptions
	 * @param $getKeys
	 * @param $mainTpl
	 * @param $formatTpl
	 * @param $mode
	 *
	 * @return mixed
	 */
	function showEntries( $refs, $bib2TplOptions, $getKeys, $mainTpl, $formatTpl, $mode, $options = array() ) {

		if ( empty( $options ) ) {
			$options = $this->options;
		}
		// Get the template files
		$main   = self::getContent( "$mainTpl", "tpl", "tpl", "MIMETYPE", $options, true );
		$format = self::getContent( "$formatTpl", "tpl", "format", "MIMETYPE", $options, true );

		// Fallback to defaults if needed
		if ( ! $main ) {
			$main = self::getContent( self::$default_options["${mode}_template"], "tpl", "tpl", "MIMETYPE", $options, true );
			if ( ! $main ) {
				throw new \Exception( "Could not find template ${mode}_template" );
			}
		}
		if ( ! $format ) {
			$format = self::getContent( self::$default_options["format"], "tpl", "format", "MIMETYPE", $options, true );
			if ( ! $main ) {
				throw new \Exception( "Could not find template " . self::$default_options["format"] );
			}
		}


		//require_once PAPERCITE_CUR_DIR . "/bib2tpl/bib2tpl-entry.php";

		$bibtexEntryTemplate = new PaperciteBibtexEntryFormat( $format );

		// Gives a distinct ID to each publication (i.e. to display the corresponding bibtex)
		// in the reference list
		if ( $refs ) {
			foreach ( $refs as &$entry ) {
				$entry["papercite_id"] = $this->counter ++;
			}
		}

		// Convert (also set the citation key)
		$bib2tpl = new TplConverter( $bib2TplOptions, $main, $bibtexEntryTemplate );
		$bib2tpl->setGlobal( "WP_PLUGIN_URL", PAPERCITE_CUR_DIR );
		$bib2tpl->setGlobal( "PAPERCITE_DATA_URL", self::getCustomDataDirectory() );

		$r = array();

		// Csl formatting
		if ( isset( $options['format_type'] ) && $options['format_type'] == 'csl' ) {

			$cslfile = papercite::getContent( $options['format'], "csl", CSL_STYLES_LOCATION, "text/x-csl", $options, true );
			if ( empty( $cslfile ) ) {
				throw new \Exception ( "Couldn't find CSL file " . $options['format'] . ".csl" );
			}
			$csl_defs                = $cslfile;
			$bib2csl                 = new CslConverter();
			$csl_collection          = $bib2csl->convert( $refs );
			$csl_renderer            = new CiteProcRenderer();
			$csl_renderer->styleName = $options['format'];
			$csl_renderer->setStyleDefs( $csl_defs );
			$r['data'] = $refs;
			$r['text'] = $csl_renderer->bibliography( array( 'data' => $csl_collection ) );
		} else { // tpl formatting

			// Now, check for attached files
			if ( ! $refs ) {
				// No references: return nothing
				return "";
			}

			foreach ( $refs as &$ref ) {
				// --- Add custom fields
				$this->checkFiles( $ref, $options );
			}

			// This will set the key of each reference
			$r = $bib2tpl->display( $refs );

			// If we need to get the citation key back
			if ( $getKeys ) {
				foreach ( $refs as &$group ) {
					foreach ( $group as &$ref ) {
						$this->keys[] = $ref["pKey"];
						if ( $options["show_links"] ) {
							$this->keyValues[] = "<a class=\"papercite_bibcite\" href=\"#paperkey_{$ref["papercite_id"]}\">{$ref["key"]}</a>";
						} else {
							$this->keyValues[] = $ref["key"];
						}
					}
				}
			}
		}


		// Process text in order to avoid some unexpected WordPress formatting
		return str_replace( "\t", '  ', trim( $r["text"] ) );
	}


	static function getCustomDataDirectory() {
		return PAPERCITE_CUR_DIR . "/papercite-data";
	}


	/** Process a parsed command
	 *
	 * @param $command String The command (shortcode)
	 * @param $options array The options of the command
	 */

	function processCommand( $command, $options ) {

		// --- Process the commands ---
		switch ( $command ) {
			// display form, convert bibfilter to bibtex command and recursivelly call the same;-)
			case "bibfilter":
				// this should return html form and new command composed of (modified) $options_pairs
				return $this->bibfilter( $options );

			// bibtex command:
			case "bibtex":
				$result = $this->getEntries( $options );

				return $this->showEntries( $result, $this->getBib2TplOptions( $options ), false, $options["bibtex_template"], $options["format"], "bibtex", $options );

			// bibshow / bibcite commands
			case "bibshow":
				$data = $this->getData( $options["file"], $options );
				if ( ! $data ) {
					return "<span style='color: red'>[Could not find the bibliography file(s)" .
					       ( current_user_can( "edit_post" ) ? " with name [" . htmlspecialchars( $options["file"] ) . "]" : "" ) . "</span>";
				}

				// TODO: replace this by a method call
				$refs = array( "__DB__" => array() );
				foreach ( $data as $bib => &$outer ) {
					// If we have a database backend for a bibtex, use it
					if ( is_array( $outer ) && $outer[0] == "__DB__" ) {
						array_push( $refs["__DB__"], $outer[1] );
					} else {
						foreach ( $outer as &$entry ) {
							if ( isset( $entry['cite'] ) ) {
								$key          = $entry["cite"];
								$refs[ $key ] = &$entry;
							}
						}
					}
				}

				$this->bibshow_tpl_options[] = $this->getBib2TplOptions( $options );
				$this->bibshow_options[]     = $options;
				array_push( $this->bibshows, $refs );
				$this->cites[] = array();
				break;

			// Just cite
			case "bibcite":
				if ( sizeof( $this->bibshows ) == 0 ) {
					if ( $options["auto_bibshow"] ) {
						// Automatically insert [bibshow] because of unexpected [bibcite]
						$generated_bibshow = array( '[bibshow]', 'bibshow' );
						$this->process( $generated_bibshow );
						unset( $generated_bibshow );
					} else {
						return "[<span title=\"Unknown reference: $options[key]\">?</span>]";
					}
				}

				$keys    = preg_split( "/,/", $options["key"] );
				$cites   = &$this->cites[ sizeof( $this->cites ) - 1 ];
				$returns = "";

				foreach ( $keys as $key ) {
					if ( $returns ) {
						$returns .= ", ";
					}

					// First, get the corresponding entry
					$num = Papercite::array_get( $cites, $key, false );

					// Did we already cite this?
					if ( ! $num ) {
						// no, register this using a custom ID (hopefully, there will be no conflict)
						$id = "BIBCITE%%" . $this->citesCounter . "%";
						$this->citesCounter ++;
						$num           = sizeof( $cites );
						$cites[ $key ] = array( $num, $id );
					} else {
						// yes, just copy the id
						$id = $num[1];
					}
					$returns .= "$id";
				}

				return "[$returns]";

			case "/bibshow":
				return $this->end_bibshow();


			default:
				return "[error in papercite: unhandled]";
		}
	}

	/** Main entry point using the matches array
	 *
	 * @param $matches array the shortcode matches to process
	 *
	 * @return String the markup txt from the processing
	 * If the user as edit permissions, adds the error messages to the output
	 */
	function process( &$matches ) {
		$r = $this->_process( $matches );
		if ( current_user_can( "edit_post", get_the_ID() ) ) {
			$r .= $this->getAndCleanMessages();
		}

		return $r;
	}

	/**
	 * Internal method
	 * Main entry point: Handles a match in the post
	 */
	function _process( &$matches ) {

		$debug = false;

		$post = null;

		//d($matches);

		// --- Initialisation ---

		// Includes once the bibtex parser
		require_once( dirname( __FILE__ ) . "/lib/BibTex_" . $this->options["bibtex_parser"] . ".php" );

		// Includes once the converter
		require_once( "bib2tpl/tpl_converter.php" );

		// Get the command
		$command = $matches[1];


		// Get all the options pairs and store them
		// in the $options array
		$options_pairs = array();
		preg_match_all( "/\s*([\w-:_]+)=(?:([^\"]\S*)|\"([^\"]+)\")(?:\s+|$)/", sizeof( $matches ) > 2 ? $matches[2] : "", $options_pairs, PREG_SET_ORDER );


		// print "<pre>";
		// print htmlentities(print_r($options_pairs,true));
		// print "</pre>";
//		print "\n$command,";
//		print_r($options_pairs);
		// ---Set preferences
		// by order of increasing priority
		// (0) Set in the shortcode
		// (1) From the preferences
		// (2) From the custom fields
		// (3) From the general options
		// $this->options has already processed the steps 0-2

		//d($matches);

		$options            = $this->options;

		// ensure that the options passed to the bibshow
		// are reused on the following bibcite
//		if ($this->lastCommand == 'bibshow' && $command=='bibcite') {
//			$options = $this->lastCommandOptions;
//		}


		$options["filters"] = array();

		foreach ( $options_pairs as $x ) {
			$value = $x[2] . ( sizeof( $x ) > 3 ? $x[3] : "" );

			if ( $x[1] == "template" ) {
				// Special case of template: should overwrite the corresponding command template
				$options["${command}_$x[1]"] = $value;
			} elseif ( self::startsWith( $x[1], "filter:" ) ) {
				$options["filters"][ substr( $x[1], 7 ) ] = $value;
			} else {
				$options[ $x[1] ] = $value;
			}
		}


		// --- Compatibility issues: handling old syntax
		if ( array_key_exists( "groupByYear", $options ) && ( strtoupper( $options["groupByYear"] ) == "TRUE" ) ) {
			$options["group"]       = "year";
			$options["group_order"] = "desc";
		}

		$data = null;

//		$this->lastCommand = $command;
//		$this->lastCommandOptions = $options;



		return $this->processCommand( $command, $options );
	}


	/** Returns true if the all the regular expression filters are matched */
	static function userFiltersMatch( $filters, $entry ) {
		if ( ! empty( $filters ) ) {
			foreach ( $filters as $fieldname => $regexp ) {
				$v = array_key_exists( $fieldname, $entry ) ? $entry[ $fieldname ] : "";
				if ( ! preg_match( $regexp, $v ) ) {
					return false;
				}
			}
		}

		return true;
	}


	function addMessage( $message ) {
		if ( defined( 'PHPUNIT_PAPERCITE_TESTSUITE' ) ) {
			print "[message] $message\n";
		}
		$this->error_messages[] = "<div>" . $message . "</div>";
	}


	// digfish: get textual footnotes

	function getTextualFootnotes() {
		return $this->textual_footnotes;
	}


	/**
	 * Get the bibtex data from an URI
	 */
	function getData( $biburis, $options ) {

		$timeout       = $options["timeout"];
		$processtitles = $options["process_titles"];
		$sslverify     = $options["ssl_check"];

		// Loop over the different given URIs
		$bibFile = false;
		$array   = explode( ",", $biburis );
		$result  = array();

		foreach ( $array as $biburi ) {
			// (1) Get the context
			$data          = false;
			$stringedFile  = false;
			$custom_prefix = "custom://";

			// Handles custom:// by adding the post number

			if ( ! Papercite::array_get( $this->cache, $biburi, false ) ) {
				if ( $stringedFile ) {
					// do nothing
				} elseif ( preg_match( '#^(ftp|http)s?://#', $biburi ) == 1 ) {
					$bibFile = $this->getCached( $biburi, $timeout, $sslverify );
				} else {
					$biburi  = preg_replace( "#\\.bib$#", "", $biburi );
					$bibFile = self::getDataFile( "$biburi", "bib", "bib", "application/x-bibtex", $options );
				}


				if ( $data === false && ! ( $bibFile && file_exists( $bibFile[0] ) ) ) {
					continue;
				}

				// Customize URIs depending on parsing options
				$biburi .= $processtitles ? "#pt=1" : "#pt=0";

				// (2) Parse the BibTeX
				if ( $data || file_exists( $bibFile[0] ) ) {
					if ( ! $data ) {
						$fileTS = filemtime( $bibFile[0] );


						$data = file_get_contents( $bibFile[0] );
					}

					if ( ! empty( $data ) ) {
						switch ( $this->options["bibtex_parser"] ) {
							case "pear": // Pear parser
								$this->_parser = new PaperciteStructures_BibTex( array(
									'removeCurlyBraces' => true,
									'extractAuthors'    => true,
									'processTitles'     => $processtitles
								) );
								$this->_parser->loadString( $data );
								$stat = $this->_parser->parse();

								if ( ! $stat ) {
									return $this->cache[ $biburi ] = false;
								}
								$this->cache[ $biburi ] = &$this->_parser->data;
								break;

							default: // OSBiB parser
								$parser = new PaperciteBibTexEntries();
								$parser->processTitles( $processtitles );
								if ( ! $parser->parse( $data ) ) {
									$this->cache[ $biburi ] = false;
									continue;
								} else {
									$this->cache[ $biburi ] = &$parser->data;
								}

						}


					}

				}

			} // end bibtex processing (not in cache)

			// Add to the list
			if ( Papercite::array_get( $this->cache, $biburi, false ) ) {
				$result[ $biburi ] = $this->cache[ $biburi ];
			}
		} // end loop over URIs

		return $result;
	}

	function listAllofAttribute( $attr ) {
		$entries          = $this->getEntries();
		$allEntriesByAttr = array_map( function ( $entry ) use ( $attr ) {
			$val = $entry[ $attr ];
			if ( is_object( $val ) && get_class( $val ) == 'PaperciteBibtexCreators' ) {
				$bibtexCreators = $val;
				$authors        = $bibtexCreators->toArray();

				return $authors;
			} else {
				return $val;
			}
		}, $entries );
		$allEntriesByAttr = array_flatten( $allEntriesByAttr, array() );
		$unique_values    = array_unique( $allEntriesByAttr );
		sort( $unique_values );

		return $unique_values;
	}

	function parseBibTexString( $data, $processtitles = true ) {
		$bibtexParsedData = null;
		if ( ! empty( $data ) ) {
			switch ( $this->options["bibtex_parser"] ) {
				case "pear": // Pear parser
					$this->_parser = new PaperciteStructures_BibTex( array(
						'removeCurlyBraces' => true,
						'extractAuthors'    => true,
						'processTitles'     => $processtitles
					) );
					$this->_parser->loadString( $data );
					$stat = $this->_parser->parse();

					if ( ! $stat ) {
						return false;
					}
					$bibtexParsedData = &$this->_parser->data;
					break;

				default: // OSBiB parser
					$parser = new PaperciteBibTexEntries();
					$parser->processTitles( $processtitles );
					if ( $parser->parse( $data ) ) {
						$bibtexParsedData = &$parser->data;
					}
			}


		}

		return $bibtexParsedData;

	}


	/**
	 * Get a writeable directory for caching remote bibtex files.
	 *
	 * @param string $type 'path' for local filepath, or 'url' for URL.
	 *
	 * @return string
	 */
	static function getCacheDirectory( $type = 'dir' ) {
		return getcwd() . '/papercite-cache';
	}

	/** Returns filename of cached version of given url
	 *
	 * @param url The URL
	 * @param timeout The timeout of the cache
	 */
	function getCached( $url, $timeout = 3600, $sslverify = false ) {
		// check if cached file exists
		$name     = strtolower( preg_replace( "@[/:]@", "_", $url ) );
		$dir_path = self::getCacheDirectory( 'path' );
		$file     = "$dir_path/$name.bib";

		// check if file date exceeds 60 minutes
		if ( ! ( file_exists( $file ) && ( filemtime( $file ) + $timeout > time() ) ) ) {
			// Download URL and process
			$urlContents = file_get_contents( $url );
			if ( empty( $urlContents ) ) {
				$this->addMessage( "Could not retrieve remote URL " . htmlentities( $url ) );

				return false;
			}


			// Everything is OK: store the contents

			if ( ! file_exists( $dir_path ) ) {
				mkdir( $dir_path );
			}

			if ( $urlContents ) {
				$f = fopen( $file, "wb" );
				fwrite( $f, $urlContents );
				fclose( $f );
			} else {
				$this->addMessage( "Could not retrieve remote URL " . htmlentities( $url ) );

				return null;
			}


			if ( ! $f ) {
				$this->addMessage( "Failed to write file " . $file . " - check directory permission according to your Web server privileges." );

				return false;
			}
		}

		$dir_url = self::getCacheDirectory( 'url' );

		return array( $file, $dir_url . '/' . $name );
	}


	/** Get entries fullfilling a condition (bibtex & bibfilter)
	 *
	 * @param $options the arguments of the shortcode
	 *
	 * @return the entries in the bibliography
	 *
	 */
	function getEntries( $options = array() ) {


		if ( func_num_args() == 0 ) {
			$options = $this->options;
		}
		// --- Filter the data
		$result = array();
		$dbs    = array();

		$entries = $this->getData( $options["file"], $options );

		if ( $entries === false ) {
			$this->addMessage( "[Could not find the bibliography file(s) with name [" . htmlspecialchars( $options["file"] ) . "]" );

			return false;
		}

		if ( array_key_exists( 'key', $options ) ) {
			// Select only specified entries
			$keys = preg_split( "-,-", $options["key"] );
			$a    = array();
			$n    = 0;

			$result = self::getEntriesByKey( $entries, $keys );

			if ( array_key_exists( "allow", $options ) || array_key_exists( "deny", $options ) || array_key_exists( "author", $options ) ) {
				$this->addMessage( "[papercite] Filtering by (key argument) is compatible with filtering by type or author (allow, deny, author arguments)", E_USER_NOTICE );
			}
		} else {
			// Based on the entry types
			$allow = Papercite::array_get( $options, "allow", "" );
			$deny  = Papercite::array_get( $options, "deny", "" );
			$allow = $allow ? preg_split( "-,-", $allow ) : array();
			$deny  = $deny ? preg_split( "-,-", $deny ) : array();

			$author_matcher = new PaperciteAuthorMatcher( Papercite::array_get( $options, "author", "" ) );


			foreach ( $entries as $key => &$outer ) {
				if ( is_array( $outer ) && $outer[0] == "__DB__" ) {
					$dbs[] = $outer[1];
				} else {
					foreach ( $outer as &$entry ) {
						$t = &$entry["entrytype"];
						if ( ( sizeof( $allow ) == 0 || in_array( $t, $allow ) ) && ( sizeof( $deny ) == 0 || ! in_array( $t, $deny ) ) && $author_matcher->matches( $entry ) && Papercite::userFiltersMatch( $options["filters"], $entry ) ) {
							$result[] = $entry;
						}
					}
				}
			}


		}

		return $result;
	}


	/**
	 * Check the different paths where papercite data can be stored
	 * and return the first match, starting by the preferred ones
	 *
	 * @param $relfile The file name
	 * @param $ext The extension for the file (file in folder)
	 * @param $folder The folder that contains the file (file in folder)
	 * @param $mimetype The mime-type (wordpress media)
	 *
	 * @return either false (no match), or an array with the full
	 * path and the URL
	 *
	 * This method searches:
	 * 1) In the wordpress medias
	 * 2) In the papercite folders
	 *
	 * @return FALSE if no match, an array (path, URL)
	 */
	static function getDataFile( $relfile, $ext, $folder, $mimetype, $options, $use_files = false ) {
		$curdir = PAPERCITE_CONTENT_DIR;

		if ( $use_files || $options["use_files"] ) {
			// Rel-file as usual
			$relfile = "$folder/$relfile.$ext";


			if ( file_exists( $curdir . "/papercite-data/$relfile" ) ) {
				return array( $curdir . "/papercite-data/$relfile", null );
			}

			$path = $curdir . "/$relfile";
			if ( file_exists( $path ) ) {
				return array( $path, null );
			}
		}

		// Nothin' found
		return false;
	}
}
