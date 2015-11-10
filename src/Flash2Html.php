<?php
/**
 * This class can convert HTML for Flash to regular HTML.
 *
 * @author  Gonzalo Chumillas <gchumillas@email.com>
 * @license https://github.com/soloproyectos-php/flash2html/blob/master/LICENSE The MIT License (MIT)
 * @link    https://github.com/soloproyectos-php/flash2html
 */
namespace soloproyectos;

/**
 * Flash2Html class.
 *
 * @package Utilities
 * @author  Gonzalo Chumillas <gchumillas@email.com>
 * @license https://github.com/soloproyectos-php/flash2html/blob/master/LICENSE The MIT License (MIT)
 * @link    https://github.com/soloproyectos-php/flash2html
 */
class Flash2Html
{
    /**
     * Parser object.
     * @var Resource
     */
    private $_parser;
    
    /**
     * Nodes.
     * @var array of entities.
     */
    private $_nodes = [];
    
    /**
     * Entities.
     * @var array of entities.
     */
    private $_entities = [];
    
    /**
     * Transformed HTML.
     * @var string
     */
    private $_content = "";
    
    /**
     * Data handler.
     * @var Function
     */
    private $_dataHandler = null;
    
    /**
     * Transform to plain-text?
     * @var boolean
     */
    public $isPlainText = false;
    
