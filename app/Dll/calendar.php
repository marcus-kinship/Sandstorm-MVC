<?

class Calendar {  
   
    private $currentYear=0;
    private $currentMonth=0;
    private $currentDay=0;
    private $currentDate=null;
    private $daysInMonth=0;
    private $firstday=0;

    public function __construct($month=null,$year=null){     
    
       if($year){
            $this->currentYear = $year; 
        }else{
            $this->currentYear = date("Y",time());  
        }          
         
        if($month!="" && $month<=12){
           $this->currentMonth = $month;
        }else{
           $this->currentMonth = date("m",time());
        } 
	$this->daysInMonth=$this->_daysInMonth();  
        	
	$this->firstday = mktime(0,0,0,$this->currentMonth,1,$this->currentYear);
    }

    
    public function getdays() {
      $content="";
	$weeksInMonth = $this->_weeksInMonth();
	
	for( $i=0; $i<$weeksInMonth; $i++ ){
	 
	    for($j=1;$j<=7;$j++){
		$content[]=$this->_showDay($i*7+$j);
	    }
	}

        return $content;   
    }
     

    private function _showDay($cellNumber){
         
        if($this->currentDay==0){
             
            $firstDayOfTheWeek = date('N',strtotime($this->currentYear.'-'.$this->currentMonth.'-01'));
                     
            if(intval($cellNumber) == intval($firstDayOfTheWeek)){
                 
                $this->currentDay=1;
                 
            }
        }
         
        if( ($this->currentDay!=0)&&($this->currentDay<=$this->daysInMonth) ){
             
            $this->currentDate = date('Y-m-d',strtotime($this->currentYear.'-'.$this->currentMonth.'-'.($this->currentDay)));
            $cellContent = $this->currentDay;
            $this->currentDay++;   
             
        }else{
            $this->currentDate =null;
            $cellContent=null;
        }
             
        return array('time'=> $this->currentDate, 'mask' => ($cellContent==null?'mask':''), 'day'=>$cellContent);
    }
     
      
    public function getYear(){
	return $this->currentYear;
    }
    
     public function titleOfMonth(){
	
	  $m = array(
	       _('Januari'), 
               _('Februari'), 
               _('Mars'), 
               _('April'), 
               _('Maj'), 
               _('Juni'), 
               _('Juli'), 
               _('Augusti'), 
               _('September'), 
               _('Oktober'), 
               _('November'), 
               _('December')); 
	$ms = date('m',$this->firstday);
	return $m[($ms-1)];
    }

    public function getWeeks(){
	
	    $year =  $this->currentYear; 
            $month = $this->currentMonth;
	    
	$d=cal_days_in_month(CAL_GREGORIAN,$month,$year);

	for($x=1; $x<=$d; $x++){
		$a = "$year-$month-$x";
		$week_num = date('W', strtotime($a));
		  $sa[strval(intval($week_num))]=strval(intval($week_num));
	} 

	return $sa;

    }
    
    public function prev(){
        $preMonth = $this->currentMonth==1?12:intval($this->currentMonth)-1; 
        $preYear = $this->currentMonth==1?intval($this->currentYear)-1:$this->currentYear;
	return array('month' => sprintf('%02d',$preMonth),
		     'year' => $preYear
		     );
    }
    
    public function next(){
	$nextMonth = $this->currentMonth==12?1:intval($this->currentMonth)+1;         
        $nextYear = $this->currentMonth==12?intval($this->currentYear)+1:$this->currentYear;
	return array('month' => sprintf('%02d',$nextMonth),
		     'year' => $nextYear
		     );
    }


    private function _weeksInMonth(){
         
            $year =  $this->currentYear; 
            $month = $this->currentMonth;
        
        $daysInMonths = $this->_daysInMonth();
        $numOfweeks = ($daysInMonths%7==0?0:1) + intval($daysInMonths/7); 
        $monthEndingDay= date('N',strtotime($year.'-'.$month.'-'.$daysInMonths));
	$monthStartDay = date('N',strtotime($year.'-'.$month.'-01'));
         
        if($monthEndingDay<$monthStartDay){
             
            $numOfweeks++;
        }
         
        return $numOfweeks;
    }
 
    private function _daysInMonth(){             
        return date('t',strtotime($this->currentYear.'-'.$this->currentMonth.'-01'));
    }
     
}