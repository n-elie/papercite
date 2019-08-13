<?php
/**
 * Papercite within Wordpress functionality
 * User: digfish
 */

include_once "papercite.classes.php";

class WpPapercite extends Papercite {

	//digfish: extra textual footnotes
	var $textual_footnotes = array();
	var $textual_footnotes_counter = 0;
	var $cssStyles;
	var $papercite_table_name;
	var $papercite_table_name_url;


	/**
	 * Init is called before the first callback
	 */
	function init() {

	    global $wpdb;

		parent::init();

		// i18n
		// http://codex.wordpress.org/I18n_for_WordPress_Developers#Translating_Plugins
		$plugin_dir = basename( dirname( __FILE__ ) );
		load_plugin_textdomain( 'papercite', false, $plugin_dir );

		// Get general preferences & page wise preferences
		$pOptions = get_option( 'papercite_options' );
		if ( ! empty( $pOptions ) ) {

			// Use preferences if set to override default values
			if ( is_array( $pOptions ) ) {
				foreach ( self::$option_names as &$name ) {
					if ( array_key_exists( $name, $pOptions ) && $pOptions[ $name ] !== "" ) {
						$this->options[ $name ] = $pOptions[ $name ];
					}
				}
			}

			// Use custom field values "papercite_options"
			$option_fields = get_post_custom_values( "papercite_options" );
			if ( $option_fields && sizeof( $option_fields ) > 0 ) {
				foreach ( $option_fields as $field ) {
					$matches = array();
					preg_match_all( "#^\s*([\w\d-_]+)\s*=\s*(.+)$#m", $field, $matches, PREG_SET_ORDER );
					foreach ( $matches as &$match ) {
						$this->options[ $match[1] ] = trim( $match[2] );
					}
				}
			}


			// Upgrade if needed
			if ( $this->options["bibtex_parser"] == "papercite" ) {
				$this->options["bibtex_parser"] = "osbib";
			}
		}

		$this->papercite_table_name = $wpdb->prefix . "plugin_papercite";
		$this->papercite_table_name_url = $this->papercite_table_name . "_url";

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
			$req = wp_remote_get( $url, array( 'sslverify' => $sslverify ) );
			if ( is_wp_error( $req ) ) {
				$this->addMessage( "Could not retrieve remote URL " . htmlentities( $url ) . ": " . $req->get_error_message() );

				return false;
			}

			$code = $req["response"]["code"];
			if ( ! preg_match( "#^2\d+$#", $code ) ) {
				$this->addMessage( "Could not retrieve remote URL " . htmlentities( $url ) . ": Page not found / {$code} error code" );

				return false;
			}

			// Everything is OK: retrieve the body of the HTTP answer
			$body = wp_remote_retrieve_body( $req );
			if ( ! file_exists( $dir_path ) ) {
				mkdir( $dir_path );
			}

			if ( $body ) {
				$f = fopen( $file, "wb" );
				fwrite( $f, $body );
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

	/**
	 * Get a writeable directory for caching remote bibtex files.
	 *
	 * @param string $type 'path' for local filepath, or 'url' for URL.
	 *
	 * @return string
	 */
	static function getCacheDirectory( $type = 'dir' ) {
		$uploads_dir = wp_upload_dir();

		$base = 'url' === $type ? $uploads_dir['baseurl'] : $uploads_dir['basedir'];

		return apply_filters( 'papercite_cache_directory', $base . '/papercite-cache' );
	}


	static function getCustomDataDirectory() {
		global $wpdb;
		$url = WP_CONTENT_URL;
		if ( is_multisite() ) {
			$subpath = '/blogs.dir/' . $wpdb->blogid . "/files";
			$url     .= $subpath;
		}

		return $url . "/papercite-data";
	}

	/**
	 * Check the different paths where papercite data can be stored
	 * and return the first match, starting by the preferred ones
	 *
	 * @param $filenamepart The file name (without the extension)
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
	static function getDataFile( $filenamepart, $ext, $folder, $mimetype, $options, $use_files = false ) {
		global $wpdb;

		if ( $options["use_media"] ) {
			// Search for files in media
			$filter = array(
				'name'      => $filenamepart,
				'post_type' => 'attachment'
			);
			if ( ! empty( $mimetype ) ) {
				$filter["post_mime_type"] = $mimetype;
			}
			$posts = get_posts( $filter );

			if ( sizeof( $posts ) > 0 ) {
				// We should have only one match (names are unique) ?
				$path = get_attached_file( $posts[0]->ID );
				$url  = wp_get_attachment_url( $posts[0]->ID );

				return array( $path, $url );
			}
		}

		if ( $use_files || $options["use_files"] ) {
			// Rel-file as usual
			$filename = "$filenamepart.$ext";
			$relfile  = "$folder/$filename";

			// Multi-site case
			if ( is_multisite() ) {
				$subpath = '/blogs.dir/' . $wpdb->blogid . "/files/papercite-data/$relfile";
				$path    = WP_CONTENT_DIR . $subpath;
				if ( file_exists( $path ) ) {
					return array( $path, WP_CONTENT_URL . $subpath );
				}
			}

			// check for CSL files in papercite-data/csl
			if ( $ext == 'csl' && file_exists( WP_CONTENT_DIR . "/papercite-data/csl/$filename" ) ) {
				return array(
					WP_CONTENT_DIR . "/papercite-data/csl/$filename",
					WP_CONTENT_URL . "/papercite-data/csl/$filename"
				);
			}

			if ( file_exists( WP_CONTENT_DIR . "/papercite-data/$relfile" ) ) {
				return array(
					WP_CONTENT_DIR . "/papercite-data/$relfile",
					WP_CONTENT_URL . "/papercite-data/$relfile"
				);
			}

			$path = plugin_dir_path( __FILE__ ) . "/$relfile";
			if ( file_exists( $path ) ) {
				return array( $path, plugin_dir_url( $path ) );
			}
		}

		// Nothin' found
		return false;
	}

	/**
	 * Returns the content of a file (disk or post data)
	 *
	 * @see Papercite::getDataFile
	 */
	static function getContent( $relfile, $ext, $folder, $mimetype, $options, $use_files = false ) {
		// Handles custom://
		$custom_prefix = "custom://";
		if ( Papercite::startsWith( $relfile, $custom_prefix ) ) {
			$key  = substr( $relfile, strlen( $custom_prefix ) );
			$data = get_post_custom_values( "papercite_$key" );
			if ( $data ) {
				return $data[0];
			}
		}

		// Normal behavior
		$data = self::getDataFile( $relfile, $ext, $folder, $mimetype, $options, $use_files );
		if ( $data ) {
			return file_get_contents( $data[0] );
		}

		return false;
	}

	/**
	 * Get the bibtex data from an URI
	 */
	function getData( $biburis, $options ) {
    global $wpdb;

		$timeout       = isset( $options["timeout"] ) ? $options['timeout'] : $this->options['timeout'];
		$processtitles = isset( $options["process_titles"] ) ? $options["process_titles"] : $this->options['process_titles'];
		$sslverify     = isset( $options["ssl_check"] ) ? $options["ssl_check"] : $this->options['ssl_check'];;

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
			if ( Papercite::startsWith( $biburi, $custom_prefix ) ) {
				$stringedFile = true;
				$key          = substr( $biburi, strlen( $custom_prefix ) );
				$biburi       = "post://" . get_the_ID() . "/" . $key;
				$data         = get_post_custom_values( "papercite_$key" );
				if ( $data ) {
					$data = $data[0];
				}
			}

			if ( ! Papercite::array_get( $this->cache, $biburi, false ) ) {
				if ( $stringedFile ) {
					// do nothing
				} elseif ( preg_match( '#^(ftp|http)s?://#', $biburi ) == 1 ) {
					$bibFile = $this->getCached( $biburi, $timeout, $sslverify );
				} else {
					$biburi  = preg_replace( "#\\.bib$#", "", $biburi );
					$bibFile = $this->getDataFile( "$biburi", "bib", "bib", "application/x-bibtex", $options );
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

						// Check if we don't have the data in cache
						if ( $this->useDb() ) {

							$oldurlid = - 1;
							// We use entrytype as a timestamp
							$row = $wpdb->get_row( $wpdb->prepare( "SELECT urlid, ts FROM {$this->papercite_table_name_url} WHERE url=%s", $biburi ) );
							if ( $row ) {
								$oldurlid = $row->urlid;
								if ( $row->ts >= $fileTS ) {
									$result[ $biburi ] = $this->cache[ $biburi ] = array( "__DB__", $row->urlid );
									continue;
								}
							}
						}

						$data = file_get_contents( $bibFile[0] );
					}

					if ( ! empty( $data ) ) {
						switch ( $options["bibtex_parser"] ) {
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
								break;
						}


						// Save to DB
						if ( ! $stringedFile && $this->useDb() ) {
							// First delete everything
							if ( $oldurlid >= 0 ) {
								$code = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->papercite_table_name} WHERE urlid=%d", $oldurlid ) );
								if ( $code === false ) {
									break;
								}
							} else {
								$code = $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->papercite_table_name_url}(url, ts) VALUES (%s, 0)", $biburi ) );
								if ( $code === false ) {
									break;
								}
								$oldurlid = $wpdb->insert_id;
							}

							$code = true;
							foreach ( $this->cache[ $biburi ] as &$value ) {
								if ( isset( $value['year'] ) ) {
									$year = is_numeric( $value["year"] ) ? intval( $value["year"] ) : - 1;
								} else {
									$year = - 1;
								}
								$statement = $wpdb->prepare(
									"REPLACE {$this->papercite_table_name}(urlid, bibtexid, entrytype, year, data) VALUES (%s,%s,%s,%s,%s)",
									$oldurlid,
									isset( $value['cite'] ) ? $value["cite"] : '',
									isset( $value["entrytype"] ) ? $value["entrytype"] : '',
									$year,
									maybe_serialize( $value )
								);
								$code      = $wpdb->query( $statement );
								if ( $code === false ) {
									break;
								}
							}
							if ( $code !== false ) {
								$statement = $wpdb->prepare( "REPLACE INTO {$this->papercite_table_name_url}(url, urlid, ts) VALUES(%s,%s,%s)", $biburi, $oldurlid, $fileTS );
								$code      = $wpdb->query( $statement );
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

	// Get the subset of keys present in the entries
	static function getEntriesByKey( &$entries, &$keys ) {

		$n     = 0;
		$a     = array();
		$dbs   = array();
		$found = array();
		foreach ( $entries as $key => &$outer ) {
			if ( is_array( $outer ) && $outer[0] == "__DB__" ) {
				$dbs[] = $outer[1];
			} else {
				foreach ( $outer as $entry ) {
					if ( in_array( $entry["cite"], $keys ) ) {
						$a[]     = $entry;
						$found[] = $entry["cite"];
						$n       = $n + 1;
						// We found everything, early break
						if ( $n == sizeof( $keys ) ) {
							break;
						}
					}
				}
			}
			if ( $n == sizeof( $keys ) ) {
				break;
			}
		}

		// Case where we have to check the db
		$unfound = array_diff( $keys, $found );
		if ( $dbs && sizeof( $unfound ) > 0 ) {
			$dbs = Papercite::getDbCond( $dbs );
			foreach ( $unfound as &$v ) {
				$v = '"' . $wpdb->escape( $v ) . '"';
			}
			$keylist = implode( ",", $unfound );
			$st      = "SELECT data FROM {$this->papercite_table_name} WHERE $dbs and bibtexid in ($keylist)";
			$val     = $wpdb->get_col( $st );
			if ( $val !== false ) {
				foreach ( $val as &$data ) {
					$a[] = maybe_unserialize( $data );
				}
			}
		}

		return $a;
	}

	//! Get a db condition subquery
	static function getDbCond( &$dbArray ) {
		global $wpdb;

		$dbs = array();
		foreach ( $dbArray as &$db ) {
			$dbs[] = "\"" . $wpdb->escape( $db ) . "\"";
		}
		$dbs = implode( ",", $dbs );
		if ( $dbs ) {
			$dbs = "urlid in ($dbs)";
		}

		return $dbs;
	}

	/** Get entries fullfilling a condition (bibtex & bibfilter)
	 *
	 * @param $options the arguments of the shortcode
	 *
	 * @return the entries in the bibliography
	 *
	 */
	function getEntries( $options = array() ) {


		if ( func_num_args() < 1 ) {
			$options = $this->options;
		}
		// --- Filter the data
		$entries = $this->getData( $options["file"], $options );
		if ( $entries === false ) {
			$this->addMessage( "[Could not find the bibliography file(s) with name [" . htmlspecialchars( $options["file"] ) . "]" );

			return false;
		}
		if ( array_key_exists( 'key', $options ) ) {
			// Select only specified entries
			$keys   = preg_split( "-,-", $options["key"] );
			$a      = array();
			$n      = 0;
			$result = papercite::getEntriesByKey( $entries, $keys );
			if ( array_key_exists( "allow", $options ) || array_key_exists( "deny", $options ) || array_key_exists( "author", $options ) ) {
				$this->addMessage( "[papercite] Filtering by (key argument) is compatible with filtering by type or author (allow, deny, author arguments)", E_USER_NOTICE );
			}
		} else {
			// Based on the entry types
			$allow          = Papercite::array_get( $options, "allow", "" );
			$deny           = Papercite::array_get( $options, "deny", "" );
			$allow          = $allow ? preg_split( "-,-", $allow ) : array();
			$deny           = $deny ? preg_split( "-,-", $deny ) : array();
			$author_matcher = new PaperciteAuthorMatcher( Papercite::array_get( $options, "author", "" ) );
			$result         = array();
			$dbs            = array();

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
			// --- Add entries from database
			if ( $dbs ) {
				$dbCond = $this->getDbCond( $dbs );
				// Handles year and entry type by direct SQL
				foreach ( $allow as &$v ) {
					$v = '"' . $wpdb->escape( $v ) . '"';
				}
				$allowCond = $allow ? "and entrytype in (" . implode( ",", $allow ) . ")" : "";
				foreach ( $deny as &$v ) {
					$v = '"' . $wpdb->escape( $v ) . '"';
				}
				$denyCond = $deny ? "and entrytype not in (" . implode( ",", $deny ) . ")" : "";
				// Retrieve and filter further
				$st   = "SELECT data FROM {$this->papercite_table_name} WHERE $dbCond $denyCond $allowCond";
				$rows = $wpdb->get_col( $st );
				if ( $rows ) {
					foreach ( $rows as $data ) {
						$entry = maybe_unserialize( $data );
						if ( $author_matcher->matches( $entry ) && Papercite::userFiltersMatch( $options["filters"], $entry ) ) {
							$result[] = $entry;
						}
					}
				}
			}
		}

		return $result;
	}

	//! Get all the error messages and clean the stack
	function getAndCleanMessages() {
		if ( sizeof( $this->error_messages ) == 0 ) {
			return "";
		}

		$s = "<div class='papercite_errors'>";
		foreach ( $this->error_messages as $message ) {
			$s .= $message;
		}
		$s                    .= "</div>";
		$this->error_messages = array();

		return $s;
	}

	function end_bibshow() {


		// select from cites
		if ( sizeof( $this->bibshows ) == 0 ) {
			return "";
		}
		// Remove the array from the stack
		$data       = array_pop( $this->bibshows );
		$cites      = array_pop( $this->cites );
		$tplOptions = array_pop( $this->bibshow_tpl_options );
		$options    = array_pop( $this->bibshow_options );
		$refs       = array();


		$dbs = self::getDbCond( $data["__DB__"] );


		// Order the citations according to citation order
		// (might be re-ordered latter)
		foreach ( $cites as $key => &$cite ) {
			// Search
			if ( ( ! isset( $data[ $key ] ) || ! $data[ $key ] ) && $dbs ) {
				$val = $wpdb->get_var( $wpdb->prepare( "SELECT data FROM {$this->papercite_table_name} WHERE $dbs and bibtexid=%s", $key ) );
				if ( $val !== false ) {
					$refs[ $cite[0] ] = maybe_unserialize( $val );
				}
			} else {
				$refs[ $cite[0] ] = $data[ $key ];
			}

			$refs[ $cite[0] ]["pKey"] = $cite[1];
			// just in case
			$refs[ $cite[0] ]["cite"] = $key;
		}

		ksort( $refs );

		return $this->showEntries( array_values( $refs ), $tplOptions, true, $options["bibshow_template"], $options["format"], "bibshow", $options);
	}


	function showTextualFootnotes() {
		$post_id = '0';
		if ( func_num_args() == 1 ) {
			$post_id = func_get_arg( 0 );
		}
		$buf = '<UL class="ppc_footnotes" style="list-style: none">';
		foreach ( $this->getTextualFootnotes() as $id => $content ) {
			$buf .= "<li><a class='ppc_footnote' name='fn_" . $post_id . '_' . $id . "'></a>(<sup>$id</sup>) $content</li>";
		}
		$buf .= '</UL>';

		return $buf;
	}

	function processTextualFootnotes( &$matches ) {
		$post_id = "0";
		if ( func_num_args() == 2 ) {
			$post_id = func_get_arg( 1 );
		}
		//$ft_id = ++$this->textual_footnotes_counter;
		$ft_id                             = ++ $this->counter;
		$this->textual_footnotes[ $ft_id ] = $matches[1];

		//d($this->textual_footnotes);

		return "<A href='#fn_" . $post_id . '_' . $ft_id . "'>(<sup>$ft_id</sup>)</A>";

	}

	/**
	 * Show a set of entries
	 *
	 * @param $refs
	 * @param $bib2tplOptions
	 * @param $getKeys
	 * @param $mainTpl
	 * @param $formatTpl
	 * @param $mode
	 *
	 * @return mixed
	 */
	function showEntries( $refs, $bib2tplOptions, $getKeys, $mainTpl, $formatTpl, $mode, $options = array() ) {

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

		//require_once "bib2tpl/bibtex_converter.php";

		$bibtexEntryTemplate = new PaperciteBibtexEntryFormat( $format );

		// Gives a distinct ID to each publication (i.e. to display the corresponding bibtex)
		// in the reference list
		if ( $refs ) {
			foreach ( $refs as &$entry ) {
				$entry["papercite_id"] = $this->counter ++;
			}
		}

		// Convert (also set the citation key)
		$bib2tpl = new TplConverter( $bib2tplOptions, $main, $bibtexEntryTemplate );
		$bib2tpl->setGlobal( "WP_PLUGIN_URL", WP_PLUGIN_URL );
		$bib2tpl->setGlobal( "PAPERCITE_DATA_URL", self::getCustomDataDirectory() );

		$r = array();

		if ( isset( $options['format_type'] ) && $options['format_type'] == 'csl' ) {
			$cslfile = self::getContent( $options['format'], "csl", CSL_STYLES_LOCATION, "text/x-csl", $options, true );
			if ( empty( $cslfile ) ) {
				$fallback = "apa";
				$this->addMessage( "Couldn't find CSL file  {$options['format']}.csl. <br> Resorting to $fallback ." );
				$cslfile_location  = plugin_dir_path( __FILE__ ) . CSL_STYLES_LOCATION . "/$fallback.csl";
				$cslfile           = file_get_contents( $cslfile_location );
				$options['format'] = $fallback;
			}
			$csl_defs               = $cslfile;
			$bib2csl                = new CslConverter();
			$csl_collection         = $bib2csl->convert( $refs );
			$cslrenderer            = new CiteProcRenderer();
			$cslrenderer->styleName = $options['format'];
			$cslrenderer->setStyleDefs( $csl_defs );
			$r['data']       = $refs;
			$r['text']       = $cslrenderer->bibliography( array( 'data' => $csl_collection ) );
			$this->cssStyles = $cslrenderer->cssStyles();

            // assign the numeric indexes of the references for the citations
             $i=0;

             // FIXME: the ref numbers are not being associated with their corresponding footnotes (the anchor name is not being generated !!! see $this->citeproc - the bibliography is being generated by a standalone function in citeproc that does not receive the ref numbers ! )
			foreach ($refs as &$ref) {

				$keyValue = $ref['papercite_id']+1;
				//$key = substr_replace($ref["pKey"],$keyValue,9,3) ;
                $key = $ref['pKey'];
				$this->keys[] = $key;
				if ( $options["show_links"] ) {
					$this->keyValues[] = "<a class=\"papercite_bibcite\" href=\"#paperkey_$keyValue\">{$keyValue}</a>";
				} else {
					$this->keyValues[] = $keyValue;
					}
			}



		} else {

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

	/**
	 * This does two things:
	 * -dynamically creates html form based on parameters (author and menutype)
	 * -rebuilds command which is then sent as the bibtex command
	 *  TODO: add CSL support (it's working only with [bibtex] short tag!)
	 * @param unknown $options The arguments
	 *
	 * @return multitype:string The output of the bibfilter shortcode
	 */
	function bibfilter( $options ) {
		// create form with custom types and authors
		global $post, $papercite;

		$selected_author = false;
		$selected_type   = false;

		$original_authors = Papercite::array_get( $options, "author", "" );

		$original_allow   = Papercite::array_get( $options, "allow", "" );

		// filter according to the values selected on dropdowns
		if ( isset( $_POST ) && ( Papercite::array_get( $_POST, "papercite_post_id", 0 ) == $post->ID ) ) {
			if ( isset( $_POST["papercite_author"] ) && ! empty( $_POST["papercite_author"] ) ) {
				$selected_author = ( $options["author"] = $_POST["papercite_author"] );
			}

			if ( isset( $_POST["papercite_allow"] ) && ! empty( $_POST["papercite_allow"] ) ) {
				$selected_type = ( $options["allow"] = $_POST["papercite_allow"] );
			}
		}

		$result = $this->getEntries( $options );
		//d($selected_author,$selected_type);
		ob_start();
		?>
		<?php  ?>

        <form method="post" accept-charset="UTF-8">
            <input type="hidden" name="papercite_post_id" value="<?php echo $post->ID ?>">
            <table style="border-top: solid 1px #eee; border-bottom: solid 1px #eee; width: 100%">
                <tr>
                    <td>Authors:</td>
                    <td><select name="papercite_author" id="papercite_author">
                            <option value="">ALL</option>
							<?php
							if ( empty( $original_authors ) ) {
								// fill in with all the authors
								$author_results = $papercite->listAllofAttribute( 'author' );

								$surnames = array();
								foreach ( $author_results as $author ) {

									$surnames [] = $author;
								}
								$authors = $surnames;
							} else {
								$authors = preg_split( "#\s*\\|\s*#", $original_authors );
							};
							if ( Papercite::array_get( $options, "sortauthors", 0 ) ) {
								sort( $authors );
							}

							foreach ( $authors as $author ) {
								print "<option value=\"" . htmlentities( $author, ENT_QUOTES, "UTF-8" ) . "\"";
								if ( $selected_author == $author ) {
									print " selected=\"selected\"";
								}
								print ">$author</option>";
							}
							?>
                        </select>
                    </td>

                    <td>Type:</td>
                    <td><select name="papercite_allow" id="papercite_type">
                            <option value="">ALL</option>
							<?php
							if ( empty( $original_allow ) ) {
								$types = $papercite->listAllofAttribute( 'entrytype' );
							} else {
								$types = preg_split( "#\s*,\s*#", $original_allow );
							}

							foreach ( $types as $type ) {
								print "<option value='" . htmlentities( $type, ENT_QUOTES, "UTF-8" ) . "' ";
								if ( $selected_type == $type ) {
									print " selected=\"selected\"";
								}
								print ">" . papercite_bibtype2string( $type ) . "</option>";
							}
							?>
                        </select></td>
                    <td><input type="submit" value="Filter"/></td>
                </tr>
            </table>

        </form>

		<?php

		$entries_output = $this->showEntries(
			$result, $this->getBib2TplOptions( $options ), false, $options["bibtex_template"], $options["format"], "bibtex"
		);


		return ob_get_clean() . $entries_output;
	}


}