<?php

/**
 * Class Templater3
 * @see https://github.com/shinji00/Micro_Templater
 */
class Templater3 {

    protected $blocks   = array();
    protected $vars     = array();
    protected $_p       = array();
    protected $reassign = false;
    protected $loop     = '';
    protected $html     = '';


    /**
     * @param  string    $template_file
     * @throws Exception
     */
    public function __construct($template_file = '') {
        if ($template_file) $this->loadTemplate($template_file);
    }


    /**
     * Isset block
     * @param  string $block
     * @return bool
     * @throws Exception
     */
    public function __isset($block) {
        $begin_pos = strpos($this->html, "<!-- BEGIN {$block} -->");
        $end_pos   = strrpos($this->html, "<!-- END {$block} -->");

        return $begin_pos !== false && $end_pos !== false && $end_pos >= $begin_pos;
    }


    /**
     * Nested blocks will be stored inside $_p
     * @param  string               $block
     * @return Templater3|null
     * @throws Exception
     */
    public function __get($block) {

        $this->touchBlock($block);

        if ( ! array_key_exists($block, $this->_p)) {
            $tpl = new Templater3();
            $tpl->setTemplate($this->getBlock($block));
            $this->_p[$block] = $tpl;
        }

        return $this->_p[$block];
    }


    /**
     * Load the HTML file to parse
     * @param  string     $filename
     * @throws Exception
     */
    public function loadTemplate($filename) {
        if ( ! file_exists($filename)) {
            throw new Exception("File not found '{$filename}'");
        }
        $this->setTemplate(file_get_contents($filename));
    }


    /**
     * Set the HTML to parse
     * @param $html
     */
    public function setTemplate($html) {
        $this->html = preg_replace("~<\!--\s*(BEGIN|END)\s+([a-zA-Z0-9_]+?)\s*-->~s", '<!-- $1 $2 -->', $html);
        $this->clear();
    }


    /**
     * Assign variable
     * @param string $var
     * @param string $value
     */
    public function assign($var, $value = '') {
        if ($this->reassign) $this->startReassign();
        $this->vars[$var] = $value;
    }


    /**
     * Reset the current instance's variables and make them able to assign again
     */
    public function reassign() {
        $this->reassign = true;
    }


    /**
     * Touched block
     * @param string $block
     */
    public function touchBlock($block) {
        if ($this->reassign) $this->startReassign();
        $this->blocks[$block]['TOUCHED'] = true;
    }


    /**
     * Get html block
     * @param  string      $block
     * @return string|bool
     * @throws Exception
     */
    public function getBlock($block) {
        $begin_pos = strpos($this->html, "<!-- BEGIN {$block} -->")  + strlen("<!-- BEGIN {$block} -->");
        $end_pos   = strrpos($this->html, "<!-- END {$block} -->");

        if ($end_pos >= $begin_pos) {
            return substr($this->html, $begin_pos, $end_pos - $begin_pos);
        } else {
            throw new Exception("Block '{$block}' not found");
        }
    }


    /**
     * The final render
     * @return string
     */
    public function render() {
        $html = $this->html;

        if (strpos($html, 'BEGIN')) {
            $matches = array();
            preg_match_all("~<\!-- BEGIN ([a-zA-Z0-9_]+?) -->~s", $html, $matches);
            if (isset($matches[1]) && count($matches[1])) {
                foreach ($matches[1] as $block) {
                    if ( ! isset($this->blocks[$block])) {
                        $this->blocks[$block] = array('TOUCHED' => false);
                    }
                }
            }

            foreach ($this->blocks as $block => $data) {
                $block_begin = "<!-- BEGIN {$block} -->";
                $block_end   = "<!-- END {$block} -->";

                $begin_pos = strpos($html, $block_begin);
                $end_pos   = strrpos($html, $block_end);

                if ($begin_pos !== false && $end_pos !== false && $end_pos >= $begin_pos) {
                    $after_html  = substr($html, 0, $begin_pos);
                    $inside_html = substr($html, $begin_pos + strlen($block_begin), $end_pos - ($begin_pos + strlen($block_begin)));
                    $before_html = substr($html, $end_pos + strlen($block_end));

                    if (isset($data['TOUCHED']) && $data['TOUCHED']) {
                        $block_tpl = array_key_exists($block, $this->_p) ? $this->_p[$block] : null;
                        if ($block_tpl instanceof Templater3) {
                            $parsed = $block_tpl->render();
                            $html = $after_html . $parsed . $before_html;
                        } else {
                            $html = $after_html . $inside_html . $before_html;
                        }

                    } else {
                        $html = $after_html . $before_html;
                    }
                }
            }
        }


        $assigned   = str_replace(array_keys($this->vars), $this->vars, $html);
        $html       = $this->loop . $assigned;
        $this->loop = '';

        return $html;
    }


    /**
     * Fill SELECT items on page
     * @param string       $id
     * @param array        $options
     * @param string|array $selected
     */
    public function fillDropDown($id, array $options, $selected = null) {

        if ($this->reassign) $this->startReassign();
        $html = "";
        foreach ($options as $value => $option) {
            if (is_array($option)) {
                $html .= "<optgroup label=\"{$value}\">";
                foreach ($option as $val => $opt) {
                    $sel = $selected !== null && ((is_array($selected) && in_array((string)$val, $selected)) || (string)$val === (string)$selected)
                        ? 'selected="selected" '
                        : '';
                    $html .= "<option {$sel}value=\"{$val}\">{$opt}</option>";
                }
                $html .= '</optgroup>';

            } else {
                $sel = $selected !== null && ((is_array($selected) && in_array((string)$value, $selected)) || (is_scalar($selected) && (string)$value === (string)$selected))
                    ? 'selected="selected" '
                    : '';
                $html .= "<option {$sel}value=\"{$value}\">{$option}</option>";
            }
        }
        if ($html) {
            $id = preg_quote($id);
            $reg = "~(<select.*?id\s*=\s*[\"']{$id}[\"'][^>]*>).*?(</select>)~si";
            $this->html = preg_replace($reg, "$1[[$id]]$2", $this->html);
            $this->assign("[[$id]]", $html, true);
        }
    }


    /**
     * Clear vars & blocks
     */
    protected function clear() {
        $this->blocks   = array();
        $this->vars     = array();
        $this->reassign = false;
        foreach ($this->_p as $obj) {
            if ($obj instanceof Templater3) {
                $obj->clear();
            }
        }
    }


    /**
     * Start reassign
     */
    protected function startReassign() {
        $this->loop = $this->render();
        $this->clear();
    }
}