<?php
/*
 * 文章信息抽取程序
 * 
 * 抽取方式
 * 正文：文字密度，链接密度，标点符号密度
 * 标题：<title>标签，和正文匹配最大长度。适当考虑中HTML TAG
 * 来源：标题附近区域的来源链接，可适当考虑class和A标签
 * 作者：
 */
class WF_Extract {  
    private $meta = array(
        'url'     => '',
        'site'    => '',
        'source'  => '',
        'pubdate' => '',
        'author'  => '',
        'desc'    => '',
    );
    private $default_allow_tags = array('title', 'p', 'div', 'span', 'table', 'tr', 'td', 'ul', 'li', 'ol', 'dt', 'dl', 'dd', "h1", "h2", "h3", "h4", "a");
    private $default_junk_tags  = array( "style", "script", "noscript", "form", "button", "select"); 
    private $blkSize = 2;
    private $blksLen = array();
    private $title_min_len = 4;
    private $engine = null;

    private function setEngine($engine){
        $this->engine = $engine;
    }

    private function getTitleRegion(){}
    public function findPubDate($html, $title = '', $url='') { // code...  
        $len  = mb_strlen($html);
        $html = mb_substr($html, 0, $len/2);
        //可从链接、网页最后修改时间、内容中提取
        $pattern1 = "201\d[\-/\x{5e74}]\d{1,2}[\-/\x{6708}]\d{1,2}[\x{65e5}]?(?:\s*\d{1,2}:\d{1,2}(:\d{1,2})?)?";
        $pattern2 = "(?<=\>)\s+${pattern1}\s+(?=\<)";

        if (preg_match_all("@$pattern2@u", $html, $match)){
            $timestr = trim($match[0][0]);
        }else if(preg_match_all("@$pattern1@u", $html, $match)){
            $timestr = trim($match[0][0]);
        }
        else {
            //from last-modified-time
        }

        $timestr = str_replace(array("年", "月", "日"), array('-', '-', ' '), $timestr);
        $time = strtotime($timestr);
        return $time;
    }

    public function findTitle($html){
        $base_len = $this->title_min_len;
        $result = preg_match("@<title>(.*?)<\/title>@is", $html, $match);
        if (!$result){
            //todo return first H1, H2, span/div.title 
            return "";
        }
        $title_str = trim($match[1]);

        mb_internal_encoding("utf-8");
        $title_len = mb_strlen($title_str);
        $html      = preg_replace("/<title>.*?<\/title>/", "", $html);
        $html      = strip_tags($html);

        $content_len = mb_strlen($html);
        $subtitle    = mb_substr($title_str, 0, $base_len);
        $offset      = 0;
        $lcs_len     = 0;
        while(true){
            $pos = mb_strpos($html, $subtitle, $offset);
            if ($pos === false) break;
            $i   = $base_len;
            $pos += $base_len;
            while(mb_substr($title_str, $i, 1) == mb_substr($html, $pos, 1)){
                $i++; $pos++;
                if ($i == $title_len || $pos == $content_len){
                    break;
                }
            };
            if ($i-1 > $lcs_len) {
                $lcs_len = $i;
            }
            $offset = $pos;
            if ($offset > $content_len) {
                break;
            }
        }
        $real_title = $lcs_len > 0 ? mb_substr($title_str, 0, $lcs_len) : $title_str;
        return $real_title;
    }

    /* public fetchPage($url, $use_cache=false) {{{ */ 
    /**
     * fetchPage 抓取网页的工具类
     * @todo 也许不适合放在这里。
     * 
     * @param mixed $url 
     * @param bool $use_cache 
     * @access public
     * @return void
     */
    public function fetchPage($url, $use_cache=false){
        return WF_Http::fetchPage($url, $use_cache);
    }
    // }}}

    //去掉标签和里面的内容
    public function stripTotalTags($html, $junk_tags = null) {
        $junk_tags = empty($junk_tags) ? $this->default_junk_tags : $junk_tags;
        $unused_tags = implode('|', $junk_tags);
        $preg = '@<('.$unused_tags . ')[^>]*>.+?<\/\1>@is';
        //! 注意有些写法将</script> 写成"</scr" + "ipt>" 可能会产生问题。目前将.*? 改成了.+?，但不保证问题不会发生。
        $html = preg_replace($preg, '', $html);
        return $html;
    }

    public function preProcess($html){
        $html = $this->stripTotalTags($html);
        $html = $this->stripComments($html);
        $html = $this->stripSpecialChars($html);
        $html = $this->stripDTD($html);
        $html = $this->convertToUtf8($html);
        $html = $this->stripTags($html);
        $html = $this->stripSpace($html);
        return $html;
    }

    public function stripSpace($html){
        $html=preg_replace("/^[[:blank:]]+$/", "", $html);
        $html=preg_replace("/(\r?\n)\\1+/", "\\1",$html);
        return $html;
    }

