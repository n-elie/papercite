<?php
/**
 *
 * User: digfish
 * Date: 23-03-2019
 * Time: 12:12
 */

/**
 * Class MyConverter
 * converts the bibtex entries into different formats, e.g. JSON
 */
class MyConverter
{

    var $data;

    public function __construct($data)
    {
    	$this->data = $data;
    }

    public function setOneEntry() {
    	$this->entry = $this->data;
    }

	public function setEntries() {
		$this->entries = $this->data;
	}


	public function toJson($json_filename = '', $format = 'csl')
    {
        if (empty($json_filename)) {
            throw new Exception("No JSON filename specified");
        }

        if (empty($this->entries)) {
            throw new Exception("No entries to convert to Json!");
        }

        call_user_func(array($this,"to" . ucfirst($format). "Json"), $json_filename);
    }

    protected function toRawJson($json_filename)
    {
        file_put_contents($json_filename, json_encode($this->data, JSON_PRETTY_PRINT));
    }

    protected function toCslJson($json_filename)
    {
    	$csl_formatted = $this->convert();
        file_put_contents($json_filename, json_encode($csl_formatted, JSON_PRETTY_PRINT));
    }

    protected function convertToCslEntry($entry)
    {
        $cslEntry = new stdClass();
        if (empty($entry['cite'])) {
            $entry_id = md5($entry['bibtex']);
        } else {
            $entry_id = $entry['cite'];
        }
        $cslEntry->id = $entry_id;
        $cslEntry->type = $entry['entrytype'];
        $cslEntry->title = $entry['title'];
        $cslEntry->author = array();

        if (is_object($entry['author'])) {
            foreach ($entry['author']->creators as $creator) {
                $cslEntry->author[] = $this->toCslName($creator);
            }
        }

	    if (!empty($entry['year'])) {
		    $cslEntry->issued = $this->toCslDate( $entry['year'] );
	    }
        if (!empty($entry['isbn'])) {
            $cslEntry->ISBN = $entry['isbn'];
        }
        if (!empty($entry['address'])) {
            $cslEntry->{"event-place"} = $entry['address'];
        }
	    if (!empty($entry['mendeley-tags'])) {
		    $cslEntry->tags = $entry['mendeley-tags'];
	    }
        if (!empty($entry['url'])) {
            $cslEntry->URL = $entry['url'];
        }
	    if (!empty($entry['publisher'])) {
		    $cslEntry->publisher = $entry['publisher'];
	    }
        if (!empty($entry['pages'])) {
            $cslEntry->{"number-of-pages"} = $entry['pages'];
        }

        $cslEntry->editor = array();
        if (!empty($entry['editor']) && is_object($entry['editor'])) {
            foreach ($entry['editor']->creators as $creator) {
                $cslEntry->editor[] = $this->toCslName($creator);
            }
        }

	    if (!empty($entry['edition'])) {
		    $cslEntry->edition = $entry['edition'];
	    }
        return $cslEntry;
    }

    protected function toCslName($bibAuthor)
    {
        $cslAuthor = new stdClass();
        $cslAuthor->family = $bibAuthor['surname'];
        $cslAuthor->given = $bibAuthor['firstname'];
        $cslAuthor->suffix = '';
        $cslAuthor->{"dropping-particle"} = $bibAuthor['initials'];
        $cslAuthor->{"non-dropping-particle"} = $bibAuthor['prefix'];
        return $cslAuthor;
    }

    protected function toCslDate($bibDate)
    {
        $cslDate = new stdClass();
        $cslDate->raw = $bibDate;
        return $cslDate;
    }

    protected function convertEntries($entries=array())
    {
    	if (func_num_args() == 0) {
    		$entries = $this->entries;
	    }
        $cslData = array();
        foreach ($entries as $entry) {
            $cslData[] = $this->convertToCslEntry($entry);
        }
        return $cslData;
    }

    public function convert() {
	    if ( isset( $this->entries ) ) {
		    return $this->convertEntries( $this->entries  );
	    } else if ( isset( $this->entry ) ) {
		    return $this->convertToCslEntry( $this->entry );
	    }
    }
}