    /**
     * Protect email?
     * @var boolean
     */
    public $isProtectEmail = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->setTagTransformation('B', 'strong');
        $this->setTagTransformation('I', 'em');
        $this->setTagTransformation('LI', 'li');
        $this->setStartTagHandler('A', array($this, "_aStartTagHandler"));
        $this->setStartTagHandler('FONT', array($this, "_fontStartTagHandler"));
        $this->setStartTagHandler('IMG', array($this, "_imgStartTagHandler"));
        $this->setStartTagHandler("P", array($this, "_pStartTagHandler"));
        $this->setEndTagHandler("P", array($this, "_pEndTagHandler"));
        $this->setStartTagHandler('TEXTFORMAT', array($this, "_textformatStartTagHandler"));
        $this->setStartTagHandler('U', array($this, "_uStartTagHandler"));
    }

    /**
     * Magic '__invoke' method.
     * 
     * Example:
     * ```PHP
     * $f = new Flash2Html();
     * echo $f($html);  // equivalent to echo $f->html($html);
     * ```
     *
     * @param string $data       Non-Standard HTML
     * @param array  $properties Additional properties
     * 
     * @return string
     */
    public function __invoke($data, $properties = [])
    {
        return $this->html($data, $properties);
    }

    /**
     * Ignores a given tag.
     *
     * @param string $tag Tagname
     * 
     * @return void
     */
    public function ignoreTag($tag)
    {
        $offset = array_search($tag, array_keys($this->_entities));
        if ($offset !== false) {
            array_splice($this->_entities, $offset, 1);
        }
    }

    /**
     * Sets tag transformation.
     * 
     * @param string $from_tag Tagname
     * @param string $to_tag   Tagname
     * 
     * @return void
     */
    public function setTagTransformation($from_tag, $to_tag)
    {
        if (preg_match("/^\w+$/", $to_tag, $matches)) {
            $this->_entities[$from_tag]["tag"] = $matches[0];
            $this->_entities[$from_tag]["attributes"] = null;
            $this->_entities[$from_tag]["closed"] = false;
            $this->_entities[$from_tag]["start_tag_handler"] = null;
            $this->_entities[$from_tag]["data_tag_handler"] = null;
            $this->_entities[$from_tag]["end_tag_handler"] = null;
        } elseif (preg_match("/^<(\w+)\s+(.*)(\/?)>$/U", $to_tag, $matches)) {
            $this->_entities[$from_tag]["tag"] = $matches[1];
            $this->_entities[$from_tag]["attributes"] = $matches[2];
            $this->_entities[$from_tag]["closed"] = $matches[3] == "/";
            $this->_entities[$from_tag]["start_tag_handler"] = null;
            $this->_entities[$from_tag]["data_tag_handler"] = null;
            $this->_entities[$from_tag]["end_tag_handler"] = null;
        }
    }

    /**
     * Sets 'start-tag' handler.
     * 
     * @param string   $tag               Tagname
     * @param Function $start_tag_handler Start-tag handler
     * 
     * @return void
     */
    public function setStartTagHandler($tag, $start_tag_handler)
    {
        if (!array_key_exists($tag, $this->_entities)) {
            $this->setTagTransformation($tag, strtolower($tag));
        }
        $this->_entities[$tag]["start_tag_handler"] = $start_tag_handler;
    }

    /**
     * Sets 'data' handler.
     * 
     * @param Function $data_handler Data handler
     * 
     * @return void
     */
    public function setDataHandler($data_handler)
    {
        $this->_dataHandler = $data_handler;
    }

    /**
     * Sets 'end-tag' handler.
     * 
     * @param string   $tag             Tagname
     * @param Function $end_tag_handler End-tag handler
     * 
     * @return void
     */
    public function setEndTagHandler($tag, $end_tag_handler)
    {
        if (!array_key_exists($tag, $this->_entities)) {
            $this->setTagTransformation($tag, strtolower($tag));
        }
        $this->_entities[$tag]["end_tag_handler"] = $end_tag_handler;
    }

    /**
     * Removes 'start-tag' handler.
     * 
     * @param string $tag Tagname
     * 
     * @return void
     */
    public function removeStartTagHandler($tag)
    {
        $this->_entities[$tag]["start_tag_handler"] = null;
    }

    /**
     * Removes 'data-tag' handler.
     * 
     * @param string $tag Tagname
     * 
     * @return void
     */
    public function removeDataTagHandler($tag)
    {
        $this->_entities[$tag]["data_tag_handler"] = null;
    }

    /**
     * Removes 'end-tag' handler.
     * 
     * @param string $tag Tagname
     * 
     * @return void
     */
    public function removeEndTagHandler($tag)
    {
        $this->_entities[$tag]["end_tag_handler"] = null;
    }

    /**
     * Transform non-standard HTML into standard HTML.
     *
     * @param string $data       Non-Standard HTML
     * @param Array  $properties Additional attributes
     * 
     * @return string
     */
    public function html($data, $properties = [])
    {
        $uniqid = uniqid("unicode_");
        $data = json_encode($data);
        $data = preg_replace('/\\\u([0-9a-z]{4})/', "$uniqid\$1", $data);
        $data = json_decode($data);

        $original_properties = [];
        foreach ($properties as $key => $value) {
            $original_properties[$key] = $this->$key;
            $this->$key = $value;
        }

        $data = $this->_prepare($data);

        $this->_parser = xml_parser_create();
        xml_set_object($this->_parser, $this);
        xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, true);
        xml_set_element_handler($this->_parser, "_startTagHandler", "_endTagHandler");
        xml_set_character_data_handler($this->_parser, "_dataHandler");
        xml_parse($this->_parser, $data);
        xml_parser_free($this->_parser);
        $ret = $this->_content;
        $this->_content = null;

        $ret = utf8_decode(str_replace(array('–', '’'), array('&#8211', '&rsquo;'), $ret));
        if ($this->isProtectEmail) {
            $ret = preg_replace_callback(
                "/mailto:(.*)\"/U", array($this, "_protectEmailCallback"), $ret
            );
        }

        // restore original properties
        foreach ($original_properties as $key => $value) {
            $this->$key = $value;
        }

        $ret = preg_replace("/$uniqid([0-9a-z]{4})/", '&#x$1;', $ret);
        $ret = preg_replace("/<br \/>\n$/", null, $ret);
        $ret = preg_replace("/<span[^>]*><\/span>/U", null, $ret);
        $ret = preg_replace(
            "/<a([^>]*)><span style=\"text-decoration: underline; \">(.*)<\/span><\/a>/U",
            "<a\$1>\$2</a>",
            $ret
        );
        $ret = preg_replace("/<li>(.*)<\/li>/", "<ul><li>$1</li>\n</ul>\n", $ret);
        $ret = preg_replace("/<li>/", "\n\t<li>", $ret);
        $ret = preg_replace("/<br \/>\n<ul>/", "<ul>", $ret);
        $ret = preg_replace("/<\/ul>\n<br \/>/", "</ul>", $ret);

        return $ret;
    }

    /**
     * Prepares data.
     * 
     * @param string $data Non-Standard HTML
     * 
     * @return string
     */
    private function _prepare($data)
    {
        if (!$this->isPlainText) {
            $data = preg_replace_callback("/HREF=\"(.*)\"/U", array($this, "_fixHrefCallback"), $data);
            $data = preg_replace("/<IMG(.*)>/U", "<IMG$1 />", $data);
        }
        return "<root>$data</root>";
    }

    /**
     * Start-tag handler.
     * 
     * @param Resource $parser Parser
     * @param string   $tag    Tagname
     * @param array    $attrs  Attributes
     * 
     * @return void
     */
    private function _startTagHandler($parser, $tag, $attrs)
    {
        if ($this->isPlainText) {
            return;
        }

        if (!key_exists($tag, $this->_entities)) {
            return;
        }

        $entity = $this->_entities[$tag];
        if ($entity['start_tag_handler'] != null) {
            $str = call_user_func($entity["start_tag_handler"], $parser, $tag, $attrs);
            if (preg_match("/^<(\w+)/", $str, $matches)) {
                $tag_name = $matches[1];
                $entity["tag"] = $tag_name;
                $entity["closed"] = preg_match("/.*\/>/", $str) === 1;
            }
            $this->_content .= $str;
        } else {
            $this->_content .= "<$entity[tag]";
            if (strlen($entity["attributes"]) > 0) {
                $this->_content .= " $entity[attributes]";
            }
            if ($entity["closed"]) {
                $this->_content .= " />";
            } else {
                $this->_content .= ">";
            }
        }

        array_push($this->_nodes, $entity);
    }

    /**
     * Data-tag handler.
     * 
     * @param Resource $parser Parser
     * @param string   $cdata  CData
     * 
     * @return void
     */
    private function _dataHandler($parser, $cdata)
    {
        if ($this->isPlainText) {
            $this->_content .= $cdata;
        } else {
            $str = null;
            if ($cdata == "&") {
                    $str = "&amp;";
            } else {
                $str = preg_replace_callback(
                    "/\s{2,}/", array($this, "_replaceSpacesCallback"), $cdata
                );
            }

            if ($this->_dataHandler != null) {
                $str = call_user_func($this->_dataHandler, $parser, $cdata);
            }

            $this->_content .= $str;
        }
    }
    
    /**
     * End-tag handler.
     * 
     * @param Resource $parser Parser
     * @param string   $tag    Tagname
     * 
     * @return void
     */
    private function _endTagHandler($parser, $tag)
    {
        if (!key_exists($tag, $this->_entities)) {
            return;
        }

        if ($this->isPlainText) {
            if ($tag == "P") {
                $this->_content .= "\n";
            }
            return;
        }

        $entity = array_pop($this->_nodes);
        if (!$entity["closed"]) {
            if ($entity['end_tag_handler'] != null) {
                $this->_content .= call_user_func($entity["end_tag_handler"], $parser, $tag);
            } else {
                $this->_content .= "</$entity[tag]>";
            }
        }
    }
    
    /**
     * P tag handler.
     * 
     * @param Resource $parser Parser
     * @param string   $tag    Tagname
     * @param array    $attrs  Attributes
     * 
     * @return null
     */
    private function _pStartTagHandler($parser, $tag, $attrs)
    {
        return null;
    }

    /**
     * P tag handler.
     * 
     * @param Resource $parser Parser
     * @param string   $tag    Tagname
     * 
     * @return string
     */
    private function _pEndTagHandler($parser, $tag)
    {
        return "<br />\n";
    }

    /**
     * A tag handler.
     * 
     * @param Resource $parser Parser
     * @param string   $tag    Tagname
     * @param array    $attrs  Attributes
     * 
     * @return string
     */
    private function _aStartTagHandler($parser, $tag, $attrs)
    {
        $ret = '<a href="' . htmlspecialchars($attrs["HREF"]) . '"';
        $ret .= array_key_exists("TARGET", $attrs) && (strlen($attrs["TARGET"]) > 0)
            ? ' target="' . $attrs["TARGET"] . '"'
            : null;
        $ret .= '>';
        return $ret;
    }

    /**
     * FONT tag handler.
     * 
     * @param Resource $parser Parser
     * @param string   $tag    Tagname
     * @param array    $attrs  Attributes
     * 
     * @return string
     */
    private function _fontStartTagHandler($parser, $tag, $attrs)
    {
        $ret = '<span style="';
        $ret .= array_key_exists("FACE", $attrs)? "font-family: '" . $attrs["FACE"] . "'; ": null;
        $ret .= array_key_exists("SIZE", $attrs)? "font-size: " . $attrs["SIZE"] . "px; ": null;
        $ret .= array_key_exists("COLOR", $attrs)? "color: " . $attrs["COLOR"] . "; ": null;
        $ret .= array_key_exists("LETTERSPACING", $attrs)
            ? "letter-spacing: " . $attrs["LETTERSPACING"] . "px; "
            : null;
        $ret .= '">';
        return $ret;
    }

    /**
     * IMG tag handler.
     * 
     * @param Resource $parser Parser
     * @param string   $tag    Tagname
     * @param array    $attrs  Attributes
     * 
     * @return string
     */
    private function _imgStartTagHandler($parser, $tag, $attrs)
    {
        $style = $attrs["ALIGN"] == "right"
            ? "float: right; margin-left: 10px; "
            : "float: left; margin-right: 10px; ";
        $ret = "<img style=\"$style\" src=\"$attrs[SRC]\" alt=\"\" />";
        return $ret;
    }

    /**
     * TEXTFORMAT tag handler.
     * 
     * @param Resource $parser Parser
     * @param string   $tag    Tagname
     * @param array    $attrs  Attributes
     * 
     * @return string
     */
    private function _textformatStartTagHandler($parser, $tag, $attrs)
    {
        $ret = null;
        if (isset($attrs["BLOCKINDENT"])) {
            $ret = '<div style="';
            $ret .= array_key_exists("BLOCKINDENT", $attrs)
                ? "margin-left: " . $attrs["BLOCKINDENT"] . "px; "
                : null;
            $ret .= '">';
        }
        return $ret;
    }

    /**
     * U tag handler.
     * 
     * @param Resource $parser Parser
     * @param string   $tag    Tagname
     * @param array    $attrs  Attributes
     * 
     * @return string
     */
    private function _uStartTagHandler($parser, $tag, $attrs)
    {
        $ret = '<span style="text-decoration: underline; ">';
        return $ret;
    }

    /**
     * Replace spaces callback.
     * 
     * @param array $matches Matches
     * 
     * @return string
     */
    private function _replaceSpacesCallback($matches)
    {
        $matches[0];
        return str_repeat("&nbsp;", strlen($matches[0]));
    }

    /**
     * Protect email callback.
     * 
     * @param array $matches Matches
     * 
     * @return string
     */
    private function _protectEmailCallback($matches)
    {
        $ret = null;
        $str = $matches[1];
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $ret .= "&#x" . strtoupper(dechex(ord($str[$i]))) . ";";
        }
        return "mailto:$ret\"";
    }

    /**
     * Fix href callback.
     * 
     * @param array $matches Matches
     * 
     * @return string
     */
    private function _fixHrefCallback($matches)
    {
        return 'HREF="' . htmlentities($matches[1]) . '"';
    }
}
