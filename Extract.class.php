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

    function readability($html){
        $obj = new Readability($html);
        return $obj->getContent();
    }

}
define("READABILITY_VERSION", 0.12);

class Readability {
    // 保存判定结果的标记位名称
    const ATTR_CONTENT_SCORE = "contentScore";

    // DOM 解析类目前只支持 UTF-8 编码
    const DOM_DEFAULT_CHARSET = "utf-8";

    // 当判定失败时显示的内容
    const MESSAGE_CAN_NOT_GET = "Sorry, readability was unable to parse this page for content.  \n
            If you feel like it should have been able to, 
            please let me know by mail: lucky[at]gracecode.com";

    // DOM 解析类（PHP5 已内置）
    protected $DOM = null;

    // 需要解析的源代码
    protected $source = "";

    // 章节的父元素列表
    private $parentNodes = array();

    // 需要删除的标签
    private $junkTags = Array("style", "form", "iframe", "script", "button", "input", "textarea");

    // 需要删除的属性
    private $junkAttrs = Array("style", "class", "onclick", "onmouseover", "align", "border", "margin");


    /**
     * 构造函数
     *      @param $input_char 字符串的编码。默认 utf-8，可以省略
     */
    function __construct($source, $input_char = "utf-8") {
        $this->source = $source;

        // DOM 解析类只能处理 UTF-8 格式的字符
        $source = mb_convert_encoding($source, 'HTML-ENTITIES', $input_char);

        // 预处理 HTML 标签，剔除冗余的标签等
        $source = $this->preparSource($source);

        // 生成 DOM 解析类
        $this->DOM = new DOMDocument('1.0', $input_char);
        try {
            //libxml_use_internal_errors(true);
            // 会有些错误信息，不过不要紧 :^)
            if (!@$this->DOM->loadHTML('<?xml encoding="'.Readability::DOM_DEFAULT_CHARSET.'">'.$source)) {
                throw new Exception("Parse HTML Error!");
            }

            foreach ($this->DOM->childNodes as $item) {
                if ($item->nodeType == XML_PI_NODE) {
                    $this->DOM->removeChild($item); // remove hack
                }
            }

            // insert proper
            $this->DOM->encoding = Readability::DOM_DEFAULT_CHARSET;
        } catch (Exception $e) {
            // ...
        }
    }


    /**
     * 预处理 HTML 标签，使其能够准确被 DOM 解析类处理
     *
     * @return String
     */
    private function preparSource($string) {
        // 剔除多余的 HTML 编码标记，避免解析出错
        preg_match("/charset=([\w|\-]+);?/", $string, $match);
        if (isset($match[1])) {
            $string = preg_replace("/charset=([\w|\-]+);?/", "", $string, 1);
        }

        // Replace all doubled-up <BR> tags with <P> tags, and remove fonts.
        $string = preg_replace("/<br\/?>[ \r\n\s]*<br\/?>/i", "</p><p>", $string);
        $string = preg_replace("/<\/?font[^>]*>/i", "", $string);

        return trim($string);
    }


    /**
     * 删除 DOM 元素中所有的 $TagName 标签
     *
     * @return DOMDocument
     */
    private function removeJunkTag($RootNode, $TagName) {
        $Tags = $RootNode->getElementsByTagName($TagName);

        $i = 0;
        while($Tag = $Tags->item($i++)) {
            $parentNode = $Tag->parentNode;
            $parentNode->removeChild($Tag);
        }

        return $RootNode;
    }

    /**
     * 删除元素中所有不需要的属性
     */
    private function removeJunkAttr($RootNode, $Attr) {
        $Tags = $RootNode->getElementsByTagName("*");

        $i = 0;
        while($Tag = $Tags->item($i++)) {
            $Tag->removeAttribute($Attr);
        }

        return $RootNode;
    }

    /**
     * 根据评分获取页面主要内容的盒模型
     *      判定算法来自：http://code.google.com/p/arc90labs-readability/
     *
     * @return DOMNode
     */
    private function getTopBox() {
        // 获得页面所有的章节
        $allParagraphs = $this->DOM->getElementsByTagName("p");

        // Study all the paragraphs and find the chunk that has the best score.
        // A score is determined by things like: Number of <p>'s, commas, special classes, etc.
        $i = 0;
        while($paragraph = $allParagraphs->item($i++)) {
            $parentNode   = $paragraph->parentNode;
            $contentScore = intval($parentNode->getAttribute(Readability::ATTR_CONTENT_SCORE));
            $className    = $parentNode->getAttribute("class");
            $id           = $parentNode->getAttribute("id");

            // Look for a special classname
            if (preg_match("/(comment|meta|footer|footnote)/i", $className)) {
                $contentScore -= 50;
            } else if(preg_match(
                "/((^|\\s)(post|hentry|entry[-]?(content|text|body)?|article[-]?(content|text|body)?)(\\s|$))/i",
                $className)) {
                $contentScore += 25;
            }

            // Look for a special ID
            if (preg_match("/(comment|meta|footer|footnote)/i", $id)) {
                $contentScore -= 50;
            } else if (preg_match(
                "/^(post|hentry|entry[-]?(content|text|body)?|article[-]?(content|text|body)?)$/i",
                $id)) {
                $contentScore += 25;
            }

            // Add a point for the paragraph found
            // Add points for any commas within this paragraph
            if (strlen($paragraph->nodeValue) > 10) {
                $contentScore += strlen($paragraph->nodeValue);
            }

            // 保存父元素的判定得分
            $parentNode->setAttribute(Readability::ATTR_CONTENT_SCORE, $contentScore);

            // 保存章节的父元素，以便下次快速获取
            array_push($this->parentNodes, $parentNode);
        }

        $topBox = $this->DOM->createElement('div', Readability::MESSAGE_CAN_NOT_GET);
        // Assignment from index for performance. 
        //     See http://www.peachpit.com/articles/article.aspx?p=31567&seqNum=5 
        for ($i = 0, $len = sizeof($this->parentNodes); $i < $len; $i++) {
            $parentNode      = $this->parentNodes[$i];
            $contentScore    = intval($parentNode->getAttribute(Readability::ATTR_CONTENT_SCORE));
            $orgContentScore = intval($topBox->getAttribute(Readability::ATTR_CONTENT_SCORE));

            if ($contentScore && $contentScore > $orgContentScore) {
                $topBox = $parentNode;
            }
        }

        // 此时，$topBox 应为已经判定后的页面内容主元素
        return $topBox;
    }




    /**
     * 获取页面的主要内容（Readability 以后的内容）
     *
     * @return Array
     */
    public function getContent() {
        if (!$this->DOM) return false;

        // 获取页面主内容
        $ContentBox = $this->getTopBox();

        // 复制内容到新的 DOMDocument
        $Target = new DOMDocument;
        $Target->appendChild($Target->importNode($ContentBox, true));

        // 删除不需要的属性
        foreach ($this->junkAttrs as $attr) {
            $Target = $this->removeJunkAttr($Target, $attr);
        }

        // 多个数据，以数组的形式返回
        return $Target->saveHTML();
    }
}
?>
