<?php 
class WF_Element
{
    public static function a($href, $text = null, $attr = null, $target = null)
    {
        $attr         = self::attrConvert($attr);
        $attr['href'] = $href;
        if (is_null($text)) {
            $text = $href;
        }
        if (!is_null($target)) {
            $attr['target'] = $target;
        }

        return self::tag('a', $text, $attr);
    }

    public static function button($title, $onclick = null, $attr = null)
    {
        $attr         = self::attrConvert($attr);
        $attr['type'] = 'button';
        if ($onclick === true) {
            $attr['type'] = 'submit';
        } elseif ($onclick === false) {
            $attr['type'] = 'reset';
        } elseif (!is_null($onclick)) {
            $attr['onclick'] = $onclick;
        }

        return self::tag('button', $title, $attr);
    }

    public static function checkbox($name, $checked = null, $value = null, $attr = null, $label = null)
    {
        $attr         = self::attrConvert($attr);
        $attr['type'] = 'checkbox';
        $attr['name'] = $name;
        if ($checked) {
            $attr['checked'] = 'checked';
        }
        $html = self::tag('input', (!is_null($value) ? $value : 1), $attr, true);
        if (!empty ($label)) {
            return self::tag('label', $html . ' ' . $label);
        } else {
            return $html;
        }
    }

    public static function checkboxListing($arr, $name, $selected = null, $separate = null, $onlyvalues = false)
    {
        $tmp = '';
        $sel_arr = $selected;
        if (!is_array($sel_arr)) {
            $sel_arr = array($sel_arr);
        }
        foreach ($arr as $val => $title) {
            if ($onlyvalues) {
                $val = $title;
            }
            $tmp .= self::checkbox($name . '[]', in_array($val, $sel_arr, is_null($selected)), $val, null, $title);
            if ($separate) {
                $tmp .= $separate === true ? "<br />" : $separate;
            }
            $tmp .= "\n";
        }

        return $tmp;
    }

    public static function form($value, $action = null, $enctype = null, $attr = null)
    {
        $attr = self::attrConvert($attr);
        if (!isset ($attr['action'])) {
            $attr['action'] = !is_null($action) ? $action : '';
        }
        if (!isset ($attr['enctype'])) {
            $attr['enctype'] = !is_null($enctype) ? $enctype : 'multipart/form-data';
        }
        if (!isset ($attr['method'])) {
            $attr['method'] = 'POST';
        }

        return self::tag('form', $value, $attr, false, true);
    }

    public static function hidden($name, $value, $attr = null)
    {
        $attr         = self::attrConvert($attr);
        $attr['type'] = 'hidden';
        $attr['name'] = $name;

        return self::tag('input', $value, $attr, true);
    }

    public static function img($src, $width = null, $height = null, $alt = null, $attr = null)
    {
        $attr        = self::attrConvert($attr);
        $attr['src'] = $src;
        if ($width) {
            $attr['width'] = $width;
        }
        if ($height) {
            $attr['height'] = $height;
        }
        $attr['alt'] = $alt;

        return self::tag('img', null, $attr, true);
    }

    public static function input($name, $value, $attr = null)
    {
        $attr         = self::attrConvert($attr);
        $attr['type'] = 'text';
        $attr['name'] = $name;

        return self::tag('input', $value, $attr, true);
    }

    public static function option($value, $title = null, $selected = null)
    {
        $attr = array('value' => $value);
        if ($selected) {
            $attr['selected'] = 'selected';
        }
        if (is_null($title)) {
            $title = $value;
        }

        return self::tag('option', $title, $attr);
    }

    public static function optionListing($arr, $selected = null, $optgroup = null, $onlyvalues = false)
    {
        $tmp = '';
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $tmp .= self::optionListing($val, $selected, $key);
            } else {
                if ($onlyvalues) {
                    $key = $val;
                }
                $tmp .= self::option($key, $val, !is_null($selected) && $key == $selected) . "\n";
            }
        }
        if ($optgroup) {
            $tmp = self::tag('optgroup', $tmp, array('label' => $optgroup));
        }

        return $tmp;
    }

    public static function password($name, $value, $attr = null)
    {
        $attr         = self::attrConvert($attr);
        $attr['type'] = 'password';
        $attr['name'] = $name;

        return self::tag('input', $value, $attr, true);
    }

    public static function radio($name, $checked = null, $value = null, $attr = null, $label = null)
    {
        $attr         = self::attrConvert($attr);
        $attr['type'] = 'radio';
        $attr['name'] = $name;
        if ($checked) {
            $attr['checked'] = 'checked';
        }
        $html = self::tag('input', (!is_null($value) ? $value : 1), $attr, true);
        if (!empty ($label)) {
            return self::tag('label', $html . ' ' . $label);
        } else {
            return $html;
        }
    }

    public static function radioListing($arr, $name, $selected = null, $separate = null, $onlyvalues = false)
    {
        $tmp = '';
        foreach ($arr as $val => $title) {
            if ($onlyvalues) {
                $val = $title;
            }
            $ch = !is_null($selected) && $val == $selected;
            $tmp .= self::radio($name, $ch, $val, null, $title);
            if ($separate) {
                $tmp .= $separate === true ? "<br />" : $separate;
            }
            $tmp .= "\n";
        }

        return $tmp;
    }

    public static function select($mixed, $name, $selected = null, $attr = null, $header = null, $multiple = null)
    {
        $attr         = self::attrConvert($attr);
        $attr['name'] = $name;
        if ($multiple) {
            $attr['multiple'] = 'multiple';
        }
        if (is_array($mixed)) {
            $mixed = self::optionListing($mixed, $selected);
        }
        if ($header) {
            $mixed = self::option('', $header) . "\n" . $mixed;
        }

        return self::tag('select', $mixed, $attr, false, true);
    }

    public static function tag($tag, $value = null, $attr = null, $single = false, $nl = false)
    {
        $attr = self::attrConvert($attr);
        if (!is_null($value) && $single) {
            $attr['value'] = $value;
        }
        $tmp = '';
        foreach ($attr as $key => $val) {
            $tmp .= ' ' . $key . '="' . $val . '"';
        }
        $attr = $tmp;

        return '<' . $tag . $attr . ($single ? ' />' : ">" . ($nl ? "\n" : '') . $value . '</' . $tag . '>');
    }

    public static function textarea($name, $value = null, $attr = null, $rows = null, $cols = null)
    {
        $attr         = self::attrConvert($attr);
        $attr['name'] = $name;
        if (!is_null($rows)) {
            $attr['rows'] = $rows;
        }
        if (!is_null($cols)) {
            $attr['cols'] = $cols;
        }

        return self::tag('textarea', $value, $attr);
    }

    public static function attrConvert($attr, $_ = null)
    {
        $args = func_get_args();
        if (count($args) > 1) {
            $attr = array();
            foreach ($args as $a) {
                $attr = array_merge($attr, self::attrConvert($a));
            }
        } else {
            if (is_null($attr)) {
                $attr = array();
            } elseif (!is_array($attr)) {
                $attr = array('class' => (string)$attr);
            }
        }

        ksort($attr);
        return $attr;
    }
}
