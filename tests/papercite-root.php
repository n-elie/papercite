<?php
/**
 * Created by PhpStorm.
 * User: sam
 * Date: 03-04-2019
 * Time: 17:21
 */

if ( ! defined( 'PAPERCITE_ROOT' ) ) {
	define( 'PAPERCITE_ROOT', dirname( __DIR__ ) );
}

echo "Running " . __FILE__ . " with curdir at " . getcwd() . "\n";

echo "PAPERCITE_ROOT is " . PAPERCITE_ROOT . "\n";

if ( file_exists( PAPERCITE_ROOT . '/vendor/autoload.php' ) && class_exists( 'Composer\Autoload\ClassLoader' ) === false ) {
	echo "Loading vendor/autoload.php\n";
	require_once( PAPERCITE_ROOT . '/vendor/autoload.php' );
}
