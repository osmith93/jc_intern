<?php

namespace App\Http\Controllers;

use App\Birthday;
use App\Gig;
use App\Rehearsal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Input;
use MaddHatter\LaravelFullcalendar\Event;
use MaddHatter\LaravelFullcalendar\Facades\Calendar;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class DateController extends Controller {
    private static $view_types = [
        'calendar' => 'calendarIndex',
        'list'     => 'listIndex',
    ];

    private static $date_types = [
        'rehearsals' => Rehearsal::class,
        'gigs'       => Gig::class,
        'birthdays'  => Birthday::class,
    ];

    private static $additional_filters = [
        'answered',
        'unanswered',
        'going',
        'not-going'
    ];

    public static function getViewTypes() {
        return array_keys(self::$view_types);
    }

    /**
     * Returns available date types as an arrays of strings (str_singular was applied to)
     *
     * @return array
     */
    public static function getDateTypes() {
        return array_map('str_singular', array_keys(self::$date_types));
    }

    /**
     * Returns all properties that the UI can distinguish enough to hide elements by.
     *
     * @return array
     */
    public static function getFilterTypes() {
        return array_merge(self::getDateTypes(), self::$additional_filters);
    }

    /**
     * Compare the given date types to the available ones and return the inverse.
     * If a date type is unknown, it will be dropped.
     *
     * @param array $date_types
     * @return array
     */
    public static function invertDateTypes(array $date_types) {
        $available_types = self::getDateTypes();
        return array_diff($available_types, array_intersect($available_types, $date_types));
    }

    /*public static function invertFilters(array $filters) {
        $filter_types = self::getFilterTypes();
        return array_diff($filter_types, array_intersect($filter_types, $filters));
    }*/ // Useless function

    /**
     * DateController constructor.
     */
    public function __construct() {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @param String $view_type
     * @return \Illuminate\Http\Response
     */
    public function index ($view_type = 'list') {
        // If we have no valid parameter we take the default.
        if (!array_key_exists($view_type, self::$view_types)) {
            $view_type = self::$view_types[0];
        }

        $filters = null;
        if (Input::has('hideByType') && is_array(Input::get('hideByType')) && (count(Input::get('hideByType')) > 0 )) {
            $filters = Input::get('hideByType');
            $filters = array_intersect(self::getFilterTypes(), $filters); // Because never trust the client!
        }

        $with_old = 'calendar' === $view_type;

        if (false !== $view = call_user_func_array([$this, self::$view_types[$view_type]], ['dates' => $this->getDates(self::$date_types, $with_old), 'override_filters' => $filters])) {
            return $view;
        } else {
            return redirect()->route('index', ['override_filters' => $filters])->withErrors(trans('date.view_type_not_found'));
        }
    }

    protected function calendarIndex (\Illuminate\Support\Collection $dates, $filters) {
        if (!(null === $filters || is_array($filters))) {
            throw new FatalThrowableError('Type of second parameter of calendarIndex has to be null or array');
        }
        $calendar = Calendar::addEvents($dates);
        $calendar->setId('dates');

        return view('date.calendar', ['calendar' => $calendar, 'override_filters' => $filters]);
    }

    protected function listIndex (\Illuminate\Support\Collection $dates, $filters) {
        if (!(null === $filters || is_array($filters))) {
            throw new FatalThrowableError('Type of second parameter of listIndex has to be null or array');
        }
        $dates = $dates->sortBy(function (Event $date) {
            return Carbon::now()->diffInSeconds($date->getStart(), false);
        });

        return view('date.list', ['dates' => $dates, 'override_filters' => $filters]);
    }

    private function getDates (array $date_types, bool $with_old = false) {
        $data = new Collection();

        foreach ($date_types as $set) {
            $data->add(call_user_func_array([$set, 'all'], [['*'], $with_old]));
        }

        return $data->flatten();
    }
}
