<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\AnnualEvent;
use App\Models\EventType;
use App\Models\NewEvent;
use App\Models\SubTask;
use App\Models\Task;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\select;

class AnnualEventController extends Controller
{
    public function index1()
    {
        // Retrieve all events from the database
        $events = EventType::all();
        return view('pages.space_event_type.space_event_type_list_by_EVENT', [
            'events' => $events,
        ]);
    }
    public function createEvent(Request $request)
    {
        // Retrieve the event ID from the request
        $id = $request->input('event_id');

        $tasklist = Task::where('event_type_id', $id)
            ->select('task_name')
            ->get();

        // Retrieve all coordinators
        $coordinators = Employee::all();

        // Pass the coordinators and event ID to the view
        return view(
            'pages.space_event_type.createEvent',
            compact('coordinators', 'id', 'tasklist')
        );
    }

    public function viewEvent(Request $request)
    {
        // Retrieve event type and event name based on the event ID
        $eventType = EventType::select('id', 'event_type')
            ->where('id', $request->event_id)
            ->first();

        $eventname = NewEvent::where('event_id', $request->event_id)
            ->select('event_name', 'id', 'event_id')
            ->get();

        // $subtasks = SubTask::where('emp_assign','id', $request->emp_assign)
        //     ->select('emp_assign')
        //     ->get();

        $docs = Task::where('event_type_id', $request->event_id)
            ->select('attachment')
            ->get();

        // Return view with event data
        return view('pages.space_event_type.eventReportView', [
            'eventname' => $eventname,
            'eventType' => $eventType,
            'docs' => $docs,
            // 'subtasks'=>$subtasks,
        ]);
    }

    private $eventId;
    public function viewEventById(Request $request, $eventId)
    {
        $this->eventId = $eventId;

        // Retrieve event data based on the event ID
        $eventData = NewEvent::where('id', $eventId)
            ->select('*')
            ->get();

        $employees = Employee::select('*')->get();


        $tasks = SubTask::where('new_event_id', $eventId)
            ->select('tasks','emp_assign','status','id')
            ->get();

        $assign_employee = SubTask::where('id', $request->id)
        ->select('emp_assign')->get();

        // Join the SubTask and Employee tables to get the employee name
        $tasks = SubTask::leftJoin('employees', 'sub_tasks.emp_assign', '=', 'employees.emp_id')
        ->where('new_event_id', $eventId)
        ->select('sub_tasks.tasks', 'sub_tasks.emp_assign', 'sub_tasks.status', 'employees.emp_name')
        ->get();


        // Return JSON response with rendered view and event data
        return response()->json([
            'html' => view(
                'pages.space_event_type.eventReportById',
                compact('eventData', 'tasks', 'employees','assign_employee')
            )->render(),
        ]);
    }

    public function assignEmployee(Request $request)
    {
        // Validate inputs
        $request->validate([
            'new_event_id' => 'required',
            'event_name' => 'required',
            'employee_id' => 'required',
        ]);

        // Retrieve data from the request
        $new_event_id = $request->input('new_event_id');
        $event_name = $request->input('event_name');
        $employee_id = $request->input('employee_id');


        // Update the emp_assign column for the corresponding subtask
        $subtask = SubTask::where('new_event_id', $new_event_id)
            ->where('tasks', $event_name)
            ->update(['emp_assign' => $employee_id]);

        // Check if any rows were affected by the update operation
        if ($subtask > 0) {
            // Retrieve the employee's name
            $employee = Employee::find($employee_id);

            // Respond with a success message and employee's name
            return response()->json([
                'success' => true,
                'message' => 'Employee assigned successfully.',
                'employee_name' => $employee->emp_name
            ]);
        } else {
            // Respond with an error message if no rows were affected
            return response()->json([
                'success' => false,
                'message' => 'No matching records found.',
            ]);
        }
    }


    // Method to fetch and return employees data
    public function getEmployees(Request $request)
    {
        // Retrieve employees from the database
        $employees = SubTask::where('new_event_id', $request->new_event_id)
        ->pluck('emp_assign');


        // Return the employees data as JSON response
        return response()->json(['employees' => $employees]);
    }

