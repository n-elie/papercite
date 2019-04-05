<?php
/**
 * Created by PhpStorm.
 * User: sam
 * Date: 23-03-2019
 * Time: 16:36
 * Adapter for generating  bibliographies in HTML using citeproc-php
 * @see \citeproc
 */

require dirname( __DIR__ ) . "/vendor/autoload.php";

use Seboettg\CiteProc\CiteProc;
use Seboettg\CiteProc\StyleSheet;

class CiteProcRenderer {

	var $jsonFilename;
	var $data;
	var $styleDefs;
	var $styleName;
	var $citeproc;
	var $language;


	private function getClientLanguage() {
		$default = "en-US";
		if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$header  = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			$matches = preg_split( '/\,/', $header );
			if ( count( $matches ) > 0 ) {
				return $matches[0];
			}
		}

		return $default;
	}


	public function setJsonSource( $jsonFilename ) {
		$this->jsonFilename = $jsonFilename;
		$this->data         = json_decode( file_get_contents( $this->jsonFilename ) );
	}

	public function setStyleDefs( $styleDefs ) {
		$this->styleDefs = $styleDefs;
		$htmlExtensions  = array( 'URL' => array( $this, 'renderURL' ) );
		$lang            = $this->getClientLanguage();

		$this->citeproc = new CiteProc( $this->styleDefs, $lang, $htmlExtensions );
	}

	public function setStyleName( $styleName ) {
		$this->styleName = $styleName;
		$this->styleDefs = StyleSheet::loadStyleSheet( $styleName );
	}


	public function init( $params = array() ) {
		if ( empty( $params['styleName'] ) && ! empty( $this->styleName ) ) {
			$styleName = $this->styleName;
		} else {
			$styleName = $params['styleName'];
		}

		if ( ! empty( $params['data'] ) ) {
			$this->data = $params['data'];
		}

		return $styleName;
	}

	public function cssStyles() {
		return $this->citeproc->renderCssStyles();
	}

	public function renderURL( $cslItem, $renderedText ) {
		if ( ! empty( $renderedText ) ) {
			return "<A href=\"{$cslItem->URL}\">" . htmlspecialchars( $renderedText ) . "</A>";
		} else {
			return "";
		}
	}

	public function bibliography( $params = array() ) {

		/*		if (empty($this->citeproc)) {
					$styleName = $this->init( $params );

					$this->setStyleName( $styleName );
					$this->setStyleDefs( $this->styleDefs );
				}*/
		if ( ! empty( $params['data'] ) ) {
			$this->data = $params['data'];
		}
		$dataObj = $this->data;

		$bibliography = $this->citeproc->render( $dataObj, "bibliography" );

		return $bibliography;
	}


	public function citation( $params = array() ) {
		$styleName = $this->init( $params );
		$this->setStyleName( $styleName );
		$this->setStyleDefs( $this->styleDefs );

		if ( ! empty( $params['data'] ) ) {
			$this->data = $params['data'];
		}

		$citation = $this->citeproc->render( array( $this->data ), "citation" );

		return $citation;
	}
}