<?
class Wtime{
    private $time ="";
    function __construct($time){
    $this->time = $time;

    }


    
    function word(){
        $_word = "";

	if($this->time==""){
	    return $this->time;
	}

    /* Get the difference between the current time
       and the time given in days */


$today = new DateTime();
$date = new DateTime(explode(" ", $this->time)[0]);
$interval = $today->diff($date);

$days = $interval->format('%d');


    /* If some forward time is given return error */
    if($days < 0) {
        return -1;
    }


    switch($days) {

        case 0:
                $tmpt = $this->translateDate(date('H:i', strtotime($this->time)));
                 $_word = sprintf(_('Idag %s'),$tmpt);
                break;

        case 1:
                $tmpt = $this->translateDate(date('H:i', strtotime($this->time)));
                $_word =sprintf(_('Igår %s'),$tmpt);
                break;
        //case ($days >= 2 && $days <= 6):
        //      $_word =  sprintf(_("%s dagar sedan"), $days);
        //      break;
        //case ($days >= 7 && $days < 14):
        //      $_word= sprintf(_("En vecka sedan"), $days);
        //      break;
        //case ($days >= 14 && $days <= 365): 
        //      $_word =  sprintf(_("%s veckor sedan"), intval($days / 7));
        //      break;
       
        default :
        $tmpt = $this->translateDate(date('D M j G:i', strtotime($this->time)));
        $pieces = explode(" ", $tmpt);
        return _($pieces[0]).' '.$pieces[2].' '._($pieces[1]).' '.$pieces[3];
        break;

    }

    return $_word;
    }
    
    function translateDate($string) {
	$date = array(
		"/January/", "/February/", "/March/", "/May/", "/June/", "/July/", "/August/", "/October/",
		"/May/", "/Oct/",
		"/Monday/", "/Tuesday/", "/Wednesday/", "/Thursday/", "/Friday/", "/Saturday/", "/Sunday/",
		"/Mon/", "/Tue/", "/Wed/", "/Thu/", "/Fri/", "/Sat/", "/Sun/"
	);
	$dateSE = array(
		"Januari", "Februari", "Mars", "Maj", "Juni", "Juli", "Augusti", "Oktober",
		"Maj", "Okt",
		"Måndag", "Tisdag", "Onsdag", "Torsdag", "Fredag", "Lördag", "Söndag",
		"Mån", "Tis", "Ons", "Tor", "Fre", "Lör", "Sön"
	);
	return preg_replace($date, $dateSE, $string);
}
}