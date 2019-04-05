<?php

namespace Papercite\Tests;
/**
 * Created by PhpStorm.
 * User: sam
 * Date: 23-03-2019
 * Time: 12:31
 */

if ( ! defined( 'PAPERCITE_ROOT' ) ) {
	require_once "papercite-root.php";
}
if ( ! defined( 'PHPUNIT_PAPERCITE_TESTSUITE' ) ) {
	define( 'PHPUNIT_PAPERCITE_TESTSUITE', 1 );
}

if ( ! defined( 'PAPERCITE_CONTENT_DIR' ) ) {
	define( 'PAPERCITE_CONTENT_DIR', dirname( __DIR__ ) );
}

#require_once dirname(__FILE__) . '/common.inc.php';
require_once __DIR__ . "/../papercite.classes.php";
require_once __DIR__ . "/../utils/my-converter.php";
require_once __DIR__ . "/../csl/citeproc-renderer.php";


use Papercite;
use PHPUnit\Framework\TestCase;

class BibTexFetchTest extends TestCase {

	var $papercite;
	var $entries;

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

	public function setUp() {

		$this->papercite = new Papercite();
		$this->papercite->init();
		$this->papercite->options['file']          = 'http://digfish.org/shared/converted.bib';
		$this->papercite->options["bibtex_parser"] = "pear";
	}


	public function testListAllofAttribute() {
		$allFieldTypes = $this->papercite->listAllOfAttribute( 'author' );
		$firstFive     = array_slice( $allFieldTypes, 0, 5 );
		//var_dump($allFieldTypes);
		$this->assertNotEmpty( $allFieldTypes );
	}

	public function testPaperciteParseBibTexString() {
		$this->entries = $this->papercite->parseBibTexString( self::$data );
		//var_dump($this->entries);
		$this->assertNotNull( $this->entries );
	}

	public function testPaperciteFormatBibliographyItem() {
		echo __METHOD__ . "\n";
		$this->entries = $this->papercite->parseBibTexString( self::$data );
		$format        = $this->papercite->options['format'];
		$bib_html      = $this->papercite->formatBibliographyItems( $this->entries, $format, $this->papercite->options );
		$this->assertNotEmpty( $bib_html );
		//echo $bib_html;
	}


	public function testPaperciteShowCslEntries() {
		echo __METHOD__, "\n";
		$this->papercite->options['format_type'] = 'csl';
		$this->papercite->options['format']      = 'apa';
		$this->papercite->options['show_links']  = false;
		$format                                  = $this->papercite->options['format'];
		$this->entries                           = $this->papercite->getEntries( $this->papercite->options );
		//$this->entries = $this->papercite->parseBibTexString(self::$data);
		$bib2tplOptions = $this->papercite->getBib2TplOptions( $this->papercite->options );
		$html_entries   = $this->papercite->showEntries( $this->entries, $bib2tplOptions, false, $this->papercite->options["bibtex_template"], null, null );
		echo "CSL method:\n";
		print ( $html_entries );
		$this->assertNotEmpty( $html_entries );
	}


}
