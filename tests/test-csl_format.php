<?php

namespace Papercite\Tests;
// See http://wp-cli.org/blog/plugin-unit-tests.html
require_once dirname( __FILE__ ) . '/common.inc.php';

use DOMXPath;


class CslFormatTest extends PaperciteTestCase {
	static $data = <<<EOF
@book{aalves2013
 , author = {Alves, Adalberto}
 , edition = {1}
 , editor = {Imprensa Nacional Casa da Moeda}
 , isbn = {9789722721790}
 , keywords = {arabico,arabismo,dicionário,reference}
 , mendeley-tags = {arabico,arabismo,dicionário,reference}
 , publisher = {Imprensa Nacional Casa da Moeda}
 , title = {Dicionário de Arabismos da Língua Portuguesa - Adalberto Alves - Google Livros}
 , url = {https://books.google.pt/books?id=LzveAgAAQBAJ&printsec=frontcover&hl=pt-PT&source=gbs_vpt_buy#v=onepage&q&f=false}
 , year = {2013}
}
EOF;


	function testBibtexToCslOneEntry() {
		echo( __METHOD__ );
		$doc = $this->process_post( "[bibtex file=custom://data format=apa format_type=csl bibtex_parser=pear]", self::$data );

		echo( $doc->saveHTML() );

		$xpath   = new DOMXpath( $doc );
		$entries = $xpath->evaluate( "//div[@class = 'csl-entry']" );
		$entry   = $entries->item( 0 );
		$this->assertTrue( $entries->length == 1, "There were {$entries->length} entries detected - expected 1" );

	}

	function testBibtexToCslBibtexFile() {
		echo( __METHOD__ );
		$doc = $this->process_post( "[bibtex file=http://digfish.org/shared/converted.bib format=apa format_type=csl bibtex_parser=pear]" );

		echo( $doc->saveHTML() );

		$xpath   = new DOMXpath( $doc );
		$entries = $xpath->evaluate( "//div[@class = 'csl-entry']" );

		$this->assertTrue( $entries->length > 0, "There were no entries detected" );

	}


}

