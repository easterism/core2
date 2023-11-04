<?php

/**
 * Class Templater3
 * @see https://github.com/shabuninil/Micro_Templater
 */
class Templater3 {

    protected $blocks   = [];
    protected $vars     = [];
    protected $_p       = [];
    protected $plugins  = [];
    protected $reassign = false;
    protected $loop     = '';
    protected $html     = '';


    /**
     * @param  string    $template_file
     * @throws Exception
     */
    public function __construct($template_file = '') {

        if ($template_file) {
            $this->loadTemplate($template_file);
        }

        //добавляем плагин по умолчанию
        $this->addPlugin("tr", Zend_Registry::get('translate'));
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
     * The final render
     * @return string
     */
    public function __toString() {
        return $this->render();
    }


    /**
     * @param $title
     * @param $obj
     */
    public function addPlugin($title, $obj) {
        $this->plugins[strtolower($title)] = $obj;
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
     * Isset html block
     * @param  string $block
     * @return bool
     */
    public function issetBlock($block) {

        $begin_pos = strpos($this->html, "<!-- BEGIN {$block} -->");

        if ($begin_pos === false) {
            return false;
        }

        $begin_pos += strlen("<!-- BEGIN {$block} -->");
        $end_pos    = strrpos($this->html, "<!-- END {$block} -->");

        return $begin_pos !== false &&
               $end_pos !== false &&
               $end_pos >= $begin_pos;
    }


    /**
     * The final render
     * @return string
     */
    public function render() {
        $html = $this->html;

        if (strpos($html, 'BEGIN')) {
            $matches = [];
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
                $end_pos   = strpos($html, $block_end, $begin_pos);

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




        //apply plugins
        foreach ($this->plugins as $plugin => $process) {
            $matches = [];
            preg_match_all("/_{$plugin}\(([^\)]+)\)/sm", $html, $matches);

            if ( ! empty($matches[1])) {
                foreach ($matches[1] as $key => $value) {
                    $explode_value = explode('|', $value);
                    array_walk($explode_value, function (&$val) {
                        $val = trim($val, "\"'");
                        return $val;
                    });
                    $matches[1][$key] = call_user_func_array([$process, $plugin], $explode_value);
                }
            }
            $html = str_replace($matches[0], $matches[1], $html);
        }


        return $html;
    }


    /**
     * Fill SELECT items on page
     * @param string       $id
     * @param array        $options
     * @param string|array $selected
     */
    public function fillDropDown($id, array $options, $selected = null) {

        if ($this->reassign) {
            $this->startReassign();
        }

        $html = "";

        foreach ($options as $value => $option) {
            if (is_array($option)) {
                if ( ! empty($option['title'])) {
                    $item_value = ! empty($option['value'])
                        ? $option['value']
                        : $value;

                    $attr = [];

                    if ($selected !== null &&
                        (
                            (is_array($selected) && in_array((string)$item_value, $selected)) ||
                            (is_scalar($selected) && (string)$item_value === (string)$selected)
                        )
                    ) {
                        $attr[] = 'selected="selected" ';
                    }

                    if ( ! empty($option['attr']) && is_array($option['attr'])) {
                        foreach ($option['attr'] as $attr_name => $attr_value) {
                            $attr[] = "{$attr_name}=\"{$attr_value}\"";
                        }
                    }

                    $attr[] = "value=\"{$item_value}\"";
                    $attr = implode(' ', $attr);

                    $html .= "<option {$attr}>{$option['title']}</option>";

                } else {
                    $html .= "<optgroup label=\"{$value}\">";

                    foreach ($option as $option_value => $option_item) {
                        if ( ! empty($option_item['title'])) {
                            $item_value = ! empty($option_item['value'])
                                ? $option_item['value']
                                : $option_value;

                            $attr = [];

                            if ($selected !== null &&
                                (
                                    (is_array($selected) && in_array((string)$item_value, $selected)) ||
                                    (is_scalar($selected) && (string)$item_value === (string)$selected)
                                )
                            ) {
                                $attr[] = 'selected="selected"';
                            }

                            if ( ! empty($option_item['attr']) && is_array($option_item['attr'])) {
                                foreach ($option_item['attr'] as $attr_name => $attr_value) {
                                    $attr[] = "{$attr_name}=\"{$attr_value}\"";
                                }
                            }

                            $attr[] = "value=\"{$item_value}\"";
                            $attr = implode(' ', $attr);

                            $html .= "<option {$attr}>{$option_item['title']}</option>";

                        } else {
                            $selected_attr = $selected !== null &&
                                   (
                                       (is_array($selected) && in_array((string)$option_value, $selected)) ||
                                       (is_scalar($selected) && (string)$option_value === (string)$selected)
                                   )
                                ? 'selected="selected" '
                                : '';
                            $html .= "<option {$selected_attr}value=\"{$option_value}\">{$option_item}</option>";
                        }
                    }
                    $html .= '</optgroup>';
                }

            } else {
                $selected_attr = $selected !== null &&
                        (
                            (is_array($selected) && in_array((string)$value, $selected)) ||
                            (is_scalar($selected) && (string)$value === (string)$selected)
                        )
                    ? 'selected="selected" '
                    : '';
                $html .= "<option {$selected_attr}value=\"{$value}\">{$option}</option>";
            }
        }

        if ($html) {
            $id = preg_quote($id);

            $this->html = preg_replace(
                "~(<select.*?id\s*=\s*[\"']{$id}[\"'][^>]*>).*?(</select>)~si",
                "$1[[$id]]$2",
                $this->html
            );
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