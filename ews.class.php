<?php
/**
 * EWS - Exchange Web Services
 * Implementation of Exchange Web Services via PHP SOAP
 * 
 * @version 0.1.0
 * @author Andreas Bilz <andreas@subsolutions.at>
 * @package ews
 */

class EWS
{
    public $modx;
    public $props;
    public $s;
    public $u;
    public $p;
    public $month;
    public $year;
    public $range;
    public $limit;
    public $outerTpl;
    public $dayTpl;
    public $eventTpl;
    public $headerTpl;
    public $navTpl;
    public $outerAttr;
    public $dayAttr;
    public $dayClass;
    public $eventAttr;
	
    public function __construct($modx, $props) {
        $this->modx = $modx;
        
        $this->props    = $props;
        $this->year     = $props['year'];
        $this->month    = $props['month'];
        $this->range    = $props['range'];
        $this->limit    = $props['limit'];
        
        $this->outerTpl = $props['outerTpl'];
        $this->dayTpl   = $props['dayTpl'];
        $this->eventTpl = $props['eventTpl'];
        $this->headerTpl= $props['headerTpl'];
        $this->navTpl   = $props['navTpl'];
        
        $this->outerAttr = $props['outerAttr'];
        $this->dayAttr = $props['dayAttr'];
        $this->dayClass = $props['dayClass'];
        $this->eventAttr = $props['eventAttr'];
        
        $this->s = 'mail.domain.com';
        $this->u = 'username';
        $this->p = 'password';
        
        require_once($this->modx->getOption('base_path') . 'assets/libs/ews/ExchangeWebServices.php');
        require_once($this->modx->getOption('base_path') . 'assets/libs/ews/NTLMSoapClient.php');
        require_once($this->modx->getOption('base_path') . 'assets/libs/ews/NTLMSoapClient/Exchange.php');
        require_once($this->modx->getOption('base_path') . 'assets/libs/ews/EWS_Exception.php');
        require_once($this->modx->getOption('base_path') . 'assets/libs/ews/EWSType.php');
        require_once($this->modx->getOption('base_path') . 'assets/snippets/ics.class.php');
        
        $dir = new DirectoryIterator($this->modx->getOption('base_path') . 'assets/libs/ews/EWSType/');
        
        foreach ($dir as $fileinfo) {
            if(!in_array($fileinfo->getFilename(), array('.', '..')))
                require_once($this->modx->getOption('base_path') . 'assets/libs/ews/EWSType/'. $fileinfo->getFilename());
        }
        
    }
    
    /**
     * getCalendarList
     * Retrieve calendar items from Exchange
     * @param integer $m The month to search for
     * @param integer $y The year to search for
     * @param mixed $range How many months to look forward
     * @todo Make highly flexible for search options
     */
    public function getCalendarList($m, $y, $range = false, $limit = false) {
		
		$start = "$m/01/$y -00";
        $last = date('t', strtotime($start));
		$end = "$m/$last/$y -00";
        
        if($limit) {
            $d = date('d');
            $em = $m + 2;
            $ey = $m > 11 ? $y+1 : $y;
            $start = "$m/$d/$y -00";
            $end = "$em/$d/$ey -00";
        }
        
		$ews = new ExchangeWebServices($this->s, $this->u, $this->p);
		
        $request = new EWSType_FindItemType();
        $request->Traversal = EWSType_ItemQueryTraversalType::SHALLOW;
        
        $request->ItemShape = new EWSType_ItemResponseShapeType();
        $request->ItemShape->BaseShape = EWSType_DefaultShapeNamesType::ALL_PROPERTIES;
        
        $request->CalendarView = new EWSType_CalendarViewType();
        $request->CalendarView->StartDate = date('c', strtotime($start));
        if($range) {
            $request->CalendarView->EndDate = date('c', strtotime($end) + $range * date('t', strtotime($start)) * 24 * 60 * 60);
        } else {
            $request->CalendarView->EndDate = date('c', strtotime($end));
        }
		
        $request->ParentFolderIds = new EWSType_NonEmptyArrayOfBaseFolderIdsType();
        $request->ParentFolderIds->DistinguishedFolderId = new EWSType_DistinguishedFolderIdType();
        $request->ParentFolderIds->DistinguishedFolderId->Id = EWSType_DistinguishedFolderIdNameType::CALENDAR;
		$response = $ews->FindItem($request);
        
        $items = $response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->CalendarItem;
        //var_dump($response);
		if($response)
			return $response;
		return false;
    }
    
	public function getCalendarItems() {
		
	}
	