    public function stripBlank($html){
        $html = str_replace(array("\r\n", "\n"), " ", $html);
        $html = preg_replace("/\s{2,}/s", " ", $html);
        return $html;
    }

    public function stripDTD($html){
        $pattern = '/<!DOCTYPE.*?>/si';    
        return preg_replace($pattern, '', $html);
    }

    //去掉评论
    public function stripComments($html){
        $pattern ="@<!--.*?-->@si";
        $html = preg_replace($pattern, '', $html);
        return $html;
    }

    //仅保留的标签
    public function stripTags($html, $tags = null){
        $allow_tags = '';
        if ($tags === null) {
            $tags = $this->default_allow_tags;
        }
        if (!empty($tags)){
            $allow_tags = "<" . implode("><", $tags) .">";
        }
        $html = strip_tags($html, $allow_tags);
        return $html;
    }

    //去掉特殊字符
    public function stripSpecialChars($html){
        $pattern = "@&.{1,5};|&#{1,5};@";
        $html = preg_replace($pattern, ' ', $html);
        return $html;
    }

    //去掉标签属性
    public function stripAttributes($html, $attrs=array()){
        return preg_replace('/<([a-z]+)[^>]*>/i', '<\1>', $html);
    }

    /* public convertToUtf8($html) {{{ */ 
    /**
     * convertToUtf8 将网页转成UTF-8格式
     * 
     * @param mixed $html 
     * @access public
     * @return void
     */
    public function convertToUtf8($html) {
        // add <meta 限制，保证不是从正文中获得的charset
        $result = preg_match("/<meta [^<>]*charset=\s*\"?([\w|\-]+)\"?\s*;?/i", $html, $match);
        if ($result) {
            $encoding = $match[1];
            $from_page= true;
        } else {
            $encoding = mb_detect_encoding($html, array("GB2312", "GBK", "UTF-8", "ASCII", "BIG5"));
            $from_page = false;
        }

        if($encoding == 'EUC-CN'){
            $encoding = 'GB2312';
        }


        if($encoding && strtoupper($encoding) != 'UTF-8'){
            $html = iconv($encoding, "UTF-8//IGNORE", $html);
            if ($from_page){
                $html = preg_replace("/(<meta [^<>]*charset=\s*\"?)([\w|\-]+)(\"?\s*;?)/i", "\\1utf-8\\3", $html);
            }
            $encoding = "utf-8";
        }
        return $encoding ? $html : false;
    }
    // }}}

    public function findPlainText($html){
        $html      = $this->stripBlank($html);
        $html = preg_replace("/(<\/.*?>)/s", "\1\n", $html);
        $html      = strip_tags($html);
        $textLines = $this->getTextLines($html);
        $blksLen   = $this->calBlocksLen($textLines);


        $start = $end = -1;
        $i = $maxTextLen = 0;

        $blkNum = count( $blksLen );
        $text = '';
        while( $i < $blkNum ) {
            //跳过空blk
            while( ($i < $blkNum) && ($blksLen[$i] == 0) ) $i++;

            $tmp = $i;

            $curTextLen = 0;
            $portion = '';
            while( ($i < $blkNum) && ($blksLen[$i] >= 0) ) {
                if( $textLines[$i] != '' ) {
                    $portion .= $textLines[$i];
                    $portion .= '<br />';
                    $curTextLen += strlen( $textLines[$i] );
                }
                $i++;
            }
            if( $curTextLen > $maxTextLen ) {
                $text = $portion;
                $maxTextLen = $curTextLen;
                $start = $tmp;
                $end = $i - 1;
            }
        }
        return $text;
    }

    function calBlocksLen($textLines) {
        $textLineNum = count( $textLines );

        // calculate the first block's length
        $blkLen = 0;
        $blksLen = array();
        for( $i = 0; $i < $this->blkSize; $i++ ) {
            $blkLen += strlen( $textLines[$i] );
        }
        $blksLen[] = $blkLen;

        // calculate the other block's length using Dynamic Programming method
        for( $i = 1; $i < ($textLineNum - $this->blkSize); $i++ ) {
            $blkLen = $blksLen[$i - 1] + strlen( $textLines[$i - 1 + $this->blkSize] ) - strlen( $textLines[$i - 1] );
            $blksLen[] = $blkLen;
        }
        return $blksLen;
    }

    function getTextLines( $rawText ) {
        // do some replacement
        $order = array( "\r\n", "\n", "\r" );
        $replace = '\n';
        $rawText = str_replace( $order, $replace, $rawText );

        $lines = explode( '\n', $rawText );
        $textLines = array();

        foreach( $lines as $line ) {
            // remove the blanks in each line
            $tmp = preg_replace( '/\s+/s', '', $line );
            $textLines[] = $tmp;
        }
        return $textLines;
    }

}
?>
