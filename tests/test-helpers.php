<?php

namespace Papercite\Tests;
// See http://wp-cli.org/blog/plugin-unit-tests.html
require_once 'common.inc.php';
require_once PAPERCITE_ROOT . "/papercite_helpers.php";

class HelpersTest extends PaperciteTestCase {


	public function testPaperciteOptionsListFormats() {
		echo __METHOD__;
		$cslFiles = papercite_list_formats( 'csl' );
		//var_dump($cslFiles);
		$this->assertNotEmpty( $cslFiles );
	}


}