    public function status(Request $request)
    {
        $request->validate([
            'task_id' => 'required|integer|exists:sub_tasks,id',
        ]);

        $task = SubTask::find($request->input('task_id'));
        $task->status = $task->status == 1 ? 0 : 1;
        $task->save();

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully.',
        ]);
    }



    public function index()
    {
        $annual_events = AnnualEvent::with('eventType')->get();
        $event_types = EventType::with('annual_events')->get();
        //        dump($annual_events);

        $space_event_Obj = AnnualEvent::orderBy('id', 'ASC')->paginate(50);

        //fetch all event types
        $event_types['data'] = EventType::orderby('event_type', 'asc')
            ->select('id', 'event_type')
            ->get();

        //fetch all employees
        $coordinators['data'] = Employee::orderby('emp_name', 'asc')
            ->select('emp_id', 'emp_name')
            ->get();

        // return view('pages.space_event.space_event_list',
        // edited parts are included in the new file. (space_event_list.blade.php -> space_event_list_new.blade.php)

        return view('pages.space_event.space_event_list_new', [
            'spnew_event' => $annual_events,
            'event_types' => $event_types,
            'coordinators' => $coordinators,
        ]);
    }

    /*to only display upcoming events in week*/
    public function upcomingEvents()
    {
        $currentDate = Carbon::now()->today();
        $endOfWeek = Carbon::now()->addDays(50);

        $upcomingEvents = NewEvent::whereBetween('start_date', [
            $currentDate,
            $endOfWeek,
        ])
            ->orderBy('start_date')
            ->get();
        return view('admin.dashboard', compact('upcomingEvents'));
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'coordinator' => 'required|string',
            'event_id' => 'required|exists:event_types,id',
        ]);

        if ($validator->passes()) {
            $createdEvent = NewEvent::create([
                'event_name' => $request->event_name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'coordinator' => $request->coordinator,
                'event_id' => $request->event_id,
            ]);

            $existingEvent = NewEvent::where('event_id', $request->event_id)
                ->where('event_name', $request->event_name)
                ->where('start_date', $request->start_date)
                ->where('end_date', $request->end_date)
                ->where('coordinator', $request->coordinator)
                ->select('id')
                ->first();

            $employee = Employee::where('emp_id', $request->coordinator)->get();

            $taskNames = $request->input('tasknames');

            foreach ($taskNames as $taskName) {
                SubTask::create([
                    'new_event_id' => $existingEvent->id,
                    'event_id' => $request->event_id,
                    'tasks' => $taskName,
                ]);
            }

            return view('pages.space_event_type.eventReport', [
                'createdEvent' => $createdEvent,
                'employee' => $employee,
            ]);
        } else {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }
    }

    public function show($event_id)
    {
        $event = NewEvent::where('id', $event_id)->first();

        return response()->json($event);
    }

// These are previously developed functions - have to check them


    public static function edit($space_event_id)
    {
        $space_event = AnnualEvent::where('id', $space_event_id)
            ->get()
            ->first();
        $space_event_types = EventType::orderBy(
            'evetype_id',
            'ASC'
        )->paginate();
        $employees = Employee::orderBy('emp_id', 'ASC')->paginate();

        return view('space_event.space_event_edit', [
            'space_event' => $space_event,
            'space_event_types' => $space_event_types,
            'employees' => $employees,
        ]);
    }

    public function update($spaceEventId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sDate' => 'required',
            'eDate' => 'required',
            'sp_event' => 'required',
            'coordinatorID' => 'required',
        ]);

        if ($validator->passes()) {
            AnnualEvent::where('id', $spaceEventId)->update([
                'start_date' => $request->sDate,
                'end_date' => $request->eDate,
                'coordinator_id' => $request->coordinatorID,
                'event_type_id' => $request->sp_event,
            ]);

            return redirect()
                ->route('space_event.index')
                ->with('success_updated', 'Employee edited successfully.');
        } else {
            return redirect()
                ->route('space_event.edit', $spaceEventId)
                ->withErrors($validator)
                ->withInput();
        }
    }

    public function delete($spaceEventId)
    {
        AnnualEvent::where('id', $spaceEventId)->delete();
        return redirect()
            ->route('space_event.index')
            ->with('delete_success', 'Space Event deleted successfully.');
    }

    // search events & category
    public function searchEvents_And_Types(Request $request)
    {
        if ($request->search) {
            $searchEvents_And_Types = AnnualEvent::where(
                'event_name',
                'LIKE',
                '%' . $request->search . '%'
            )
                ->latest()
                ->paginate(15);
            return view('components.searchbar');
        } else {
            return redirect()
                ->back()
                ->with('message', 'No search found');
        }
    }
}
