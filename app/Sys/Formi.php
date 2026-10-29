<?

class formi{
	
    public static $form = array();

    function __construct($name){
    	
    }

    public function attrToHTML(array $attr){

        $tmp = "";
        foreach ($attr as $k =>  $v) {
            $tmp .= $k . '="' . $v . '" ';
        }

        $tmp = trim($tmp);
        return $tmp;
    }
    
       public function __call($name, $arguments){

        $dbname = $arguments[0];
        $html = $arguments[1];
        
           $re = '/label#(?<label>[a-z -A-Z0-9]+)\{(?<label_attr>[^}]{0,})}/m';
        $tmp = $html;

        if (preg_match($re, $html, $m, PREG_OFFSET_CAPTURE, 0)) {

            $tmp = str_replace($m[0][0], '', $html);
        }
        
        $re = '/#(?<name>[a-z -A-Z0-9]+)\{(?<name_attr>[^}]{0,})}/m';
        if (!preg_match($re, $tmp, $m2, PREG_OFFSET_CAPTURE, 0)) {

            // finns inte några label#namn[..] eller #namn[..] 
            return 0;
        }

        self::$form[$m2['name'][0]]['dbname'] = $dbname;
        self::$form[$m2['name'][0]]['find'][] = $m2[0][0];

        if (array_key_exists('name_attr', $m2)) {

            preg_match_all("/([^,= ]+)=\"([^\"]+)\"/", $m2['name_attr'][0], $r);
            unset($r[0]);

            self::$form[$m2['name'][0]]['attr']['type'] = 'text';
            self::$form[$m2['name'][0]]['attr']['name'] = $m2['name'][0];
            self::$form[$m2['name'][0]]['attr']['id'] = $m2['name'][0];

            foreach ($r[1] as $k =>  $v) {
                self::$form[$m2['name'][0]]['attr'][$r[1][$k]] = $r[2][$k];
            }

            $attr = self::$form[$m2['name'][0]]['attr'];
            self::$form[$m2['name'][0]]['string'] = $html;
            self::$form[$m2['name'][0]]['html'][] = '<input ' . $this->attrToHTML($attr) . '>';
        }

        if (array_key_exists('label', $m)) { 

            self::$form[$m2['name'][0]]['label'] = $m['label'][0];
            self::$form[$m2['name'][0]]['find'][] = $m[0][0];
        }

        if (array_key_exists('label_attr', $m)) {

            preg_match_all("/([^,=]+)=\"([^\"]+)\"/", $m['label_attr'][0], $r);
            foreach ($r[1] as $k =>  $v) {
                self::$form[$m2['name'][0]]['label_attr'][$r[1][$k]] = $r[2][$k];
            }

            self::$form[$m2['name'][0]]['label_attr']['for'] = $m2['name'][0];
            $attr = self::$form[$m2['name'][0]]['label_attr'];
            self::$form[$m2['name'][0]]['html'][] = "<label " . $this->attrToHTML($attr) . ">" . $m['label'][0] . "</label>";
        }
        
    }
}


$form = new formi('db.table');
$form->text('db.name', '<div>label#full namn{class="ff"} #namn{class="fdd"}</div>');
$form->text('db.name', '#epost{class="",style="", max="4",min="1"}');
print_r(formi::$form);

$form->get('db.name or name')->value;

if ($form->valid()) {
    $form->save($id);
}

print $f->value;

print $form->statusMsg;

print $form;
