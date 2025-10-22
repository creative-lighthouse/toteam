<?php


namespace App\Events;

use App\Controllers\CalendarController;
use App\Events\EventDay;
use App\Pages\CalendarPage;
use SilverStripe\Model\ArrayData;
use App\Events\EventDayParticipation;
use SilverStripe\Model\List\ArrayList;

class Calendar
{
    private $active_year, $active_month, $active_day;
    private $member;
    private $events = [];
    private $eventdays = [];
    private $currentEventDayID = null;

    public function __construct($date = null, $member = null, $eventdayID = null)
    {
        setlocale(LC_ALL, 'de_DE.UTF-8');
        $this->active_year = $date != null ? date('Y', strtotime((string) $date)) : date('Y');
        $this->active_month = $date != null ? date('m', strtotime((string) $date)) : date('m');
        $this->active_day = $date != null ? date('d', strtotime((string) $date)) : date('d');
        $this->member = $member;
        $this->currentEventDayID = $eventdayID;
        if ($this->member) {
            $this->eventdays = EventDay::get();
            foreach ($this->eventdays as $day) {
                $memberparticipation = EventDayParticipation::get()->filter(['ParentID' => $day->ID, 'MemberID' => $this->member->ID])->first();
                if ($memberparticipation) {
                    $this->getColorOfEvent($day);
                } else {
                    $this->add_event($day->Title, $day->Date, 1, 'event-color--gray');
                }
            }
        }
    }

    public function getColorOfEvent($day)
    {
        $memberparticipation = EventDayParticipation::get()->filter(['ParentID' => $day->ID, 'MemberID' => $this->member->ID])->first();
        switch ($memberparticipation->Type) {
            case 'Accept':
                $this->add_event($day->Title, $day->Date, 1, 'event-color--green');
                break;
            case 'Maybe':
                $this->add_event($day->Title, $day->Date, 1, 'event-color--orange');
                break;
            case 'Decline':
                $this->add_event($day->Title, $day->Date, 1, 'event-color--red');
                break;
            default:
                $this->add_event($day->Title, $day->Date, 1, 'event-color--gray');
        }
    }

    public function add_event($txt, $date, $days = 1, $color = '')
    {
        $color = $color ? ' ' . $color : $color;
        $this->events[] = [$txt, $date, $days, $color];
    }

    public function getNumOfDays()
    {
        $num_days = date('t', strtotime($this->active_day . '-' . $this->active_month . '-' . $this->active_year));
        return $num_days;
    }

    public function getNumOfDaysLastMonth()
    {
        $num_days_last_month = date('t', strtotime('last month', strtotime($this->active_day . '-' . $this->active_month . '-' . $this->active_year)));
        return $num_days_last_month;
    }

    public function getNumOfDaysNextMonth()
    {
        $num_days_next_month = date('t', strtotime('next month', strtotime($this->active_day . '-' . $this->active_month . '-' . $this->active_year)));
        return $num_days_next_month;
    }

    public function getDays()
    {
        return [0 => 'MO', 1 => 'DI', 2 => 'MI', 3 => 'DO', 4 => 'FR', 5 => 'SA', 6 => 'SO'];
    }

    public function getFirstDayOfWeek()
    {
        $first_day_of_week = array_search(date('D', strtotime($this->active_year . '-' . $this->active_month . '-1')), $this->getDays());
        return $first_day_of_week;
    }
    // Removed __toString HTML generation. Use template rendering instead.

    public function getDaysOfWeek()
    {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $out = [];
        foreach ($days as $day) {
            $out[] = ArrayData::create(['Value' => $day]);
        }
        return ArrayList::create($out);
    }

    public function getCalendarDays()
    {
        $num_days = date('t', strtotime($this->active_year . '-' . $this->active_month . '-01'));
        $num_days_last_month = date('j', strtotime('last day of previous month', strtotime($this->active_year . '-' . $this->active_month . '-01')));
        $first_day_of_week = date('N', strtotime($this->active_year . '-' . $this->active_month . '-01')) - 1; // 0=Mon
        $days = [];

        // Days from previous month (ignored)
        for ($i = $first_day_of_week; $i > 0; $i--) {
            $dateStr = $this->active_year . '-' . ($this->active_month - 1) . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);

            foreach ($this->events as $event) {
                for ($d = 0; $d <= ($event[2] - 1); $d++) {
                    $eventDate = date('Y-m-d', strtotime($event[1]));
                    $compareDate = date('Y-m-d', strtotime($dateStr . ' -' . $d . ' day'));
                    if ($compareDate === $eventDate) {
                        $events[] = ArrayData::create([
                            'Title' => $event[0],
                            'Date' => $event[1],
                            'Color' => trim($event[3]),
                        ]);
                    }
                }
            }
            $currentDate = date('Y-m-d');
            $dateStr = date('Y-m-d', strtotime(($this->active_month - 1) . '/' . ($num_days_last_month - $i + 1) . '/' . $this->active_year));
            $isToday = ($dateStr === $currentDate);
            
            $days[] = ArrayData::create([
                'Number' => $num_days_last_month - $i + 1,
                'IsCurrentMonth' => false,
                'IsSelected' => false,
                'IsToday' => $isToday,
                'EventDays' => ArrayList::create([]),
            ]);
        }

        // Days in current month
        for ($i = 1; $i <= $num_days; $i++) {
            $isSelected = ($i == $this->active_day);
            $dateStr = $this->active_year . '-' . $this->active_month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $currentDate = date('Y-m-d');
            $isToday = ($dateStr === $currentDate);
            $events = [];
            foreach ($this->events as $event) {
                for ($d = 0; $d <= ($event[2] - 1); $d++) {
                    $eventDate = date('Y-m-d', strtotime($event[1]));
                    $compareDate = date('Y-m-d', strtotime($dateStr . ' -' . $d . ' day'));
                    if ($compareDate === $eventDate) {
                        $events[] = ArrayData::create([
                            'Title' => $event[0],
                            'Date' => $event[1],
                            'Color' => trim($event[3]),
                        ]);
                    }
                }
            }
            $days[] = ArrayData::create([
                'Number' => $i,
                'IsCurrentMonth' => true,
                'IsSelected' => $isSelected,
                'IsToday' => $isToday,
                'EventDays' => $this->getEventsForDay($dateStr),
            ]);
        }

        // Fill up to 42 days (6 weeks)
        $total = count($days);
        for ($i = 1; $i <= (35 - $total); $i++) {
            $currentDate = date('Y-m-d');
            $dateStr = date('Y-m-d', strtotime(($this->active_month + 1) . '/' . $i . '/' . $this->active_year));
            $isToday = ($dateStr === $currentDate);
            
            $days[] = ArrayData::create([
                'Number' => $i,
                'IsCurrentMonth' => false,
                'IsSelected' => false,
                'IsToday' => $isToday,
                'EventDays' => ArrayList::create([])
            ]);
        }

        return ArrayList::create($days);
    }

    public function getEventsForDay($day)
    {
        $events = EventDay::get()->filter('Date', $day);
        //Sort by Date and Time
        $events = $events->sort('Date', 'ASC')->sort('TimeStart', 'ASC');
        return $events;
    }

    public function render()
    {
        return ArrayData::create([
            'DaysOfWeek' => $this->getDaysOfWeek(),
            'CalendarDays' => $this->getCalendarDays(),
            'ActiveYear' => $this->active_year,
            'ActiveMonth' => $this->active_month,
            'ActiveDay' => $this->active_day,
            'CurrentEventDayID' => $this->currentEventDayID,
            'Controller' => CalendarController::create(),
        ])->renderWith('Includes/Calendar');
    }
}
