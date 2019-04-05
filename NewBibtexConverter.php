<?php
/**
 * Created by PhpStorm.
 * User: sam
 * Date: 03-04-2019
 * Time: 10:41
 */


interface NewBibtexConverter {
	/**
	 * Set a global variable
	 */
	function setGlobal( $name, $value );

	/**
	 * Converts the parsed data stored in a collection structure to a string in the devised target format (eg HTML)
	 *
	 * @access public
	 *
	 * @param string $entries parsed bibtex entries in their data structure
	 *
	 *
	 * @return resulting string (eg HTML)
	 */
	function convert( $entries );


}