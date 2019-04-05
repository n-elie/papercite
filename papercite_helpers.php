<?php
/**
 * Created by PhpStorm.
 * User: sam
 * Date: 25-03-2019
 * Time: 12:53
 */

/**
 * Flatten a multi-dimensional array (@see https://davidwalsh.name/flatten-nested-arrays-php)
 *
 * @param $array
 * @param $return
 *
 * @return array
 */
function array_flatten( $array, $return ) {
	for ( $x = 0; $x <= count( $array ); $x ++ ) {
		if ( isset( $array[ $x ] ) && is_array( $array[ $x ] ) ) {
			$return = array_flatten( $array[ $x ], $return );
		} else {
			if ( isset( $array[ $x ] ) ) {
				$return[] = $array[ $x ];
			}
		}
	}

	return $return;
}


/**
 * list all avaiable formatting files
 *
 * @param $format_type tpl or csl (default 'tpl')
 *
 * @return a list with the filenames without the extensions
 */
function papercite_list_formats( $format_type = 'tpl' ) {
	$path = null;
	switch ( $format_type ) {
		case 'tpl':
			$path = plugin_dir_path( __FILE__ ) . "/format";
			break;
		case 'csl':
			$path = plugin_dir_path( __FILE__ ) . "/vendor/citation-style-language/styles-distribution";
			break;
	}
	$formats_list = list_files( $path );

	return array_map( function ( $tpl_filename ) {
		return pathinfo( $tpl_filename, PATHINFO_FILENAME );
	}, $formats_list );

}

function papercite_list_csl_formats() {
	return papercite_list_formats( 'csl' );

}
