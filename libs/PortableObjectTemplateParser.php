<?php

namespace NetDesign;

/**
 * Parser class for .pot files.
 */
class PortableObjectTemplateParser {
    protected $strings;
    /**
     * Parses the contents of a .pot file.
     *
     * @param string $contents
     * @return array
     */
    public function parseString($contents) {
        $this->strings = array();

        // Check if it contains strings
        $matched = preg_match_all(
            '/(msgid\s+("([^"]|\\\\")*?"\s*)+)\s+' .
            '(msgstr\s+("([^"]|\\\\")*?"\s*)+)/',
            $contents, $matches
        );
        if (!$matched) {
            return array();
        }
        // Get all msgids and msgstrs.
        for ($i = 0; $i < $matched; $i++) {
            $msgid = preg_replace(
                '/\s*msgid\s*"(.*)"\s*/s', '\\1', $matches[1][$i]);
            $msgstr= preg_replace(
                '/\s*msgstr\s*"(.*)"\s*/s', '\\1', $matches[4][$i]);
            $msgid = $this->prepare($msgid);
            $msgstr = $this->prepare($msgstr);

            if (empty($msgid)) continue;
            $this->strings[$msgid] = $msgstr;
        }

        // Flag $filename as loaded
        return $this->strings;
    }
    /**
     * Loads the strings from a .pot file.
     * @param string $file
     * @return array
     */
    public function parseFile($filename) {
        // Check if $filename exists
        if (!file_exists($filename)) {
            return array();
        }

        // Read the file
        $contents = file_get_contents($filename);
        return $this->parseString($contents);
    }
    /**
     * Special character replacement (for reading from .pot files).
     *
     * @param string $string
     * @return string
     */
    public function prepare($string) {
        $smap = array('/"\s+"/', '/\\\\n/', '/\\\\r/', '/\\\\t/', '/\\\\"/');
        $rmap = array('', "\n", "\r", "\t", '"');
        return (string) preg_replace($smap, $rmap, $string);
    }
    /**
     * Special character replacement (for writing to .pot files).
     *
     * @param string $string
     * @return string
     */
    public function unprepare($string) {
        $rmap = array('/"\s+"/', '/\\\\n/', '/\\\\r/', '/\\\\t/', '/\\\\"/');
        $smap = array('', "\n", "\r", "\t", '"');
        return (string) preg_replace($smap, $rmap, $string);
    }
}