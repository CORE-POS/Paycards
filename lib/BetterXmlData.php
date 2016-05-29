<?php

class BetterXmlData
{
    public function __construct($str)
    {
        $this->dom = new DOMDocument();
        $this->dom->loadXML($str);
        $this->xpath = new DOMXPath($this->dom);
    }

    public function query($query, $as_array=false)
    {
        $res = $this->xpath->query($query);
        // bad query
        if ($res === false) {
            return false;
        }
        // no result
        if ($res->length == 0) {
            return false;
        }
        // one result
        if ($res->length == 1) {
            return $res->item(0)->textContent;
        }

        // multiple results:
        // send as array or series of lines
        if ($as_array) {
            $ret = array();
            foreach ($res as $node) {
                $ret[] = $node->textContent;
            }
            return $ret;
        } else {
            $ret = '';
            foreach ($res as $node) {
                $ret .= $node->textContent . "\n";
            }
            return $ret;
        }
    }
}

