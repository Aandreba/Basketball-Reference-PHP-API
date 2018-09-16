<?php
function inString(string $string, string $substring){
    return strpos($string, $substring) !== false;
}
class NBA_Player {
    private function getComments ($html) {
        $comments = explode("<!--", $html);
        foreach ($comments as &$comment){
            $comment = str_replace("-->", "", $comment);
        }
        return $comments;
 }
    private function getElementByAttribute(&$parentNode, $attr, $tagName, $attrVal) {
    $response = false;

    $childNodeList = $parentNode->getElementsByTagName($tagName);
    $tagCount = 0;
    for ($i = 0; $i < $childNodeList->length; $i++) {
        $temp = $childNodeList->item($i);
        if (stripos($temp->getAttribute($attr), $attrVal) !== false) {
             $response[] = $temp;

            $tagCount++;
        }

    }

    return $response;
}

    private function tableProcessing(&$parentNode, string $tabID){
        $table = $parentNode->getElementById($tabID);
        if ($table == NULL){
            $div = $parentNode->getElementById("all_".$tabID);
            $html = $parentNode->saveHTML($div);
            $comments = $this->getComments($html)[1];
            $newNode = new DOMDocument();
            $newNode->loadHTML($comments);
            $table = $newNode->getElementById($tabID);
        }
        //$table = $parentNode->getElementById($tabID);
        $head = $table->getElementsByTagName('thead')[0];
        $head = $head->getElementsByTagName('th');
        $headers = [];
        foreach ($head as $h){
            $headers[] = $h->textContent;
        }
        //var_dump($headers);
        $body = $table->getElementsByTagName('tbody')[0];
        $seasons = $body->getElementsByTagName('tr');
        foreach ($seasons as $season){
            $items[$headers[0]] = $season->getElementsByTagName('th')[0]->textContent;
            $i = 1;
            foreach ($season->getElementsByTagName('td') as $item){
                $items[$headers[$i]] = $item->textContent;
                $i += 1;
            }
            $array[] = $items;
        }
        return $array;
    }
    // Construct NBA Player Object with its BBRef ID (Format -> s{1:5}+n{1:2}+n{2 / 01-99})
    function __construct(string $playerID = "abdulka01"){
        $init = file_get_contents("https://www.basketball-reference.com/players/".$playerID[0]."/".$playerID.".html");
        $DOM = new DOMDocument();
        $DOM->loadHTML($init);
        $this->name = $DOM->getElementsByTagName('h1')[0]->textContent;
        $ps = $DOM->getElementsByTagName('p');
        $this->pronunciation = explode(": ", $ps[0]->textContent)[1];
        $hr = $DOM->getElementsByTagName("a");
        foreach ($hr as $item){
            if ($item->textContent == "Player Front"){
                $this->NBA_ID = explode("/", $item->getAttribute('href'))[4];
                break;
            }
        }
        $a = $this->getElementByAttribute($DOM, "class", "a", "poptip");
        $this->jersey = [];
        foreach ($a as $item){
            $txt = str_replace(' ','', $item->textContent);
            $txt = str_replace('\n','', $txt);
            $txt = $txt[1].$txt[2];
            if (ctype_digit($txt)){
                $id = count($this->jersey);
                $this->jersey[$id][] = intval($txt);
                $data = explode(', ', $item->getAttribute('data-tip'));
                $this->jersey[$id][] = $data[0];
                $data = explode("-", $data[1]);
                $this->jersey[$id][] = $data;
            }
        }
        foreach ($ps as $p){
            if (inString($p->textContent, "Position")){
                $text = str_replace("\n", "", $p->textContent);
                $exp = explode("    ▪      ", $text);
                $pos = explode(":    ",$exp[0])[1];
                $this->positions = explode(" and ", $pos);
                $this->hand = explode(":    ",$exp[1])[1];
            } elseif (inString($p->textContent, "kg")){
                $systems = explode("(", $p->textContent);
                $this->height = [str_replace(" ","",explode(",", $systems[0])[0])];
                $this->weight = [mb_substr(str_replace(" ","",explode(",", $systems[0])[1]), 0, -1)];
                $this->weight[0] = mb_substr($this->weight[0], 1);
                $this->height[] = str_replace(" ","",explode(",", $systems[1])[0]);
                $this->weight[] = mb_substr(str_replace(" ","",explode(",", $systems[1])[1]), 0, -1);
                $this->weight[1] = mb_substr($this->weight[1], 1);
            } elseif (inString($p->textContent, "Born")){
                $text = str_replace("\n", "", $p->textContent);
                $data = explode(":          ", $text)[1];
                $data = explode(", ", $data);
                $birth = $data[0];
                $data = explode("          ", explode("           ", $data[1])[1]);
                $birth .= ", ".$data[0];
                $this->birth[] = strtotime($birth);
                $data = explode("  ", $data[1]);
                $country = strtoupper($data[1]);
                $data = explode(',', $data[0]);
                $data[0] = mb_substr($data[0], 3);
                if (isset($data[1])){
                    $data[1] = mb_substr($data[1], 1);
                }
                $this->birth[] = $data;
                $this->birth[] = $country;
            } elseif (inString($p->textContent, "College")){
                $text = str_replace("\n", "", $p->textContent);
                $this->college = str_replace('      ','',explode(":                  ",$text)[1]);
                //var_dump($text);
            } elseif (inString($p->textContent, "High School")){
                $text = str_replace("\n", "", $p->textContent);
                $data = explode(":    ", $text)[1];
                $data = explode("  in  ", $data);
                $this->high_school[] = $data[0];
                $data = explode(",        ", $data[1]);
                $data[1] = mb_substr($data[1], 0, -2);
                $this->high_school[] = $data;
            } elseif (inString($p->textContent, "Draft")){
                $text = str_replace("\n", "", $p->textContent);
                $data = explode(':    ', $text)[1];
                $data = explode(', ', $data);
                $this->draft[] = $data[0];
                $ovr = str_replace(' overall)', '', $data[2]);
                $ovr = mb_substr($ovr, 0, -2);
                $this->draft[] = intval($ovr);
            } elseif (inString($p->textContent, "NBA Debut")){
                $text = str_replace("\n", "", $p->textContent);
                $this->debut = strtotime(explode(": ",$text)[1]);
            }
        }
        $this->per_game = $this->tableProcessing($DOM, 'per_game');
        $this->totals = $this->tableProcessing($DOM, 'totals');
        $this->per_36_min = $this->tableProcessing($DOM, 'per_minute');
        $this->per_100_poss = $this->tableProcessing($DOM, 'per_poss');
        $this->advanced = $this->tableProcessing($DOM, 'advanced');
    }
}

//$player = new NBA_Player("caldejo01");
$player = new NBA_Player();
//echo file_get_contents("https://www.basketball-reference.com/players/a/anthoca01.html");