    /**
     * getCalendarView
     * Generate the view for the retrieved items by days
     * @todo Make different views available
     * @todo Make flexible via chunks
     */
    public function getCalendarView() {
        
		// Check for given parameters
		$year  = $this->year;
		$month = $this->month;
        $range = $this->range;
		$limit = $this->limit;
        $navTpl= $this->navTpl;
        
		// Fetch data via EWS
        $response = $this->getCalendarList($month, $year, $range, $limit);
		$items = $response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->CalendarItem;
        if(!is_array($items)) {
            $items = array();
            $items[] = $response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->CalendarItem;
        }
        
        $range = date('t', strtotime("$month/01/$year"));  //Monthly range
        //$first = '01';              //Start months at 01
        //if($limit) {
        //    $range = $limit;        //List range
        //    $first = date('d');     //Start list with today
        //}
        
        $calendar = array();
        
        // Build a monthly view (01 - 31)
        if(!$limit) {
            for($i = 0; $i <= ($range - 1); $i++) {
                $calendar[date('m/d/Y', strtotime("$year-$month-01") + $i*24*60*60)] = '';
            }
        }
        
        // Store the events
        $j = 1;
        foreach($items as $item) {
            
            if($limit && $j > $limit) break;
            
            $calendar[date('m/d/Y', strtotime($item->Start))][] = $item;
            $j++;
        }
        
        $day = key($calendar);
        
        // Do we need a navigation?
        if($navTpl) {
            
            // Prepare data for month navigation
            $lastMonth = $month - 1;
            $lastYear  = $year;
            $nextMonth = $month + 1;
            $nextYear  = $year;
            
            if($nextMonth == 13) {
                $nextMonth = 1;
                $nextYear++;
            }
            if($lastMonth == 0) {
                $lastMonth = 12;
                $lastYear--;
            }
            
            $currentMonth = strftime('%B %Y', strtotime("$year-$month-01"));
            
            // Generate month navigation
            $output .= $this->modx->getChunk($navTpl, array(
                                                                         'lastYear' => $lastYear,
                                                                         'lastMonth' => $lastMonth,
                                                                         'currentMonth' => $currentMonth,
                                                                         'nextYear' => $nextYear,
                                                                         'nextMonth' => $nextMonth
                                                                         )
                                             );
        }
		
		$output .= '<div class="clearfix">';
        
        // Render header (weekdays) and push empty day elements
        // only if limit not set
        if(!$limit) {
            
            // Generate header, only if no limit is set
            $output .= $this->modx->getChunk($this->headerTpl);
            
            // Generate space elements for getting weekday of month 1st day
            for($i = date('N', strtotime($day)); $i > 1; $i--) {
            	$events .= $this->modx->getChunk($this->dayTpl, array('day' => '', 'dayClass' => $this->dayClass));
            }
        }
        
		// Generate output - DAY
        $k = 1;
        foreach($calendar as $key => $val) {
            $elements = '';
            if(is_array($val))
                $elements = $this->createItem((array) $val);
			$daynum = date('d', strtotime($key));
            $class = $this->dayClass . (date('N', strtotime($key)) == 7 ? ' is_sunday' : '');
            
			$args = array(
				'day'		=> $key,
				'daynum'	=> $daynum,
				'items'		=> $elements,
                'attributes'=> $this->dayAttr,
                'dayClass'  => $class
			);
			
            //DayTpl
			$events .= $this->modx->getChunk($this->dayTpl, $args);
            $k++;
        }
		$output .= $this->modx->getChunk($this->outerTpl, array('items' => $events, 'attributes' => $this->outerAttr));
		$output .= '</div>';
        echo $output;
        return;
    }
    
    /**
     * createItem
     * Generate a single event
     */
    public function createItem($items) {
		
        $output = '';
        foreach($items as $item) {
            $ews = new ExchangeWebServices($this->s,  $this->u, $this->p);
            $request = new EWSType_GetItemType();
            
            $request->ItemShape = new EWSType_ItemResponseShapeType();
            $request->ItemShape->BaseShape = EWSType_DefaultShapeNamesType::ALL_PROPERTIES;
            
            $request->ItemIds = new EWSType_NonEmptyArrayOfBaseItemIdsType();
            $request->ItemIds->ItemId = new EWSType_ItemIdType();
            $request->ItemIds->ItemId->Id = $item->ItemId->Id;
            $detail = $ews->GetItem($request);
            $body = $detail->ResponseMessages->GetItemResponseMessage->Items->CalendarItem->Body;
            
            $arg = array(
                'startTime'     => $item->IsAllDayEvent ? 'ganztags' : date('H:i', strtotime($item->Start)),
                'endTime'       => $item->IsAllDayEvent ? false : date('H:i', strtotime($item->End)),
                'start'         => $item->Start,
                'end'           => $item->End,
                'location'      => $item->Location,
                'subject'       => $item->Subject,
                'detail'        => $body->_,
                'fullday'       => $item->IsAllDayEvent,
                'eventAttr'     => $this->eventAttr
                         );
            //ItemTpl
            $output .= $this->modx->getChunk($this->eventTpl, $arg);
        }
        return $output;
    }
    
    /**
     * getForm
     * Generate the search form
     */
    public function getForm() {
        
    }
    
    /**
     * generateICS
     * Generate webcal/export for ICS
     */
    public function generateICS() {
        $cal = new vcalendar();
		$cal->setProperty( "x-wr-calname", "Calendar Name" );
        $cal->setProperty( "X-WR-CALDESC", "A description" );
        $cal->setProperty( "X-WR-TIMEZONE", "Timezone (e.g. 'Europe/Vienna')" );
		
		$response = $this->getCalendarList($this->month, $this->year, $this->range);
		if(is_object($response))
			$items = $response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->CalendarItem;
		
		foreach($items as $item) {
			$this->addIcsItem($cal, $item);
		}
		$str = $cal->createCalendar();
        echo $str;
		//return $cal->returnCalendar();
    }
	
	/**
	 * addIcsItem
	 * Adds an event item to the referenced calendar element
	 */
	public function addIcsItem(&$cal, $item) {
		$event = &$cal->newComponent('vevent');
		$event->setProperty('dtstart', array('timestamp' => strtotime($item->Start)));
		$event->setProperty('dtend', array('timestamp' => strtotime($item->End)));
		$event->setProperty('summary', $item->Subject);
		$event->setProperty('description', 'Blabla');
	}
	
}//END EWS CLASS